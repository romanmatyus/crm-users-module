<?php

namespace Crm\UsersModule\Email;

interface ValidatorInterface
{
    public function isValid($email): bool;
}
