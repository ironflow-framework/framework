<?php

namespace IronFlow\Support\Facades;

class Config
{
   /**
    * Les éléments de configuration.
    *
    * @var array
    */
   protected static array $items = [];

   /**
    * Charge tous les fichiers de configuration.
    *
    * @return void
    */
   public static function load(): void
   {
      $configPath = config_path();
      $files = glob($configPath . '/*.php');

      foreach ($files as $file) {
         $key = basename($file, '.php');
         self::$items[$key] = require $file;
      }
   }

   /**
    * Obtient tous les éléments de configuration.
    *
    * @return array
    */
   public static function all(): array
   {
      return self::$items;
   }

   /**
    * Obtient une valeur de configuration.
    *
    * @param string $key
    * @param mixed $default
    * @return mixed
    */
   public static function get(string $key, mixed $default = null): mixed
   {
      $keys = explode('.', $key);
      $value = self::$items;

      foreach ($keys as $segment) {
         if (!isset($value[$segment])) {
            return $default;
         }

         $value = $value[$segment];
      }

      return $value;
   }

   /**
    * Définit une valeur de configuration.
    *
    * @param string $key
    * @param mixed $value
    * @return void
    */
   public static function set(string $key, mixed $value): void
   {
      $keys = explode('.', $key);
      $array = &self::$items;

      foreach ($keys as $segment) {
         if (!isset($array[$segment])) {
            $array[$segment] = [];
         }

         $array = &$array[$segment];
      }

      $array = $value;
   }

   /**
    * Vérifie si une clé de configuration existe
    *
    * @param string $key
    * @return bool
    */
   public static function has(string $key): bool
   {
      return isset(self::$items[$key]);
   }

   /**
    * Charge la configuration depuis un chemin spécifique
    *
    * @param string $path Le chemin du répertoire de configuration
    * @return void
    */
   public static function loadFromPath(string $path): void
   {
      if (!is_dir($path)) {
         throw new \RuntimeException("Le répertoire de configuration {$path} n'existe pas");
      }

      $files = glob($path . '/*.php');
      foreach ($files as $file) {
         $key = basename($file, '.php');
         self::$items[$key] = require $file;
      }
   }
}
