<?php

namespace App\Security;

use App\Config\Env;

class Crypto {
    private static function getKey() {
        return Env::get('AES_KEY', 'DefaultFallbackKeyDoNotUseInProd');
    }

    public static function encrypt($data) {
        $key = self::getKey();
        // Generate an IV
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLength);

        if (is_array($data) || is_object($data)) {
            $data = json_encode($data);
        }

        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        
        // Return IV + Encrypted Data (base64 encoded together for transmission)
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt($payload) {
        $key = self::getKey();
        $data = base64_decode($payload);
        
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
        
        // Attempt to JSON decode, if it fails return as string
        $jsonDecoded = json_decode($decrypted, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $jsonDecoded : $decrypted;
    }
}
