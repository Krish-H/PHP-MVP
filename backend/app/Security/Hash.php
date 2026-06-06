<?php

namespace App\Security;

class Hash {
    public static function make(string $value): string {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    public static function verify(string $value, string $hash): bool {
        return password_verify($value, $hash);
    }
}
