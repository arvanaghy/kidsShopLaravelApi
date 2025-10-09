<?php

namespace App\Http\Controllers\V2\Profile;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use Exception;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{

    protected $invoiceService;
    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    public function accountBalance(Request $request)
    {
        try {
            $token = $request->bearerToken();
            $accountBalance = $this->invoiceService->getAccountBalance($token);

            return response()->json([
                "message" => "با موفقیت انجام شد",
                "result" => $accountBalance,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function listPastInvoices(Request $request)
    {
        try {

            $token = $request->bearerToken();
            $invoices = $this->invoiceService->listPastInvoices($token);
            return response()->json([
                "message" => "با موفقیت انجام شد",
                "result" => $invoices,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function listPastOrders(Request $request)
    {
        try {
            $token = $request->bearerToken();
            $orders = $this->invoiceService->listPastOrders($token);
            return response()->json([
                "message" => "با موفقیت انجام شد",
                "result" => $orders
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function listPastOrdersProducts(Request $request, $order)
    {
        try {
            $token = $request->bearerToken();
            $products = $this->invoiceService->listPastOrdersProducts($token, $order);
            return response()->json([
                "message" => "با موفقیت انجام شد",
                "result" => $products
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function listUnverifiedOrders(Request $request)
    {
        try {
            $token = $request->bearerToken();
            $orders = $this->invoiceService->listUnverifiedOrders($token);
            return response()->json([
                "message" => "با موفقیت انجام شد",
                "result" => $orders
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function listUnverifiedOrdersProducts(Request $request, $order)
    {
        try {
            $token = $request->bearerToken();
            $products = $this->invoiceService->listUnverifiedOrdersProducts($token, $order);
            return response()->json([
                "message" => "با موفقیت انجام شد",
                "result" => $products
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }
}
