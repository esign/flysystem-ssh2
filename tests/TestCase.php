<?php

namespace Tests;

use Esign\Flysystem\Ssh2\Ssh2Adapter;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected ?Ssh2Adapter $adapter = null;

    protected function config(): array
    {
        return [
            'host' => $_SERVER['SSH_HOST'] ?? null,
            'hostFingerprint' => $_SERVER['SSH_HOST_FINGERPRINT'] ?? null,
            'username' => $_SERVER['SSH_USERNAME'] ?? null,
            'password' => $_SERVER['SSH_PASSWORD'] ?? null,
            'privateKey' => $_SERVER['SSH_PRIVATE_KEY'] ?? null,
            'publicKey' => $_SERVER['SSH_PUBLIC_KEY'] ?? null,
            'root' => $_SERVER['SSH_ROOT'] ?? null,
        ];
    }

    protected function adapter(): Ssh2Adapter
    {
        if ($this->adapter) {
            return $this->adapter;
        }

        return $this->adapter = new Ssh2Adapter($this->config());
    }
}
