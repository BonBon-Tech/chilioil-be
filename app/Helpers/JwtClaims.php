<?php

namespace App\Helpers;

use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Helper to read custom claims from the current JWT token.
 * Avoids extra DB queries for company_id, store_id, and role.
 */
class JwtClaims
{
    private static ?array $cache = null;

    private static function payload(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        try {
            $payload = JWTAuth::parseToken()->getPayload();
            self::$cache = $payload->toArray();
        } catch (\Throwable) {
            self::$cache = [];
        }
        return self::$cache;
    }

    public static function userId(): ?string
    {
        return self::payload()['user_id'] ?? null;
    }

    public static function companyId(): ?string
    {
        return self::payload()['company_id'] ?? null;
    }

    public static function storeId(): ?string
    {
        return self::payload()['store_id'] ?? null;
    }

    public static function role(): ?string
    {
        return self::payload()['role'] ?? null;
    }

    public static function isOwner(): bool
    {
        return self::role() === 'owner';
    }

    /** Clear cache between requests (call from middleware). */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
