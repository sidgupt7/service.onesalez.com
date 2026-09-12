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
        if (str_contains($field, 'password') && (!is_string($value) || strlen($value) > 72 || str_contains($value, "\0"))) {
            return sprintf('%s must be a string of at most 72 bytes without null characters.', $field);
        }
        if ($rule === 'required') {
            if ((is_array($value) || is_object($value)) && !in_array($field, ['roles', 'ids', 'location_ids'], true)) {
                return sprintf('%s must be a scalar value.', $field);
            }
            return $value === [] ? sprintf('%s is required.', $field) : null;
        }
        if (is_array($value) || is_object($value)) {
            return sprintf('%s must be a scalar value.', $field);
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
