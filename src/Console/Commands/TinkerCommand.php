<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Application;
use Ironflow\Console\Command;

/**
 * Interactive PHP REPL for IronFlow.
 * Uses PsySH when available (composer require psy/psysh --dev),
 * otherwise falls back to a minimal readline eval loop.
 */
class TinkerCommand extends Command
{
    protected string $signature   = 'tinker';
    protected string $description = 'Start an interactive PHP REPL with the application booted';

    protected function handle(): int
    {
        // Boot the application into the REPL scope
        $app = Application::getInstance();

        if (class_exists(\Psy\Shell::class)) {
            return $this->runPsySh($app);
        }

        return $this->runFallbackRepl($app);
    }

    private function runPsySh(Application $app): int
    {
        $config = new \Psy\Configuration();
        $config->setStartingVariables(['app' => $app]);

        $shell = new \Psy\Shell($config);
        $shell->run();

        return self::SUCCESS;
    }

    private function runFallbackRepl(Application $app): int
    {
        $this->newLine();
        $this->line('  <options=bold,fg=blue>IronFlow Tinker</> — Interactive REPL');
        $this->line('  <fg=gray>Tip: install psy/psysh for a richer experience.</>');
        $this->line('  <fg=gray>Type </><fg=yellow>exit</><fg=gray> or press Ctrl+D to quit.</>');
        $this->newLine();

        if (!function_exists('readline')) {
            $this->error('readline extension is not available.');
            return self::FAILURE;
        }

        // Make $app available in eval scope
        $__app = $app;

        while (true) {
            $line = readline('>>> ');

            if ($line === false || in_array(trim((string) $line), ['exit', 'quit'], true)) {
                break;
            }

            if (trim((string) $line) === '') {
                continue;
            }

            readline_add_history((string) $line);

            // Wrap in a try/catch; support both expression and statement forms
            $code = trim((string) $line);
            if (!str_ends_with($code, ';') && !str_ends_with($code, '}')) {
                $code .= ';';
            }

            try {
                // Run in the current scope so $app/$__app are accessible
                $__result = eval($code); // @phpstan-ignore-line
                if ($__result !== null) {
                    $this->io->writeln(var_export($__result, true));
                }
            } catch (\Throwable $e) {
                $this->error(get_class($e) . ': ' . $e->getMessage());
            }
        }

        $this->newLine();
        return self::SUCCESS;
    }
}