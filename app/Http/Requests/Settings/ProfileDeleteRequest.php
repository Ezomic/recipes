<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ProfileDeleteRequest extends FormRequest
{
    /**
     * There is no password to confirm with: the emailed code is the only credential, and in
     * workflow mode the route has already sent the user through ID (RequirePasswordInWorkflowMode).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
