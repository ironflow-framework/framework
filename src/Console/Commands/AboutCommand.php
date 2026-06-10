<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Application;
use Ironflow\Console\Command;
use Ironflow\Database\Connection;
use Ironflow\Module\ModuleManager;

/**
 * Display framework and environment information.
 */
class AboutCommand extends Command
{
    protected string $signature   = 'about';
    protected string $description = 'Display information about the IronFlow application';

    protected function handle(): int
    {
        $app = Application::getInstance();

        $this->newLine();
        $this->line('  <options=bold,fg=blue>IronFlow Framework</> — Application Information');
        $this->line('  ' . str_repeat('─', 54));
        $this->newLine();

        // ── Environment ──────────────────────────────────────────────
        $this->line('  <options=bold>Environment</>');
        $this->twoColumnDetail('  PHP Version',       PHP_VERSION);
        $this->twoColumnDetail('  Framework Version', $_ENV['APP_VERSION'] ?? '0.1.0');
        $this->twoColumnDetail('  App Name',          $_ENV['APP_NAME'] ?? 'IronFlow');
        $this->twoColumnDetail('  Environment',       $_ENV['APP_ENV'] ?? 'production');
        $this->twoColumnDetail('  Debug Mode',        ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? '<info>enabled</info>' : '<fg=gray>disabled</>');
        $this->twoColumnDetail('  Base Path',         base_path());
        $this->newLine();

        // ── Cache ────────────────────────────────────────────────────
        $this->line('  <options=bold>Cache</>');
        $cacheDriver = function_exists('apcu_fetch') ? 'APCu' : 'File';
        $this->twoColumnDetail('  Driver', $cacheDriver);
        $this->newLine();

        // ── Database ─────────────────────────────────────────────────
        $this->line('  <options=bold>Database</>');
        try {
            /** @var Connection $db */
            $db = $app->getContainer()->make(Connection::class);
            $cls = get_class($db->getPlatform());
            $platform = substr($cls, (int) strrpos($cls, '\\') + 1);
            $this->twoColumnDetail('  Driver', $platform);
        } catch (\Throwable) {
            $this->twoColumnDetail('  Driver', '<fg=gray>not connected</>');
        }
        $this->newLine();

        // ── Modules ──────────────────────────────────────────────────
        $this->line('  <options=bold>Modules</>');
        try {
            /** @var ModuleManager $manager */
            $manager = $app->getContainer()->make(ModuleManager::class);
            $modules = $manager->getLoadedModules();
            if (empty($modules)) {
                $this->line('  <fg=gray>  (none)</>');
            } else {
                foreach ($modules as $name => $module) {
                    $this->twoColumnDetail("  {$name}", '<info>loaded</>');
                }
            }
        } catch (\Throwable) {
            $this->line('  <fg=gray>  (module system not available)</>');
        }
        $this->newLine();

        return self::SUCCESS;
    }
}