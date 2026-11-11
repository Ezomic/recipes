<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RequestLoginCode;
use App\Actions\Auth\VerifyLoginCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendLoginCodeRequest;
use App\Http\Requests\Auth\VerifyLoginCodeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LoginCodeController extends Controller
{
    public const SESSION_EMAIL = 'login_code_email';

    public function send(SendLoginCodeRequest $request, RequestLoginCode $action): RedirectResponse
    {
        $action->handle($request->email(), (string) $request->ip());

        $request->session()->put(self::SESSION_EMAIL, $request->email());

        return to_route('login');
    }

    public function verify(VerifyLoginCodeRequest $request, VerifyLoginCode $action): RedirectResponse
    {
        $user = $action->handle($request->email(), $request->code());

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => 'Deze code is onjuist of verlopen. Vraag zo nodig een nieuwe code aan.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_EMAIL);

        return redirect()->intended(route('recipes.index'));
    }
}
