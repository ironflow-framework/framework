<?php

declare(strict_types=1);

namespace Core\Http;

use Core\Exceptions\HttpException;
use Core\Validation\ValidationException;
use Core\Validation\ValidatorFactory;

/**
 * Base class for typed, auto-validated form/API requests.
 *
 * Usage:
 *   class StorePostRequest extends FormRequest {
 *       public function rules(): array { return ['title' => 'required|string']; }
 *   }
 *
 * When injected into a controller method the Router calls validateResolved()
 * automatically before the action runs.
 */
abstract class FormRequest extends Request
{
    private bool  $isValidated   = false;
    private array $validatedData = [];

    // ── Contract ──────────────────────────────────────────────────────

    abstract public function rules(): array;

    public function messages(): array
    {
        return [];
    }

    /** Override to add authorization logic. Return false → 403. */
    public function authorize(): bool
    {
        return true;
    }

    /** Hook called before validation — mutate $this->merge() or skip fields. */
    public function prepareForValidation(): void {}

    // ── Auto-validation ───────────────────────────────────────────────

    /**
     * Called by the Router right after the FormRequest is injected.
     * Runs authorization then validation; throws on failure.
     */
    public function validateResolved(): void
    {
        if ($this->isValidated) {
            return;
        }

        if (!$this->authorize()) {
            throw new HttpException(403, 'Cette action n\'est pas autorisée.');
        }

        $this->prepareForValidation();

        $data      = $this->isJson() ? array_merge($this->all(), (array) $this->json()) : $this->all();
        $factory   = new ValidatorFactory();
        $validator = $factory->make($data, $this->rules(), $this->messages());

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $this->isValidated   = true;
        $this->validatedData = $validator->validated();
    }

    /** Returns only the data that passed validation (throws if not yet validated). */
    public function validated(?string $key = null, mixed $default = null): mixed
    {
        if (!$this->isValidated) {
            $this->validateResolved();
        }

        if ($key !== null) {
            return $this->validatedData[$key] ?? $default;
        }

        return $this->validatedData;
    }

    // ── Factory ───────────────────────────────────────────────────────

    /**
     * Build a FormRequest subclass from an existing Request (used by the Router).
     * Copies all parameter bags so route params, headers and body are preserved.
     */
    public static function createFrom(Request $request): static
    {
        /** @var static $instance */
        $instance = new static(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            [],                          // files — not needed for most validation
            $request->server->all(),
            $request->getContent()
        );
        $instance->setRouteParams($request->allRouteParams());
        return $instance;
    }
}
