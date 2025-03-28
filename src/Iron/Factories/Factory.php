<?php

namespace IronFlow\Database\Factories;

use Faker\Generator as FakerGenerator;
use Faker\Factory as FakerFactory;
use IronFlow\Database\Contracts\FactoryInterface;

abstract class Factory implements FactoryInterface
{

   /**
    * Instance du générateur Faker.
    *
    * @var FakerGenerator
    */
   protected FakerGenerator $faker;
   protected string $model;
   protected int $count = 1;

   public function __construct(string $model)
   {
      $this->faker = FakerFactory::create(config('APP_LANG', 'en_US'));
      $this->model = $model;
   }

   abstract public function definition(FakerGenerator $fake): array;
  

   public function count(int $count): self
   {
      $this->count = $count;
      return $this;
   }

   public function create(array $override = []): array
   {
      $createdModels = [];

      for ($i = 0; $i < $this->count; $i++) {
         $attributes = array_merge($this->definition($this->faker), $override);
         $createdModels[] = ($this->model)::create($attributes);
      }

      return $this->count === 1 ? $createdModels[0] : $createdModels;
   }

   public function make(array $override = []): array
   {
      $models = [];

      for ($i = 0; $i < $this->count; $i++) {
         $attributes = array_merge($this->definition($this->faker), $override);
         $models[] = new $this->model($attributes);
      }

      return $this->count === 1 ? $models[0] : $models;
   }

   /**
    * Retourne la classe associée au modèle.
    *
    * @return string
    */
   protected function getModelClass(): string
   {
      return $this->model;
   }
}
