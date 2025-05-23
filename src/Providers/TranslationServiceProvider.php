<?php

declare(strict_types=1);

namespace IronFlow\Providers;

use IronFlow\Core\Service\ServiceProvider;
use IronFlow\Support\Facades\Filesystem;
use IronFlow\Support\Facades\Trans;

/**
 * Service Provider pour l'internationalisation
 * 
 * Ce service provider initialise et configure le système de traduction
 * pour permettre l'internationalisation de l'application.
 */
class TranslationServiceProvider extends ServiceProvider
{
   /**
    * Enregistre les services liés à l'internationalisation.
    *
    * @return void
    */
   public function register(): void
   {
      $this->container->singleton('translator', function () {
         Trans::initialize();
         return Trans::class;
      });
   }

   /**
    * Démarre les services liés à l'internationalisation.
    */
   public function boot(): void
   {
      // Création du répertoire de base pour les traductions s'il n'existe pas
      $langPath = lang_path();
      if (!Filesystem::isDirectory($langPath)) {
         Filesystem::makeDirectory($langPath, 0755, true);
      }

      // Création des répertoires pour les langues par défaut
      $locales = ['fr', 'en'];
      foreach ($locales as $locale) {
         $localePath = $langPath . '/' . $locale;
         if (!Filesystem::isDirectory($localePath)) {
            Filesystem::makeDirectory($localePath, 0755, true);
         }
      }

      // Création d'un fichier de traduction d'exemple si nécessaire
      $this->createExampleTranslations();
   }

   /**
    * Crée des fichiers de traduction d'exemple
    */
   protected function createExampleTranslations(): void
   {
      // Fichier pour le français
      $frMessagesFile = lang_path('fr/messages.php');
      if (!file_exists($frMessagesFile)) {
         $frContent = <<<'EOT'
<?php

return [
    'welcome' => 'Bienvenue sur IronFlow',
    'login' => 'Connexion',
    'register' => 'Inscription',
    'logout' => 'Déconnexion',
    'email' => 'Adresse e-mail',
    'password' => 'Mot de passe',
    'confirm_password' => 'Confirmez le mot de passe',
    'remember_me' => 'Se souvenir de moi',
    'forgot_password' => 'Mot de passe oublié ?',
    'reset_password' => 'Réinitialiser le mot de passe',
    'submit' => 'Envoyer',
    'cancel' => 'Annuler',
    'save' => 'Enregistrer',
    'delete' => 'Supprimer',
    'edit' => 'Modifier',
    'create' => 'Créer',
    'back' => 'Retour',
    'actions' => 'Actions',
    'success' => 'Succès',
    'error' => 'Erreur',
    'warning' => 'Avertissement',
    'info' => 'Information',
];
EOT;
         file_put_contents($frMessagesFile, $frContent);
      }

      // Fichier pour l'anglais
      $enMessagesFile = lang_path('en/messages.php');
      if (!file_exists($enMessagesFile)) {
         $enContent = <<<'EOT'
<?php

return [
    'welcome' => 'Welcome to IronFlow',
    'login' => 'Login',
    'register' => 'Register',
    'logout' => 'Logout',
    'email' => 'Email address',
    'password' => 'Password',
    'confirm_password' => 'Confirm password',
    'remember_me' => 'Remember me',
    'forgot_password' => 'Forgot your password?',
    'reset_password' => 'Reset password',
    'submit' => 'Submit',
    'cancel' => 'Cancel',
    'save' => 'Save',
    'delete' => 'Delete',
    'edit' => 'Edit',
    'create' => 'Create',
    'back' => 'Back',
    'actions' => 'Actions',
    'success' => 'Success',
    'error' => 'Error',
    'warning' => 'Warning',
    'info' => 'Information',
];
EOT;
         file_put_contents($enMessagesFile, $enContent);
      }
   }
}
