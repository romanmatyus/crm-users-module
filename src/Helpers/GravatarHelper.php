<?php

namespace Crm\UsersModule\Helpers;

use Nette\Utils\Html;

class GravatarHelper
{
    public function process($email, $size = 40)
    {
        $hash = md5($email); // @phpstan-ignore-line
        $gravatarPath = 'avatar/' . $hash . '?s=' . $size . '&d=identicon';
        $data = [
            'class' => 'avatar',
            'src' => "https://www.gravatar.com/$gravatarPath",
            'alt' => $email,
        ];
        return Html::el('img', $data)->render();
    }
}
