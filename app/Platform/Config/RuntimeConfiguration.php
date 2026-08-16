<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Platform\Config;

use RuntimeException;

final class RuntimeConfiguration
{
    /** @param array<string, mixed> $fileValues */
    private function __construct(private readonly array $fileValues)
    {
    }

    public static function fromProjectRoot(string $projectRoot): self
    {
        $path = rtrim($projectRoot, '/\\') . '/config/runtime.php';
        if (!is_file($path)) {
            return new self([]);
        }

        $values = require $path;
        if (!is_array($values)) {
            throw new RuntimeException('Runtime configuration must return an array.');
        }

        return new self($values);
    }

    public function required(string $name): string
    {
        $environmentValue = getenv($name);
        if (is_string($environmentValue) && $environmentValue !== '') {
            return $environmentValue;
        }

        $fileValue = $this->fileValues[$name] ?? null;
        if (!is_string($fileValue) || $fileValue === '') {
            throw new RuntimeException('Missing required runtime configuration: ' . $name);
        }

        return $fileValue;
    }
}
