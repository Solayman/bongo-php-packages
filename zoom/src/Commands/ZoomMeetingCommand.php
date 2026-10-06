<?php

declare(strict_types=1);

namespace Bongo\Packages\Zoom\Commands;

use Bongo\Packages\Zoom\Zoom;
use System\Console\Command;

class ZoomMeetingCommand extends Command
{
    protected string $name = 'zoom:create';
    protected string $description = 'Create a new Zoom meeting from CLI';

    public function handle(array $args, array $options): int
    {
        $topic = $options['topic'] ?? ($args[0] ?? 'CLI Scheduled Meeting');
        $duration = (int) ($options['duration'] ?? 45);

        $this->info("Creating Zoom meeting: '{$topic}' ({$duration} mins)...");

        $zoom = new Zoom();
        $res = $zoom->createMeeting([
            'topic'    => $topic,
            'duration' => $duration,
        ]);

        if (!empty($res['join_url'])) {
            $this->info("✓ Meeting created successfully!");
            $this->line("Meeting ID : " . ($res['id'] ?? 'N/A'));
            $this->line("Join URL   : " . $res['join_url']);
            if (!empty($res['password'])) {
                $this->line("Password   : " . $res['password']);
            }
            return 0;
        }

        $this->error("Failed to create meeting: " . json_encode($res));
        return 1;
    }
}
