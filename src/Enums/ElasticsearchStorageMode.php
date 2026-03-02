<?php

namespace rajmundtoth0\AuditDriver\Enums;

enum ElasticsearchStorageMode: string
{
    case Index      = 'index';
    case DataStream = 'data_stream';

    public function isDataStream(): bool
    {
        return self::DataStream === $this;
    }
}
