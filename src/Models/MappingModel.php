<?php

namespace rajmundtoth0\AuditDriver\Models;

class MappingModel
{
    private const DATE_FORMAT_CONFIG_KEY = 'audit.drivers.elastic.dateFormat';

    private const DEFAULT_DATE_FORMAT = 'yyyy-MM-dd HH:mm:ss';

    private readonly string $dateFormat;

    public function __construct()
    {
        $dateFormatConfig = config(self::DATE_FORMAT_CONFIG_KEY);
        $dateFormatConfig ??= self::DEFAULT_DATE_FORMAT;
        assert(is_string($dateFormatConfig));
        $this->dateFormat = $dateFormatConfig;
    }

    /** @return array<string, array<string, array<string, array<string, string>>|string>> */
    public function getModel(): array
    {
        return [
            'event' => [
                'type' => 'keyword',
            ],
            'auditable_type' => [
                'type' => 'keyword',
            ],
            'ip_address' => [
                'type' => 'keyword',
            ],
            'url' => [
                'type' => 'keyword',
            ],
            'user_agent' => [
                'type' => 'keyword',
            ],
            'created_at' => $this->getDateField(),
            'new_values' => [
                'properties' => [
                    'created_at' => $this->getDateField(),
                    'updated_at' => $this->getDateField(),
                    'deleted_at' => $this->getDateField(),
                ],
            ],
            'old_values' => [
                'properties' => [
                    'created_at' => $this->getDateField(),
                    'updated_at' => $this->getDateField(),
                    'deleted_at' => $this->getDateField(),
                ],
            ],
        ];
    }

    /** @return array{type:string, format:string} */
    private function getDateField(): array
    {
        return [
            'type'   => 'date',
            'format' => $this->dateFormat,
        ];
    }
}
