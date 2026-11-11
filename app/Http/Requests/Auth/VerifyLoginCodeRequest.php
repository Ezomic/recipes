<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Concerns\NormalisesLoginInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyLoginCodeRequest extends FormRequest
{
    use NormalisesLoginInput;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:254', 'regex:/\A[^\x00-\x1F\x7F]+\z/', 'email'],
            'code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.*' => 'Vul een geldig e-mailadres in.',
            'code.*' => 'Voer de 6 cijfers van je code in.',
            'remember.*' => 'Ongeldige keuze.',
        ];
    }

    public function email(): string
    {
        return $this->string('email')->value();
    }

    public function code(): string
    {
        return $this->string('code')->value();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->normalisedEmail($this->input('email')),
            'code' => $this->trimmed($this->input('code')),
        ]);
    }
}
