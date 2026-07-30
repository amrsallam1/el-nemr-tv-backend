<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseNotificationService
{
    public function configured(): bool
    {
        try {
            return $this->credentials(false) !== null;
        } catch (\Throwable) {
            // The admin page must remain usable when the Railway variable is
            // present but incomplete; sending will still report the exact error.
            return false;
        }
    }

    /** @param array<string, string> $data */
    public function sendToAll(array $data): string
    {
        $credentials = $this->credentials();
        $projectId = (string) (config('services.firebase.project_id') ?: $credentials['project_id'] ?? '');

        if ($projectId === '') {
            throw new RuntimeException('Firebase project_id is missing.');
        }

        $response = Http::acceptJson()
            ->withToken($this->accessToken($credentials))
            ->timeout(20)
            ->retry(2, 500)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'topic' => (string) config('services.firebase.topic', 'all'),
                    'data' => collect($data)->map(fn ($value) => (string) $value)->all(),
                    'android' => ['priority' => 'high'],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Firebase rejected the notification (HTTP '.$response->status().').');
        }

        return (string) $response->json('name', 'sent');
    }

    /** @return array<string, mixed>|null */
    private function credentials(bool $required = true): ?array
    {
        $raw = trim((string) config('services.firebase.credentials_json'));
        if ($raw === '') {
            if ($required) {
                throw new RuntimeException('Firebase service account is not configured yet.');
            }

            return null;
        }

        if (! str_starts_with($raw, '{')) {
            $decoded = base64_decode($raw, true);
            $raw = $decoded === false ? '' : $decoded;
        }

        $credentials = json_decode($raw, true);
        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Firebase service account JSON is invalid.');
        }

        return $credentials;
    }

    /** @param array<string, mixed> $credentials */
    private function accessToken(array $credentials): string
    {
        $cacheKey = 'firebase-access-token-'.sha1((string) $credentials['client_email']);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));

            $signature = '';
            if (! openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign the Firebase access token.');
            }

            $assertion = "{$header}.{$claims}.".$this->base64Url($signature);
            $response = Http::asForm()->timeout(15)->post(
                $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion]
            );

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Could not authenticate with Firebase.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
