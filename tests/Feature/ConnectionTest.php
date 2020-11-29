<?php

namespace Tests\Feature;

use Tests\TestCase;
use ReflectionClass;

class ConnectionTest extends TestCase
{
    public function testConnection(): void
    {
        $this->assertFalse($this->adapter()->isConnected());

        $this->assertNull($this->adapter()->connect());

        $this->assertTrue($this->adapter()->isConnected());

        $reflection = new ReflectionClass(get_class($this->adapter()));
        $method = $reflection->getMethod('listDirectoryContents');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->adapter(), ['', false]);
        $this->assertTrue(is_array($result));

        $this->assertNull($this->adapter()->disconnect());

        $this->assertFalse($this->adapter()->isConnected());
    }
}
