<?php

namespace App\Services;

use App\Repositories\CustomerRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\DB;
use Exception;

class InvoiceService
{
    protected $active_company;
    protected $financial_period;
    protected $customerRepository;
    protected $invoiceRepository;
    protected $orderRepository;

    public function __construct(
        CompanyService $companyService,
        CustomerRepository $customerRepository,
        InvoiceRepository $invoiceRepository,
        OrderRepository $orderRepository
    ) {
        $this->active_company = $companyService->getActiveCompany();
        $this->financial_period = $this->active_company
            ? $companyService->getFinancialPeriod($this->active_company)
            : null;
        $this->customerRepository = $customerRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->orderRepository = $orderRepository;
    }


    protected function execProcedure($customerCode)
    {
        if (!$this->financial_period) {
            throw new Exception('Financial period is not set.');
        }
        DB::statement('EXEC Proc_GCustomer ?, ?', [$this->financial_period, $customerCode]);
    }


    public function getAccountBalance($token)
    {
        $customer = $this->customerRepository->findByToken($token);
        $this->execProcedure($customer->Code);
        return $this->invoiceRepository->getAccountBalance($this->financial_period, $customer->Code);
    }


    public function listPastInvoices($token)
    {
        $customer = $this->customerRepository->findByToken($token);
        $this->execProcedure($customer->Code);
        return $this->invoiceRepository->listPastInvoices($this->financial_period, $customer->Code);
    }


    public function listPastOrders($token)
    {
        $customer = $this->customerRepository->findByToken($token);
        $this->execProcedure($customer->Code);
        return $this->orderRepository->listPastOrders($this->financial_period, $this->active_company, $customer->Code);
    }


    public function listPastOrdersProducts($token, $order)
    {
        $customer = $this->customerRepository->findByToken($token);
        $this->execProcedure($customer->Code);
        return $this->orderRepository->listPastOrdersProducts($this->financial_period, $order, $customer->Code);
    }

    public function listUnverifiedOrders($token)
    {
        $customer = $this->customerRepository->findByToken($token);
        $this->execProcedure($customer->Code);
        return $this->orderRepository->listUnverifiedOrders($this->financial_period, $customer->Code);
    }


    public function listUnverifiedOrdersProducts($token, $order)
    {
        $customer = $this->customerRepository->findByToken($token);
        $this->execProcedure($customer->Code);
        return $this->orderRepository->listUnverifiedOrdersProducts($order, $customer->Code);
    }
}
