<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsappOtpSender
{
    public function send(User $user, string $code): void
    {
        $endpoint = config('services.whatsapp_mfa.endpoint');

        if (! $endpoint) {
            return;
        }

        try {
            Http::withHeader('X-API-Key', config('services.whatsapp_mfa.api_key'))
                ->acceptJson()
                ->timeout(10)
                ->post($endpoint, [
                    'platform_id' => config('services.whatsapp_mfa.platform_id'),
                    'external_id' => config('services.whatsapp_mfa.external_id'),
                    'template_id' => config('services.whatsapp_mfa.template_id'),
                    'header_media_url' => null,
                    'callback_url' => null,
                    'metadata' => [],
                    'recipient' => [
                        'type' => 'npk',
                        'value' => $user->username,
                    ],
                    'body_params' => [
                        [
                            'model' => 1,
                            'value' => $user->name,
                            'param_type' => 'body',
                        ],
                        [
                            'model' => 2,
                            'value' => $code,
                            'param_type' => 'body',
                        ],
                    ],
                ]);

            Log::info('Sent WhatsApp MFA OTP.', [
                'username' => $user->username,
                'code' => $code,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to send WhatsApp MFA OTP.', [
                'username' => $user->username,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
