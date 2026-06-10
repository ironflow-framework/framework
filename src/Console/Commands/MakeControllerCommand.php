<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;

class MakeControllerCommand extends Command
{
    protected string $signature = 'make:controller {name} {--module=} {--resource}';
    protected string $description = 'Create a new controller class';

    protected function handle(): int
    {
        $name = (string) $this->argument('name');
        $module = $this->option('module');
        $resource = (bool) $this->option('resource');

        if ($module) {
            $path = base_path("modules/{$module}/Controllers/{$name}.php");
            $ns = "Modules\\{$module}\\Controllers";
        } else {
            $path = base_path("app/Controllers/{$name}.php");
            $ns = "App\\Controllers";
            @mkdir(base_path('app/Controllers'), 0755, true);
        }

        if (is_file($path)) {
            $this->error("Controller [{$name}] already exists.");
            return self::FAILURE;
        }

        $methods = $resource ? $this->resourceMethods() : $this->basicMethods();
        $content = $this->stub($ns, $name, $methods);

        file_put_contents($path, $content);
        $this->success("Controller [{$name}] created.");
        return self::SUCCESS;
    }

    private function stub(string $ns, string $name, string $methods): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use Ironflow\\Http\\Request;
use Ironflow\\Http\\Response;

class {$name}
{
{$methods}
}
PHP;
    }

    private function basicMethods(): string
    {
        return <<<PHP
    public function index(Request \$request): Response
    {
        return Response::view('index', []);
    }
PHP;
    }

    private function resourceMethods(): string
    {
        return <<<'PHP'
    public function index(Request $request): Response
    {
        return Response::view('index', []);
    }

    public function create(Request $request): Response
    {
        return Response::view('create', []);
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([]);
        // store...
        return Response::redirect('/')->route('index');
    }

    public function show(Request $request, int $id): Response
    {
        return Response::view('show', ['id' => $id]);
    }

    public function edit(Request $request, int $id): Response
    {
        return Response::view('edit', ['id' => $id]);
    }

    public function update(Request $request, int $id): Response
    {
        $data = $request->validate([]);
        // update...
        return Response::redirect('/')->route('index');
    }

    public function destroy(Request $request, int $id): Response
    {
        // delete...
        return Response::redirect('/')->route('index');
    }
PHP;
    }
}
