<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class ZeroMailer
{
    /**
     * @throws ZeroMailFailed
     */
    public function send(string $to, string $subject, string $text): void
    {
        $url = config('services.zero.url');
        $token = config('services.zero.token');

        if (! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            Log::error('Zero outbound mail is not configured.');

            throw new ZeroMailFailed;
        }

        try {
            $response = Http::withToken($token)
                ->connectTimeout(5)
                ->timeout(10)
                ->acceptJson()
                ->asJson()
                ->post(rtrim($url, '/').'/api/outbound', ['to' => $to, 'subject' => $subject, 'text' => $text]);
        } catch (ConnectionException) {
            Log::warning('Zero outbound mail could not reach Zero.');

            throw new ZeroMailFailed;
        }

        if ($response->status() !== 202) {
            Log::warning('Zero outbound mail was not accepted, status '.$response->status().'.');

            throw new ZeroMailFailed;
        }
    }
}
