<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\Command;
use Core\Container;

class DbSeedCommand extends Command
{
    protected string $signature   = 'db:seed {--class=DatabaseSeeder}';
    protected string $description = 'Run database seeders';

    public function __construct(private readonly Container $container)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $class = $this->option('class') ?? 'DatabaseSeeder';

        if (!class_exists($class)) {
            $this->error("Seeder class [{$class}] not found.");
            return self::FAILURE;
        }

        $seeder = $this->container->make($class);
        $seeder->run();
        $this->success("Seeded [{$class}]");
        return self::SUCCESS;
    }
}
