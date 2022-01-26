<?php

namespace Crm\UsersModule\Auth;

class PasswordGenerator
{
    public function generatePassword($passwordLength = 8)
    {
        $password = [];
        $characters = $this->availableCharacters();
        $length = strlen($characters);
        for ($i = 0; $i < $passwordLength; $i++) {
            $password[] = $characters[rand(0, $length - 1)];
        }
        return implode('', $password);
    }

    public function generatePin($pinLength = 6)
    {
        $password = [];
        $characters = $this->availablePinCharacters();
        $length = strlen($characters);
        for ($i = 0; $i < $pinLength; $i++) {
            $password[] = $characters[rand(0, $length - 1)];
        }
        return implode('', $password);
    }

    private function availableCharacters()
    {
        return 'abcdefghjkmnpqrstuvzxy123456789AABCDEFGHJKMNPQRSTUVZXY';
    }

    private function availablePinCharacters()
    {
        return '123456789';
    }
}
