<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifySmsRequest extends FormRequest
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
            'phone_number' => 'required|min:10|regex:/^09[0-9]{9}$/',
            'sms' => 'required|size:4'
        ];
    }

    public function messages()
    {
        return [
            'sms.size' => 'کد وارد شده صحیح نیست',
            'sms.required' => 'کد وارد شده صحیح نیست',
            'phone_number.regex' => 'شماره تلفن وارد شده صحیح نیست',
            'phone_number.min' => 'شماره تلفن وارد شده صحیح نیست',
            'phone_number.required' => 'شماره تلفن ضروری است',
        ];
    }
}
