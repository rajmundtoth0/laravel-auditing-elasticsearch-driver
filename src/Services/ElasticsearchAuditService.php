<?php

namespace rajmundtoth0\AuditDriver\Services;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Audit;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\AuditDriver;
use OwenIt\Auditing\Exceptions\AuditingException;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverMissingCaCertException;
use rajmundtoth0\AuditDriver\Jobs\IndexAuditDocumentJob;
use rajmundtoth0\AuditDriver\Models\DocumentModel;
use rajmundtoth0\AuditDriver\Types\ElasticsearchTypes;
use Ramsey\Uuid\Uuid;

/**
 *  @phpstan-import-type CountParams from ElasticsearchTypes
 *  @phpstan-import-type SearchParams from ElasticsearchTypes
 */
class ElasticsearchAuditService implements AuditDriver
{
    public string $index;

    private AuditServiceConfig $config;

    /**
     * @throws AuditDriverMissingCaCertException
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly ElasticsearchClient $client,
        AuditServiceConfig $config,
    ) {
        $this->config = $config;
        $this->index  = $this->config->index;
        $this->client->setClient();
    }

    /**
     * @throws AuditDriverException
     * @throws AuditingException
     * @throws ClientResponseException
     * @throws InvalidArgumentException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function audit(Auditable $model): Audit
    {
        $this->indexDocument($model->toAudit());
        $implementation = new $this->config->implementation();

        return $implementation;
    }

    /**
     * @param Auditable&Model $model
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function prune(Auditable $model, bool $shouldReturnResult = false): bool
    {
        if ($model->getAuditThreshold() <= 0) {
            return false;
        }
        $key = $model->getKey();
        assert(is_string($key) || is_int($key));

        return $this->deleteAuditDocument($key, $shouldReturnResult);
    }

    /**
     * @param array<mixed> $model
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws InvalidArgumentException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function indexDocument(array $model, bool $shouldReturnResult = false): ?bool
    {
        if ($this->config->storageMode->isDataStream() && !array_key_exists('@timestamp', $model)) {
            $model['@timestamp'] = now()->utc()->toIso8601String();
        }

        $document = new DocumentModel(
            index: $this->index,
            id: Uuid::uuid4(),
            body: $model,
        );

        if (!$this->config->useQueue) {
            return $this->index($document, $shouldReturnResult);
        }

        dispatch(new IndexAuditDocumentJob($document))
            ->onQueue($this->config->queueName)
            ->onConnection($this->config->queueConnection);

        return null;
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws InvalidArgumentException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function index(DocumentModel $document, bool $shouldReturnResult = false): ?bool
    {
        return $this->client->index($document, $shouldReturnResult, $this->config->storageMode->isDataStream());
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function searchAuditDocument(
        Auditable&Model $model,
        int $pageSize = 10_000,
        ?int $from = null,
        string $sort = 'desc',
    ): Elasticsearch {
        $from ??= $model->getAuditThreshold() - 1;

        $key = $model->getKey();

        $params = [
            'index' => $this->index,
            'size'  => $pageSize,
            'from'  => $from,
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'term' => [
                                    'auditable_id' => $key,
                                ],
                            ],
                            [
                                'term' => [
                                    'auditable_type' => $model->getMorphClass(),
                                ],
                            ],
                        ],
                    ],
                ],
                'sort' => [
                    'created_at' => [
                        'order' => $sort,
                    ],
                ],
            ],
        ];

        return $this->client->search($params);
    }

    /**
     * @param SearchParams $query
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function search(array $query = []): Elasticsearch
    {
        return $this->client->search($query);
    }

    /**
     * @param CountParams $query
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function count(array $query = []): Elasticsearch
    {
        return $this->client->count($query);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function deleteAuditDocument(int|string $documentId, bool $shouldReturnResult = false): bool
    {
        return $this
            ->client
            ->deleteDocument($this->index, $documentId, $shouldReturnResult);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function deleteIndex(): bool
    {
        if ($this->config->storageMode->isDataStream()) {
            return $this
                ->client
                ->deleteDataStream($this->index);
        }

        return $this
            ->client
            ->deleteIndex($this->index);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     * @throws InvalidArgumentException
     */
    public function createIndex(): string
    {
        if ($this->config->storageMode->isDataStream()) {
            if ($this->config->dataStreamLifecyclePolicyName && is_array($this->config->dataStreamLifecyclePolicy)) {
                $this->client->putLifecyclePolicy([
                    'policy' => $this->config->dataStreamLifecyclePolicyName,
                    'body'   => $this->config->dataStreamLifecyclePolicy,
                ]);
            }

            $this->client->createDataStreamTemplate(
                templateName: $this->config->dataStreamTemplateName,
                indexPattern: $this->config->dataStreamIndexPattern,
                templatePriority: $this->config->dataStreamTemplatePriority,
                settings: $this->config->settings,
                mappings: $this->config->mappings,
                lifecyclePolicyName: $this->config->dataStreamLifecyclePolicyName,
                pipeline: $this->config->dataStreamPipeline,
            );

            return $this->index;
        }

        if ($this->client->isIndexExists($this->index)) {
            return $this->index;
        }
        $this->client->createIndex(
            $this->index,
            $this->config->settings,
            $this->config->mappings,
        );

        $this->client->updateAliases($this->index);

        return $this->index;
    }

    /**
     * @throws AuditDriverMissingCaCertException
     * @throws InvalidArgumentException
     */
    public function setClient(?Client $client = null): self
    {
        $this->client->setClient(
            client: $client,
        );

        return $this;
    }

    public function isAsync(): bool
    {
        return $this->client->isAsync();
    }

    public function getIndexName(): string
    {
        return $this->index;
    }
}
