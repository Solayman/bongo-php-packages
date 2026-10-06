<?php

declare(strict_types=1);

namespace Bongo\Packages\QrCode\Commands;

use Bongo\Packages\QrCode\QrCode;
use System\Console\Command;

class QrCodeGenerateCommand extends Command
{
    protected string $signature = 'qrcode:generate {text : Content/URL to encode} {--output= : Optional file path to save SVG or PNG} {--size=250 : Dimension in pixels}';
    protected string $description = 'Generate a QR code directly to file or terminal';

    public function handle(array $args, array $options): int
    {
        $text = $args['text'] ?? '';
        if ($text === '') {
            $this->error('Please provide text or a URL to encode.');
            return 1;
        }

        $size = (int) ($options['size'] ?? 250);
        $output = $options['output'] ?? null;

        $qr = QrCode::generate($text)->size($size);

        if ($output) {
            $qr->save($output);
            $this->info("QR code successfully saved to: {$output}");
        } else {
            $this->line($qr->ascii());
            $this->info("Encoded: {$text}");
        }

        return 0;
    }
}
