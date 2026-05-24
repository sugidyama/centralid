<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * users / user_identities / user_profiles テーブルクエリ定義
 */
class UserModel
{
    /**
     * central_id でユーザー取得
     */
    public static function findByCentralId(string $centralId): ?object
    {
        return DB::table('users')
            ->where('central_id', $centralId)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * identity_type と identity でユーザー取得
     */
    public static function findByIdentity(string $type, string $identity): ?object
    {
        $id = DB::table('user_identities')
            ->where('identity_type', $type)
            ->where('identity', $identity)
            ->first();

        if (!$id)
        {
            return null;
        }

        return DB::table('users')
            ->where('central_id', $id->central_id)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * central_id から user_identities を取得
     */
    public static function findIdentityByCentralId(string $centralId, string $type): ?object
    {
        return DB::table('user_identities')
            ->where('central_id', $centralId)
            ->where('identity_type', $type)
            ->first();
    }

    /**
     * 次の public_id を算出（既存最大値 + 1）
     */
    public static function nextPublicId(): int
    {
        return (int) (DB::table('users')->max('public_id') ?? 1000) + 1;
    }

    /**
     * ユーザー挿入
     */
    public static function insert(array $data): void
    {
        DB::table('users')->insert($data);
    }

    /**
     * user_profiles 挿入
     */
    public static function insertProfile(array $data): void
    {
        DB::table('user_profiles')->insert($data);
    }

    /**
     * user_identities 挿入
     */
    public static function insertIdentity(array $data): void
    {
        DB::table('user_identities')->insert($data);
    }
}
