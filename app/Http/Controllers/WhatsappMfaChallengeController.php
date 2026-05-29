<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WhatsappOtpSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WhatsappMfaChallengeController extends Controller
{
    public function __construct(private readonly WhatsappOtpSender $whatsappOtpSender)
    {
    }

    public function create(Request $request)
    {
        if (! $request->session()->has('whatsapp_mfa.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/WhatsappMfaChallenge', [
            'csrfToken' => csrf_token(),
            'status' => $request->session()->get('status'),
            'errors' => $request->session()->get('errors')?->getBag('default')->messages() ?? [],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $userId = $request->session()->get('whatsapp_mfa.id');
        $remember = $request->session()->get('whatsapp_mfa.remember', false);

        if (! $userId) {
            return redirect()->route('login');
        }

        $cachedCode = Cache::get("whatsapp_mfa:{$userId}");

        if (! $cachedCode || ! hash_equals($cachedCode, $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => [__('The provided verification code is invalid.')],
            ]);
        }

        Cache::forget("whatsapp_mfa:{$userId}");

        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('login');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->forget(['whatsapp_mfa.id', 'whatsapp_mfa.remember']);

        return redirect()->intended(config('fortify.home'));
    }

    public function resend(Request $request)
    {
        $userId = $request->session()->get('whatsapp_mfa.id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user || ! $user->whatsapp_number) {
            return redirect()->route('login');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("whatsapp_mfa:{$userId}", $code, now()->addMinutes(5));
        $this->whatsappOtpSender->send($user, $code);

        return back()->with('status', 'A new verification code has been sent to your WhatsApp.');
    }
}
