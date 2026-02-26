<?php

namespace rajmundtoth0\AuditDriver\Tests\Feature;

use rajmundtoth0\AuditDriver\Models\DocumentModel;
use rajmundtoth0\AuditDriver\Tests\TestCase;

/**
 * @internal
 */
class DocumentModelTest extends TestCase
{
    public function testToArrayShape(): void
    {
        $document = new DocumentModel(
            index: 'mocked',
            id: '1',
            body: [],
        );

        $result = $document->toArray();

        static::assertSame('mocked', $result['index']);
        static::assertSame('1', $result['id']);
        static::assertArrayNotHasKey('type', $result);
    }

    public function testDefaultTimestampsUseIso8601(): void
    {
        $document = new DocumentModel(
            index: 'mocked',
            id: '1',
            body: [],
        );

        $result = $document->toArray();
        $body   = $result['body'];
        assert(is_array($body));
        assert(is_string($body['created_at']));
        assert(is_string($body['updated_at']));

        static::assertNotFalse(str_contains($body['created_at'], 'T'));
        static::assertNotFalse(str_contains($body['updated_at'], 'T'));
    }
}
