<?php

namespace Esign\Flysystem\Ssh2;

use League\Flysystem\Adapter\AbstractFtpAdapter;
use League\Flysystem\Adapter\Polyfill\StreamedCopyTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Exception;
use InvalidArgumentException;
use League\Flysystem\Util;

class Ssh2Adapter extends AbstractFtpAdapter
{
    use StreamedCopyTrait;

    /**
     * @var resource
     */
    protected $connection = null;

    /**
     * @var resource
     */
    public $sftp;

    /**
     * @var int
     */
    protected $port = 22;

    /**
     * @var string
     */
    protected $hostFingerprint;

    /**
     * @var string
     */
    protected $privateKey;

    /**
     * @var string
     */
    protected $publicKey;

    /**
     * @var int
     */
    protected $directoryPerm = 0744;

    /**
     * @var array
     */
    protected $configurable = [
        'host',
        'hostFingerprint',
        'port',
        'username',
        'password',
        'timeout',
        'root',
        'privateKey',
        'publicKey',
        'passphrase',
        'permPrivate',
        'permPublic',
        'directoryPerm'
    ];

    /**
     * Set the finger print of the public key of the host you are connecting to.
     *
     * If the key does not match the server identification, the connection will
     * be aborted.
     *
     * @param string $fingerprint Example: '88:76:75:96:c1:26:7c:dd:9f:87:50:db:ac:c4:a8:7c'.
     *
     * @return $this
     */
    public function setHostFingerprint($fingerprint)
    {
        $this->hostFingerprint = $fingerprint;

        return $this;
    }

    /**
     * Set the private key (path to local file).
     *
     * @param string $key
     *
     * @return $this
     */
    public function setPrivateKey($key)
    {
        $this->privateKey = $key;

        return $this;
    }

    /**
     * Set the public key (path to local file).
     *
     * @param string $key
     *
     * @return $this
     */
    public function setPublicKey($key)
    {
        $this->publicKey = $key;

        return $this;
    }

    /**
     * Set the passphrase for the privatekey.
     *
     * @param string $passphrase
     *
     * @return $this
     */
    public function setPassphrase($passphrase)
    {
        $this->passphrase = $passphrase;

        return $this;
    }

    /**
     * Set permissions for new directory
     *
     * @param int $directoryPerm
     *
     * @return $this
     */
    public function setDirectoryPerm($directoryPerm)
    {
        $this->directoryPerm = $directoryPerm;

        return $this;
    }

    /**
     * Get permissions for new directory
     *
     * @return int
     */
    public function getDirectoryPerm()
    {
        return $this->directoryPerm;
    }

    /**
     * Prefix a path.
     *
     * @param string $path
     *
     * @return string
     */
    protected function prefix($path)
    {
        return $this->root . ltrim($path, $this->separator);
    }

    /**
     * List the contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array
     */
    protected function listDirectoryContents($directory, $recursive = true)
    {
        $sftp = $this->getSftp();
        $location = $this->prefix($directory);
        $filenamePrefix = "ssh2.sftp://$sftp/";
        $handle = @opendir("$filenamePrefix$location");

        if (!$handle) {
            return [];
        }

        $exclude = ['.', '..'];
        while (($filename = readdir($handle)) !== false) {
            if (in_array($filename, $exclude)) {
                continue;
            }

            $path = empty($directory) ? $filename : ($directory . '/' . $filename);
            $statInfo = ssh2_sftp_stat($sftp, "$location/$filename");
            $normalized = $this->normalizeListingObject($path, $statInfo);
            $result[] = $normalized;

            if ($recursive && $normalized['type'] === 'dir') {
                $result = array_merge($result, $this->listDirectoryContents($path));
            }
        }

        closedir($handle);
        return $result;
    }

    protected function normalizeListingObject($path, array $statInfo)
    {
        $type = $this->detectType($statInfo['mode']);
        $permissions = $this->normalizePermissions($statInfo['mode']);
        $timestamp = $statInfo['mtime'];

        if ($type === 'dir') {
            return compact('path', 'timestamp', 'type');
        }

        $visibility = $this->visibility($permissions);
        $size = $statInfo['size'];

        return compact('path', 'timestamp', 'type', 'visibility', 'size');
    }

    protected function detectType($permissions)
    {
        $permissions = decoct($permissions);
        $typeInt = octdec(substr($permissions, 0, -4));
        return $typeInt === 4 ? 'dir' : 'file';
    }

    protected function normalizePermissions($permissions)
    {
        $permissions = decoct($permissions);
        return octdec(substr($permissions, -3));
    }

    protected function visibility($permissions)
    {
        return $permissions & 0044 ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE;
    }

    public function connect()
    {
        $this->connection = $this->connection ?: ssh2_connect($this->host, $this->port);

        if (!$this->connection) {
            throw new ConnectionErrorException('Could not connect to server.');
        }

        $this->login();
        $this->initializeSftpSubsystem();
        $this->setConnectionRoot();
    }

