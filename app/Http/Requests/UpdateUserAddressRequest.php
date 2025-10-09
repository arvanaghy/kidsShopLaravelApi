<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserAddressRequest extends FormRequest
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
            'address' => 'required|min:3',
        ];
    }

    public function messages()
    {
        return [
            'address.required' => 'لطفا آدرس را وارد کنید',
            'address.min' => 'لطفا حداقل 3 کاراکتر وارد کنید',
        ];
    }
}
