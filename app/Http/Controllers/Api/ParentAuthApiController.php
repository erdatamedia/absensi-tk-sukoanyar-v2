<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ParentAuthApiController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'pin' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->string('phone')) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout($request));
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'phone' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $credentials = [
            'phone' => $request->string('phone'),
            'password' => $request->string('pin'),
            'role' => 'orang_tua',
        ];

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'phone' => 'Nomor HP atau PIN salah.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return response()->json(['status' => 'ok', 'user' => Auth::user()]);
    }
}
