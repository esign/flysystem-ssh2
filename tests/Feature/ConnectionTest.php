<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConnectionTest extends TestCase
{
    public function testConnection(): void
    {
        $this->assertFalse($this->adapter()->isConnected());

        $this->assertNull($this->adapter()->connect());

        $this->assertTrue($this->adapter()->isConnected());

        $this->adapter()->listDirectoryContents('');

        $this->assertNull($this->adapter()->disconnect());

        $this->assertFalse($this->adapter()->isConnected());
    }
}
