<?php

namespace App\Security;

use App\Config\Env;

class JWT {
    private static function getSecret() {
        return Env::get('JWT_SECRET', 'DefaultSecretKeyDoNotUseInProd');
    }

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function encode(array $payload, string $secret) {
        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body = self::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "$header.$body", $secret, true);
        $signature = self::base64UrlEncode($signature);

        return "$header.$body.$signature";
    }

    public static function decode(string $token, string $secret) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        list($header, $payload, $signature) = $parts;
        $validSignature = hash_hmac('sha256', "$header.$payload", $secret, true);
        $validSignature = self::base64UrlEncode($validSignature);

        if (!hash_equals($validSignature, $signature)) {
            return null;
        }

        $decoded = json_decode(self::base64UrlDecode($payload), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    public static function generateAccessToken($userId, $tenantId, $roleId) {
        $payload = [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
            'iat' => time(),
            'exp' => time() + (15 * 60)
        ];

        return self::encode($payload, self::getSecret());
    }

    public static function generateRefreshToken($userId) {
        $payload = [
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + (30 * 24 * 60 * 60)
        ];

        return self::encode($payload, self::getSecret());
    }

    public static function verifyToken($jwt) {
        $decoded = self::decode($jwt, self::getSecret());
        if (!$decoded) {
            return false;
        }

        if (isset($decoded['exp']) && $decoded['exp'] < time()) {
            return false;
        }

        return $decoded;
    }
}