    protected function login()
    {
        if ($this->hostFingerprint) {
            $fingerprint = ssh2_fingerprint($this->connection);

            if (0 !== strcasecmp($this->hostFingerprint, $fingerprint)) {
                throw new ConnectionErrorException(
                    'The authenticity of host '.$this->host.' can\'t be established.'
                );
            }
        }

        if ($this->privateKey && $this->publicKey) {
            if (! ssh2_auth_pubkey_file($this->connection, $this->getUsername(), $this->publicKey, $this->privateKey)) {
                throw new ConnectionErrorException('Could not connect using public key.');
            }
            return;
        }

        if (! ssh2_auth_password($this->connection, $this->getUsername(), $this->getPassword())) {
            throw new ConnectionErrorException('Could not connect using plain password.');
        }

        return;
    }

    /**
     * @return resource
     */
    public function getSftp()
    {
        $this->getConnection();
        return $this->sftp;
    }

    protected function initializeSftpSubsystem()
    {
        $this->sftp = ssh2_sftp($this->connection);

        if (!$this->sftp) {
            throw new ConnectionErrorException('Could not initialize SFTP subsystem.');
        }
    }

    /**
     * Set the connection root.
     *
     * @throws InvalidRootException
     */
    public function setConnectionRoot()
    {
        $root = $this->getRoot();

        if (! $root) {
            return;
        }

        if (ssh2_sftp_stat($this->getSftp(), $root) === false) {
            throw new InvalidRootException('Root is invalid or does not exist: ' . $root);
        }

        $this->setRoot($root);
    }

    protected function exec(string $cmd)
    {
        if (!($stream = ssh2_exec($this->connection, $cmd))) {
            throw new Exception('SSH command failed');
        }
        stream_set_blocking($stream, true);

        $data = '';
        while ($buf = fread($stream, 4096)) {
            $data .= $buf;
        }
        fclose($stream);
        return $data;
    }

    /**
     * Disconnect
     */
    public function disconnect()
    {
        if ($this->connection) {
            $this->sftp = null;
            ssh2_disconnect($this->connection);
            $this->connection = null;
        }
    }

    public function isConnected()
    {
        return is_resource($this->connection);
    }

    public function write($path, $contents, Config $config)
    {
        // TODO: Implement write() method.
    }

    public function writeStream($path, $resource, Config $config)
    {
        // TODO: Implement writeStream() method.
    }

    public function update($path, $contents, Config $config)
    {
        // TODO: Implement update() method.
    }

    public function updateStream($path, $resource, Config $config)
    {
        // TODO: Implement updateStream() method.
    }

    public function rename($path, $newpath)
    {
        $source = $this->prefix($path);
        $target = $this->prefix($newpath);

        return ssh2_sftp_rename($this->getSftp(), $source, $target);
    }

    public function delete($path)
    {
        $location = $this->prefix($path);
        return ssh2_sftp_unlink($this->getSftp(), $location);
    }

    public function deleteDir($dirname)
    {
        $location = $this->prefix($dirname);
        return ssh2_sftp_rmdir($this->getSftp(), $location);
    }

    public function createDir($dirname, Config $config)
    {
        $location = $this->prefix($dirname);

        if (!ssh2_sftp_mkdir($this->getSftp(), $location, $this->directoryPerm, true)) {
            return false;
        }

        return ['path' => $dirname];
    }

    public function setVisibility($path, $visibility)
    {
        $location = $this->prefix($path);
        $visibility = ucfirst($visibility);

        if (!isset($this->{'perm' . $visibility})) {
            throw new InvalidArgumentException('Unknown visibility: '.$visibility);
        }

        ssh2_sftp_chmod($this->getSftp(), $location, $this->{'perm' . $visibility});
    }

    public function read($path)
    {
        $sftp = $this->getSftp();
        $location = $this->prefix($path);
        $contents = @file_get_contents("ssh2.sftp://$sftp$location");

        return $contents === false
            ? false
            : compact('path', 'contents');
    }

    public function readStream($path)
    {
        $connection = $this->getConnection();
        $location = $this->prefix($path);
        $stream = tmpfile();
        $streamFilename = stream_get_meta_data($stream)['uri'];

        if (!ssh2_scp_recv($connection, $location, $streamFilename)) {
            fclose($stream);
            return false;
        }

        rewind($stream);

        return compact('stream', 'path');
    }

    public function getMetadata($path)
    {
        $location = $this->prefix($path);
        $statInfo = ssh2_sftp_stat($this->getSftp(), $location);

        $size = $statInfo['size'];
        $timestamp = $statInfo['mtime'];
        $type = $this->detectType($statInfo['mode']);
        $permissions = $this->normalizePermissions($statInfo['mode']);
        $visibility = $this->visibility($permissions);

        return compact('path', 'timestamp', 'type', 'visibility', 'size');
    }

    public function getMimetype($path)
    {
        $location = $this->prefix($path);
        if (!$data = $this->read($location)) {
            return false;
        }

        $data['mimetype'] = Util::guessMimeType($path, $data['contents']);

        return $data;
    }

    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }
}
