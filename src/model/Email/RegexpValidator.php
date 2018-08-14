<?php

namespace Crm\UsersModule\Email;

use Nette\Utils\Validators;

class RegexpValidator implements ValidatorInterface
{
    public function isValid($email): bool
    {
        return Validators::isEmail($email);
    }
}
