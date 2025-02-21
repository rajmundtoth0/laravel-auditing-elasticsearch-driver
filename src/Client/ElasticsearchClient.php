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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverConfigNotSetException;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverMissingCaCertException;
use rajmundtoth0\AuditDriver\Models\DocumentModel;
use rajmundtoth0\AuditDriver\Models\MappingModel;
use RuntimeException;

class ElasticsearchClient
{
    private bool|string $caBundlePath = false;

    private ClientBuilder $clientBuilder;

    private Client $client;

    /**
     * @throws AuditDriverConfigNotSetException
     * @throws AuditDriverMissingCaCertException
     * @throws RuntimeException
     */
    public function setClient(?Client $client = null): self
    {
        $this->setHosts();
        $this->setBasicAuth();
        $this->setCaBundle();

        if (!$client) {
            $client = $this
                ->clientBuilder
                ->build();
        }

        $this->client = $client;

        return $this;
    }

    /**
     * @throws AuditDriverConfigNotSetException
     * @throws AuditDriverMissingCaCertException
     * @throws RuntimeException
     */
    public function setCaBundle(): void
    {
        if (!Config::boolean('audit.drivers.elastic.useCaCert', false)) {
            return;
        }
        if (!$caCert = Config::string('audit.drivers.elastic.certPath', '')) {
            throw new AuditDriverConfigNotSetException(
                message: 'Key audit.drivers.elastic.certPath is missing.',
            );
        }

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

    /**
     * @throws AuditDriverConfigNotSetException
     * @throws RuntimeException
     */
    public function setBasicAuth(): void
    {
        if (!Config::boolean('audit.drivers.elastic.useBasicAuth', false)) {
            return;
        }
        if (!$userName = Config::string('audit.drivers.elastic.userName', '')) {
            throw new AuditDriverConfigNotSetException(
                message: 'Key audit.drivers.elastic.userName is missing.',
            );
        }
        if (!$password = Config::string('audit.drivers.elastic.password', '')) {
            throw new AuditDriverConfigNotSetException(
                message: 'Key audit.drivers.elastic.password is missing.',
            );
        }

        $this->clientBuilder->setBasicAuthentication(
            username: $userName,
            password: $password,
        );
    }

    /**
     * @throws AuditDriverConfigNotSetException
     * @throws RuntimeException
     */
    public function setHosts(): void
    {
        if (!$hosts = Config::array('audit.drivers.elastic.hosts', ['localhost'])) {
            throw new AuditDriverConfigNotSetException(
                message: 'Key audit.drivers.elastic.hosts is unset or has incorrect data type. Expected: array.',
            );
        }

        $this->clientBuilder = ClientBuilder::create()
            ->setHosts($hosts);
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
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     * @throws RuntimeException
     */
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
     * @param array<string, mixed> $params
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
            'id'    => $id,
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
    public function index(DocumentModel $model, bool $shouldReturnResult): bool
    {
        $result = $this->client->index($model->toArray());

        return $this
            ->getResult($result, $shouldReturnResult);
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
}
