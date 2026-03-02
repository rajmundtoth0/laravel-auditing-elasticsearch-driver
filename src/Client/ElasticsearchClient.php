<?php

namespace rajmundtoth0\AuditDriver\Client;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Http\Promise\Promise;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverMissingCaCertException;
use rajmundtoth0\AuditDriver\Models\DocumentModel;
use rajmundtoth0\AuditDriver\Services\AuditServiceConfig;
use rajmundtoth0\AuditDriver\Types\ElasticsearchTypes;
use stdClass;

/**
 *  @phpstan-import-type CountParams from ElasticsearchTypes
 *  @phpstan-import-type PutLifecycleParams from ElasticsearchTypes
 *  @phpstan-import-type SearchParams from ElasticsearchTypes
 */
class ElasticsearchClient
{
    /** @var list<int> */
    private const RETRIABLE_CLIENT_STATUS_CODES = [
        408,
        429,
    ];

    private bool|string $caBundlePath = false;

    private ClientBuilder $clientBuilder;

    private Client $client;

    public function __construct(
        private readonly AuditServiceConfig $config,
    ) {
    }

    /**
     * @throws AuditDriverMissingCaCertException
     * @throws InvalidArgumentException
     */
    public function setClient(?Client $client = null): self
    {
        $this->setHosts();
        $this->setBasicAuth();
        $this->setCaBundle();

        $this->client = $client ?: $this
            ->clientBuilder
            ->build();

        return $this;
    }

    /**
     * @throws AuditDriverMissingCaCertException
     */
    public function setCaBundle(): void
    {
        if (!$this->config->useCaCert) {
            $this->clientBuilder->setSSLVerification(false);

            return;
        }
        $caCert = $this->config->certPath;

        $this->caBundlePath = Storage::path($caCert);
        if (!$this->caBundlePath) {
            throw new AuditDriverMissingCaCertException(
                message: 'Cacert file path is invalid!',
            );
        }

        $this->clientBuilder->setCABundle(
            cert: $this->caBundlePath,
        );
    }

    public function setBasicAuth(): void
    {
        if (!$this->config->useBasicAuth) {
            return;
        }

        $this->clientBuilder->setBasicAuthentication(
            username: $this->config->userName,
            password: $this->config->password,
        );
    }

    public function setHosts(): void
    {
        $this->clientBuilder = ClientBuilder::create()
            ->setHosts($this->config->hosts);
    }

