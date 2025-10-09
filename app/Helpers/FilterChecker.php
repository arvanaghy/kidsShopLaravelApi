<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class FilterChecker
{
    private static $filters = ['search'];
    public static function hasFilters(Request $request)
    {
        foreach (self::$filters as $filter) {
            if ($request->has($filter) && $request->$filter != '') {
                return true;
            }
        }
        return false;
    }
}
