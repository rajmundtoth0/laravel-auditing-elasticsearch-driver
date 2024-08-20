<?php

namespace rajmundtoth0\AuditDriver\Services;

use Carbon\Carbon;
use Elastic\Elasticsearch\Client;

use Elastic\Elasticsearch\Response\Elasticsearch;
use OwenIt\Auditing\Contracts\Audit;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\AuditDriver;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Models\DocumentModel;
use Ramsey\Uuid\Uuid;

class ElasticsearchAuditService implements AuditDriver
{
    public function __construct(
        private readonly ElasticsearchClient $client,
        private string $implementation = '',
        private string $auditType = '',
        public string $index = 'laravel_auditing',
        /** @var array<string, mixed> $query */
        private array $query = [],
    ) {
        $index = config('audit.drivers.elastic.index');
        $auditType = config('audit.drivers.elastic.type');
        $implementation = config('audit.implementation');

        assert(is_string($index));
        assert(is_string($auditType));
        assert(is_string($implementation) && class_exists($implementation));

        $this->index = $index;
        $this->auditType = $auditType;
        $this->implementation = $implementation;
        $this->setBaseQuery();
        $this->client->setClient();
    }

    private function setBaseQuery(): void
    {
        $this->query = [
            'index' => $this->index,
            'type'  => $this->auditType,
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'range' => [],
                        ],
                        'minimum_should_match' => 1,
                    ],
                ],
                'track_scores' => true,
            ],
        ];
    }

    public function audit(Auditable $model): Audit
    {
        $this->indexDocument($model->toAudit());
        $implementation = new $this->implementation();
        assert($implementation instanceof Audit);

        return $implementation;
    }

    public function prune(Auditable $model, bool $shouldReturnResult = false): bool
    {
        if ($model->getAuditThreshold() <= 0) {
            return false;
        }
        /** @phpstan-ignore-next-line */
        $id = $model->id;

        return $this->deleteAuditDocument($id, $shouldReturnResult);
    }

    /**
     * @param array<string, mixed> $model
     */
    public function indexDocument(array $model, bool $shouldReturnResult = false): ?bool
    {
        $params = new DocumentModel(
            index: $this->index,
            id: Uuid::uuid4(),
            type: $this->auditType,
            body: $model,
        );

        return $this->client->index($params, $shouldReturnResult);
    }

    public function setDateRange(
        ?Carbon $date,
        string $name = 'created_at',
        string $operator = 'gte',
    ): self {
        if (!$date) {
            return $this;
        }
        data_set($this->query, "body.query.bool.must.range.{$name}.{$operator}", $date->toDateTimeString());

        return $this;
    }

    public function setTerm(string $name, int|string $value): self
    {
        // @var Elasticsearch $rawResult
        $this->query['body']['query']['bool']['should'][] = [
            'term' => [
                $name => $value,
            ],
        ];

        return $this;
    }

    public function searchAuditDocument(
        Auditable $model,
        int $pageSize = 10_000,
        ?int $from = null,
        string $sort = 'desc',
    ): Elasticsearch {
        $from ??= $model->getAuditThreshold() - 1;

        /** @phpstan-ignore-next-line */
        $id = $model->id;

        assert(method_exists($model, 'getMorphClass'));
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
                                    'auditable_id' => $id,
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

    public function search(
        int $pageSize = 10_000,
        ?int $from = 0,
        string $sort = 'desc',
    ): Elasticsearch {
        data_set($this->query, 'size', $pageSize);
        data_set($this->query, 'from', $from);
        data_set($this->query, 'body.sort.created_at.order', $sort);

        return $this->client->search($this->query);
    }

    public function deleteAuditDocument(int|string $documentId, bool $shouldReturnResult = false): bool
    {
        return $this
            ->client
            ->deleteDocument($this->index, $documentId, $shouldReturnResult);
    }

    public function deleteIndex(): bool
    {
        return $this
            ->client
            ->deleteIndex($this->index);
    }

    public function createIndex(): string
    {
        if ($this->client->isIndexExists($this->index)) {
            return $this->index;
        }
        $this->client->createIndex($this->index, $this->auditType);

        $this->client->updateAliases($this->index);

        return $this->index;
    }

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
