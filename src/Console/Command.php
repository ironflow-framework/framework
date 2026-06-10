<?php

declare(strict_types=1);

namespace Ironflow\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Base console command. Parses an Artisan-style signature into Symfony
 * InputArgument / InputOption definitions and exposes SymfonyStyle helpers.
 *
 * Signature format: 'name:action {arg} {--option} {--flag=} {arg?=default}'
 */
abstract class Command extends SymfonyCommand
{
    protected string $signature = '';
    protected string $description = '';
    protected SymfonyStyle $io;
    protected InputInterface $input;
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
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);
        return $this->handle() ?? self::SUCCESS;
    }

    abstract protected function handle(): int|null;

    // ─────────────────────── IO helpers ──────────────────────────────

    protected function argument(string $name): mixed
    {
        return $this->input->getArgument($name);
    }

    protected function option(string $name): mixed
    {
        return $this->input->getOption($name);
    }

    protected function info(string $message): void
    {
        $this->io->writeln("<info>{$message}</info>");
    }
    protected function warn(string $message): void
    {
        $this->io->writeln("<comment>{$message}</comment>");
    }
    protected function error(string $message): void
    {
        $this->io->writeln("<error>{$message}</error>");
    }
    protected function success(string $message): void
    {
        $this->io->writeln("<info>✓ {$message}</info>");
    }
    protected function line(string $message): void
    {
        $this->io->writeln($message);
    }

    protected function newLine(int $count = 1): void
    {
        $this->io->newLine($count);
    }

    /**
     * Run a labeled task. Prints "✓ title" on success or "✗ title" on failure.
     * The callable should return false (or throw) to indicate failure.
     */
    protected function task(string $title, callable $task): bool
    {
        $this->io->write("  <fg=blue>…</> {$title}");
        try {
            $result = $task();
            $ok = ($result !== false);
        } catch (\Throwable) {
            $ok = false;
        }
        $this->io->write("\r");
        if ($ok) {
            $this->io->writeln("  <info>✓</info> {$title}");
        } else {
            $this->io->writeln("  <error>✗</error> {$title}");
        }
        return $ok;
    }

    /**
     * Print a two-column label → value line (dot-padded, like Laravel's `twoColumnDetail`).
     */
    protected function twoColumnDetail(string $left, string $right, string $style = 'fg=gray'): void
    {
        $width = 30;
        $dots  = str_pad($left, $width, '.');
        $this->io->writeln("  <{$style}>{$dots}</> <options=bold>{$right}</>");
    }

    /**
     * Create a progress bar, invoke the callback with it, then finish.
     * Usage: $this->progress(count($items), function($bar) use ($items) { … $bar->advance(); });
     */
    protected function progress(int $max, callable $callback): void
    {
        $bar = $this->io->createProgressBar($max);
        $bar->start();
        $callback($bar);
        $bar->finish();
        $this->io->newLine();
    }

    /**
     * Return the argument value, or interactively ask the user if it is empty.
     * Make the argument optional in the signature ({name?}) to enable this.
     */
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

    // ─────────────────────── Signature parsing ───────────────────────

    private function parseSignature(): void
    {
        // name:action {arg} {arg?} {arg=default} {--option} {--option=} {--option=default}
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

        $mode = InputArgument::REQUIRED;
        $default = null;

        if (str_ends_with($token, '?')) {
            $token = rtrim($token, '?');
            $mode = InputArgument::OPTIONAL;
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

        $mode = InputOption::VALUE_NONE;
        $default = null;

        if (str_ends_with($token, '=')) {
            $token = rtrim($token, '=');
            $mode = InputOption::VALUE_OPTIONAL;
        } elseif (str_contains($token, '=')) {
            [$token, $default] = explode('=', $token, 2);
            $mode = InputOption::VALUE_OPTIONAL;
        }

        $this->addOption(trim($token), null, $mode, trim($description), $default);
    }
}
