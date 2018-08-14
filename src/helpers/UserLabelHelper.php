<?php

namespace Crm\UsersModule\Helpers;

class UserLabelHelper
{
    public function process($user)
    {
        $append = '';
        if ($user->is_institution) {
            $append .= ' <small>Inštitúcia: ' . $user->institution_name . '</small>';
        }

        if ($user->first_name || $user->last_name) {
            return "{$user->first_name} {$user->last_name}{$append}";
        } else {
            return $user->email . $append;
        }
    }
}
