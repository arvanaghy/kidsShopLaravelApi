<?php

namespace App\Helpers;

class StringHelper
{
    public static function normalizePersianCharacters($text)
    {
        if (!$text) {
            return $text;
        }

        $search  = ['ی'];
        $replace = ['ي'];

        return str_replace($search, $replace, $text);
    }
}
