<?php

namespace App\Config;

class Constants {
    public const ROLE_ADMIN = 1;
    public const ROLE_PROVIDER = 2;
    public const ROLE_NURSE = 3;
    public const ROLE_PATIENT = 4;
    public const ROLE_PHARMACIST = 5;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PENDING = 'pending';
}
