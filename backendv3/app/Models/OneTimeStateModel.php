<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * one_time_states テーブルクエリ定義
 */
class OneTimeStateModel
{
    /**
     * 有効なステート取得（有効期限内のみ）
     */
    public static function findValid(string $state, string $provider): ?object
    {
        return DB::table('one_time_states')
            ->where('state', $state)
            ->where('provider', $provider)
            ->where('expires_at', '>', now()->toDateTimeString())
            ->first();
    }

    /**
     * ステート挿入
     */
    public static function insert(array $data): void
    {
        DB::table('one_time_states')->insert($data);
    }

    /**
     * ステート削除（消費）
     */
    public static function delete(string $state, string $provider): void
    {
        DB::table('one_time_states')
            ->where('state', $state)
            ->where('provider', $provider)
            ->delete();
    }
}
