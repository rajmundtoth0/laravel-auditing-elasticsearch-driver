<?php

namespace rajmundtoth0\AuditDriver\Models;

use Illuminate\Support\Facades\Config;
use RuntimeException;

class MappingModel
{
    private readonly string $dateFormat;

    /**
     * @throws RuntimeException
     */
    public function __construct()
    {
        $this->dateFormat = Config::string('audit.drivers.elastic.dateFormat');
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
