<?php

namespace rajmundtoth0\AuditDriver\Services;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Audit;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\AuditDriver;
use OwenIt\Auditing\Exceptions\AuditingException;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverConfigNotSetException;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverMissingCaCertException;
use rajmundtoth0\AuditDriver\Jobs\IndexAuditDocumentJob;
use rajmundtoth0\AuditDriver\Models\DocumentModel;
use Ramsey\Uuid\Uuid;

class ElasticsearchAuditService implements AuditDriver
{
    public string $index;

    /** @var class-string<Audit> */
    private string $implementation;

    private string $auditType;

    private bool $useQueue;

    private string $queueName;

    private string $queueConnection;

    /**
     * @throws AuditDriverConfigNotSetException
     * @throws AuditDriverMissingCaCertException
     */
    public function __construct(
        private readonly ElasticsearchClient $client,
    ) {
        $this->loadConfigs();
        $this->client->setClient();
    }

    private function loadConfigs(): void
    {
        $index           = config('audit.drivers.elastic.index', 'laravel_auditing');
        $auditType       = config('audit.drivers.elastic.type', '');
        $implementation  = config('audit.implementation', Audit::class);
        $useQueue        = config('audit.drivers.queue.enabled', false);
        $queueName       = config('audit.drivers.queue.name', '');
        $queueConnection = config('audit.drivers.queue.connection', '');

        assert(is_string($index));
        assert(is_string($auditType));
        assert(is_string($implementation) && is_subclass_of($implementation, Audit::class));
        assert(is_bool($useQueue));
        assert(is_string($queueName));
        assert(is_string($queueConnection));

        if ($useQueue) {
            assert($queueName);
            assert($queueConnection);
        }

        $this->index           = $index;
        $this->auditType       = $auditType;
        $this->implementation  = $implementation;
        $this->useQueue        = $useQueue;
        $this->queueName       = $queueName;
        $this->queueConnection = $queueConnection;
    }

    /**
     * @throws AuditDriverException
     * @throws AuditingException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function audit(Auditable $model): Audit
    {
        $this->indexDocument($model->toAudit());
        $implementation = new $this->implementation();

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
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function indexDocument(array $model, bool $shouldReturnResult = false): ?bool
    {
        $document = new DocumentModel(
            index: $this->index,
            id: Uuid::uuid4(),
            type: $this->auditType,
            body: $model,
        );

        if (!$this->useQueue) {
            return $this->index($document, $shouldReturnResult);
        }

        dispatch(new IndexAuditDocumentJob($document))
            ->onQueue($this->queueName)
            ->onConnection($this->queueConnection);

        return null;
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function index(DocumentModel $document, bool $shouldReturnResult = false): ?bool
    {
        return $this->client->index($document, $shouldReturnResult);
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
            'type'  => $this->auditType,
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
                'track_scores' => true,
            ],
        ];

        return $this->client->search($params);
    }

    /**
     * @param array<string, mixed> $query
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
     * @param array<string, mixed> $query
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
     */
    public function createIndex(): string
    {
        if ($this->client->isIndexExists($this->index)) {
            return $this->index;
        }
        $this->client->createIndex($this->index, $this->auditType);

        $this->client->updateAliases($this->index);

        return $this->index;
    }

    /**
     * @throws AuditDriverConfigNotSetException
     * @throws AuditDriverMissingCaCertException
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
