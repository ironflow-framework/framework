<?php

declare(strict_types=1);

namespace IronFlow\Foundation\Service;

use IronFlow\Foundation\Application;
use IronFlow\Foundation\Service\Contracts\ServiceInterface;

/**
 * Classe de base pour les services d'application
 * 
 * Cette classe fournit une implémentation de base pour les services de l'application,
 * avec des méthodes register() et boot() qui peuvent être surchargées par les classes filles.
 */
abstract class Service implements ServiceInterface
{
   /**
    * Instance de l'application
    *
    * @var Application
    */
   protected Application $app;

   /**
    * Constructeur
    *
    * @param Application $app Instance de l'application
    */
   public function __construct(Application $app)
   {
      $this->app = $app;
   }

   /**
    * Enregistre le service dans l'application
    * 
    * Cette méthode est appelée lors de l'inscription du service
    * et doit être implémentée par les classes filles.
    *
    * @return void
    */
   public function register(): void
   {
      // À implémenter dans les classes filles
   }

   /**
    * Démarre le service après son enregistrement
    * 
    * Cette méthode est appelée après l'enregistrement de tous les services
    * et peut être surchargée pour effectuer des actions d'initialisation.
    *
    * @return void
    */
   public function boot(): void
   {
      // Les services peuvent surcharger cette méthode si nécessaire
   }
}
