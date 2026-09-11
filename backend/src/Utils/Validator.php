<?php

declare(strict_types=1);

namespace App\Utils;

use App\Exceptions\ValidationException;

final class Validator
{
    public function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            foreach ($fieldRules as $rule) {
                $error = $this->check($field, $data[$field] ?? null, $rule);
                if ($error !== null) {
                    $errors[$field][] = $error;
                }
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return $data;
    }

    private function check(string $field, mixed $value, string|array $rule): ?string
    {
        if ($rule === 'required' && ($value === null || $value === '')) {
            return sprintf('%s is required.', $field);
        }
        if ($value === null || $value === '') {
            return null;
        }
        if ($rule === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return sprintf('%s must be a valid email address.', $field);
        }
        if ($rule === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            return sprintf('%s must be an integer.', $field);
        }
        if (is_array($rule) && isset($rule['in']) && !in_array($value, $rule['in'], true)) {
            return sprintf('%s contains an unsupported value.', $field);
        }
        if (is_array($rule) && isset($rule['max']) && mb_strlen((string) $value) > $rule['max']) {
            return sprintf('%s may not exceed %d characters.', $field, $rule['max']);
        }
        if (is_array($rule) && isset($rule['min']) && mb_strlen((string) $value) < $rule['min']) {
            return sprintf('%s must contain at least %d characters.', $field, $rule['min']);
        }
        return null;
    }
}
