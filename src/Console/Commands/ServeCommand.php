<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;

class ServeCommand extends Command
{
    protected string $signature = 'serve {--host=localhost} {--port=8080}';
    protected string $description = 'Start the built-in PHP development server';

    protected function handle(): int
    {
        $host = $this->option('host') ?? 'localhost';
        $port = $this->option('port') ?? '8080';
        $root = base_path('public');

        $this->info("IronFlow dev server started on http://{$host}:{$port}");
        $this->line("Document root: {$root}");
        $this->warn("Press Ctrl+C to stop.");

        passthru("php -S {$host}:{$port} -t \"{$root}\"");
        return self::SUCCESS;
    }
}
