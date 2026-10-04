<?php

declare(strict_types=1);

namespace Bongo\Packages\ApiTools\Commands;

use System\Console\Command;

class MakeResourceCommand extends Command
{
    protected string $name = 'make:resource';
    protected string $description = 'Create a new API resource or resource collection class';

    public function handle(array $args, array $options): int
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->error("Please specify the resource name (e.g. php bongo make:resource UserResource).");
            return 1;
        }

        $isCollection = isset($options['collection']) || isset($options['c']) || str_ends_with($name, 'Collection');
        $baseClass = $isCollection ? 'ResourceCollection' : 'JsonResource';

        $baseDir = function_exists('base_path') ? base_path('app/Http/Resources') : 'app/Http/Resources';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }

        $filePath = "{$baseDir}/{$name}.php";
        if (file_exists($filePath)) {
            $this->warn("Resource [{$name}] already exists at {$filePath}.");
            return 1;
        }

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Bongo\Packages\ApiTools\Resources\\{$baseClass};
use System\Http\Request;

class {$name} extends {$baseClass}
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request \$request): array
    {
        return [
            'id' => \$this->id,
            // 'name' => \$this->name,
            // 'created_at' => \$this->created_at,
        ];
    }
}

PHP;

        if (file_put_contents($filePath, $content) !== false) {
            $this->info("Created Resource [{$name}] successfully.");
            $this->line("Path: {$filePath}");
            return 0;
        }

        $this->error("Failed to write resource file to {$filePath}.");
        return 1;
    }
}
