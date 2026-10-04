<?php

namespace Bongo\Packages\S3;

use InvalidArgumentException;
use RuntimeException;

/**
 * Zero-dependency AWS Signature Version 4 (SigV4) REST Client.
 *
 * Fully compatible with Amazon S3, DigitalOcean Spaces, Cloudflare R2,
 * MinIO, Wasabi, and Backblaze B2.
 */
class S3Client
{
    protected string $key;
    protected string $secret;
    protected string $region;
    protected string $bucket;
    protected ?string $endpoint;
    protected bool $usePathStyle;
    protected ?string $url;
    protected bool $throw;

    /** @var callable|null Mock transport for testing */
    public static $mockTransport = null;

    public function __construct(array $config)
    {
        $this->key          = (string) ($config['key'] ?? '');
        $this->secret       = (string) ($config['secret'] ?? '');
        $this->region       = (string) ($config['region'] ?? 'us-east-1');
        $this->bucket       = (string) ($config['bucket'] ?? '');
        $this->endpoint     = !empty($config['endpoint']) ? rtrim((string) $config['endpoint'], '/') : null;
        $this->usePathStyle = (bool) ($config['use_path_style_endpoint'] ?? false);
        $this->url          = !empty($config['url']) ? rtrim((string) $config['url'], '/') : null;
        $this->throw        = (bool) ($config['throw'] ?? true);
    }

    public static function setMockTransport(?callable $transport): void
    {
        self::$mockTransport = $transport;
    }

