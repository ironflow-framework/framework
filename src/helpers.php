<?php

if (!function_exists("dump")) {
   function dump($data)
   {
      return Symfony\Component\VarDumper\VarDumper::dump($data);
   }
}


if (!function_exists('base_path')) {
   /**
    * Obtient le chemin vers le dossier racine du projet.
    *
    * @param string $path
    * @return string
    */
   function base_path(string $path = ''): string
   {
      return BASE_PATH . ($path ? DIRECTORY_SEPARATOR . $path : $path);
   }
}

if (!function_exists('app')) {
   /**
    * Récupère une instance du conteneur ou résout une dépendance
    *
    * @param string|null $abstract
    * @param array $parameters
    * @return mixed
    */
   function app($abstract = null, array $parameters = []): mixed
   {
      if (is_null($abstract)) {
         return \IronFlow\Core\Application\Application::getInstance();
      }

      return \IronFlow\Core\Application\Application::getInstance()->getContainer()->make($abstract, $parameters);
   }
}

if (!function_exists('bcrypt')) {
   /**
    * Hash un mot de passe avec Bcrypt
    */
   function bcrypt(string $value): string
   {
      return password_hash($value, PASSWORD_BCRYPT);
   }
}

if (!function_exists('str_random')) {
   /**
    * Génère une chaîne aléatoire
    */
   function str_random(int $length = 16): string
   {
      $string = '';
      while (($len = strlen($string)) < $length) {
         $size = $length - $len;
         $bytes = random_bytes($size);
         $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
      }
      return $string;
   }
}

