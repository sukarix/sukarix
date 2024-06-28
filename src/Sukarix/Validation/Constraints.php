<?php

namespace Sukarix\Validation;

use Sukarix\Behaviours\HasF3;
use Sukarix\Core\Tailored;

class Constraints extends Tailored
{
    use HasF3;

    protected $errors = [];

    public function __construct()
    {
        // Load the configuration only once when the class is instantiated
        \Base::instance()->config('config/validation.ini');
    }

    // Verify the input against the named ruleset(s)
    public function verify(array $input, $rulesetNames, bool $throwOnFail = false): bool
    {
        $errors = [];

        // Normalize rulesetNames to an array
        if (\is_string($rulesetNames)) {
            $rulesetNames = preg_split('/\s*\|\s*/', $rulesetNames);
        }

        // Iterate through the ruleset names
        foreach ($rulesetNames as $rulesetName) {
            $ruleset = $this->getRulesFromRuleset($rulesetName);

            foreach ($ruleset as $fields => $rules) {
                foreach (preg_split('/\s*,\s*/', $fields) as $field) {
                    $this->applyRules($input[$field] ?? null, $rules, $errors, $field);
                }
            }
        }

        $this->errors = $errors;

        if (!empty($errors) && $throwOnFail) {
            throw new \RuntimeException('Validation failed: ' . implode(', ', array_map('implode', $errors)));
        }

        return empty($errors);
    }

    public function check($value, $rules, bool $throwOnFail = false, string $fieldName = 'value'): bool
    {
        $this->applyRules($value, $rules, $errors, $fieldName);

        if (!empty($errors) && $throwOnFail) {
            throw new \RuntimeException('Validation failed: ' . implode(', ', array_map('implode', $errors)));
        }

        return empty($errors);
    }

    // Validate a single field with a rule
    public function validateField($value, $rule, $params = [])
    {
        // Check for custom validator class
        $customValidatorClass = $this->f3->exists('VALIDATOR') ? $this->f3->get('VALIDATOR') : null;

        if ($customValidatorClass && class_exists($customValidatorClass) && method_exists($customValidatorClass, $rule)) {
            return \call_user_func([$customValidatorClass, $rule], $value, ...$params);
        }

        // Check for method in the Constraints class
        if (method_exists(__CLASS__, $rule)) {
            return \call_user_func([__CLASS__, $rule], $value, ...$params);
        }

        // Check for method in the Audit class
        if (class_exists('\Audit') && method_exists('\Audit', 'instance') && method_exists(\Audit::instance(), $rule)) {
            return \Audit::instance()->{$rule}($value, ...$params);
        }

        throw new \InvalidArgumentException("Validation rule '{$rule}' not found.");
    }

    // Get errors
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Check if there is no errors.
     *
     * @param mixed $popErrors
     * @param mixed $errorsHiveKey
     *
     * @return bool
     */
    public function allValid($popErrors = true, $errorsHiveKey = 'api_errors')
    {
        if (!empty($this->errors) && $popErrors) {
            $this->f3->set($errorsHiveKey, $this->errors);
        }

        return empty($this->errors);
    }

    // Validation functions

    public function required($value)
    {
        return null !== $value && '' !== $value;
    }

    public function integer($value)
    {
        return false !== filter_var($value, FILTER_VALIDATE_INT);
    }

    public function boolean($value)
    {
        return \is_bool($value) || \in_array(mb_strtolower($value), ['true', 'false', '1', '0', 1, 0], true);
    }

    public function string($value)
    {
        return \is_string($value);
    }

    public function min($value, $min)
    {
        return mb_strlen($value) >= (int) $min;
    }

    public function notEmpty(mixed $val): bool
    {
        if (\is_string($val)) {
            return '' !== trim($val);
        }
        if (\is_array($val)) {
            return !empty(array_filter($val, static fn ($item) => null !== $item && '' !== $item));
        }
        if (\is_object($val)) {
            return !empty(get_object_vars($val));
        }

        return null !== $val && '' !== $val;
    }

    public function max($value, $max)
    {
        return $value <= $max;
    }

    public function between($value, $min, $max)
    {
        return $value >= $min && $value <= $max;
    }

    public function in($value, ...$params)
    {
        if (empty($params)) {
            return false;
        }

        // Check if the first parameter is a class name with optional method
        // @todo, must check that string does not contain '(' or ')'
        if (str_contains($params[0], '::')) {
            [$class, $method] = explode('::', $params[0]);
            if (class_exists($class) && method_exists($class, $method)) {
                $params = $class::$method();
            } else {
                return false;
            }
        } elseif (class_exists($params[0]) && is_subclass_of($params[0], 'MabeEnum\Enum')) {
            $params = $params[0]::getValues();
        }

        return \in_array($value, \is_array($params[0]) ? $params[0] : $params, true);
    }

    public function file($value)
    {
        return file_exists($value instanceof \SplFileInfo ? $value->getRealPath() : $value);
    }

    public function length($value, $min = 0, $max = PHP_INT_MAX, bool $inclusive = true): bool
    {
        // Validate min and max values
        if ($min > $max) {
            throw new \InvalidArgumentException(sprintf('min value %d cannot be less than max value %d', $min, $max));
        }

        // Extract length based on input type
        $length = null;
        if (\is_string($value) || \is_int($value)) {
            $length = \UTF::instance()->strlen((string) $value);
        } elseif (\is_array($value) || $value instanceof \Countable) {
            $length = \count($value);
        } elseif (\is_object($value)) {
            $length = $this->length(get_object_vars($value), $min, $max, $inclusive);
        }

        return null !== $length
            && !($inclusive ? $length < $min || $length > $max : $length <= $min || $length >= $max);
    }

    /**
     * Apply rules to a given value.
     *
     * @param mixed $value
     * @param mixed $rules
     * @param mixed $errors
     * @param mixed $field
     */
    protected function applyRules($value, $rules, &$errors, $field)
    {
        foreach (preg_split('/\s*\|\s*/', $rules) as $rule) {
            if (preg_match('/^(\w+)(?::(.*))?$/', $rule, $matches)) {
                $ruleName = $matches[1];
                $params   = isset($matches[2]) ? preg_split('/\s*,\s*/', $matches[2]) : [];
                if (!$this->validateField($value, $ruleName, $params)) {
                    $errors[$field][] = "Validation for '{$field}' failed on rule '{$rule}'.";
                }
            }
        }
    }

    // Extract rules from a ruleset
    protected function getRulesFromRuleset(string $rulesetName): array
    {
        if ($this->f3->devoid('CONSTRAINTS.' . $rulesetName)) {
            throw new \InvalidArgumentException("Ruleset '{$rulesetName}' not found in configuration.");
        }

        return $this->f3->get('CONSTRAINTS.' . $rulesetName);
    }
}
