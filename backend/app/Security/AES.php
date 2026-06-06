<?php

namespace App\Security;

class AES {

    /**
     * Decrypts a payload formatted as Base64( IV + Ciphertext )
     */
    public static function decrypt($base64Payload, $key) {
        $data = base64_decode($base64Payload);
        
        // Ensure the payload is at least as long as our 16-byte IV
        if (strlen($data) < 16) {
            return false; 
        }

        // Extract the 16-byte IV and the encrypted text
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);

        // Decrypt using raw OpenSSL (aes-256-cbc)
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        
        return $decrypted;
    }

    /**
     * Encrypts a payload into Base64( IV + Ciphertext )
     */
    public static function encrypt($plainText, $key) {
        // Generate a fresh 16-byte IV
        $iv = openssl_random_pseudo_bytes(16);
        
        // Encrypt the data
        $ciphertext = openssl_encrypt($plainText, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        
        // Prepend the IV to the ciphertext and encode to Base64
        return base64_encode($iv . $ciphertext);
    }
}