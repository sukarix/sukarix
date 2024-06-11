<?php

declare(strict_types=1);

namespace Sukarix\Validation;

use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validatable;

/**
 * Class Validator.
 */
class DataValidator
{
    private array $errors;

    public function verify($input, Validatable $validator, bool $throwOnFail = false): bool
    {
        if (null !== $validator->getName()) {
            $validationException = null;

            try {
                $validator->assert($input);
            } catch (NestedValidationException $exception) {
                if ($throwOnFail) {
                    throw $exception;
                }
                $validationException = $exception;
            }

            $numRules      = \count($validator->getRules());
            $numExceptions = null !== $validationException ? \count($validationException->getChildren()) : 0;
            $summary       = [
                'total'  => $numRules,
                'failed' => $numExceptions,
                'passed' => $numRules - $numExceptions,
            ];
            if (null !== $validationException) {
                $fullName                            = str_replace('_', ' ', $validator->reportError($input, $summary)->getFullMessage());
                $this->errors[$validator->getName()] = $fullName;

                return false;
            }
        } else {
            throw new \RuntimeException('The validator must have a name');
        }

        return true;
    }

    /**
     * @param $popErrors     bool If true errors will be put into f3 hive
     * @param $errorsHiveKey string
     *
     * @return bool
     */

    /**
     * @return bool
     */
    public function allValid($popErrors = true, $errorsHiveKey = 'api_errors')
    {
        if (!empty($this->errors) && $popErrors) {
            \Base::instance()->set($errorsHiveKey, $this->errors);
        }

        return empty($this->errors);
    }

    public function getErrors()
    {
        return !empty($this->errors) ? $this->errors : [];
    }
}
