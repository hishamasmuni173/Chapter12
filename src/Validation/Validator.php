<?php
/**
 * Validator — minimal, whitelist-based input validator.
 *
 * Each rule is a closure returning bool. Field-by-field error messages
 * are aggregated and returned by validate(). Reusable across controllers.
 */

declare(strict_types=1);

namespace App\Validation;

final class Validator
{
    /** @var array<string, callable> */
    private array $rules = [];
    /** @var string[] */
    private array $required = [];

    public function field(string $name, callable $rule, string $error): self
    {
        $this->rules[$name] = ['rule' => $rule, 'error' => $error];
        return $this;
    }

    public function required(string ...$names): self
    {
        $this->required = array_merge($this->required, $names);
        return $this;
    }

    /** @return array<string, string> */
    public function validate(array $body, bool $partial = false): array
    {
        $errors = [];

        // Required fields (skip when partial = true)
        if (!$partial) {
            foreach ($this->required as $name) {
                if (!array_key_exists($name, $body)) {
                    $errors[$name] = "{$name} is required";
                }
            }
        }
        // Rules — only check fields that are PRESENT (so partial updates work)
        foreach ($this->rules as $name => $r) {
            if (!array_key_exists($name, $body)) continue;
            if (!$r['rule']($body[$name])) {
                $errors[$name] = $r['error'];
            }
        }
        return $errors;
    }

    // ---- common rule helpers ---------------------------------
    public static function nonEmptyString(int $max = 255): callable
    {
        return fn($v) => is_string($v) && trim($v) !== '' && mb_strlen($v) <= $max;
    }
    public static function intRange(int $min, int $max): callable
    {
        return fn($v) => is_numeric($v) && (int)$v >= $min && (int)$v <= $max;
    }
    public static function email(): callable
    {
        return fn($v) => is_string($v) && filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
    }
    public static function inSet(array $allowed): callable
    {
        return fn($v) => in_array($v, $allowed, true);
    }
}
