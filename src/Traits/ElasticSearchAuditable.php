<?php

namespace rajmundtoth0\AuditDriver\Traits;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Elastic\Transport\Exception\UnknownContentTypeException;
use Illuminate\Support\Collection;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;

trait ElasticSearchAuditable
{
    /**
     * @return Collection<int, mixed>
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     * @throws UnknownContentTypeException
     */
    public function elasticsearchAuditLog(int $page = 1, int $pageSize = 10, string $sort = 'desc'): Collection
    {
        /** @var ElasticsearchAuditService */
        $elasticsearchAuditService = resolve(ElasticsearchAuditService::class);
        $result                    = $elasticsearchAuditService->searchAuditDocument(
            model: $this,
            pageSize: $pageSize,
            from: --$page * $pageSize,
            sort: $sort,
        );

        return collect($result->asArray());
    }

    /**
     * @return Collection<int, mixed>
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     * @throws UnknownContentTypeException
     */
    public function getAuditLogAttribute(): Collection
    {
        return $this->elasticsearchAuditLog();
    }
}
