<?php

declare(strict_types=1);

namespace Ironflow\Exceptions;

use Ironflow\Http\JsonResponse;
use Ironflow\Http\Request;
use Ironflow\Http\Response;
use Ironflow\Template\Engine;
use Ironflow\Validation\ValidationException;
use Psr\Log\LoggerInterface;

use Throwable;

/**
 * Global exception handler. Routes to:
 *  - Dev debug page (Whoops-like) if APP_DEBUG=true
 *  - Styled error Twig templates in production
 *  - JSON error if the request expects JSON
 */
class Handler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Engine $view,
        private readonly bool $debug,
        private readonly string $errorViewsPath
    ) {
    }

    public function render(Request $request, Throwable $e): Response|JsonResponse
    {
        // ValidationException: 422 JSON or redirect-back with flashed errors (web)
        if ($e instanceof ValidationException) {
            return $this->renderValidationError($request, $e);
        }

        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;

        $this->logException($request, $e, $status);

        if ($request->wantsJson()) {
            return $this->renderJson($e, $status);
        }

        if ($this->debug) {
            return $this->renderDebugPage($request, $e, $status);
        }

        return $this->renderErrorPage($e, $status);
    }

    private function renderValidationError(Request $request, ValidationException $e): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse(
                ['message' => 'Les données soumises sont invalides.', 'errors' => $e->errors()],
                422
            );
        }

        // Web: flash errors + old input, then redirect back
        $referer = $request->headers->get('referer', '/');

        try {
            $session = \Ironflow\Application::getInstance()
                ->getContainer()
                ->make(\Ironflow\Session\SessionManager::class);
            $session->flash('_errors', $e->errors());
            $session->flash('_old_input', $request->all());
        } catch (Throwable) {
        }

        return new Response('', 302, ['Location' => $referer]);
    }

    private function renderJson(Throwable $e, int $status): JsonResponse
    {
        $data = ['error' => $e->getMessage(), 'status' => $status];
        if ($this->debug) {
            $data['trace'] = array_map(
                fn($f) => ($f['file'] ?? '') . ':' . ($f['line'] ?? ''),
                array_slice($e->getTrace(), 0, 10)
            );
        }
        return new JsonResponse($data, $status);
    }

    private function renderErrorPage(Throwable $e, int $status): Response
    {
        // Try app error views first, then core fallback
        $template = "@core_errors/{$status}.html.twig";

        $appView = $this->errorViewsPath . "/{$status}.html.twig";
        if (is_file($appView)) {
            $template = "errors/{$status}.html.twig";
        }

        try {
            $html = $this->view->render($template, [
                'code'    => $status,
                'message' => $e->getMessage(),
            ]);
            return new Response($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
        } catch (Throwable) {
            return new Response(
                $this->fallbackHtml($status, $e->getMessage()),
                $status,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }
    }

    private function renderDebugPage(Request $request, Throwable $e, int $status): Response
    {
        $frames = $e->getTrace();
        $html   = $this->buildDebugHtml($request, $e, $status, $frames);
        return new Response($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function buildDebugHtml(Request $request, Throwable $e, int $status, array $frames): string
    {
        $class = get_class($e);
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES);
        $file = $e->getFile();
        $line = $e->getLine();

        $sourceHtml = $this->buildSourceSnippet($file, $line);
        $traceHtml = $this->buildTraceHtml($frames);
        $requestHtml = $this->buildRequestInfo($request);

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>⚠ {$status} — {$class}</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  pre code { font-family: 'JetBrains Mono', 'Fira Code', monospace; }
  .line-fault { background: rgba(239,68,68,.25); display: block; }
</style>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen font-sans">
  <div class="max-w-6xl mx-auto p-6">
    <!-- Header -->
    <div class="mb-6">
      <span class="inline-block px-3 py-1 rounded-full text-xs font-mono bg-red-900/50 text-red-300 mb-3">{$class}</span>
      <h1 class="text-3xl font-bold text-white mb-2">{$message}</h1>
      <p class="text-gray-400 font-mono text-sm">{$file}:{$line}</p>
    </div>

    <!-- Tabs -->
    <div x-data="{tab:'source'}" class="mb-6">
      <nav class="flex gap-1 mb-4 border-b border-gray-800 pb-2">
        <button onclick="showTab('source')"  id="tab-source"  class="tab-btn px-4 py-2 rounded-t text-sm font-medium bg-indigo-600 text-white">Source</button>
        <button onclick="showTab('trace')"   id="tab-trace"   class="tab-btn px-4 py-2 rounded-t text-sm font-medium bg-gray-800 text-gray-300 hover:bg-gray-700">Stack Trace</button>
        <button onclick="showTab('request')" id="tab-request" class="tab-btn px-4 py-2 rounded-t text-sm font-medium bg-gray-800 text-gray-300 hover:bg-gray-700">Request</button>
      </nav>

      <div id="pane-source"  class="tab-pane">{$sourceHtml}</div>
      <div id="pane-trace"   class="tab-pane hidden">{$traceHtml}</div>
      <div id="pane-request" class="tab-pane hidden">{$requestHtml}</div>
    </div>
  </div>

<script>
function showTab(name) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
  document.getElementById('pane-' + name).classList.remove('hidden');
  document.querySelectorAll('.tab-btn').forEach(b => {
    b.classList.remove('bg-indigo-600','text-white');
    b.classList.add('bg-gray-800','text-gray-300');
  });
  var btn = document.getElementById('tab-' + name);
  btn.classList.remove('bg-gray-800','text-gray-300');
  btn.classList.add('bg-indigo-600','text-white');
}
</script>
</body>
</html>
HTML;
    }

    private function buildSourceSnippet(string $file, int $faultLine): string
    {
        if (!is_file($file)) {
            return '<p class="text-gray-500">Source file not readable.</p>';
        }

        $lines = file($file);
        $start = max(0, $faultLine - 11);
        $end = min(count($lines) - 1, $faultLine + 9);

        $html = '<pre class="bg-gray-900 rounded-lg p-4 overflow-x-auto text-sm leading-relaxed"><code>';
        for ($i = $start; $i <= $end; $i++) {
            $lineNum = $i + 1;
            $content = htmlspecialchars($lines[$i] ?? '', ENT_QUOTES);
            $isFault = ($lineNum === $faultLine);
            $class = $isFault ? ' class="line-fault"' : '';
            $numStyle = $isFault ? 'text-red-400' : 'text-gray-600';
            $html .= "<span{$class}><span class=\"{$numStyle} select-none mr-4\">" . str_pad((string) $lineNum, 4) . "</span>{$content}</span>";
        }
        $html .= '</code></pre>';

        return '<div class="mb-2 text-xs text-gray-400 font-mono">' . htmlspecialchars($file, ENT_QUOTES) . '</div>' . $html;
    }

    private function buildTraceHtml(array $frames): string
    {
        $html = '<div class="space-y-2">';
        foreach (array_slice($frames, 0, 20) as $i => $frame) {
            $file = htmlspecialchars($frame['file'] ?? '[internal]', ENT_QUOTES);
            $line = $frame['line'] ?? '';
            $func = htmlspecialchars(($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''), ENT_QUOTES);
            $html .= <<<FRAME
<div class="bg-gray-900 rounded p-3 font-mono text-sm">
  <div class="text-indigo-400">#{$i} {$func}()</div>
  <div class="text-gray-500 text-xs mt-1">{$file}:{$line}</div>
</div>
FRAME;
        }
        return $html . '</div>';
    }

    private function buildRequestInfo(Request $request): string
    {
        $method = htmlspecialchars($request->getMethod(), ENT_QUOTES);
        $uri = htmlspecialchars($request->getRequestUri(), ENT_QUOTES);
        $headers = '';
        foreach ($request->headers->all() as $name => $values) {
            $key = htmlspecialchars($name, ENT_QUOTES);
            $val = htmlspecialchars(implode(', ', $values), ENT_QUOTES);
            $headers .= "<tr><td class='py-1 pr-6 text-indigo-400'>{$key}</td><td class='py-1 text-gray-300'>{$val}</td></tr>";
        }

        $bodyParams = '';
        foreach ($request->request->all() as $k => $v) {
            $k = htmlspecialchars((string) $k, ENT_QUOTES);
            $v = htmlspecialchars((string) $v, ENT_QUOTES);
            $bodyParams .= "<tr><td class='py-1 pr-6 text-indigo-400'>{$k}</td><td class='py-1 text-gray-300'>{$v}</td></tr>";
        }

        return <<<HTML
<div class="space-y-4">
  <div class="bg-gray-900 rounded p-4">
    <h3 class="text-gray-400 uppercase text-xs tracking-widest mb-3">Request</h3>
    <div class="font-mono"><span class="text-green-400">{$method}</span> <span class="text-white">{$uri}</span></div>
  </div>
  <div class="bg-gray-900 rounded p-4">
    <h3 class="text-gray-400 uppercase text-xs tracking-widest mb-3">Headers</h3>
    <table class="font-mono text-sm w-full">{$headers}</table>
  </div>
  <div class="bg-gray-900 rounded p-4">
    <h3 class="text-gray-400 uppercase text-xs tracking-widest mb-3">Body Parameters</h3>
    <table class="font-mono text-sm w-full">{$bodyParams}</table>
  </div>
</div>
HTML;
    }

    private function logException(Request $request, Throwable $e, int $status): void
    {
        if ($status >= 500) {
            $this->logger->error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->getRequestUri(),
                'method' => $request->getMethod(),
            ]);
        }
    }

    private function fallbackHtml(int $status, string $message): string
    {
        return "<!DOCTYPE html><html><head><title>{$status}</title></head><body>"
            . "<h1>Error {$status}</h1><p>" . htmlspecialchars($message, ENT_QUOTES) . "</p>"
            . "</body></html>";
    }
}
