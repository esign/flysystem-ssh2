<?php

namespace Esign\Flysystem\Ssh2;

use League\Flysystem\Adapter\AbstractFtpAdapter;
use League\Flysystem\Config;
use Exception;

class Ssh2Adapter extends AbstractFtpAdapter
{
    /**
     * @var resource
     */
    protected $connection = null;

    /**
     * @var resource
     */
    protected $sftp;

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
        return $this->root.ltrim($path, $this->separator);
    }

    public function listDirectoryContents($directory, $recursive = false)
    {
        $connection = $this->getConnection();
        $sftp = $this->sftp;
        $directory = rtrim($directory, $this->separator) . '/';
        $location = $this->prefix($directory);
        $handle = @opendir("ssh2.sftp://$sftp/$location");

        if (!$handle) {
            return [];
        }

        $exclude = ['.', '..'];
        while (($filename = readdir($handle)) !== false) {
            if (in_array($filename, $exclude)) {
                continue;
            }
            var_dump(ssh2_sftp_stat($sftp, "$location/$filename"));
        }

        closedir($handle);
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

    protected function ssh2Callbacks()
    {
        return [

        ];
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

        if (ssh2_sftp_stat($this->sftp, $root) === false) {
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
        // TODO: Implement rename() method.
    }

    public function copy($path, $newpath)
    {
        // TODO: Implement copy() method.
    }

    public function delete($path)
    {
        // TODO: Implement delete() method.
    }

    public function deleteDir($dirname)
    {
        // TODO: Implement deleteDir() method.
    }

    public function createDir($dirname, Config $config)
    {
        // TODO: Implement createDir() method.
    }

    public function setVisibility($path, $visibility)
    {
        // TODO: Implement setVisibility() method.
    }

    public function read($path)
    {
        // TODO: Implement read() method.
    }

    public function readStream($path)
    {
        // TODO: Implement readStream() method.
    }

    public function getMetadata($path)
    {
        // TODO: Implement getMetadata() method.
    }

    public function getMimetype($path)
    {
        // TODO: Implement getMimetype() method.
    }

    public function getTimestamp($path)
    {
        // TODO: Implement getTimestamp() method.
    }
}
