<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResendSmsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone_number' => 'required|min:10|regex:/^09[0-9]{9}$/'
        ];
    }

    public function messages()
    {
        return [
            'phone_number.required' => 'شماره موبایل را وارد کنید',
            'phone_number.min' => 'شماره موبایل باید 10 رقم باشد',
            'phone_number.regex' => 'شماره موبایل صحیح نمی باشد',
        ];
    }
}
