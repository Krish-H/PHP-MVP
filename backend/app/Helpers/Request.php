<?php

namespace App\Helpers;

class Request {
    private static $data;

    public static function body() {
        if (self::$data !== null) {
            return self::$data;
        }

        $raw = file_get_contents('php://input');
        if (!$raw) {
            self::$data = [];
            return self::$data;
        }

        $decoded = json_decode($raw, true);
        self::$data = is_array($decoded) ? $decoded : [];
        return self::$data;
    }
}
