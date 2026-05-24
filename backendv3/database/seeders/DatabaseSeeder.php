<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * テスト用データベースシーダー
 */
class DatabaseSeeder extends Seeder
{
    /** テストユーザーのセントラルID */
    public const TEST_CENTRAL_ID = '2026-test-user-00000000-0000-0000-000000000000';

    public function run(): void
    {
        $now = now()->toDateTimeString();

        // サービス設定（configs テーブルに EAV 形式で登録）
        DB::table('configs')->insert([
            'config_name'  => 'services',
            'config_value' => json_encode(['lunchmap', 'testapp']),
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        // テストユーザー
        DB::table('users')->insert([
            'central_id'              => self::TEST_CENTRAL_ID,
            'public_id'               => 1001,
            'user_name'               => null,
            'display_name'            => null,
            'user_name_updated_at'    => $now,
            'display_name_updated_at' => $now,
            'created_at'              => $now,
            'deleted_at'              => null,
        ]);

        // 有効アクセストークン（GET /auth/token 検証テスト用）
        DB::table('tokens')->insert([
            'central_id'               => self::TEST_CENTRAL_ID,
            'access_token'             => 'valid-access-token-for-testing',
            'refresh_token'            => 'seed-refresh-unused-1',
            'access_token_expires_at'  => now()->addMinutes(15)->toDateTimeString(),
            'refresh_token_expires_at' => now()->addDays(30)->toDateTimeString(),
            'revoked_at'               => null,
            'created_at'               => $now,
        ]);

        // 有効リフレッシュトークン（PATCH /auth/token 更新テスト用・アクセストークンとは別行）
        DB::table('tokens')->insert([
            'central_id'               => self::TEST_CENTRAL_ID,
            'access_token'             => 'seed-access-unused-1',
            'refresh_token'            => 'valid-refresh-token-for-testing',
            'access_token_expires_at'  => now()->addMinutes(15)->toDateTimeString(),
            'refresh_token_expires_at' => now()->addDays(30)->toDateTimeString(),
            'revoked_at'               => null,
            'created_at'               => $now,
        ]);

        // 期限切れアクセストークン（TOKEN_EXPIRED テスト用）
        DB::table('tokens')->insert([
            'central_id'               => self::TEST_CENTRAL_ID,
            'access_token'             => 'expired-access-token-for-testing',
            'refresh_token'            => 'seed-refresh-unused-2',
            'access_token_expires_at'  => now()->subMinutes(30)->toDateTimeString(),
            'refresh_token_expires_at' => now()->addDays(30)->toDateTimeString(),
            'revoked_at'               => null,
            'created_at'               => now()->subHour()->toDateTimeString(),
        ]);

        // 期限切れリフレッシュトークン（TOKEN_EXPIRED テスト用）
        DB::table('tokens')->insert([
            'central_id'               => self::TEST_CENTRAL_ID,
            'access_token'             => 'seed-access-unused-2',
            'refresh_token'            => 'expired-refresh-token-for-testing',
            'access_token_expires_at'  => now()->subMinutes(30)->toDateTimeString(),
            'refresh_token_expires_at' => now()->subDays(1)->toDateTimeString(),
            'revoked_at'               => null,
            'created_at'               => now()->subDays(31)->toDateTimeString(),
        ]);

        // 有効 OAuth 一時コード（POST /oauth/{identity}/login 正常系テスト用）
        DB::table('one_time_codes')->insert([
            'central_id' => self::TEST_CENTRAL_ID,
            'auth_code'  => 'valid-auth-code-for-testing',
            'expires_at' => now()->addMinutes(5)->toDateTimeString(),
            'used_at'    => null,
            'created_at' => $now,
        ]);

        // 期限切れ OAuth 一時コード（CODE_EXPIRED テスト用）
        DB::table('one_time_codes')->insert([
            'central_id' => self::TEST_CENTRAL_ID,
            'auth_code'  => 'expired-auth-code-for-testing',
            'expires_at' => now()->subMinutes(10)->toDateTimeString(),
            'used_at'    => null,
            'created_at' => now()->subMinutes(15)->toDateTimeString(),
        ]);

        // 1回限り OAuth 一時コード（使用済み確認テスト用）
        DB::table('one_time_codes')->insert([
            'central_id' => self::TEST_CENTRAL_ID,
            'auth_code'  => 'one-time-auth-code-for-testing',
            'expires_at' => now()->addMinutes(5)->toDateTimeString(),
            'used_at'    => null,
            'created_at' => $now,
        ]);
    }
}
