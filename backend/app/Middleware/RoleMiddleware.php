<?php

namespace App\Middleware;

use App\Helpers\Response;

class RoleMiddleware {
    public function handle($allowedRoles = []) {
        $currentRole = $_SESSION['current_role_id'] ?? null;

        if (empty($allowedRoles)) {
            return;
        }

        if ($currentRole === null || !in_array($currentRole, $allowedRoles, true)) {
            Response::json(['error' => 'Forbidden - insufficient permissions'], 403);
        }
    }
}
