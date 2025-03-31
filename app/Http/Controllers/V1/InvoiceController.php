<?php

namespace App\Http\Controllers\V1;

use App\Models\InvoiceModel;
use Illuminate\Http\Request;
use App\Models\CustomerModel;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Exception;

class InvoiceController extends Controller
{


    protected $companyCode = null;
    protected $financialPeriod = null;

    public function __construct()
    {
        $active_company = DB::table('Company')->where('DeviceSelected', 1)->first();
        if ($active_company) {
            $this->companyCode = $active_company->Code;
            $active_financial_period = DB::table('DoreMali')->where('CodeCompany', $active_company->Code)->where('DeviceSelected', 1)->first();
            if ($active_financial_period) {
                $this->financialPeriod = $active_financial_period->Code;
            }
        }
    }
    protected function exec_procedure($customerCode)
    {
        DB::statement('EXEC Proc_GCustomer ?, ?', [$this->financialPeriod, $customerCode]);
    }

    // ریز گردش حساب
    public function list_past_invoices(Request $request)
    {
        try {

            // CodeFactorForoosh
            $token = $request->bearerToken();
            $userResult = CustomerModel::where('UToken', $token)->first();
            if ($userResult) {
                $this->exec_procedure($userResult->Code);
                return response()->json([
                    "message" => "با موفقیت انجام شد",
                    "result" => InvoiceModel::where('CodeDoreMali', $this->financialPeriod)->where('CodeCustomer', $userResult->Code)->orderBy('Code', 'Desc')->paginate(12),
                ], 201);
            } else {
                return response()->json([
                    "message" => "کاربری با این توکن یافت نشد",
                    'result' => null
                ], 401);
            }
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    public function account_balance(Request $request)
    {
        try {
            $token = $request->bearerToken();
            $userResult = CustomerModel::where('UToken', $token)->first();
            if ($userResult) {
                $this->exec_procedure($userResult->Code);

                return response()->json([
                    "message" => "با موفقیت انجام شد",
                    "result" => DB::table('GHesab')->where('CodeDoreMali', $this->financialPeriod)->where('CodeCustomer', $userResult->Code)->sum('MCustomer'),
                ], 201);
            } else {
                return response()->json([
                    "message" => "کاربری با این توکن یافت نشد",
                    "result" => null
                ], 401);
            }
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    // سفارشات تایید شده
    public function list_past_orders(Request $request)
    {
        try {
            $token = $request->bearerToken();
            $userResult = CustomerModel::where('UToken', $token)->first();
            if ($userResult) {
                $this->exec_procedure($userResult->Code);
                return response()->json([
                    "message" => "با موفقیت انجام شد",
                    "result" => DB::table('AV_RFactorForoosh_VIEW')->where('CodeDoreMali', $this->financialPeriod)->where('CodeCompany', $this->companyCode)->where('CCode', $userResult->Code)->orderBy('Code', 'Desc')->paginate(12),
                ], 201);
            } else {
                return response()->json([
                    "message" => "کاربری با این توکن یافت نشد",
                    "result" => null
                ], 401);
            }
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    // لیست کالاهای سفارشات تایید شده
    public function list_past_orders_products(Request $request, $order)
    {
        try {
            $token = $request->bearerToken();
            $userResult = CustomerModel::where('UToken', $token)->first();
            if ($userResult) {
                $this->exec_procedure($userResult->Code);

                return response()->json([
                    "message" => "با موفقیت انجام شد",
                    "result" => DB::table('AV_RFactorForooshKala_VIEW')->select(
                        'CodeFactorForoosh',
                        'CodeFactor',
                        'SDate',
                        'CSarResid',
                        'SarResid',
                        'CCode',
                        'CName',
                        'CustomerName',
                        'Address',
                        'CodeEgtesadi',
                        'CodeMelli',
                        'CodeAnbar',
                        'AnbarName',
                        'GroupName',
                        'SubGroupName',
                        'CKala',
                        'CodeKala',
                        'UCode',
                        'Name',
                        'Model',
                        'Comment',
                        'Vahed',
                        'Tedad',
                        'TedadM',
                        'KVahed',
                        'Fee',
                        'RFee',
                        'KMegdar',
                        'KTedad',
                        'KTedadM',
                        'KFee',
                        'SumTedad',
                        'Vazn',
                        'SSum',
                        'SSumM',
                        'CTakhfifByPercent',
                        'DTakhfif',
                        'Takhfif',
                        'TSUM',
                        'TSUMM',
                        'DAvarez',
                        'Avarez',
                        'AvarezM',
                        'AllSum',
                        'AllSumM',
                        'DPoorsant',
                        'Poorsant',
                        'CPFactor',
                        'Seryal',
                        'MSeryal',
                        'BTamamShode',
                        'Sood',
                        'SumAllFactor',
                        'SumVazn',
                        'MCurrency',
                        'SumCurrency',
                        'KSeryal',
                        'Place',
                        'GPoint',
                        'SumGPoint'
                    )->where('CodeDoreMali', $this->financialPeriod)->where('CodeFactorForoosh', $order)->where('CCode', $userResult->Code)->paginate(12)
                ], 201);
            }
            return response()->json([
                "message" => "کاربری با این توکن یافت نشد",
                "result" => null
            ], 401);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    // لیست سفارشات تایید نشده
    public function list_unverified_orders(Request $request)
    {
        try {
            $token = $request->bearerToken();
            $userResult = CustomerModel::where('UToken', $token)->first();
            if ($userResult) {
                $this->exec_procedure($userResult->Code);

                return response()->json([
                    "message" => "با موفقیت انجام شد",
                    "result" => DB::table('AV_SOrder_View')->where('CodeDoreMali', $this->financialPeriod)->where('CCode', $userResult->Code)->orderBy('Code', 'Desc')->paginate(12)
                ], 201);
            } else {
                return response()->json([
                    "message" => "کاربری با این توکن یافت نشد",
                    "result" => null
                ], 401);
            }
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }

    // لیست کالاهای سفارشات تایید نشده
    public function list_unverified_orders_products(Request $request, $order)
    {
        try {
            $token = $request->bearerToken();
            $userResult = CustomerModel::where('UToken', $token)->first();
            if ($userResult) {
                $this->exec_procedure($userResult->Code);
                return response()->json([
                    "message" => 'دریافت اطلاعات با موفقیت همراه بود',
                    "result" => DB::table('AV_SOrderKala_View')->where('SCode', $order)->where('CCode', $userResult->Code)->paginate(12),
                ], 201);
            }
            return response()->json([
                "message" => "کاربری با این توکن یافت نشد",
                "result" => null
            ], 401);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "result" => null
            ], 503);
        }
    }
}
