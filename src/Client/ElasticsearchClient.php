<?php

namespace rajmundtoth0\AuditDriver\Client;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Response\Elasticsearch;
use GuzzleHttp\Client as GuzzleHttpClient;
use Http\Adapter\Guzzle7\Client as Guzzle7Client;
use Http\Promise\Promise;
use Illuminate\Support\Facades\Storage;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverConfigNotSetException;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverMissingCaCertException;
use rajmundtoth0\AuditDriver\Models\DocumentModel;
use rajmundtoth0\AuditDriver\Models\MappingModel;

class ElasticsearchClient
{
    private bool|string $caBundlePath = false;

    private ClientBuilder $clientBuilder;

    private Client $client;

    public function __construct()
    {
    }

    public function setAsync(bool $isAsync = false): void
    {
        if (!$isAsync) {
            return;
        }
        $this->setAsyncHttpClient();
    }

    public function setClient(?Client $client = null, bool $isAsync = false): self
    {
        $this->setHosts();
        $this->setBasicAuth();
        $this->setCaBundle();

        $this->setAsync($isAsync);
        if (!$client) {
            $client = $this
            ->clientBuilder
            ->build();
        }

        $this->client = $client;
        $this->client->setAsync($isAsync);

        return $this;
    }

    public function setCaBundle(): void
    {
        if (!config('audit.drivers.elastic.useCaCert', true)) {
            return;
        }
        if (!$caCert = config('audit.drivers.elastic.certPath', false)) {
            throw new AuditDriverConfigNotSetException(
                message: 'Key audit.drivers.elastic.certPath is missing.',
            );
        }

        assert(is_string($caCert));
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

    public function setAsyncHttpClient(): void
    {
        $guzzleClient    = new GuzzleHttpClient(['verify' => $this->caBundlePath]);
        $httpAsyncClient = new Guzzle7Client($guzzleClient);
        $this->clientBuilder->setAsyncHttpClient($httpAsyncClient);
    }

    public function setBasicAuth(): void
    {
        if (!config('audit.drivers.elastic.useBasicAuth', true)) {
            return;
        }
        if (!$userName = config('audit.drivers.elastic.userName', false)) {
            throw new AuditDriverConfigNotSetException(
                message: 'Key audit.drivers.elastic.userName is missing.',
            );
        }
        if (!$password = config('audit.drivers.elastic.password', false)) {
            throw new AuditDriverConfigNotSetException(
                message: 'Key audit.drivers.elastic.password is missing.',
            );
        }
        assert(is_string($userName));
        assert(is_string($password));

        $this->clientBuilder->setBasicAuthentication(
            username: $userName,
            password: $password,
        );
    }

    public function setHosts(): void
    {
        $hosts = config('audit.drivers.elastic.hosts', []);
        if (!$hosts || !is_array($hosts)) {
            throw new AuditDriverConfigNotSetException(
                message: 'Key audit.drivers.elastic.hosts is unset or has incorrect data type. Expected: array.',
            );
        }

        $this->clientBuilder = ClientBuilder::create()
            ->setHosts($hosts);
    }

    public function updateAliases(string $index): Elasticsearch|Promise
    {
        $params['body'] = [
            'actions' => [
                [
                    'add' => [
                        'index' => $index,
                        'alias' => $index.'_write'
                    ]
                ]
            ]
        ];

        return $this->client->indices()->updateAliases($params);
    }

    public function createIndex(
        string $index,
        string $auditType,
        int $shardNumber = 5,
        int $replicaNumber = 0
    ): Elasticsearch|Promise {
        $mappings = new MappingModel();
        $params   = [
            'index' => $index,
            'type'  => $auditType,
            'body'  => [
                'settings' => [
                    'number_of_shards'   => $shardNumber,
                    'number_of_replicas' => $replicaNumber,
                ],
                'mappings' => [
                    'properties' => $mappings->getModel(),
                ],
            ],
        ];

        return $this->client->indices()->create($params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function search(array $params): Elasticsearch
    {
        $result = $this->client->search($params);

        if ($result instanceof Promise) {
            $result = $result->wait(true);
        }

        /** @var Elasticsearch $result */
        return $result;
    }

    public function deleteDocument(string $index, int|string $id, bool $shouldReturnResult = false): bool
    {
        $result = $this->client->delete([
            'index' => $index,
            'id'    => $id,
        ]);

        return $this
            ->getResult($result, $shouldReturnResult);
    }

    public function deleteIndex(string $index): bool
    {
        $result = $this->client->indices()->delete([
            'index' => $index,
        ]);

        return $this
            ->getResult($result, true);
    }

    public function isAsync(): bool
    {
        return $this->client->getAsync();
    }

    public function isIndexExists(string $index): bool
    {
        $result = $this->client->indices()->exists(
            [
                'index' => $index,
            ],
        );

        return $this
            ->getResult($result, true);
    }

    public function index(DocumentModel $model, bool $shouldReturnResult): bool
    {
        $result = $this->client->index($model->toArray());

        return $this
            ->getResult($result, $shouldReturnResult);
    }

    private function getResult(Elasticsearch|Promise $rawResult, bool $shouldReturnResult = false): bool
    {
        if (!$shouldReturnResult) {
            return false;
        }
        if ($rawResult instanceof Promise) {
            $rawResult = $rawResult
                ->wait(true);
        }

        /** @var Elasticsearch $rawResult */
        return $rawResult
            ->asBool();
    }
}
