<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * tokens テーブルクエリ定義
 */
class TokenModel
{
    /**
     * access_token でトークン取得
     */
    public static function findByAccessToken(string $token): ?object
    {
        return DB::table('tokens')->where('access_token', $token)->first();
    }

    /**
     * refresh_token でトークン取得
     */
    public static function findByRefreshToken(string $token): ?object
    {
        return DB::table('tokens')->where('refresh_token', $token)->first();
    }

    /**
     * トークン挿入
     */
    public static function insert(array $data): void
    {
        DB::table('tokens')->insert($data);
    }

    /**
     * トークン失効
     */
    public static function revoke(int $id): void
    {
        DB::table('tokens')
            ->where('id', $id)
            ->update(['revoked_at' => now()->toDateTimeString()]);
    }
}