    public function getBucket(): string
    {
        return $this->bucket;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * Upload an object into the bucket.
     */
    public function putObject(string $key, string $contents, array $headers = []): bool
    {
        $cleanKey = ltrim($key, '/');
        $headers['Content-Length'] = (string) strlen($contents);
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = $this->detectMimeType($cleanKey, $contents);
        }

        $res = $this->request('PUT', $cleanKey, [], $headers, $contents);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    /**
     * Retrieve an object from the bucket.
     */
    public function getObject(string $key): ?string
    {
        $cleanKey = ltrim($key, '/');
        $res = $this->request('GET', $cleanKey);
        if ($res['status'] === 200) {
            return $res['body'];
        }
        if ($res['status'] === 404) {
            return null;
        }

        $this->handleErrorResponse($res, "Failed getting object [$cleanKey]");
        return null;
    }

    /**
     * Check if an object exists and retrieve metadata headers.
     */
    public function headObject(string $key): ?array
    {
        $cleanKey = ltrim($key, '/');
        $res = $this->request('HEAD', $cleanKey);
        if ($res['status'] === 200) {
            return $res['headers'];
        }
        if ($res['status'] === 404) {
            return null;
        }

        $this->handleErrorResponse($res, "Failed checking object [$cleanKey]");
        return null;
    }

    /**
     * Delete an object from the bucket.
     */
    public function deleteObject(string $key): bool
    {
        $cleanKey = ltrim($key, '/');
        $res = $this->request('DELETE', $cleanKey);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    /**
     * List objects in the bucket (ListObjectsV2 API).
     *
     * @return array<int, string> List of object keys
     */
    public function listObjectsV2(string $prefix = '', string $delimiter = ''): array
    {
        $query = ['list-type' => '2'];
        if ($prefix !== '') {
            $query['prefix'] = rtrim(ltrim($prefix, '/'), '/') . '/';
        }
        if ($delimiter !== '') {
            $query['delimiter'] = $delimiter;
        }

        $res = $this->request('GET', '', $query);
        if ($res['status'] !== 200) {
            $this->handleErrorResponse($res, "Failed listing objects with prefix [$prefix]");
            return [];
        }

        return $this->parseListObjectsXml($res['body']);
    }

    /**
     * Generate a public or CDN URL for an object.
     */
    public function getPublicUrl(string $key): string
    {
        $cleanKey = ltrim($key, '/');
        if ($this->url !== null && $this->url !== '') {
            return $this->url . '/' . $cleanKey;
        }

        $hostInfo = $this->resolveHostAndPath($cleanKey);
        $scheme = $hostInfo['scheme'];
        $host = $hostInfo['host'];
        $path = $hostInfo['path'];

        return "{$scheme}://{$host}{$path}";
    }

    /**
     * Generate a presigned SigV4 temporary URL with query parameter authentication.
     */
    public function generatePresignedUrl(string $key, int $expiresInSeconds = 3600, string $method = 'GET'): string
    {
        $cleanKey = ltrim($key, '/');
        $hostInfo = $this->resolveHostAndPath($cleanKey);
        $scheme = $hostInfo['scheme'];
        $host = $hostInfo['host'];
        $canonicalUri = $this->canonicalUri($hostInfo['path']);

        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $credentialScope = "{$dateStamp}/{$this->region}/s3/aws4_request";

        $queryParams = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => "{$this->key}/{$credentialScope}",
            'X-Amz-Date'          => $amzDate,
            'X-Amz-Expires'       => (string) $expiresInSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ];

        // 1. Canonical Query String
        ksort($queryParams);
        $queryParts = [];
        foreach ($queryParams as $k => $v) {
            $queryParts[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $canonicalQueryString = implode('&', $queryParts);

        // 2. Canonical Headers
        $canonicalHeaders = "host:{$host}\n";
        $signedHeaders = "host";

        // 3. Payload hash for presigned URL is UNSIGNED-PAYLOAD
        $payloadHash = 'UNSIGNED-PAYLOAD';

        $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        // 4. String to Sign
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        // 5. Signature
        $signingKey = $this->getSigningKey($dateStamp);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        return "{$scheme}://{$host}{$canonicalUri}?{$canonicalQueryString}&X-Amz-Signature={$signature}";
    }

    /**
     * Execute an authenticated SigV4 HTTP request.
     */
    public function request(
        string $method,
        string $key = '',
        array $query = [],
        array $headers = [],
        string $body = ''
    ): array {
        if ($this->key === '' || $this->secret === '' || $this->bucket === '') {
            throw new RuntimeException("S3 credentials not configured. Please set AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, and AWS_BUCKET.");
        }

        $cleanKey = ltrim($key, '/');
        $hostInfo = $this->resolveHostAndPath($cleanKey);
        $scheme = $hostInfo['scheme'];
        $host = $hostInfo['host'];
        $canonicalUri = $this->canonicalUri($hostInfo['path']);

        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $credentialScope = "{$dateStamp}/{$this->region}/s3/aws4_request";
        $payloadHash = hash('sha256', $body);

        $headers['Host'] = $host;
        $headers['x-amz-date'] = $amzDate;
        $headers['x-amz-content-sha256'] = $payloadHash;

        // Canonical query string
        ksort($query);
        $queryParts = [];
        foreach ($query as $k => $v) {
            $queryParts[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $canonicalQueryString = implode('&', $queryParts);

        // Canonical headers
        $lowerHeaders = [];
        foreach ($headers as $k => $v) {
            $lowerHeaders[strtolower(trim($k))] = trim($v);
        }
        ksort($lowerHeaders);

        $canonicalHeaders = '';
        foreach ($lowerHeaders as $k => $v) {
            $canonicalHeaders .= "{$k}:{$v}\n";
        }
        $signedHeaders = implode(';', array_keys($lowerHeaders));

        // Canonical Request
        $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        // String to Sign
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        // Signature
        $signingKey = $this->getSigningKey($dateStamp);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $headers['Authorization'] = "AWS4-HMAC-SHA256 Credential={$this->key}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $url = "{$scheme}://{$host}{$canonicalUri}" . ($canonicalQueryString !== '' ? "?{$canonicalQueryString}" : '');

        // Mock transport check for unit tests
        if (self::$mockTransport !== null) {
            return (self::$mockTransport)($method, $url, $headers, $body);
        }

        return $this->sendHttpRequest($method, $url, $headers, $body);
    }

    /**
     * Compute SigV4 derivation signing key.
     */
    protected function getSigningKey(string $dateStamp): string
    {
        $kSecret  = "AWS4" . $this->secret;
        $kDate    = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    /**
     * Resolve host and URI path for endpoint styles.
     */
    protected function resolveHostAndPath(string $key): array
    {
        $scheme = 'https';

        if ($this->endpoint !== null && $this->endpoint !== '') {
            $parsed = parse_url($this->endpoint);
            $scheme = $parsed['scheme'] ?? 'https';
            $epHost = $parsed['host'] ?? '127.0.0.1';
            if (isset($parsed['port'])) {
                $epHost .= ':' . $parsed['port'];
            }

            if ($this->usePathStyle) {
                $path = '/' . $this->bucket . ($key !== '' ? '/' . $key : '');
                return ['scheme' => $scheme, 'host' => $epHost, 'path' => $path];
            }

            $host = "{$this->bucket}.{$epHost}";
            $path = $key !== '' ? '/' . $key : '/';
            return ['scheme' => $scheme, 'host' => $host, 'path' => $path];
        }

        // Standard AWS endpoints
        if ($this->usePathStyle) {
            $host = "s3.{$this->region}.amazonaws.com";
            $path = '/' . $this->bucket . ($key !== '' ? '/' . $key : '');
            return ['scheme' => $scheme, 'host' => $host, 'path' => $path];
        }

        $host = "{$this->bucket}.s3.{$this->region}.amazonaws.com";
        $path = $key !== '' ? '/' . $key : '/';
        return ['scheme' => $scheme, 'host' => $host, 'path' => $path];
    }

    /**
     * Canonical URI format per RFC 3986 (preserving slashes).
     */
    protected function canonicalUri(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }
        $segments = explode('/', $path);
        $encoded = array_map('rawurlencode', $segments);
        return implode('/', $encoded);
    }

    /**
     * Execute pure PHP stream request.
     */
    protected function sendHttpRequest(string $method, string $url, array $headers, string $body): array
    {
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = "{$k}: {$v}";
        }

        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headerLines),
                'content'       => $body,
                'timeout'       => 30,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $stream = @fopen($url, 'r', false, $context);
        if ($stream === false) {
            if ($this->throw) {
                throw new RuntimeException("Could not connect to S3 endpoint at $url");
            }
            return ['status' => 0, 'headers' => [], 'body' => ''];
        }

        $meta = stream_get_meta_data($stream);
        $responseBody = (string) stream_get_contents($stream);
        fclose($stream);

        $responseHeaders = $meta['wrapper_data'] ?? [];
        $status = 0;
        $parsedHeaders = [];

        foreach ($responseHeaders as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $h, $m)) {
                $status = (int) $m[1];
            } elseif (str_contains($h, ':')) {
                [$hk, $hv] = explode(':', $h, 2);
                $parsedHeaders[strtolower(trim($hk))] = trim($hv);
            }
        }

        return [
            'status'  => $status,
            'headers' => $parsedHeaders,
            'body'    => $responseBody,
        ];
    }

    /**
     * Parse XML response from ListObjectsV2.
     */
    protected function parseListObjectsXml(string $xmlContent): array
    {
        if ($xmlContent === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        if ($xml === false) {
            return [];
        }

        $keys = [];
        if (isset($xml->Contents)) {
            foreach ($xml->Contents as $content) {
                if (isset($content->Key)) {
                    $keys[] = (string) $content->Key;
                }
            }
        }

        return $keys;
    }

    protected function detectMimeType(string $key, string $contents): string
    {
        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $mimes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'pdf'  => 'application/pdf',
            'txt'  => 'text/plain',
            'html' => 'text/html',
            'json' => 'application/json',
            'xml'  => 'application/xml',
            'zip'  => 'application/zip',
            'csv'  => 'text/csv',
        ];

        return $mimes[$ext] ?? 'application/octet-stream';
    }

    protected function handleErrorResponse(array $response, string $context): void
    {
        if (!$this->throw) {
            return;
        }

        $msg = $context . " (HTTP {$response['status']})";
        if (!empty($response['body'])) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response['body']);
            if ($xml && isset($xml->Message)) {
                $msg .= ": " . (string) $xml->Message;
            }
        }

        throw new RuntimeException($msg);
    }
}
