<?php

namespace Bongo\Packages\Sms\Commands;

use System\Console\Command;

class SMSCheckCommand extends Command
{
    protected string $name = 'sms:check';
    protected string $description = 'Inspect configured SMS driver settings and credentials';

    public function handle(array $args, array $options): int
    {
        $this->info("Checking SMS Gateway configuration...");

        $driver = (string) env('SMS_DRIVER', 'log');
        $this->line("  Active Driver: $driver");

        switch ($driver) {
            case 'log':
                $this->info("Log driver active. Messages are recorded to storage/logs/sms.log.");
                return 0;

            case 'bulksmsbd':
                $key = (string) env('BULKSMSBD_API_KEY', '');
                $sid = (string) env('BULKSMSBD_SENDER_ID', '');
                if ($key === '' || $sid === '') {
                    $this->error("Missing BulkSMSBD credentials in .env:");
                    $this->line("  BULKSMSBD_API_KEY=your_key");
                    $this->line("  BULKSMSBD_SENDER_ID=your_sender_id");
                    return 1;
                }
                $this->line("  API Key:   " . substr($key, 0, 4) . '****');
                $this->line("  Sender ID: " . $sid);
                $this->info("BulkSMSBD configuration is valid.");
                return 0;

            case 'sslwireless':
                $token = (string) env('SSLWIRELESS_API_TOKEN', '');
                $sid = (string) env('SSLWIRELESS_SID', '');
                if ($token === '' || $sid === '') {
                    $this->error("Missing SSL Wireless credentials in .env:");
                    $this->line("  SSLWIRELESS_API_TOKEN=your_token");
                    $this->line("  SSLWIRELESS_SID=your_sid");
                    return 1;
                }
                $this->line("  API Token: " . substr($token, 0, 4) . '****');
                $this->line("  SID:       " . $sid);
                $this->info("SSL Wireless configuration is valid.");
                return 0;

            case 'alphasms':
                $key = (string) env('ALPHASMS_API_KEY', '');
                if ($key === '') {
                    $this->error("Missing Alpha SMS credentials in .env:");
                    $this->line("  ALPHASMS_API_KEY=your_key");
                    return 1;
                }
                $this->line("  API Key:   " . substr($key, 0, 4) . '****');
                $this->info("Alpha SMS configuration is valid.");
                return 0;

            default:
                $this->warn("Unrecognized SMS driver [$driver].");
                return 1;
        }
    }
}
