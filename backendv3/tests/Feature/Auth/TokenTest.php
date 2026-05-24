<?php

namespace Tests\Feature\Auth;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * トークン管理エンドポイント統合テスト
 *
 * 対象エンドポイント：
 * GET    /auth/token  アクセストークン検証・ユーザー情報取得 → 200 / 401
 * PATCH  /auth/token  アクセストークン更新（リフレッシュ）  → 200 / 401
 * DELETE /auth/token  ログアウト                           → 204 / 401
 */
class TokenTest extends TestCase
{
    /** テスト用有効アクセストークン（シード済み） */
    private string $validAccessToken = 'valid-access-token-for-testing';

    /** テスト用有効リフレッシュトークン（シード済み） */
    private string $validRefreshToken = 'valid-refresh-token-for-testing';

    // =========================================================
    // GET /auth/token  アクセストークン検証・ユーザー情報取得
    // =========================================================

    /**
     * トークン検証：正常系
     *
     * 有効なアクセストークンを Bearer ヘッダーで送信したとき、
     * ユーザー情報が返ること。
     */
    public function test_トークン検証_正常系(): void
    {
        // 2. 実行
        $response = $this->withToken($this->validAccessToken)
                         ->getJson('/auth/token');

        // 3. 検証
        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'central_id',
                         'public_id',
                         'user_name',
                         'display_name',
                         'created_at',
                     ],
                 ]);
    }

    /**
     * トークン検証：トークン未提供
     *
     * Authorization ヘッダーなしでアクセスした場合に UNAUTHORIZED が返ること。
     */
    public function test_トークン検証_トークン未提供(): void
    {
        // 2. 実行
        $response = $this->getJson('/auth/token');

        // 3. 検証
        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'UNAUTHORIZED'],
                 ]);
    }

    /**
     * トークン検証：無効なトークン
     *
     * DB に存在しないトークンを送信した場合に UNAUTHORIZED が返ること。
     */
    public function test_トークン検証_無効なトークン(): void
    {
        // 2. 実行
        $response = $this->withToken('completely-invalid-token-xyz')
                         ->getJson('/auth/token');

        // 3. 検証
        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'UNAUTHORIZED'],
                 ]);
    }

    /**
     * トークン検証：期限切れトークン
     *
     * 有効期間（15分）を超えたトークンを送信した場合に TOKEN_EXPIRED が返ること。
     */
    public function test_トークン検証_期限切れトークン(): void
    {
        // 2. 実行
        $response = $this->withToken('expired-access-token-for-testing')
                         ->getJson('/auth/token');

        // 3. 検証
        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'TOKEN_EXPIRED'],
                 ]);
    }

    /**
     * トークン検証：失効済みトークン（ログアウト後）
     *
     * revoked_at が設定されたトークンを送信した場合に UNAUTHORIZED が返ること。
     */
    public function test_トークン検証_失効済みトークン(): void
    {
        // 1. 準備 - 失効済みトークンを直接挿入
        $revokedToken = 'revoked-access-token-for-testing';
        DB::table('tokens')->insert([
            'central_id'               => DatabaseSeeder::TEST_CENTRAL_ID,
            'access_token'             => $revokedToken,
            'refresh_token'            => 'revoked-refresh-unused',
            'access_token_expires_at'  => now()->addMinutes(15)->toDateTimeString(),
            'refresh_token_expires_at' => now()->addDays(30)->toDateTimeString(),
            'revoked_at'               => now()->subMinutes(1)->toDateTimeString(),
            'created_at'               => now()->subHour()->toDateTimeString(),
        ]);

        // 2. 実行
        $response = $this->withToken($revokedToken)->getJson('/auth/token');

        // 3. 検証
        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'UNAUTHORIZED'],
                 ]);
    }

    // =========================================================
    // PATCH /auth/token  アクセストークン更新
    // =========================================================

    /**
     * トークン更新：正常系
     *
     * 有効なリフレッシュトークンを送信したとき、
     * 新しいアクセストークンとリフレッシュトークンのペアが返ること（トークンローテーション）。
     */
    public function test_トークン更新_正常系(): void
    {
        // 2. 実行
        $response = $this->patchJson('/auth/token', [
            'refresh_token' => $this->validRefreshToken,
        ]);

        // 3. 検証
        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'access_token',
                         'refresh_token',
                         'expires_in',
                     ],
                 ]);
    }

    /**
     * トークン更新：無効なリフレッシュトークン
     *
     * 存在しないリフレッシュトークンを送信した場合に UNAUTHORIZED が返ること。
     */
    public function test_トークン更新_無効なリフレッシュトークン(): void
    {
        // 2. 実行
        $response = $this->patchJson('/auth/token', [
            'refresh_token' => 'invalid-or-already-used-refresh-token',
        ]);

        // 3. 検証
        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'UNAUTHORIZED'],
                 ]);
    }

    /**
     * トークン更新：期限切れリフレッシュトークン
     *
     * 有効期間（30日）を超えたリフレッシュトークンを送信した場合に TOKEN_EXPIRED が返ること。
     */
    public function test_トークン更新_期限切れリフレッシュトークン(): void
    {
        // 2. 実行
        $response = $this->patchJson('/auth/token', [
            'refresh_token' => 'expired-refresh-token-for-testing',
        ]);

        // 3. 検証
        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'TOKEN_EXPIRED'],
                 ]);
    }

    /**
     * トークン更新：使用済みトークンの再利用（ローテーション確認）
     *
     * 一度使用したリフレッシュトークンを再送信した場合に UNAUTHORIZED が返ること。
     */
    public function test_トークン更新_使用済みトークンの再利用(): void
    {
        // 1. 準備 - 使い捨て用のリフレッシュトークンを挿入
        $refreshToken = 'one-time-refresh-token-' . uniqid();
        DB::table('tokens')->insert([
            'central_id'               => DatabaseSeeder::TEST_CENTRAL_ID,
            'access_token'             => 'one-time-access-unused-' . uniqid(),
            'refresh_token'            => $refreshToken,
            'access_token_expires_at'  => now()->addMinutes(15)->toDateTimeString(),
            'refresh_token_expires_at' => now()->addDays(30)->toDateTimeString(),
            'revoked_at'               => null,
            'created_at'               => now()->toDateTimeString(),
        ]);

        // 1回目の更新（成功）
        $this->patchJson('/auth/token', ['refresh_token' => $refreshToken]);

        // 2. 実行 - 同じリフレッシュトークンで2回目を試みる
        $response = $this->patchJson('/auth/token', ['refresh_token' => $refreshToken]);

        // 3. 検証 - ローテーション済みで無効化されていること
        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'UNAUTHORIZED'],
                 ]);
    }

    /**
     * トークン更新：refresh_token 未指定
     */
    public function test_トークン更新_refresh_token未指定(): void
    {
        // 2. 実行
        $response = $this->patchJson('/auth/token', []);

        // 3. 検証
        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'UNAUTHORIZED'],
                 ]);
    }

    // =========================================================
    // DELETE /auth/token  ログアウト
    // =========================================================

    /**
     * ログアウト：正常系
     *
     * 有効なアクセストークンで DELETE を送信したとき、
     * 204 が返りトークンが失効すること。
     * user_events に logout イベントが記録されること。
     */
    public function test_ログアウト_正常系(): void
    {
        // 1. 準備 - ログアウト専用のトークンを挿入
        $logoutToken = 'logout-test-access-token-' . uniqid();
        DB::table('tokens')->insert([
            'central_id'               => DatabaseSeeder::TEST_CENTRAL_ID,
            'access_token'             => $logoutToken,
            'refresh_token'            => 'logout-test-refresh-' . uniqid(),
            'access_token_expires_at'  => now()->addMinutes(15)->toDateTimeString(),
            'refresh_token_expires_at' => now()->addDays(30)->toDateTimeString(),
            'revoked_at'               => null,
            'created_at'               => now()->toDateTimeString(),
        ]);

        // 2. 実行
        $response = $this->withToken($logoutToken)->deleteJson('/auth/token');

        // 3. 検証
        $response->assertStatus(204);

        // トークンが失効（revoked_at 設定）されていること
        $token = DB::table('tokens')->where('access_token', $logoutToken)->first();
        $this->assertNotNull($token->revoked_at, 'ログアウト後にトークンが失効されていること');

        // logout イベントが記録されていること
        $this->assertDatabaseHas('user_events', [
            'central_id' => DatabaseSeeder::TEST_CENTRAL_ID,
            'event_type' => 'logout',
        ]);
    }

    /**
     * ログアウト：トークン未提供
     *
     * Authorization ヘッダーなしでアクセスした場合に UNAUTHORIZED が返ること。
     */
    public function test_ログアウト_トークン未提供(): void
    {
        // 2. 実行
        $response = $this->deleteJson('/auth/token');

        // 3. 検証
        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'UNAUTHORIZED'],
                 ]);
    }

    /**
     * ログアウト：無効なトークン
     *
     * DB に存在しないトークンで DELETE した場合に UNAUTHORIZED が返ること。
     */
    public function test_ログアウト_無効なトークン(): void
    {
        // 2. 実行
        $response = $this->withToken('completely-invalid-token-xyz')
                         ->deleteJson('/auth/token');

        // 3. 検証
        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'UNAUTHORIZED'],
                 ]);
    }

    /**
     * ログアウト：ログアウト後に同トークンでの GET /auth/token は UNAUTHORIZED
     *
     * ログアウトで失効したトークンを使った後続リクエストが拒否されること。
     */
    public function test_ログアウト後_同トークンで再アクセス不可(): void
    {
        // 1. 準備 - ログアウト用トークンを挿入
        $token = 'reuse-test-token-' . uniqid();
        DB::table('tokens')->insert([
            'central_id'               => DatabaseSeeder::TEST_CENTRAL_ID,
            'access_token'             => $token,
            'refresh_token'            => 'reuse-test-refresh-' . uniqid(),
            'access_token_expires_at'  => now()->addMinutes(15)->toDateTimeString(),
            'refresh_token_expires_at' => now()->addDays(30)->toDateTimeString(),
            'revoked_at'               => null,
            'created_at'               => now()->toDateTimeString(),
        ]);

        // ログアウト実行
        $this->withToken($token)->deleteJson('/auth/token')->assertStatus(204);

        // 2. 実行 - 失効済みトークンで再アクセス
        $response = $this->withToken($token)->getJson('/auth/token');

        // 3. 検証
        $response->assertStatus(401);
    }
}
