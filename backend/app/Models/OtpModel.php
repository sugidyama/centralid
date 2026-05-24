<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * one_time_passwords テーブルクエリ定義
 */
class OtpModel
{
    /**
     * 未使用の最新OTP取得
     */
    public static function findLatestUnused(string $email): ?object
    {
        return DB::table('one_time_passwords')
            ->where('email', $email)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();
    }

    /**
     * 未使用OTPを全て無効化
     */
    public static function invalidateAllUnused(string $email): void
    {
        DB::table('one_time_passwords')
            ->where('email', $email)
            ->whereNull('used_at')
            ->update(['used_at' => now()->toDateTimeString()]);
    }

    /**
     * OTP挿入
     */
    public static function insert(array $data): void
    {
        DB::table('one_time_passwords')->insert($data);
    }

    /**
     * 試行回数インクリメント
     */
    public static function incrementAttempts(int $id): void
    {
        DB::table('one_time_passwords')->where('id', $id)->increment('attempts');
    }

    /**
     * 使用済みにマーク
     */
    public static function markUsed(int $id): void
    {
        DB::table('one_time_passwords')
            ->where('id', $id)
            ->update(['used_at' => now()->toDateTimeString()]);
    }
}
