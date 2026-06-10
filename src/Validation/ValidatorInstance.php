<?php

declare(strict_types=1);

namespace Ironflow\Validation;

use Ironflow\Database\Connection;

/**
 * Validates an array of data against a set of rules.
 * Rules: required, email, string, int, numeric, min, max, in, confirmed,
 *        unique:table,column, regex, url, date, boolean, nullable.
 */
class ValidatorInstance
{
    private array $errors = [];
    private array $validated = [];
    private bool $validated_flag = false;

    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
        private readonly ?Connection $db = null
    ) {
    }

    public function fails(): bool
    {
        if (!$this->validated_flag) {
            $this->validate();
        }
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): array
    {
        if (!$this->validated_flag) {
            $this->validate();
        }
        return $this->errors;
    }

    public function validated(): array
    {
        if (!$this->validated_flag) {
            $this->validate();
        }
        return $this->validated;
    }

    private function validate(): void
    {
        $this->validated_flag = true;

        foreach ($this->rules as $field => $ruleString) {
            $rules = is_string($ruleString) ? explode('|', $ruleString) : $ruleString;
            $value = $this->getValue($field);
            $nullable = in_array('nullable', $rules, true);

            $this->validated[$field] = $value;

            foreach ($rules as $rule) {
                if ($rule === 'nullable')
                    continue;

                // Skip remaining rules if value is null and nullable
                if ($nullable && ($value === null || $value === '')) {
                    break;
                }

                $this->applyRule($field, $value, $rule);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);

        $passed = match ($ruleName) {
            'required' => $value !== null && $value !== '' && $value !== [],
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'string' => is_string($value),
            'int', 'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric' => is_numeric($value),
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1'], true),
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'date' => strtotime((string) $value) !== false,
            'min' => is_string($value) ? mb_strlen($value) >= (int) $param : (float) $value >= (float) $param,
            'max' => is_string($value) ? mb_strlen($value) <= (int) $param : (float) $value <= (float) $param,
            'in' => in_array($value, explode(',', (string) $param), true),
            'regex' => (bool) preg_match($param, (string) $value),
            'confirmed' => ($this->getValue($field . '_confirmation') === $value),
            'unique' => $this->validateUnique($value, (string) $param),
            'nullable' => true,
            default => true,
        };

        if (!$passed) {
            $this->addError($field, $ruleName, $param);
        }
    }

    private function validateUnique(mixed $value, string $param): bool
    {
        if ($this->db === null || $value === null || $value === '') {
            return true;
        }

        [$table, $column] = array_pad(explode(',', $param, 2), 2, 'id');
        $count = $this->db->selectOne("SELECT COUNT(*) as cnt FROM {$table} WHERE {$column} = ?", [$value]);
        return ((int) ($count['cnt'] ?? 0)) === 0;
    }

    private function addError(string $field, string $rule, ?string $param): void
    {
        $key = "{$field}.{$rule}";
        $message = $this->messages[$key] ?? $this->messages[$field] ?? $this->defaultMessage($field, $rule, $param);
        $this->errors[$field][] = $message;
    }

    private function defaultMessage(string $field, string $rule, ?string $param): string
    {
        $label = str_replace('_', ' ', $field);

        return match ($rule) {
            'required' => "Le champ {$label} est obligatoire.",
            'email' => "Le champ {$label} doit être une adresse email valide.",
            'string' => "Le champ {$label} doit être une chaîne de caractères.",
            'int', 'integer' => "Le champ {$label} doit être un entier.",
            'numeric' => "Le champ {$label} doit être numérique.",
            'boolean' => "Le champ {$label} doit être vrai ou faux.",
            'url' => "Le champ {$label} doit être une URL valide.",
            'date' => "Le champ {$label} doit être une date valide.",
            'min' => "Le champ {$label} doit avoir au minimum {$param}.",
            'max' => "Le champ {$label} ne doit pas dépasser {$param}.",
            'in' => "La valeur de {$label} n'est pas valide.",
            'regex' => "Le format de {$label} est invalide.",
            'confirmed' => "La confirmation de {$label} ne correspond pas.",
            'unique' => "Cette valeur est déjà prise pour {$label}.",
            default => "Le champ {$label} est invalide.",
        };
    }

    private function getValue(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }
}
