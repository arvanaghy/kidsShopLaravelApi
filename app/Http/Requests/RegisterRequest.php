<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'phone_number' => 'required|min:10',
            'name' => 'required|min:3',
            'Address' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'phone_number.required' => 'شماره تلفن را وارد کنید',
            'phone_number.min' => 'شماره تلفن باید بیشتر از 10 رقم باشد',
            'name.required' => 'نام را وارد کنید',
            'name.min' => 'نام باید بیشتر از 3 کاراکتر باشد',
            'Address.required' => 'لطفا آدرس را وارد کنید',
        ];
    }
}
