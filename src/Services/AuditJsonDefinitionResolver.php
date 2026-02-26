<?php

namespace rajmundtoth0\AuditDriver\Services;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Throwable;

final class AuditJsonDefinitionResolver
{
    /**
     * @return null|array<mixed>
     *
     * @throws InvalidArgumentException
     */
    public function resolve(string $configKeyPrefix, string $defaultPath, bool $required): ?array
    {
        $inlineJson = trim(Config::string($configKeyPrefix.'.json', ''));
        if ($inlineJson) {
            return $this->decodeJsonConfig($inlineJson, $configKeyPrefix.'.json');
        }

        $configuredPath = trim(Config::string($configKeyPrefix.'.path', ''));
        $path           = $configuredPath ?: $defaultPath;
        if (!$path) {
            if ($required) {
                throw new InvalidArgumentException(
                    sprintf('Configuration value for key [%s.path] must be a valid file path.', $configKeyPrefix),
                );
            }

            return null;
        }

        if (!is_file($path)) {
            throw new InvalidArgumentException(
                sprintf('JSON definition file [%s] does not exist for key [%s.path].', $path, $configKeyPrefix),
            );
        }

        $fileContent = file_get_contents($path);
        if (!$fileContent) {
            throw new InvalidArgumentException(
                sprintf('Unable to read JSON definition file [%s] for key [%s.path].', $path, $configKeyPrefix),
            );
        }

        return $this->decodeJsonConfig($fileContent, $configKeyPrefix.'.path');
    }

    /**
     * @return array<mixed>
     *
     * @throws InvalidArgumentException
     */
    private function decodeJsonConfig(string $json, string $configKey): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                sprintf('Configuration value for key [%s] must contain valid JSON.', $configKey),
                previous: $exception,
            );
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException(
                sprintf('Configuration value for key [%s] must decode to a JSON object/array.', $configKey),
            );
        }

        return $decoded;
    }
}
