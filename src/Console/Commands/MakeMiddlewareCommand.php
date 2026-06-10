<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\Command;

class MakeMiddlewareCommand extends Command
{
    protected string $signature   = 'make:middleware {name}';
    protected string $description = 'Create a new middleware class';

    protected function handle(): int
    {
        $name = (string) $this->argument('name');
        $path = base_path("app/Middleware/{$name}.php");
        @mkdir(dirname($path), 0755, true);

        if (is_file($path)) {
            $this->error("Middleware [{$name}] already exists.");
            return self::FAILURE;
        }

        file_put_contents($path, <<<PHP
<?php

declare(strict_types=1);

namespace App\\Middleware;

use Core\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\Response;

class {$name}
{
    public function handle(Request \$request, callable \$next): Response
    {
        // Before
        \$response = \$next(\$request);
        // After
        return \$response;
    }
}
PHP);
        $this->success("Middleware [{$name}] created at app/Middleware/{$name}.php");
        return self::SUCCESS;
    }
}
