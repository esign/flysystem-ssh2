<?php

namespace Tests\Feature;

use Esign\Flysystem\Ssh2\Ssh2Adapter;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use \PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
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

    public function testConnection(): void
    {
        // Connect
        $this->assertFalse($this->adapter()->isConnected());
        $this->assertNull($this->adapter()->connect());
        $this->assertTrue($this->adapter()->isConnected());

        // Root should be clean to start with
        $result = $this->adapter()->listContents('', false);
        $this->assertTrue(is_array($result));;
        $this->assertCount(0, $result);

        // Create folder
        $this->assertTrue(is_array($this->adapter()->createDir('dir1', new Config())));
        $this->assertTrue(is_array($this->adapter()->has('dir1')));

        // We should now have 1 listing
        $result = $this->adapter()->listContents('', false);
        $this->assertTrue(is_array($result));
        $this->assertCount(1, $result);

        // We should also have 1 listing recursively
        $result = $this->adapter()->listContents('', true);
        $this->assertTrue(is_array($result));
        $this->assertCount(1, $result);

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
        $result = $this->adapter()->listContents('', true);
        $this->assertTrue(is_array($result));
        $this->assertEquals(2, count($result));

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
        $this->assertArrayHasKey('visibility', $result);
        $this->assertArrayHasKey('size', $result);

        // File should be of type "file"
        $this->assertEquals('file', $result['type']);
        $this->assertEquals(754, $result['size']);

        // Visibility should be public
        $this->assertEquals(AbstractAdapter::VISIBILITY_PUBLIC, $result['visibility']);

        // Check if we can read the file
        $result = $this->adapter()->read('dir1/dir2/flysystem-stream.svg');
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('contents', $result);
        $this->assertArrayHasKey('path', $result);

        // Check if we can read the file as a stream
        $result = $this->adapter()->readStream('dir1/dir2/flysystem-stream.svg');
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('stream', $result);
        $this->assertArrayHasKey('path', $result);
        fclose($result['stream']);

        // Check the mimetype
        $result = $this->adapter()->getMimetype('dir1/dir2/flysystem-stream.svg');
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('mimetype', $result);
        $this->assertStringStartsWith('image/svg', $result['mimetype']);

        // Change visibility to private
        $result = $this->adapter()->setVisibility('dir1/dir2/flysystem-stream.svg', AdapterInterface::VISIBILITY_PRIVATE);
        $this->assertTrue($result);
        $result = $this->adapter()->getMetadata('dir1/dir2/flysystem-stream.svg');
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('visibility', $result);
        $this->assertEquals(AdapterInterface::VISIBILITY_PRIVATE, $result['visibility']);

        // Change visibility to public
        $result = $this->adapter()->setVisibility('dir1/dir2/flysystem-stream.svg', AdapterInterface::VISIBILITY_PUBLIC);
        $this->assertTrue($result);
        $result = $this->adapter()->getMetadata('dir1/dir2/flysystem-stream.svg');
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('visibility', $result);
        $this->assertEquals(AdapterInterface::VISIBILITY_PUBLIC, $result['visibility']);

        // Write file from blob
        $contents = file_get_contents(__DIR__ . '/../../test_files/flysystem.svg');
        $result = $this->adapter()->write('dir1/dir2/flysystem.svg', $contents, new Config());
        $this->assertTrue(is_array($result));
        $this->assertTrue(is_array($this->adapter()->has('dir1/dir2/flysystem.svg')));

        // Copy a file
        $result = $this->adapter()->copy('dir1/dir2/flysystem.svg', 'dir1/dir2/esign.svg');
        $this->assertTrue($result);
        $this->assertTrue(is_array($this->adapter()->has('dir1/dir2/esign.svg')));

        // Update a file
        $contents = file_get_contents(__DIR__ . '/../../test_files/esign.svg');
        $result = $this->adapter()->update('dir1/dir2/esign.svg', $contents, new Config());
        $this->assertTrue(is_array($result));
        $result = $this->adapter()->has('dir1/dir2/esign.svg');
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('visibility', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertEquals(1435, $result['size']);

        // Shouldn't be able to delete non-empty folder
        $this->assertFalse($this->adapter()->deleteDir('dir1/dir2'));

        // Remove files & folders
        $this->assertTrue($this->adapter()->delete('dir1/dir2/flysystem-stream.svg'));
        $this->assertTrue($this->adapter()->delete('dir1/dir2/flysystem.svg'));
        $this->assertTrue($this->adapter()->delete('dir1/dir2/esign.svg'));
        $this->assertTrue($this->adapter()->deleteDir('dir1/dir2'));
        $this->assertTrue($this->adapter()->deleteDir('dir1'));

        // Root should be empty again
        $result = $this->adapter()->listContents('', true);
        $this->assertTrue(is_array($result));
        $this->assertEquals(count($result), 0);

        // Disconnect
        $this->assertNull($this->adapter()->disconnect());
        $this->assertFalse($this->adapter()->isConnected());
    }
}
