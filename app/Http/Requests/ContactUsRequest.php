<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactUsRequest extends FormRequest
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
            'info' => 'required|min:3',
            'contact' => 'required|min:3',
            'message' => 'required|min:3',
        ];
    }
    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'info.required' => 'فیلد اطلاعات الزامی است.',
            'info.min' => 'فیلد اطلاعات باید حداقل ۳ کاراکتر باشد.',
            'contact.required' => 'فیلد تماس الزامی است.',
            'contact.min' => 'فیلد تماس باید حداقل ۳ کاراکتر باشد.',
            'message.required' => 'فیلد پیام الزامی است.',
            'message.min' => 'فیلد پیام باید حداقل ۳ کاراکتر باشد.',
        ];
    }
}
