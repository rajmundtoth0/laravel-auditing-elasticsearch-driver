<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Exception;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Jobs\IndexAuditDocumentJob;
use rajmundtoth0\AuditDriver\Models\DocumentModel;
use rajmundtoth0\AuditDriver\Tests\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * @internal
 */
class IndexAuditDocumentJobTest extends TestCase
{
    /**
     * @throws Exception
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     * @throws NoNodeAvailableException
     * @throws AuditDriverException
     *
     */
    public function testHandle(): void
    {
        $user     = $this->getUser();
        $document = new DocumentModel(
            index: 'mocked',
            id: Uuid::uuid4(),
            type: 'mocked',
            body: $user->toArray(),
        );
        $job     = new IndexAuditDocumentJob($document);
        $service = $this->getService(
            statuses: [200, 200, 200],
            bodies: [null,  $user->toArray()],
            shouldBind: true,
        );

        $job->handle($service);
        $result = $service->searchAuditDocument($user);

        static::assertTrue($result->asBool());
        static::assertSame($user->toArray(), $result->asArray());
    }
}
