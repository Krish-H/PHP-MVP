<?php

namespace App\Config;

class Config {
    public static function get($key, $default = null) {
        return Env::get($key, $default);
    }
}
