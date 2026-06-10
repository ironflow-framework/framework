<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;

class DownCommand extends Command
{
    protected string $signature = 'down {--secret=}';
    protected string $description = 'Put the application into maintenance mode';

    protected function handle(): int
    {
        $secret = $this->option('secret') ?? bin2hex(random_bytes(8));
        $flag = base_path('storage/maintenance.flag');
        file_put_contents($flag, $secret);
        $this->warn("Application is now in maintenance mode. Bypass secret: {$secret}");
        return self::SUCCESS;
    }
}
