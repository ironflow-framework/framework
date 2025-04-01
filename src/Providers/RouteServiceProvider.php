<?php

declare(strict_types=1);

namespace IronFlow\Providers;

use IronFlow\Routing\Router;
use IronFlow\Core\Providers\ServiceProvider;
use IronFlow\Support\Facades\Filesystem;

/**
 * Fournisseur de services pour le système de routage
 * 
 * Ce service provider initialise et configure le router de l'application
 * et charge les fichiers de définition des routes.
 */
class RouteServiceProvider extends ServiceProvider
{
   /**
    * Enregistre les services liés au routage
    *
    * @return void
    */
   public function register(): void
   {
      $this->app->singleton('router', function ($app) {
         return new Router();
      });
   }

   /**
    * Configure le système de routage après son enregistrement
    *
    * @return void
    */
   public function boot(): void
   {
      // Chargement des routes
      $this->loadRoutes();
   }

   /**
    * Charge les fichiers de définition des routes
    *
    * @return void
    */
   protected function loadRoutes(): void
   {

      $appBasePath = $this->app->basePath();

      // Chargement des routes web
      if (file_exists($appBasePath . '/routes/web.php')) {
         require $appBasePath . '/routes/web.php';
      }

      // Chargement des routes API
      if (file_exists($appBasePath . '/routes/api.php')) {
         require $appBasePath . '/routes/api.php';
      }

      // Chargement des routes du CraftPanel
      if (Filesystem::exists($appBasePath . '/routes/craftpanel.php')) {
         require $appBasePath . '/routes/craftpanel.php';
      }

      $this->app->withRoutes([
         $appBasePath . '/routes/web.php',
         $appBasePath . '/routes/api.php',
         $appBasePath . '/routes/craftpanel.php',
      ]);
   }
}
