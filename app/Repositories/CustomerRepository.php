<?php

namespace App\Repositories;

use App\Models\CustomerModel;
use App\Services\CompanyService;
use Exception;

class CustomerRepository
{
    protected $active_company;
    public function __construct(CompanyService $companyService)
    {
        $this->active_company = $companyService->getActiveCompany();
    }
    public function findByToken($token)
    {
        $customer = CustomerModel::where('UToken', $token)->where('CodeCompany', $this->active_company)->first();
        if (!$customer) {
            throw new Exception('Customer not found.');
        }
        return $customer;
    }

    public function findByCode($code)
    {
        $customer = CustomerModel::where('Code', $code)->where('CodeCompany', $this->active_company)->first();
        if (!$customer) {
            throw new Exception('Customer not found.');
        }
        return $customer;
    }

    public function logOut($token)
    {
        CustomerModel::where('UToken', $token)
            ->update([
                'UToken' => null,
                'VerifiedAT' => null,
                'SMSTime' => null,
                'SMSCode' => null,
            ]);
    }
}
