<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * one_time_codes テーブルクエリ定義
 */
class OneTimeCodeModel
{
    /**
     * 未使用の認証コード取得（有効期限不問）
     */
    public static function findUnused(string $authCode): ?object
    {
        return DB::table('one_time_codes')
            ->where('auth_code', $authCode)
            ->whereNull('used_at')
            ->first();
    }

    /**
     * 認証コード挿入
     */
    public static function insert(array $data): void
    {
        DB::table('one_time_codes')->insert($data);
    }

    /**
     * 使用済みにマーク
     */
    public static function markUsed(int $id): void
    {
        DB::table('one_time_codes')
            ->where('id', $id)
            ->update(['used_at' => now()->toDateTimeString()]);
    }
}
