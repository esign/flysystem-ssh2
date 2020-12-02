<?php

namespace Tests\Feature;

use League\Flysystem\Config;
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
        $listDirectoryContents = $reflection->getMethod('listDirectoryContents');
        $listDirectoryContents->setAccessible(true);
        $result = $listDirectoryContents->invokeArgs($this->adapter(), ['', false]);
        $this->assertTrue(is_array($result));
        $this->assertEquals(count($result), 0);

        // Create folder
        $this->assertTrue(is_array($this->adapter()->createDir('dir1', new Config())));
        $this->assertTrue(is_array($this->adapter()->has('dir1')));

        // We should now have 1 listing
        $result = $listDirectoryContents->invokeArgs($this->adapter(), ['', false]);
        $this->assertTrue(is_array($result));
        $this->assertEquals(count($result), 1);

        // We should also have 1 listing recursively
        $result = $listDirectoryContents->invokeArgs($this->adapter(), ['', true]);
        $this->assertTrue(is_array($result));
        $this->assertEquals(count($result), 1);

        // The listing should be of type dir
        $this->assertArrayHasKey('type', $result[0]);
        $this->assertEquals('dir', $result[0]['type']);

        // Create folder-in-folder
        $this->assertTrue(is_array($this->adapter()->createDir('dir1/dir2', new Config())));
        $this->assertTrue(is_array($this->adapter()->has('dir1/dir2')));

        // Remove folders again
        $this->assertTrue($this->adapter()->deleteDir('dir1/dir2'));
        $this->assertTrue($this->adapter()->deleteDir('dir1'));

        // Create folder-in-folder recursively
        $this->assertTrue(is_array($this->adapter()->createDir('dir1/dir2', new Config())));
        $this->assertTrue(is_array($this->adapter()->has('dir1/dir2')));

        // We should now have 2 listings recursively
        $result = $listDirectoryContents->invokeArgs($this->adapter(), ['', true]);
        $this->assertTrue(is_array($result));
        $this->assertEquals(count($result), 2);

        // Write file from stream
        $resource = fopen(__DIR__ . '/../../test_files/flysystem.svg', 'r');
        $result = $this->adapter()->writeStream('dir1/dir2/flysystem-stream.svg', $resource, new Config());
        $this->assertTrue(is_array($result));
        fclose($resource);
        $this->assertTrue(is_array($this->adapter()->has('dir1/dir2/flysystem-stream.svg')));

        // Check all metadata is present
        $result = $this->adapter()->getMetadata('dir1/dir2/flysystem-stream.svg');
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('type', $result);
        // $this->assertArrayHasKey('visibility', $result); // todo segfault
        $this->assertArrayHasKey('size', $result);
        // File should be of type "file"
        $this->assertEquals('file', $result['type']);

        // Shouldn't be able to delete a folder containing files
        $this->assertFalse($this->adapter()->deleteDir('dir1/dir2'));

        // Remove files & folders
        $this->assertTrue($this->adapter()->delete('dir1/dir2/flysystem-stream.svg'));
        $this->assertTrue($this->adapter()->deleteDir('dir1/dir2'));
        $this->assertTrue($this->adapter()->deleteDir('dir1'));

        // Root should be empty again
        $result = $listDirectoryContents->invokeArgs($this->adapter(), ['', false]);
        $this->assertTrue(is_array($result));
        $this->assertEquals(count($result), 0);

        $this->assertNull($this->adapter()->disconnect());
        $this->assertFalse($this->adapter()->isConnected());
    }
}