    /**
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function updateAliases(string $index): Elasticsearch|Promise
    {
        $params         = [];
        $params['body'] = [
            'actions' => [
                [
                    'add' => [
                        'index' => $index,
                        'alias' => $index.'_write',
                    ],
                ],
            ],
        ];

        return $this->client->indices()->updateAliases($params);
    }

    /**
     * @param array<mixed> $settings
     * @param array<mixed> $mappings
     *
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function createIndex(
        string $index,
        array $settings,
        array $mappings
    ): Elasticsearch|Promise {
        $params = [
            'index' => $index,
            'body'  => [
                'settings' => $settings,
                'mappings' => $mappings,
            ],
        ];

        return $this->client->indices()->create($params);
    }

    /**
     * @param array<mixed> $settings
     * @param array<mixed> $mappings
     *
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function createDataStreamTemplate(
        string $templateName,
        string $indexPattern,
        int $templatePriority = 100,
        array $settings = [],
        array $mappings = [],
        string $lifecyclePolicyName = '',
        string $pipeline = ''
    ): Elasticsearch|Promise {
        $resolvedSettings = $settings;

        if ($lifecyclePolicyName) {
            $resolvedSettings['index.lifecycle.name'] = $lifecyclePolicyName;
        }

        if ($pipeline) {
            $resolvedSettings['index.default_pipeline'] = $pipeline;
        }

        $params = [
            'name' => $templateName,
            'body' => [
                'index_patterns' => [$indexPattern],
                'priority'       => $templatePriority,
                'data_stream'    => new stdClass(),
                'template'       => [
                    'settings' => $resolvedSettings,
                    'mappings' => $mappings,
                ],
            ],
        ];

        return $this->client->indices()->putIndexTemplate($params);
    }

    /**
     * @param PutLifecycleParams $params
     *
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function putLifecyclePolicy(array $params): Elasticsearch|Promise
    {
        return $this->client->ilm()->putLifecycle($params);
    }

    /**
     * @param SearchParams $params
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function search(array $params): Elasticsearch
    {
        $result = $this->client->search($params);

        if (!$result instanceof Elasticsearch) {
            throw new AuditDriverException('Async handler is not implemented!');
        }

        return $result;
    }

    /**
     * @param CountParams $params
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function count(array $params): Elasticsearch
    {
        $result = $this->client->count($params);

        if (!$result instanceof Elasticsearch) {
            throw new AuditDriverException('Async handler is not implemented!');
        }

        return $result;
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function deleteDocument(string $index, int|string $id, bool $shouldReturnResult = false): bool
    {
        $result = $this->client->delete([
            'index' => $index,
            'id'    => (string) $id,
        ]);

        return $this
            ->getResult($result, $shouldReturnResult);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function deleteIndex(string $index): bool
    {
        $result = $this->client->indices()->delete([
            'index' => $index,
        ]);

        return $this
            ->getResult($result, true);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function deleteDataStream(string $dataStream): bool
    {
        $result = $this->client->indices()->deleteDataStream([
            'name' => $dataStream,
        ]);

        return $this
            ->getResult($result, true);
    }

    public function isAsync(): bool
    {
        return $this->client->getAsync();
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function isIndexExists(string $index): bool
    {
        $result = $this->client->indices()->exists([
            'index' => $index,
        ]);

        return $this
            ->getResult($result, true);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function index(DocumentModel $model, bool $shouldReturnResult, bool $isDataStreamMode = false): bool
    {
        $document = $model->toArray();
        $retry    = $this->getSingleWriteRetryConfig();

        if ($isDataStreamMode) {
            $document['op_type'] = 'create';
        }

        $attempt = 1;
        while (true) {
            try {
                $result = $this->client->index($document);

                return $this
                    ->getResult($result, $shouldReturnResult);
            } catch (ClientResponseException|ServerResponseException|NoNodeAvailableException $exception) {
                if ($this->isDataStreamCreateConflict($exception, $isDataStreamMode)) {
                    return $shouldReturnResult;
                }

                if (!$retry['enabled'] || !$this->isRetriableWriteException($exception) || $attempt >= $retry['maxAttempts']) {
                    throw $exception;
                }

                $delayMs = $this->calculateRetryDelayMilliseconds($attempt, $retry);
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }

                ++$attempt;
            }
        }
    }

    /**
     * @throws AuditDriverException
     */
    private function getResult(Elasticsearch|Promise $rawResult, bool $shouldReturnResult = false): bool
    {
        if (!$shouldReturnResult) {
            return false;
        }

        if (!$rawResult instanceof Elasticsearch) {
            throw new AuditDriverException('Async handler is not implemented!');
        }

        return $rawResult
            ->asBool();
    }

    public function getCaBundlePath(): bool|string
    {
        return $this->caBundlePath;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     maxAttempts: int,
     *     initialBackoffMs: int,
     *     maxBackoffMs: int,
     *     backoffMultiplier: float,
     *     jitterMs: int,
     * }
     */
    private function getSingleWriteRetryConfig(): array
    {
        return [
            'enabled'           => $this->config->singleWriteRetryEnabled,
            'maxAttempts'       => $this->config->singleWriteRetryMaxAttempts,
            'initialBackoffMs'  => $this->config->singleWriteRetryInitialBackoffMs,
            'maxBackoffMs'      => $this->config->singleWriteRetryMaxBackoffMs,
            'backoffMultiplier' => $this->config->singleWriteRetryBackoffMultiplier,
            'jitterMs'          => $this->config->singleWriteRetryJitterMs,
        ];
    }

    private function isRetriableWriteException(
        ClientResponseException|ServerResponseException|NoNodeAvailableException $exception
    ): bool {
        if ($exception instanceof NoNodeAvailableException || $exception instanceof ServerResponseException) {
            return true;
        }

        return in_array($exception->getCode(), self::RETRIABLE_CLIENT_STATUS_CODES, true);
    }

    private function isDataStreamCreateConflict(
        ClientResponseException|ServerResponseException|NoNodeAvailableException $exception,
        bool $isDataStreamMode
    ): bool {
        return $isDataStreamMode
            && $exception instanceof ClientResponseException
            && 409 === $exception->getCode();
    }

    /**
     * @param array{
     *     initialBackoffMs: int,
     *     maxBackoffMs: int,
     *     backoffMultiplier: float,
     *     jitterMs: int,
     * } $retry
     */
    private function calculateRetryDelayMilliseconds(int $attempt, array $retry): int
    {
        $baseDelay = (int) round($retry['initialBackoffMs'] * ($retry['backoffMultiplier'] ** ($attempt - 1)));
        $delayMs   = min($baseDelay, $retry['maxBackoffMs']);

        if ($retry['jitterMs'] > 0) {
            $delayMs += mt_rand(-$retry['jitterMs'], $retry['jitterMs']);
        }

        return max($delayMs, 0);
    }
}
