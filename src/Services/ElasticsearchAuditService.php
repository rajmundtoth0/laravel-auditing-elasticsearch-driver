<?php

namespace rajmundtoth0\AuditDriver\Services;

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

    ) {
        $index          = config('audit.drivers.elastic.index');
        $auditType      = config('audit.drivers.elastic.type');
        $implementation = config('audit.implementation');

        assert(is_string($index));
        assert(is_string($auditType));
        assert(is_string($implementation) && class_exists($implementation));

        $this->index          = $index;
        $this->auditType      = $auditType;
        $this->implementation = $implementation;
        $this->client->setClient();
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
    public function indexDocument(array $model, bool $shouldReturnResult = false): bool|null
    {
        $params = new DocumentModel(
            index: $this->index,
            id: Uuid::uuid4(),
            type: $this->auditType,
            body: $model,
        );

        return $this->client->index($params, $shouldReturnResult);
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
                                ]
                            ],
                            [
                                'term' => [
                                    'auditable_type' => $model->getMorphClass(),
                                ]
                            ]
                        ]
                    ]
                ],
                'sort' => [
                    'created_at' => [
                        'order' => $sort,
                    ]
                ],
                'track_scores' => true
            ]
        ];

        return $this->client->search($params);
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

    public function setClient(?Client $client = null, bool $isAsync = false): ElasticsearchAuditService
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
