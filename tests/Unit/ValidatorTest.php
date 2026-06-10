<?php

declare(strict_types=1);

namespace Core\Tests\Unit;

use Core\Validation\ValidatorInstance;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private function make(array $data, array $rules): ValidatorInstance
    {
        return new ValidatorInstance($data, $rules, [], null);
    }

    public function test_passes_with_valid_data(): void
    {
        $v = $this->make(['name' => 'Alice', 'email' => 'alice@example.com'], [
            'name'  => 'required|string',
            'email' => 'required|email',
        ]);
        $this->assertTrue($v->passes());
        $this->assertFalse($v->fails());
    }

    public function test_fails_required(): void
    {
        $v = $this->make([], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function test_fails_email(): void
    {
        $v = $this->make(['email' => 'not-an-email'], ['email' => 'required|email']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('email', $v->errors());
    }

    public function test_min_rule(): void
    {
        $v = $this->make(['password' => 'ab'], ['password' => 'required|min:6']);
        $this->assertTrue($v->fails());
    }

    public function test_max_rule(): void
    {
        $v = $this->make(['name' => str_repeat('a', 300)], ['name' => 'max:255']);
        $this->assertTrue($v->fails());
    }

    public function test_in_rule(): void
    {
        $v = $this->make(['status' => 'unknown'], ['status' => 'in:draft,published,archived']);
        $this->assertTrue($v->fails());

        $v2 = $this->make(['status' => 'draft'], ['status' => 'in:draft,published,archived']);
        $this->assertTrue($v2->passes());
    }

    public function test_confirmed_rule(): void
    {
        $v = $this->make(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'required|confirmed']
        );
        $this->assertTrue($v->passes());

        $v2 = $this->make(
            ['password' => 'secret', 'password_confirmation' => 'other'],
            ['password' => 'required|confirmed']
        );
        $this->assertTrue($v2->fails());
    }

    public function test_nullable_skips_other_rules(): void
    {
        $v = $this->make(['bio' => null], ['bio' => 'nullable|string|max:500']);
        $this->assertTrue($v->passes());
    }

    public function test_validated_returns_only_defined_keys(): void
    {
        $v = $this->make(
            ['title' => 'Hello', 'extra' => 'ignored'],
            ['title' => 'required|string']
        );
        $validated = $v->validated();
        $this->assertArrayHasKey('title', $validated);
        $this->assertArrayNotHasKey('extra', $validated);
    }

    public function test_integer_rule(): void
    {
        $v = $this->make(['age' => 'not-int'], ['age' => 'integer']);
        $this->assertTrue($v->fails());

        $v2 = $this->make(['age' => 25], ['age' => 'integer']);
        $this->assertTrue($v2->passes());
    }

    public function test_url_rule(): void
    {
        $v = $this->make(['site' => 'not a url'], ['site' => 'url']);
        $this->assertTrue($v->fails());

        $v2 = $this->make(['site' => 'https://example.com'], ['site' => 'url']);
        $this->assertTrue($v2->passes());
    }

    public function test_boolean_rule(): void
    {
        foreach ([true, false, 1, 0, '1', '0'] as $val) {
            $v = $this->make(['active' => $val], ['active' => 'boolean']);
            $this->assertTrue($v->passes(), "Expected boolean to pass for: " . var_export($val, true));
        }
        $v = $this->make(['active' => 'yes'], ['active' => 'boolean']);
        $this->assertTrue($v->fails());
    }
}
