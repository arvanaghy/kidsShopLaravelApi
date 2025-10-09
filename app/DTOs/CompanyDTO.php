<?php

namespace App\DTOs;

class CompanyDTO
{
    public string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }
}
