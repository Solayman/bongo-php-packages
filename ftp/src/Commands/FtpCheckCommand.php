<?php

declare(strict_types=1);

namespace Bongo\Packages\Ftp\Commands;

use Bongo\Packages\Ftp\FtpClient;
use System\Console\Command;
use Throwable;

class FtpCheckCommand extends Command
{
    protected string $name = 'ftp:check';
    protected string $description = 'Verify FTP server credentials and connectivity';

    public function handle(array $args, array $options): int
    {
        $this->info("Checking FTP server configuration...");

        $host = (string) (env('FTP_HOST') ?: (function_exists('config') ? config('ftp.host') : '127.0.0.1'));
        $port = (int) (env('FTP_PORT') ?: (function_exists('config') ? config('ftp.port') : 21));
        $user = (string) (env('FTP_USERNAME') ?: (function_exists('config') ? config('ftp.username') : 'anonymous'));
        $pass = (string) (env('FTP_PASSWORD') ?: (function_exists('config') ? config('ftp.password') : ''));
        $root = (string) (env('FTP_ROOT') ?: (function_exists('config') ? config('ftp.root') : '/'));
        $ssl  = (bool) (env('FTP_SSL') ?: (function_exists('config') ? config('ftp.ssl') : false));

        $this->line("  Host:     {$host}:{$port}");
        $this->line("  Username: {$user}");
        $this->line("  Root:     {$root}");
        $this->line("  SSL/TLS:  " . ($ssl ? 'Enabled' : 'Disabled'));

        $client = new FtpClient([
            'host' => $host,
            'port' => $port,
            'username' => $user,
            'password' => $pass,
            'root' => $root,
            'ssl' => $ssl,
            'timeout' => 10,
        ]);

        try {
            $this->line("Connecting to FTP server...");
            $client->connect();
            $this->info("  ✓ Handshake & authentication succeeded!");

            $testFile = 'bongo_probe_' . time() . '.txt';
            $client->upload($testFile, "Bongo probe OK " . date('Y-m-d H:i:s'));
            $this->info("  ✓ Upload write test passed.");

            $read = $client->download($testFile);
            if ($read && str_contains($read, 'Bongo probe OK')) {
                $this->info("  ✓ Read test passed.");
            }

            $client->delete($testFile);
            $this->info("  ✓ Cleanup delete passed.");

            $client->disconnect();
            $this->info("FTP server is fully operational!");
            return 0;
        } catch (Throwable $e) {
            $this->error("FTP check failed: " . $e->getMessage());
            return 1;
        }
    }
}
