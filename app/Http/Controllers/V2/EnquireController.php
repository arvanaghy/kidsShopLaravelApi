<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactUsRequest;
use App\Services\EnquireService;
use Exception;

class EnquireController extends Controller
{
    protected $enquireService;
    public function __construct(EnquireService $enquireService)
    {
        $this->enquireService = $enquireService;
    }

    public function contact_us(ContactUsRequest $request)
    {
        try {
            $validated = $request->validated();

            $this->enquireService->send_enquiry($validated['info'], $validated['contact'], $validated['message']);

            return response()->json([
                'result' => null,
                "message" => "پیغام شما با موفقیت ثبت شد",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'result' => null,
                'message' => $e->getMessage(),
            ], 503);
        }
    }
}
