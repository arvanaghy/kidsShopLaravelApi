<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'products' => 'required|array|min:1',
            'products.*.KCode' => 'required|numeric|exists:Kala,Code',
            'products.*.Tedad' => 'required|numeric|min:1',
            'products.*.ColorCode' => 'required',
            'products.*.SizeNum' => 'required',
            'products.*.RGB' => 'required',
            'description' => 'nullable|string|max:255',
            'CodeKhadamat' => 'required|numeric',
        ];
    }

    public function messages()
    {
        return [
            'products.required' => 'لطفا حداقل یک محصول انتخاب کنید',
            'products.*.KCode.required' => 'لطفا کد محصول را وارد کنید',
            'products.*.Tedad.required' => 'لطفا تعداد محصول را وارد کنید',
            'products.*.ColorCode.required' => 'لطفا رنگ محصول را وارد کنید',
            'products.*.SizeNum.required' => 'لطفا سایز محصول را وارد کنید',
            'products.*.RGB.required' => 'لطفا رنگ محصول را وارد کنید',
            'description.required' => 'لطفا توضیحات را وارد کنید',
            'CodeKhadamat.required' => 'لطفا کد خدمات را وارد کنید',
        ];
    }
}
