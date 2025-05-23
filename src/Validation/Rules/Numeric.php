<?php

declare(strict_types=1);

namespace IronFlow\Validation\Rules;

use IronFlow\Validation\AbstractRule;

/**
 * Règle de validation pour les valeurs numériques
 */
class Numeric extends AbstractRule
{
   /**
    * Message d'erreur par défaut
    */
   protected string $defaultMessage = 'Le champ :field doit être une valeur numérique';

   /**
    * Valide une valeur numérique
    *
    * @param string $field
    * @param mixed $value
    * @param array $parameters
    * @param array $data
    * @return bool
    */
   public function validate(string $field, $value, array $parameters = [], array $data = []): bool
   {
      if (empty($value) && $value !== '0' && $value !== 0) {
         return true; // Pas d'erreur si vide (utiliser Required pour vérifier la présence)
      }

      return is_numeric($value);
   }
}
