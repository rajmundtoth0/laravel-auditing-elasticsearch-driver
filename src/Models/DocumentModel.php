<?php

namespace rajmundtoth0\AuditDriver\Models;

class DocumentModel
{
    public function __construct(
        protected readonly string $index,
        protected readonly string $type,
        protected readonly string $id,
        /** @var array<mixed> */
        protected array $body,
    ) {
        if (!array_key_exists('created_at', $this->body)) {
            $this->body['created_at'] = now()->toDateTimeString();
        }
        if (!array_key_exists('updated_at', $this->body)) {
            $this->body['updated_at'] = now()->toDateTimeString();
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
