<?php

namespace App\Helpers;

use App\Security\Crypto;
use App\Security\Csrf;

class Response {
    public static function json($data, $statusCode = 200, $encrypt = false) {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        $response = [];

        if ($encrypt) {
            // Encrypt the payload and send alongside a new CSRF token
            $response['csrf_token'] = Csrf::generateToken();
            $response['payload'] = Crypto::encrypt($data);
        } else {
            $response = $data;
        }

        echo json_encode($response);
        exit;
    }
}
