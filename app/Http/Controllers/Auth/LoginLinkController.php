<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RedeemLoginLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LoginLinkController extends Controller
{
    public function __invoke(Request $request, string $token, RedeemLoginLink $action): RedirectResponse
    {
        $user = $action->handle($token);

        abort_if($user === null, 403);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(LoginCodeController::SESSION_EMAIL);

        return redirect()->intended(route('recipes.index'));
    }
}
