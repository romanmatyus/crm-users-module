<?php

namespace Crm\UsersModule\Email;

class EmailValidator
{
    private $validators = [];

    private $lastValidator = null;

    public function isValid($email): bool
    {
        $this->lastValidator = null;
        foreach ($this->validators as $validator) {
            if (!$validator->isvalid($email)) {
                $this->lastValidator = $validator;
                return false;
            }
        }
        return true;
    }

    public function addValidator(ValidatorInterface $validator)
    {
        $this->validators[] = $validator;
        return $this;
    }

    public function lastValidator()
    {
        return $this->lastValidator;
    }
}
