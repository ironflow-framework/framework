<?php

declare(strict_types=1);

namespace IronFlow\Http;

use IronFlow\Validation\Validator;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use IronFlow\Support\Facades\Session;
use IronFlow\Support\Collection;

class Request extends SymfonyRequest
{
   protected array $routeParameters = [];

   /**
    * Capture la requête actuelle depuis les variables globales
    *
    * @return static
    */
   public static function capture(): static
   {
      return static::createFromGlobals();
   }

   /**
    * Définit les paramètres de route
    *
    * @param array $parameters
    * @return void
    */
   public function setRouteParameters(array $parameters): void
   {
      $this->routeParameters = $parameters;
   }

   /**
    * Récupère tous les paramètres de route
    *
    * @return array
    */
   public function getRouteParameters(): array
   {
      return $this->routeParameters;
   }

   /**
    * Récupère un paramètre de route spécifique
    *
    * @param string $key
    * @param mixed $default
    * @return mixed
    */
   public function getRouteParameter(string $key, mixed $default = null): mixed
   {
      return $this->routeParameters[$key] ?? $default;
   }

   /**
    * Récupère tous les paramètres de la requête (POST et GET)
    *
    * @return array
    */
   public function all(): array
   {
      return array_merge($this->request->all(), $this->query->all(), $this->files->all());
   }

   /**
    * Vérifie si un paramètre existe dans la requête
    *
    * @param string $key
    * @return bool
    */
   public function has(string $key): bool
   {
      return $this->request->has($key) || $this->query->has($key);
   }

   /**
    * Vérifie si tous les paramètres existent dans la requête
    *
    * @param array $keys
    * @return bool
    */
   public function hasAll(array $keys): bool
   {
      foreach ($keys as $key) {
         if (!$this->has($key)) {
            return false;
         }
      }
      return true;
   }

   /**
    * Récupère un paramètre de la requête
    *
    * @param string $key
    * @param mixed $default
    * @return mixed
    */
   public function input(string $key, mixed $default = null): mixed
   {
      return $this->request->get($key, $this->query->get($key, $default));
   }

   /**
    * Récupère un paramètre de la requête GET
    *
    * @param string $key
    * @param mixed $default
    * @return mixed
    */
   public function query(string $key, mixed $default = null): mixed
   {
      return $this->query->get($key, $default);
   }

   /**
    * Récupère un paramètre de la requête POST
    *
    * @param string $key
    * @param mixed $default
    * @return mixed
    */
   public function post(string $key, mixed $default = null): mixed
   {
      return $this->request->get($key, $default);
   }

   /**
    * Récupère un ou plusieurs en-têtes HTTP
    *
    * @param string|array $key
    * @return string|null
    */
   public function header(string|array $key): string|null
   {
      return $this->headers->get($key);
   }

   /**
    * Récupère uniquement les paramètres spécifiés
    *
    * @param array $keys
    * @return array
    */
   public function only(array $keys): array
   {
      $all = array_merge($this->request->all(), $this->query->all());
      return array_intersect_key($all, array_flip($keys));
   }

   /**
    * Récupère tous les paramètres sauf ceux spécifiés
    *
    * @param array $keys
    * @return array
    */
   public function except(array $keys): array
   {
      $all = array_merge($this->request->all(), $this->query->all());
      return array_diff_key($all, array_flip($keys));
   }

   /**
    * Vérifie si la requête est une requête AJAX
    *
    * @return bool
    */
   public function isAjax(): bool
   {
      return $this->isXmlHttpRequest();
   }

   /**
    * Vérifie si la requête attend une réponse JSON
    *
    * @return bool
    */
   public function expectsJson(): bool
   {
      return $this->isAjax() || $this->wantsJson();
   }

   /**
    * Vérifie si la requête souhaite du JSON
    *
    * @return bool
    */
   public function wantsJson(): bool
   {
      $acceptable = $this->getAcceptableContentTypes();
      return isset($acceptable[0]) && (
         $acceptable[0] === 'application/json' ||
         $acceptable[0] === '*/*'
      );
   }

   /**
    * Récupère les données JSON de la requête
    *
    * @param string|null $key
    * @param mixed $default
    * @return mixed
    */
   public function json(?string $key = null, $default = null): mixed
   {
      $data = json_decode($this->getContent(), true);

      if ($key === null) {
         return $data ?? $default;
      }

      return $data[$key] ?? $default;
   }

   /**
    * Récupère un fichier uploadé
    *
    * @param string $key
    * @return mixed
    */
   public function file(string $key)
   {
      return $this->files->get($key);
   }

   /**
    * Vérifie si un fichier a été uploadé
    *
    * @param string $key
    * @return bool
    */
   public function hasFile(string $key): bool
   {
      return $this->files->has($key);
   }

   /**
    * Valide les données de la requête
    *
    * @param array $rules
    * @return bool
    */
   public function validate(array $rules): bool
   {
      $validator = new Validator();
      return $validator->validate($this->all(), $rules);
   }

   /**
    * Convertit les données de la requête en collection
    *
    * @return Collection
    */
   public function collect(): Collection
   {
      return new Collection($this->all());
   }

   /**
    * Récupère l'URL de la requête
    *
    * @return string
    */
   public function url(): string
   {
      return $this->getUri();
   }

   /**
    * Récupère l'URL complète incluant les paramètres de requête
    *
    * @return string
    */
   public function fullUrl(): string
   {
      $query = $this->getQueryString();
      $url = $this->getUri();

      if ($query) {
         $url .= '?' . $query;
      }

      return $url;
   }

   /**
    * Vérifie si la méthode de la requête correspond
    *
    * @param string $method
    * @return bool
    */
   public function isMethod(string $method): bool
   {
      return strtoupper($method) === $this->getMethod();
   }

   /**
    * Récupère l'adresse IP du client
    *
    * @return string
    */
   public function ip(): string
   {
      return $this->getClientIp();
   }

   /**
    * Récupère le user agent du client
    *
    * @return string|null
    */
   public function userAgent(): ?string
   {
      return $this->headers->get('User-Agent');
   }

   /**
    * Détermine si la requête est sécurisée (HTTPS)
    *
    * @return bool
    */
   public function isSecure(): bool
   {
      return parent::isSecure();
   }

   /**
    * Récupère la requête Symfony sous-jacente
    *
    * @return SymfonyRequest
    */
   public function getBaseRequest(): SymfonyRequest
   {
      return $this;
   }
}
