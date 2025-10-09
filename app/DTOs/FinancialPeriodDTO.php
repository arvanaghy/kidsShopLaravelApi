<?php

namespace App\DTOs;

class FinancialPeriodDTO
{
    public string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }
}
