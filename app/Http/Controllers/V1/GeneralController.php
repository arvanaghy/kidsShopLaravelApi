<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\CategoryModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CustomerModel;
use Exception;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManagerStatic as Image;


class GeneralController extends Controller
{
    protected $active_company = null;
    protected $financial_period = null;

    public function __construct()
    {
        $active_company = DB::table('Company')->where('DeviceSelected', 1)->first();
        if ($active_company) {
            $this->active_company = $active_company->Code;
            $active_financial_period = DB::table('DoreMali')->where('CodeCompany', $active_company->Code)->where('DeviceSelected', 1)->first();
            if ($active_financial_period) {
                $this->financial_period = $active_financial_period->Code;
            }
        }
    }

    protected function send_sms_via_webone($phoneNO, $text)
    {
        $base_url = 'https://webone-sms.ir/SMSInOutBox/SendSms';
        $params = array(
            'username' => '09354278334',
            'password' => '414411',
            'from' => '10002147',
            'text' => $text,
            'to' => $phoneNO
        );
        $url = $base_url . '?' . http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
        curl_close($ch);
    }

    public function online()
    {
        try {
            return response()->json([
                'result' => null,
                'message' => 'اتصال به سرور با موفقیت برقرار شد',

            ], 202);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function CreateBannerPath()
    {
        if (!File::isDirectory(public_path("banner-images"))) {
            File::makeDirectory("banner-images", 0755, true, true);
        }

        if (!File::isDirectory(public_path("banner-images/original"))) {
            File::makeDirectory("banner-images/original", 0755, true, true);
        }

        if (!File::isDirectory(public_path("banner-images/webp"))) {
            File::makeDirectory("banner-images/webp", 0755, true, true);
        }
    }

    protected function CreateBannerImages($data, $picName)
    {

        $imagePathDesktop = "banner-images/original/" . $picName . "_desktop.jpg";
        $webpPathDesktop =  "banner-images/webp/" . $picName . "_desktop.webp";

        File::put(public_path($imagePathDesktop), $data->Pic);
        Image::configure(['driver' => 'gd']);
        Image::make($imagePathDesktop)->encode('webp', 100)->resize(1360, 786)->save($webpPathDesktop, 100);
        File::delete($imagePathDesktop);

        $imagePathMobile = "banner-images/original/" . $picName . "_mobile.jpg";
        $webpPathMobile =  "banner-images/webp/" . $picName . "_mobile.webp";

        File::put(public_path($imagePathMobile), $data->Pic);
        Image::configure(['driver' => 'gd']);
        Image::make($imagePathMobile)->encode('webp', 100)->resize(390, 844)->save($webpPathMobile, 100);
        File::delete($imagePathMobile);
    }

    protected function removeBannerImage($data)
    {
        $webpPathDesktop =  "banner-images/webp/" . $data->PicName . "_desktop.webp";
        $webpPathMobile =  "banner-images/webp/" . $data->PicName . "_mobile.webp";
        File::delete($webpPathDesktop);
        File::delete($webpPathMobile);
    }

    public function edit_user_info(Request $request)
    {
        try {
            $validated = $request->validate([
                'Name' => 'required|min:3',
                // 'Job' => 'sometimes',
                'Address' => 'required',
                // 'Company' => 'sometimes',
                // 'Phone' => 'sometimes',
            ], [
                'Name.required' => 'لطفا نام را وارد کنید',
                'Name.min' => 'نام نمیتواند کمتر از 3 کاراکتر باشد',
                'Address.required' => 'لطفا آدرس را وارد کنید',
            ]);

            $token = $request->bearerToken();

            CustomerModel::where('UToken', $token)->where('CodeCompany', $this->active_company)->update([
                'Address' => $validated['Address'],
                'CTitle' => '',
                'StorName' => '',
                'Tel' => '',
                'Name' => $validated['Name'],
            ]);

            $user = CustomerModel::where('UToken', $token)->first();
            return response()->json([
                'result' => $user,
                "message" => "اطلاعات با موفقیت ثبت گردید"
            ], 202);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    public function contact_us(Request $request)
    {
        try {
            $validated = $request->validate([
                'info' => 'required|min:3',
                'contact' => 'required|min:3',
                'message' => 'required|min:3',
            ]);

            // check if contact is phone number
            if (is_numeric($validated['contact'])) {
                $this->send_sms_via_webone($validated['contact'], ' کاربر گرامی با تشکر از تماس شما، پیغام شما با موفقیت دریافت شد ');
            }

            $this->send_sms_via_webone('09149276590', 'یک پیغام از' . $validated['info'] . ' - ' . $validated['contact'] . ' - ' . $validated['message'] . 'دارید');


            if (true) {
                return response()->json([
                    'result' => null,
                    "message" => "پیغام شما با موفقیت ثبت شد",
                ], 200);
                return array(
                    "status" => 200,
                    'result' => null,
                );
            } else {
                return response()->json([
                    'result' => null,
                    "message" => "خطایی رخ داده است لطفا بعدا تلاش نماید",
                ], 400);
            }
        } catch (Exception $e) {

            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }


    public function checkOnlinePaymentAvailable()
    {
        try {
            $bankAccount = DB::table('AV_ShomareHesab_VIEW')->where('Def', 1)->where('CodeCompany', $this->active_company)->first();
            if ($bankAccount) {
                return response()->json([
                    'result' => $bankAccount,
                    "message" => "درگاه پرداخت اینترنتی فعال است"
                ], 201);
            } else {
                return response()->json([
                    'result' => null,
                    "message" => "درگاه پرداخت اینترنتی یافت نشد"
                ], 404);
            }
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }
    public function fetchBanners()
    {
        try {
            $imageResult = DB::table('DeviceHeaderImage')->select('Pic', 'Code', 'CChangePic', 'PicName')->where('CodeCompany', $this->active_company)->paginate('6');
            $this->CreateBannerPath();
            foreach ($imageResult as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeBannerImage($image);
                    DB::table('DeviceHeaderImage')->Where('Code', $image->Code)->update(['CChangePic' => 0, 'PicName' => null]);
                    if ($image->Pic != null) {
                        $picName = $image->Code . "_" . rand(10000, 99999);
                        $this->CreateBannerImages($image,  $picName);
                        DB::table('DeviceHeaderImage')->Where('Code', $image->Code)->update(['PicName' => $picName]);
                    }
                }
            }

            return response()->json([
                'result' => DB::table('DeviceHeaderImage')->select('Comment', 'PicName', 'Code')->where('CodeCompany', $this->active_company)->where('Pic', '!=', null)->orderBy('Code', 'DESC')->paginate('6'),
                "message" => "دریافت اطلاعات با موفقیت انجام شد",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    public function list_transfer_services()
    {
        try {
            return response()->json([
                'result' => DB::table('AV_KhadamatDevice_View')->where('CodeCompany', $this->active_company)->get(),
                "message" => "دریافت اطلاعات با موفقیت انجام شد",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    public function faq()
    {
        try {
            return response()->json([
                'result' => DB::table('DeviceAbout')->where('Type', 1)->orderBy('Radif', 'asc')->get(),
                "message" => "دریافت اطلاعات با موفقیت انجام شد",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    public function about_us()
    {
        try {
            return response()->json([
                'result' => DB::table('DeviceAbout')->where('Type', 0)->orderBy('Radif', 'asc')->get(),
                "message" => "دریافت اطلاعات با موفقیت انجام شد",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    protected function list_categories()
    {

        try {
            $imageCreation = CategoryModel::select('Pic', 'Code', 'CChangePic', 'PicName')->where('CodeCompany', $this->active_company)->orderBy('Code', 'DESC')->paginate(12);
            $this->CreateCategoryPath();
            foreach ($imageCreation as $image) {
                if ($image->CChangePic == 1) {
                    $this->removeCategoryImage($image);
                    Log::info('Image remove image for list_categories:', [
                        'Code' => $image->Code,
                        'PicName' => $image->PicName,
                    ]);
                    DB::table('KalaGroup')->Where('Code', $image->Code)->update(['CChangePic' => 0, 'PicName' => null]);
                    if ($image->Pic != null) {
                        $picName = ceil($image->Code) . "_" . rand(10000, 99999);
                        $this->CreateCategoryImages($image,  $picName);
                        Log::info('Image creation image for list_categories:', [
                            'Code' => $image->Code,
                            'PicName' => $picName,
                        ]);
                        DB::table('KalaGroup')->Where('Code', $image->Code)->update(['PicName' => $picName]);
                    }
                }
            }

            return response()->json([
                "message" => "عملیات با موفقیت انجام شد",
                "result" => CategoryModel::select('Code', 'Name', 'Comment', 'PicName')->where('CodeCompany', $this->active_company)->orderBy('Code', 'DESC')->paginate(12),
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'message' => $e->getMessage(),
                'result' => null
            ], 503);
        }
    }

    public function home_page()
    {
        try {
            return response()->json([
                'result' => null,
                "message" => "دریافت اطلاعات با موفقیت انجام شد",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }
}
