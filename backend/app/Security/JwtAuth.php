<?php

namespace App\Security;

use App\Config\Env;

class JwtAuth {
    private static function getSecret() {
        return Env::get('JWT_SECRET', 'DefaultSecretKeyDoNotUseInProd');
    }

    public static function generateAccessToken($userId, $tenantId) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload = json_encode([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'iat' => time(),
            'exp' => time() + (15 * 60) // 15 minutes
        ]);

        return self::sign($header, $payload);
    }

    public static function generateRefreshToken($userId) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload = json_encode([
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + (30 * 24 * 60 * 60) // 30 days
        ]);

        return self::sign($header, $payload);
    }

    private static function sign($header, $payload) {
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecret(), true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function verifyToken($jwt) {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        $validSignature = hash_hmac('sha256', $header . "." . $payload, self::getSecret(), true);
        $base64UrlValidSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));

        if (!hash_equals($base64UrlValidSignature, $signature)) {
            return false;
        }

        $decodedPayload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);

        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
            return false; // Expired
        }

        return $decodedPayload;
    }
}
