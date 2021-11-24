<?php
declare(strict_types=1);

namespace Dev256\Framework;

/**
 * Retrieve env configs
 */
class EnvConfig
{
    private array $config;

    public function __construct()
    {
        $this->config = require BASEDIR . '/etc/env.php';
    }

    public function getDbConfig(): array
    {
        return $this->config['db'];
    }

    public function getFrontUrl(): string
    {
        return $this->config['front']['url'];
    }
}
