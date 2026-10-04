<?php

namespace Bongo\Packages\S3\Commands;

use Bongo\Packages\S3\S3Disk;
use System\Console\Command;
use System\Storage\Storage;
use Throwable;

/**
 * Diagnostic command to test S3 object storage connectivity.
 *
 * Usage: php bongo s3:check
 */
class S3CheckCommand extends Command
{
    protected string $name = 's3:check';
    protected string $description = 'Verify S3 / Cloud Object Storage connectivity and bucket permissions';

    public function handle(array $args, array $options): int
    {
        $this->info("Checking S3 / Object Storage configuration...");

        $key    = (string) (env('AWS_ACCESS_KEY_ID') ?: config('s3.key') ?: config('storage.disks.s3.key'));
        $secret = (string) (env('AWS_SECRET_ACCESS_KEY') ?: config('s3.secret') ?: config('storage.disks.s3.secret'));
        $bucket = (string) (env('AWS_BUCKET') ?: config('s3.bucket') ?: config('storage.disks.s3.bucket'));
        $region = (string) (env('AWS_DEFAULT_REGION') ?: config('s3.region') ?: config('storage.disks.s3.region', 'us-east-1'));
        $ep     = (string) (env('AWS_ENDPOINT') ?: config('s3.endpoint') ?: config('storage.disks.s3.endpoint', ''));

        $this->line("  Bucket:   " . ($bucket ?: "\033[31mMISSING\033[0m"));
        $this->line("  Region:   " . $region);
        $this->line("  Key:      " . ($key ? substr($key, 0, 4) . '...' . substr($key, -4) : "\033[31mMISSING\033[0m"));
        if ($ep !== '') {
            $this->line("  Endpoint: " . $ep);
        }

        if ($key === '' || $secret === '' || $bucket === '') {
            $this->error("Missing required S3 credentials. Please define in .env:");
            $this->line("  AWS_ACCESS_KEY_ID=");
            $this->line("  AWS_SECRET_ACCESS_KEY=");
            $this->line("  AWS_BUCKET=");
            return 1;
        }

        $probeFile = 'bongo_probe_' . time() . '.txt';
        $probeContent = 'Bongo S3 probe test at ' . date('c');

        try {
            $disk = Storage::disk('s3');
            if (!($disk instanceof S3Disk)) {
                $this->error("Disk 's3' is not an instance of S3Disk.");
                return 1;
            }

            $this->line("Testing bucket write permission...");
            $disk->put($probeFile, $probeContent);
            $this->info("  Write: OK");

            $this->line("Testing bucket read permission...");
            $read = $disk->get($probeFile);
            if ($read !== $probeContent) {
                $this->error("  Read: content mismatch!");
                return 1;
            }
            $this->info("  Read: OK");

            $this->line("Testing temporary presigned URL generation...");
            $url = $disk->temporaryUrl($probeFile, 300);
            $this->info("  Presigned URL: OK");

            $this->line("Testing bucket delete permission...");
            $disk->delete($probeFile);
            $this->info("  Delete: OK");

            $this->info("All S3 checks passed successfully! Bucket is ready for use.");
            return 0;
        } catch (Throwable $e) {
            $this->error("S3 check failed: " . $e->getMessage());
            return 1;
        }
    }
}
