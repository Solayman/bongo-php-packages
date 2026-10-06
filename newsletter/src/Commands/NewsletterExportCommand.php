<?php

declare(strict_types=1);

namespace Bongo\Packages\Newsletter\Commands;

use Bongo\Packages\Newsletter\Newsletter;
use System\Console\Command;

class NewsletterExportCommand extends Command
{
    protected string $signature = 'newsletter:export';
    protected string $description = 'Export newsletter subscribers to a CSV file';

    public function handle(array $args, array $options): int
    {
        $status = $options['status'] ?? 'subscribed';
        if ($status === 'all') {
            $status = null;
        }

        $newsletter = new Newsletter();
        $count = $newsletter->count($status);

        if ($count === 0) {
            $this->warn("No subscribers found for status: " . ($status ?? 'all'));
            return 0;
        }

        $this->info("Exporting {$count} newsletter subscribers...");
        $csv = $newsletter->exportCsv($status);

        $outputPath = $options['output'] ?? 'storage/newsletter_subscribers.csv';
        if (str_starts_with($outputPath, 'storage/') && !is_dir(dirname($outputPath))) {
            @mkdir(dirname($outputPath), 0755, true);
        }

        file_put_contents($outputPath, $csv);

        $this->info("✓ Successfully exported {$count} subscribers to: {$outputPath}");
        return 0;
    }
}
