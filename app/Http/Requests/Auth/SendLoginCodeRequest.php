<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Concerns\NormalisesLoginInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class SendLoginCodeRequest extends FormRequest
{
    use NormalisesLoginInput;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'max:254', 'regex:/\A[^\x00-\x1F\x7F]+\z/', 'email']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.*' => 'Vul een geldig e-mailadres in.'];
    }

    public function email(): string
    {
        return $this->string('email')->value();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => $this->normalisedEmail($this->input('email'))]);
    }
}
