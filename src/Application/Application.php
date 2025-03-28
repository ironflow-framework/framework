<?php

declare(strict_types=1);

namespace IronFlow\Application;

use Closure;
use IronFlow\Http\Request;
use IronFlow\Http\Response;
use IronFlow\Routing\Router;
use IronFlow\Core\ErrorHandler;
use IronFlow\Support\Config;
use IronFlow\Application\Container;

class Application
{
   private string $basePath;
   private static ?self $instance = null;
   private Container $container;

   public function __construct()
   {
      $this->basePath = dirname(__DIR__, 2);
   }

   public static function getInstance(): self
   {
      if (self::$instance === null) {
         self::$instance = new self();
      }
      return self::$instance;
   }

   public function singleton(string $name, Closure $callback)
   {
      $this->container->singleton($name, $callback);
   }

   public function withBasePath(string $basePath): self
   {
      $this->basePath = $basePath;
      return $this;
   }

   public function configure(array $config, array $services = []): self
   {
      foreach ($config as $key => $value) {
         Config::set($key, $value);
      }
      
      foreach ($services as $service) {
         $this->container->singleton($service, function () use ($service) {
            return new $service($this);
         });
      }

      return $this;
   }

   public function withRoutes(string ...$files): self
   {
      Router::init();
      foreach ($files as $file) {
         if (file_exists($file)) {
            require $file;
         }
      }
      return $this;
   }

   public function build(): self
   {
      return $this;
   }

   public function run(): void
   {
      try {
         $request = Request::capture();
         $response = Router::dispatch($request);

         if (!($response instanceof Response)) {
            $response = new Response((string) $response);
         }

         $response->send();
      } catch (\Throwable $e) {
         ErrorHandler::handleException($e);
      }
   }

   public function getBasePath(): string
   {
      return $this->basePath;
   }
}
