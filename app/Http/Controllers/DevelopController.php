<?php

namespace App\Http\Controllers;

use App\Jobs\SendSmsJob;
use App\Models\OrderModel;
use App\Models\ProductImagesModel;
use App\Services\CompanyService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;


class DevelopController extends Controller
{
    private $active_company;

    public function __construct(CompanyService $companyService)
    {
        $this->active_company = $companyService->getActiveCompany();
    }

    // public function resetCChangePic()
    // {
    //     try {
    //         $updateCChangePic = DB::table('Kala')->where('CodeCompany', $this->active_company)->update(['CChangePic' => 1]);
    //         $updatePicName = ProductImagesModel::where('PicName', '!=', null)->update(['PicName' => null]);
    //         return response()->json([
    //             'result' =>
    //             [
    //                 'message' => 'CChangePic updated successfully',
    //                 'rowsEffectedCountChangePic' => $updateCChangePic,
    //                 'rowsEffectedCountPicName' => $updatePicName
    //             ]
    //         ], 200);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'result' => null,
    //             'message' => $e->getMessage(),
    //         ], 503);
    //     }
    // }


    // public function testRedis()
    // {
    //     try {
    //         $redis = Redis::connection('cache');
    //         $redis->ping();
    //         return "اتصال به Redis (اتصال cache) برقرار است!";
    //     } catch (\Exception $e) {
    //         return "خطا در اتصال به Redis: " . $e->getMessage();
    //     }
    // }

    // public function testOrder()
    // {
    //     try {

    //         $code = OrderModel::max('Code') + 1 ?? 1;
    //         return $code;
    //     } catch (\Exception $e) {
    //         return "خطا در اتصال به Redis: " . $e->getMessage();
    //     }
    // }

    // public function smsResult()
    // {
    //     $smsCode = rand(1000, 9999);
    //     $smsText = "کیدزشاپ.کدورود:{$smsCode}.https://kidsshop110.ir";
    //     // SendSmsJob::dispatchSync('09144744980', $smsText);
    //     return $smsText;
    // }
}
