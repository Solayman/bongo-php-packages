<?php

declare(strict_types=1);

namespace Bongo\Packages\GoogleMeet\Commands;

use Bongo\Packages\GoogleMeet\GoogleMeet;
use System\Console\Command;

class GoogleMeetCommand extends Command
{
    protected string $name = 'meet:create';
    protected string $description = 'Create a new Google Meet conference from CLI';

    public function handle(array $args, array $options): int
    {
        $summary = $options['summary'] ?? ($args[0] ?? 'CLI Google Meet Session');
        $duration = (int) ($options['duration'] ?? 45);

        $this->info("Creating Google Meet: '{$summary}' ({$duration} mins)...");

        $client = new GoogleMeet();
        $res = $client->createMeeting([
            'summary'  => $summary,
            'duration' => $duration,
        ]);

        if (!empty($res['hangoutLink'])) {
            $this->info("✓ Google Meet created successfully!");
            $this->line("Event ID   : " . ($res['id'] ?? 'N/A'));
            $this->line("Meet Link  : " . $res['hangoutLink']);
            if (!empty($res['htmlLink'])) {
                $this->line("Calendar   : " . $res['htmlLink']);
            }
            return 0;
        }

        $this->error("Failed to create Google Meet: " . json_encode($res));
        return 1;
    }
}
