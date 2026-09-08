<?php

declare(strict_types=1);

namespace MediTrack\Validation;

/**
 * Small rule-based validator. Rules are pipe-separated strings, e.g.
 * "required|max:120" or "in:hypertension,diabetes,both".
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules field => "rule1|rule2:arg"
     */
    public function __construct(private readonly array $data, private readonly array $rules)
    {
    }

    /** @return array<string,string> field => message, empty when valid */
    public function validate(): array
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            foreach (explode('|', $ruleString) as $rule) {
                if (isset($this->errors[$field])) {
                    break;
                }
                $this->applyRule($field, $value, $rule);
            }
        }
        return $this->errors;
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);

        $isEmpty = $value === null || $value === '';

        switch ($name) {
            case 'required':
                if ($isEmpty) {
                    $this->errors[$field] = 'This field is required.';
                }
                break;
            case 'string':
                if (!$isEmpty && !is_string($value)) {
                    $this->errors[$field] = 'This field must be text.';
                }
                break;
            case 'integer':
                if (!$isEmpty && !is_numeric($value)) {
                    $this->errors[$field] = 'This field must be a whole number.';
                } elseif (!$isEmpty && (float) $value != (int) $value) {
                    $this->errors[$field] = 'This field must be a whole number.';
                }
                break;
            case 'numeric':
                if (!$isEmpty && !is_numeric($value)) {
                    $this->errors[$field] = 'This field must be a number.';
                }
                break;
            case 'min':
                if (!$isEmpty && is_string($value) && mb_strlen($value) < (int) $arg) {
                    $this->errors[$field] = "This field must be at least {$arg} characters.";
                } elseif (!$isEmpty && is_numeric($value) && (float) $value < (float) $arg) {
                    $this->errors[$field] = "This field must be at least {$arg}.";
                }
                break;
            case 'max':
                if (!$isEmpty && is_string($value) && mb_strlen($value) > (int) $arg) {
                    $this->errors[$field] = "This field must be at most {$arg} characters.";
                } elseif (!$isEmpty && is_numeric($value) && (float) $value > (float) $arg) {
                    $this->errors[$field] = "This field must be at most {$arg}.";
                }
                break;
            case 'in':
                $allowed = explode(',', (string) $arg);
                if (!$isEmpty && !in_array((string) $value, $allowed, true)) {
                    $this->errors[$field] = 'This field contains an invalid value.';
                }
                break;
            case 'date':
                if (!$isEmpty && !self::isValidDate((string) $value)) {
                    $this->errors[$field] = 'This field must be a valid date (YYYY-MM-DD).';
                }
                break;
            case 'date_not_future':
                if (!$isEmpty && self::isValidDate((string) $value) && $value > date('Y-m-d')) {
                    $this->errors[$field] = 'This date cannot be in the future.';
                }
                break;
            case 'ph_mobile':
                if (!$isEmpty && !preg_match('/^(09\d{9}|\+639\d{9})$/', (string) $value)) {
                    $this->errors[$field] = 'Enter a valid Philippine mobile number (e.g. 09171234567).';
                }
                break;
            case 'email':
                if (!$isEmpty && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = 'Enter a valid email address.';
                }
                break;
        }
    }

    private static function isValidDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
