<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use rajmundtoth0\AuditDriver\Jobs\IndexAuditDocumentJob;
use rajmundtoth0\AuditDriver\Models\DocumentModel;
use rajmundtoth0\AuditDriver\Services\ElasticsearchAuditService;
use rajmundtoth0\AuditDriver\Tests\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * @internal
 */
class IndexAuditDocumentJobTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testHandleDelegatesToServiceIndex(): void
    {
        $document = new DocumentModel(
            index: 'mocked',
            id: Uuid::uuid4(),
            body: $this->getUser()->toArray(),
        );
        $job = new IndexAuditDocumentJob($document);
        /** @var ElasticsearchAuditService&MockObject $service */
        $service = $this->createMock(ElasticsearchAuditService::class);
        $service->expects($this->once())
            ->method('index')
            ->with($document)
            ->willReturn(false);

        $job->handle($service);
    }
}
