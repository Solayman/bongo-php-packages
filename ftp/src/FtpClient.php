<?php

declare(strict_types=1);

namespace Bongo\Packages\Ftp;

use Bongo\Packages\Ftp\Exceptions\FtpException;

class FtpClient
{
    protected array $config;
    /** @var resource|null Native FTP connection resource */
    protected $connection = null;
    /** @var resource|null Socket connection for zero-dependency socket transport */
    protected $socket = null;
    protected bool $connected = false;

    /** @var array<string, string>|null Mock in-memory filesystem for testing */
    protected static ?array $fakeFilesystem = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'host' => '127.0.0.1',
            'port' => 21,
            'username' => 'anonymous',
            'password' => '',
            'root' => '/',
            'passive' => true,
            'ssl' => false,
            'connect_timeout' => 4,
            'timeout' => 8,
        ], $config);

        // Clamp timeouts for fast failure deadlines
        $this->config['connect_timeout'] = min(4, max(1, (int) $this->config['connect_timeout']));
        $this->config['timeout'] = min(8, max(1, (int) $this->config['timeout']));
    }

    public static function fake(?array $initialFiles = []): void
    {
        static::$fakeFilesystem = $initialFiles ?? [];
    }

    public static function stopFaking(): void
    {
        static::$fakeFilesystem = null;
    }

    public static function isFaking(): bool
    {
        return static::$fakeFilesystem !== null;
    }

    public function connect(): void
    {
        if (static::isFaking()) {
            $this->connected = true;
            return;
        }

        if ($this->connected) {
            return;
        }

        $host = (string) $this->config['host'];
        $port = (int) $this->config['port'];
        $connectTimeout = (int) $this->config['connect_timeout'];
        $timeout = (int) $this->config['timeout'];

        // Mode 1: ext-ftp if available
        if (function_exists('ftp_connect')) {
            $conn = $this->config['ssl'] && function_exists('ftp_ssl_connect')
                ? @ftp_ssl_connect($host, $port, $connectTimeout)
                : @ftp_connect($host, $port, $connectTimeout);

            if ($conn === false) {
                throw new FtpException("Could not connect to FTP host [{$host}:{$port}].");
            }

            $login = @ftp_login($conn, (string) $this->config['username'], (string) $this->config['password']);
            if (!$login) {
                @ftp_close($conn);
                throw new FtpException("FTP authentication failed for user [{$this->config['username']}].");
            }

            if (!empty($this->config['passive'])) {
                @ftp_pasv($conn, true);
            }

            $this->connection = $conn;
            $this->connected = true;
            return;
        }

        // Mode 2: Pure-PHP raw socket transport (zero dependencies)
        $errno = 0;
        $errstr = '';
        $sock = @fsockopen($host, $port, $errno, $errstr, (float) $connectTimeout);
        if (!$sock) {
            throw new FtpException("Socket FTP connection failed to [{$host}:{$port}]: {$errstr} ({$errno})");
        }

        stream_set_timeout($sock, $timeout);
        $banner = fgets($sock);
        if (!$banner || !str_starts_with($banner, '220')) {
            fclose($sock);
            throw new FtpException("Invalid FTP greeting banner: {$banner}");
        }

        // Send USER
        fwrite($sock, "USER {$this->config['username']}\r\n");
        $res = fgets($sock);
        if ($res && str_starts_with($res, '331')) {
            fwrite($sock, "PASS {$this->config['password']}\r\n");
            $res = fgets($sock);
        }

        if (!$res || (!str_starts_with($res, '230') && !str_starts_with($res, '200'))) {
            fclose($sock);
            throw new FtpException("Socket FTP login failed: {$res}");
        }

        $this->socket = $sock;
        $this->connected = true;
    }

    public function disconnect(): void
    {
        if (static::isFaking()) {
            $this->connected = false;
            return;
        }

        if ($this->connection !== null && function_exists('ftp_close')) {
            @ftp_close($this->connection);
            $this->connection = null;
        }

        if ($this->socket !== null) {
            @fwrite($this->socket, "QUIT\r\n");
            @fclose($this->socket);
            $this->socket = null;
        }

        $this->connected = false;
    }

    public function upload(string $remotePath, string $contents): bool
    {
        $remotePath = $this->normalizePath($remotePath);

        if (static::isFaking()) {
            static::$fakeFilesystem[$remotePath] = $contents;
            return true;
        }

        $this->connect();

        if ($this->connection !== null && function_exists('ftp_fput')) {
            $temp = fopen('php://temp', 'r+');
            fwrite($temp, $contents);
            rewind($temp);
            $this->ensureRemoteDirectory(dirname($remotePath));
            $res = @ftp_fput($this->connection, $remotePath, $temp, FTP_BINARY);
            fclose($temp);
            return $res;
        }

        return false;
    }

    public function download(string $remotePath): ?string
    {
        $remotePath = $this->normalizePath($remotePath);

        if (static::isFaking()) {
            return static::$fakeFilesystem[$remotePath] ?? null;
        }

        $this->connect();

        if ($this->connection !== null && function_exists('ftp_fget')) {
            $temp = fopen('php://temp', 'r+');
            if (@ftp_fget($this->connection, $temp, $remotePath, FTP_BINARY)) {
                rewind($temp);
                $data = stream_get_contents($temp);
                fclose($temp);
                return $data !== false ? $data : null;
            }
            fclose($temp);
            return null;
        }

        return null;
    }

    public function delete(string $remotePath): bool
    {
        $remotePath = $this->normalizePath($remotePath);

        if (static::isFaking()) {
            if (isset(static::$fakeFilesystem[$remotePath])) {
                unset(static::$fakeFilesystem[$remotePath]);
                return true;
            }
            return false;
        }

        $this->connect();

        if ($this->connection !== null && function_exists('ftp_delete')) {
            return @ftp_delete($this->connection, $remotePath);
        }

        return false;
    }

    public function exists(string $remotePath): bool
    {
        $remotePath = $this->normalizePath($remotePath);

        if (static::isFaking()) {
            return isset(static::$fakeFilesystem[$remotePath]);
        }

        $this->connect();

        if ($this->connection !== null && function_exists('ftp_size')) {
            $size = @ftp_size($this->connection, $remotePath);
            return $size >= 0;
        }

        return false;
    }

    public function size(string $remotePath): ?int
    {
        $remotePath = $this->normalizePath($remotePath);

        if (static::isFaking()) {
            return isset(static::$fakeFilesystem[$remotePath]) ? strlen(static::$fakeFilesystem[$remotePath]) : null;
        }

        $this->connect();

        if ($this->connection !== null && function_exists('ftp_size')) {
            $size = @ftp_size($this->connection, $remotePath);
            return $size >= 0 ? $size : null;
        }

        return null;
    }

    public function lastModified(string $remotePath): ?int
    {
        $remotePath = $this->normalizePath($remotePath);

        if (static::isFaking()) {
            return isset(static::$fakeFilesystem[$remotePath]) ? time() : null;
        }

        $this->connect();

        if ($this->connection !== null && function_exists('ftp_mdtm')) {
            $time = @ftp_mdtm($this->connection, $remotePath);
            return $time > 0 ? $time : null;
        }

        return null;
    }

    public function list(string $directory = ''): array
    {
        $directory = $this->normalizePath($directory);

        if (static::isFaking()) {
            $matched = [];
            $prefix = $directory !== '' ? rtrim($directory, '/') . '/' : '';
            foreach (array_keys(static::$fakeFilesystem ?? []) as $path) {
                if ($prefix === '' || str_starts_with($path, $prefix)) {
                    $matched[] = $path;
                }
            }
            return $matched;
        }

        $this->connect();

        if ($this->connection !== null && function_exists('ftp_nlist')) {
            $list = @ftp_nlist($this->connection, $directory ?: '.');
            return is_array($list) ? $list : [];
        }

        return [];
    }

    public function makeDirectory(string $path): bool
    {
        $path = $this->normalizePath($path);

        if (static::isFaking()) {
            return true;
        }

        $this->connect();

        if ($this->connection !== null && function_exists('ftp_mkdir')) {
            return (bool) @ftp_mkdir($this->connection, $path);
        }

        return false;
    }

    public function deleteDirectory(string $path): bool
    {
        $path = $this->normalizePath($path);

        if (static::isFaking()) {
            $prefix = rtrim($path, '/') . '/';
            foreach (array_keys(static::$fakeFilesystem ?? []) as $f) {
                if (str_starts_with($f, $prefix)) {
                    unset(static::$fakeFilesystem[$f]);
                }
            }
            return true;
        }

        $this->connect();

        if ($this->connection !== null && function_exists('ftp_rmdir')) {
            return @ftp_rmdir($this->connection, $path);
        }

        return false;
    }

    protected function ensureRemoteDirectory(string $dir): void
    {
        if ($dir === '.' || $dir === '/' || $dir === '') {
            return;
        }

        $parts = explode('/', trim($dir, '/'));
        $current = '';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            if ($this->connection !== null && function_exists('ftp_mkdir')) {
                @ftp_mkdir($this->connection, $current);
            }
        }
    }

    protected function normalizePath(string $path): string
    {
        $path = str_replace("\0", '', $path);
        $root = rtrim((string) ($this->config['root'] ?? '/'), '/');
        $raw = str_replace('\\', '/', $path);

        $segments = [];
        foreach (explode('/', $raw) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }
        $clean = implode('/', $segments);

        if ($root === '' || $root === '/') {
            return '/' . $clean;
        }

        return $clean === '' ? $root : $root . '/' . $clean;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }
}
