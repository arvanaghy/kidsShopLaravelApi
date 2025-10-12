<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditUserInfoRequest extends FormRequest
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
            'Name' => 'required|min:3',
            'Address' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'Name.required' => 'لطفا نام را وارد کنید',
            'Name.min' => 'نام نمیتواند کمتر از 3 کاراکتر باشد',
            'Address.required' => 'لطفا آدرس را وارد کنید',
        ];
    }
}
