<?php

declare(strict_types=1);

namespace Ironflow\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Base console command.
 *
 * Output convention — every line starts with 3 spaces:
 *   "   BADGE  message"
 *
 * Available badges:
 *   INFO  (blue)   — neutral information
 *   DONE  (green)  — success
 *   WARN  (yellow) — warning
 *   ERROR (red)    — error
 */
abstract class Command extends SymfonyCommand
{
    protected string $signature   = '';
    protected string $description = '';

    protected SymfonyStyle    $io;
    protected InputInterface  $input;
    protected OutputInterface $output;

    protected function configure(): void
    {
        if (empty($this->signature)) {
            return;
        }
        $this->parseSignature();
        $this->setDescription($this->description);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input  = $input;
        $this->output = $output;
        $this->io     = new SymfonyStyle($input, $output);
        return $this->handle() ?? self::SUCCESS;
    }

    abstract protected function handle(): int|null;

    // ── Output helpers ───────────────────────────────────────────────

    protected function info(string $message): void
    {
        $this->output->writeln("   <options=bold;fg=blue>INFO</>  {$message}");
    }

    protected function success(string $message): void
    {
        $this->output->writeln("   <options=bold;fg=green>DONE</>  {$message}");
    }

    protected function warn(string $message): void
    {
        $this->output->writeln("   <options=bold;fg=yellow>WARN</>  {$message}");
    }

    protected function error(string $message): void
    {
        $this->output->writeln("   <options=bold;fg=red>ERROR</>  {$message}");
    }

    protected function line(string $message): void
    {
        $this->output->writeln($message);
    }

    protected function newLine(int $count = 1): void
    {
        $this->output->writeln(array_fill(0, $count, ''));
    }

    /**
     * Dot-padded two-column detail line:
     *   "   Left label ........ Right value"
     *
     * @param string $style  Optional Symfony Console style tag for the right side
     *                       (only applied when $right contains no existing tags).
     */
    protected function twoColumnDetail(string $left, string $right, string $style = ''): void
    {
        $plainLeft   = preg_replace('/<[^>]+>/', '', $left) ?? $left;
        $dots        = str_repeat('.', max(2, 46 - mb_strlen(trim($plainLeft))));
        $styledRight = ($style && !str_contains($right, '<'))
            ? "<{$style}>{$right}</>"
            : $right;
        $this->output->writeln("   {$left} <fg=gray>{$dots}</> {$styledRight}");
    }

    /**
     * Migration result line with dot-padding and a timing/status badge:
     *
     *   "   create_posts_table .........  12ms  DONE"
     *   "   create_posts_table .........  12ms  ROLLBACK"
     *
     * @param bool $rollback  true → yellow ROLLBACK badge, false → green DONE badge
     */
    protected function migrationLine(string $name, int $ms, bool $rollback = false): void
    {
        $badge = $rollback
            ? '<options=bold;fg=yellow>ROLLBACK</>'
            : '<options=bold;fg=green>DONE</>';

        $timeColor = match (true) {
            $ms < 100  => 'fg=green',
            $ms < 500  => 'fg=yellow',
            default    => 'fg=red',
        };

        $nameTrim = mb_strimwidth($name, 0, 50, '…');
        $timePad  = str_pad("{$ms}ms", 7, ' ', STR_PAD_LEFT);
        $dots     = str_repeat('.', max(2, 52 - mb_strlen($nameTrim)));

        $this->output->writeln(
            "   {$nameTrim} <fg=gray>{$dots}</> <{$timeColor}>{$timePad}</>  {$badge}"
        );
    }

    /**
     * Run a labeled task — prints a spinner then DONE / FAIL on completion.
     */
    protected function task(string $title, callable $task): bool
    {
        $this->output->write("   <fg=gray>…</>  {$title}");
        try {
            $result = $task();
            $ok = ($result !== false);
        } catch (\Throwable) {
            $ok = false;
        }
        $this->output->write("\r");
        if ($ok) {
            $this->output->writeln("   <options=bold;fg=green>DONE</>  {$title}");
        } else {
            $this->output->writeln("   <options=bold;fg=red>FAIL</>  {$title}");
        }
        return $ok;
    }

    protected function progress(int $max, callable $callback): void
    {
        $bar = $this->io->createProgressBar($max);
        $bar->start();
        $callback($bar);
        $bar->finish();
        $this->output->writeln('');
    }

    // ── Interactive helpers ──────────────────────────────────────────

    protected function argument(string $name): mixed
    {
        return $this->input->getArgument($name);
    }

    protected function option(string $name): mixed
    {
        return $this->input->getOption($name);
    }

    protected function argumentOrAsk(string $name, string $question, ?string $default = null): string
    {
        $value = (string) ($this->input->getArgument($name) ?? '');
        if ($value !== '') {
            return $value;
        }
        return $this->ask($question, $default);
    }

    protected function ask(string $question, ?string $default = null): string
    {
        return (string) $this->io->ask($question, $default);
    }

    protected function confirm(string $question, bool $default = false): bool
    {
        return $this->io->confirm($question, $default);
    }

    protected function secret(string $question): string
    {
        return (string) $this->io->askHidden($question);
    }

    protected function choice(string $question, array $choices, mixed $default = null): string
    {
        return (string) $this->io->choice($question, $choices, $default);
    }

    protected function table(array $headers, array $rows): void
    {
        $this->io->table($headers, $rows);
    }

    /**
     * Call another registered console command by name.
     *
     * @param array<string, mixed> $arguments  Extra arguments/options to pass
     */
    protected function call(string $command, array $arguments = []): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            $this->error("Cannot call [{$command}]: no application context.");
            return self::FAILURE;
        }
        $input = new ArrayInput(array_merge(['command' => $command], $arguments));
        $input->setInteractive(false);
        return $application->find($command)->run($input, $this->output);
    }

    // ── Signature parsing ────────────────────────────────────────────

    private function parseSignature(): void
    {
        $parts = preg_split('/\s+/', trim($this->signature), 2);
        $this->setName($parts[0]);

        if (!isset($parts[1])) {
            return;
        }

        preg_match_all('/\{([^}]+)\}/', $parts[1], $matches);

        foreach ($matches[1] as $token) {
            $token = trim($token);
            if (str_starts_with($token, '--')) {
                $this->parseOption(substr($token, 2));
            } else {
                $this->parseArgument($token);
            }
        }
    }

    private function parseArgument(string $token): void
    {
        $description = '';
        if (str_contains($token, ':')) {
            [$token, $description] = explode(':', $token, 2);
        }

        $mode    = InputArgument::REQUIRED;
        $default = null;

        if (str_ends_with($token, '?')) {
            $token = rtrim($token, '?');
            $mode  = InputArgument::OPTIONAL;
        } elseif (str_contains($token, '=')) {
            [$token, $default] = explode('=', $token, 2);
            $mode = InputArgument::OPTIONAL;
        }

        $this->addArgument(trim($token), $mode, trim($description), $default);
    }

    private function parseOption(string $token): void
    {
        $description = '';
        if (str_contains($token, ':')) {
            [$token, $description] = explode(':', $token, 2);
        }

        $mode    = InputOption::VALUE_NONE;
        $default = null;

        if (str_ends_with($token, '=')) {
            $token = rtrim($token, '=');
            $mode  = InputOption::VALUE_OPTIONAL;
        } elseif (str_contains($token, '=')) {
            [$token, $default] = explode('=', $token, 2);
            $mode = InputOption::VALUE_OPTIONAL;
        }

        $this->addOption(trim($token), null, $mode, trim($description), $default);
    }
}
