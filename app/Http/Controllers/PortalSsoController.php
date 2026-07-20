<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class PortalSsoController extends Controller
{
    private const DEV_FALLBACK_SECRET = 'aquafast-pcp-portal-sso-dev-v1-not-for-public-internet';

    public function handle(Request $request): RedirectResponse
    {
        $token = trim((string) $request->query('t', ''));
        if ($token === '') {
            abort(400, 'Token SSO ausente.');
        }

        [$payloadB64, $sigB64] = array_pad(explode('.', $token, 2), 2, '');
        if ($payloadB64 === '' || $sigB64 === '') {
            abort(400, 'Token SSO invalido.');
        }

        $payloadJson = $this->base64UrlDecode($payloadB64);
        if ($payloadJson === null) {
            abort(400, 'Payload SSO invalido.');
        }

        $secret = $this->secret();
        $expectedSigJson = $this->base64UrlEncode(hash_hmac('sha256', $payloadJson, $secret, true));
        $expectedSigB64 = $this->base64UrlEncode(hash_hmac('sha256', $payloadB64, $secret, true));
        if (!hash_equals($expectedSigJson, $sigB64) && !hash_equals($expectedSigB64, $sigB64)) {
            abort(403, 'Assinatura SSO invalida.');
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            abort(400, 'Payload SSO corrompido.');
        }

        $now = time();
        $exp = (int) ($payload['exp'] ?? 0);
        $iat = (int) ($payload['iat'] ?? 0);
        if ($iat > $now + 300 || $exp < $now) {
            abort(403, 'Token SSO expirado.');
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            abort(400, 'Email SSO invalido.');
        }

        $name = trim((string) ($payload['name'] ?? $email));
        if ($name === '') {
            $name = $email;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(Str::random(40)),
            ]
        );

        if ($user->name !== $name) {
            $user->name = $name;
            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $target = trim((string) ($payload['next'] ?? ''));
        if ($target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
            $target = '/dashboard';
        }

        return redirect()->to($target);
    }

    private function secret(): string
    {
        $secret = trim((string) (getenv('PORTAL_PCP_SSO_SECRET') ?: ''));
        if ($secret !== '') {
            return $secret;
        }

        foreach ([
            '/var/www/aquafast/secrets/.pcp_portal_sso_secret',
            dirname(__DIR__, 4) . '/.pcp_portal_sso_secret',
        ] as $path) {
            if (is_readable($path)) {
                $secret = trim((string) file_get_contents($path));
                if ($secret !== '') {
                    return $secret;
                }
            }
        }

        return self::DEV_FALLBACK_SECRET;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
