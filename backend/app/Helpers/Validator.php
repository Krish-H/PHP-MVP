<?php

namespace App\Helpers;

class Validator {
    /**
     * Checks if all required fields are present and not empty.
     */
    public static function required($data, $fields) {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Validates if a string is a valid email address.
     */
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validates if a string meets a minimum length requirement.
     */
    public static function minLength($string, $length) {
        return strlen(trim($string)) >= $length;
    }
}
