<?php

namespace rajmundtoth0\AuditDriver\Traits;

use Illuminate\Support\Collection;
use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;

trait ElasticSearchAuditable
{
    /**
     * @return Collection<int, mixed>
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
     */
    public function getAuditLogAttribute(): Collection
    {
        return $this->elasticsearchAuditLog();
    }
}