if (!function_exists('view_path')) {
   /**
    * Obtient le chemin vers le dossier des vues.
    *
    * @param string $path
    * @return string
    */
   function view_path(string $path = ''): string
   {
      return resource_path('views' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
   }
}

if (!function_exists('config_path')) {
   /**
    * Obtient le chemin vers le dossier de configuration.
    *
    * @param string $path
    * @return string
    */
   function config_path(string $path = ''): string
   {
      return base_path('config' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
   }
}

if (!function_exists('lang_path')) {
   /**
    * Obtient le chemin vers le dossier des traductions.
    *
    * @param string $path
    * @return string
    */
   function lang_path(string $path = ''): string
   {
      return resource_path('lang' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
   }
}

if (!function_exists('app_path')) {
   /**
    * Obtient le chemin vers le dossier de l'application.
    *
    * @param string $path
    * @return string
    */
   function app_path(string $path = ''): string
   {
      return base_path('app') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
   }
}

if (!function_exists('csrf_token')) {
   function csrf_token(): string
   {
      return $_SESSION['_token'] ?? bin2hex(random_bytes(32));
   }
}


if (!function_exists('public_path')) {
   /**
    * Obtient le chemin vers le dossier public.
    *
    * @param string $path
    * @return string
    */
   function public_path(string $path = ''): string
   {
      return base_path('public' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
   }
}

if (!function_exists('storage_path')) {
   /**
    * Obtient le chemin vers le dossier storage.
    *
    * @param string $path
    * @return string
    */
   function storage_path(string $path = ''): string
   {
      return base_path('storage' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
   }
}

if (!function_exists('resource_path')) {
   /**
    * Obtient le chemin vers le dossier resources.
    *
    * @param string $path
    * @return string
    */
   function resource_path(string $path = ''): string
   {
      return base_path('resources' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
   }
}

if (!function_exists('database_path')) {
   /**
    * Obtient le chemin vers le dossier database.
    *
    * @param string $path
    * @return string
    */
   function database_path(string $path = ''): string
   {
      return base_path('database' . ($path ? DIRECTORY_SEPARATOR . $path : $path));
   }
}

if (!function_exists('config')) {
   /**
    * Obtient une valeur de configuration.
    *
    * @param string|null $key
    * @param mixed $default
    * @return mixed
    */
   function config(?string $key = null, mixed $default = null): mixed
   {
      if (is_null($key)) {
         return IronFlow\Support\Facades\Config::all();
      }

      return IronFlow\Support\Facades\Config::get($key, $default);
   }
}

if (!function_exists('env')) {
   /**
    * Obtient une variable d'environnement.
    *
    * @param string $key
    * @param mixed $default
    * @return mixed
    */
   function env(string $key, mixed $default = null): mixed
   {

      $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
      $dotenv->safeLoad();

      $value = $_ENV[$key] ?? getenv($key) ?? null;

      if ($value === null) {
         return $default;
      }

      switch (strtolower($value)) {
         case 'true':
         case '(true)':
            return true;
         case 'false':
         case '(false)':
            return false;
         case 'null':
         case '(null)':
            return null;
         case 'empty':
         case '(empty)':
            return '';
      }

      return $value;
   }
}

if (!function_exists('collect')) {
   /**
    * Crée une nouvelle instance de collection à partir des éléments donnés
    * 
    * @param mixed $items
    * @return IronFlow\Database\Collection
    */
   function collect($items = []): IronFlow\Database\Collection
   {
      return new IronFlow\Database\Collection($items);
   }
}

if (! function_exists('class_basename')) {
   /**
    * Get the class "basename" of the given object / class.
    *
    * @param  string|object  $class
    * @return string
    */
   function class_basename($class)
   {
      $class = is_object($class) ? get_class($class) : $class;

      return basename(str_replace('\\', '/', $class));
   }
}

if (!function_exists('class_uses_recursive')) {
   /**
    * Get all traits used by a class.
    *
    * @param  string|object  $class
    * @return array
    */
   function class_uses_recursive($class)
   {
      return IronFlow\Support\Helpers::classUsesRecursive($class);
   }
}

if (!function_exists('trait_uses_recursive')) {
   /**
    * Get all traits used by a trait.
    *
    * @param  string|object  $trait
    * @return array
    */
   function trait_uses_recursive($trait)
   {
      return IronFlow\Support\Helpers::traitUsesRecursive($trait);
   }
}

if (!function_exists('route')) {
   /**
    * Retourner la route sous la forme d'une URL.
    *
    * @param string $name
    * @param array $parameters
    * @return string
    */
   function route(string $name, array $parameters = []): string
   {
      $app = IronFlow\Core\Application\Application::getInstance();
      $router = $app->getContainer()->get(IronFlow\Routing\RouterInterface::class);
      return $router->generateUrl($name, $parameters);
   }
}

if (!function_exists('flash')) {
   /**
    * Ajoute un message flash à la session.
    *
    * @param string $key
    * @param mixed $value
    */
   function flash(string $key, mixed $value): void
   {
      session()->set($key, $value);
   }
}

if (!function_exists('session')) {
   /**
    * Retourne l'instance de session.
    *
    * @return IronFlow\Session\Session
    */
   function session(): IronFlow\Session\Session
   {
      return IronFlow\Support\Facades\Session::getInstance();
   }
}

if (!function_exists('validator')) {
   /**
    * Retourne l'instance de validator.
    *
    * @return IronFlow\Validation\Validator
    */
   function validator(array $data, array $rules): IronFlow\Validation\Validator
   {
      return new IronFlow\Validation\Validator($data, $rules);
   }
}

if (!function_exists('auth')) {
   /**
    * Retourne l'instance de l'authentification.
    *
    * @return IronFlow\Auth\AuthManager
    */
   function auth(): IronFlow\Auth\AuthManager
   {
      return new IronFlow\Auth\AuthManager()->getInstance(config('auth'));
   }
}

if (!function_exists('logger')) {
   /**
    * Retourne l'instance de Log.
    *
    * @return IronFlow\Core\Logger\LogManager
    */
   function logger(): IronFlow\Core\Logger\LogManager
   {
      return IronFlow\Support\Facades\Log::getInstance();
   }
}

if (!function_exists('trans')) {
   /**
    * Traduit un message.
    *
    * @param string $key
    * @param array $parameters
    * @param string|null $domain
    * @param string|null $locale
    * @return string
    */
   function trans(string $key, array $parameters = [], ?string $domain = null, ?string $locale = null): string
   {
      return IronFlow\Support\Facades\Trans::trans($key, $parameters, $domain, $locale);
   }
}

if (!function_exists('excel')) {
   /**
    * Retourne l'instance de Excel.
    *
    * @return IronFlow\Support\Facades\Excel
    */
   function excel(): IronFlow\Support\Facades\Excel
   {
      return new IronFlow\Support\Facades\Excel();
   }
}

if (!function_exists('url')) {
   /**
    * Génère une URL vers le chemin donné.
    *
    * @param string $path
    * @return string
    */
   function url(string $path = ''): string
   {
      $base = config('app.url', '');
      return rtrim($base, '/') . '/' . ltrim($path, '/');
   }
}

if (!function_exists('now')) {
   /**
    * Retourne la date et l'heure actuelles.
    *
    * @return Carbon\Carbon
    */
   function now(): Carbon\Carbon
   {
      return Carbon\Carbon::now();
   }
}

if (!function_exists('Carbon')) {
   /**
    * Retourne l'instance de Carbon.
    *
    * @return Carbon\Carbon
    */
   function Carbon(): Carbon\Carbon
   {
      return new Carbon\Carbon();
   }
}

if (!function_exists('request')) {
   /**
    * Retourne l'instance de la requête.
    *
    * @return IronFlow\Http\Request
    */
   function request(): IronFlow\Http\Request
   {
      return new IronFlow\Http\Request();
   }
}

/**
 * Log un message d'urgence
 *
 * @param string $message
 * @param array $context
 * @return void
 */
function log_emergency(string $message, array $context = []): void
{
   app('log')->emergency($message, $context);
}

/**
 * Log un message d'alerte
 *
 * @param string $message
 * @param array $context
 * @return void
 */
function log_alert(string $message, array $context = []): void
{
   app('log')->alert($message, $context);
}

/**
 * Log un message critique
 *
 * @param string $message
 * @param array $context
 * @return void
 */
function log_critical(string $message, array $context = []): void
{
   app('log')->critical($message, $context);
}

/**
 * Log un message d'erreur
 *
 * @param string $message
 * @param array $context
 * @return void
 */
function log_error(string $message, array $context = []): void
{
   app('log')->error($message, $context);
}

/**
 * Log un message d'avertissement
 *
 * @param string $message
 * @param array $context
 * @return void
 */
function log_warning(string $message, array $context = []): void
{
   app('log')->warning($message, $context);
}

/**
 * Log un message de notice
 *
 * @param string $message
 * @param array $context
 * @return void
 */
function log_notice(string $message, array $context = []): void
{
   app('log')->notice($message, $context);
}

/**
 * Log un message d'information
 *
 * @param string $message
 * @param array $context
 * @return void
 */
function log_info(string $message, array $context = []): void
{
   app('log')->info($message, $context);
}

/**
 * Log un message de debug
 *
 * @param string $message
 * @param array $context
 * @return void
 */
function log_debug(string $message, array $context = []): void
{
   app('log')->debug($message, $context);
}

// Initialisation des classes statiques
IronFlow\Support\Facades\Storage::initialize();
