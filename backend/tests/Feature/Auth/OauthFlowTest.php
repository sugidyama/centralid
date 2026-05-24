<?php

namespace Tests\Feature\Auth;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * OAuth認証フロー統合テスト
 *
 * Socialite はテスト用モックを使用する。
 * モックは one_time_states テーブルの state 値を検証してユーザー情報を返す。
 * ※ laravel/socialite のインストールが必要: composer require laravel/socialite
 *
 * 対象エンドポイント：
 * GET  /oauth/{identity}          OAuth認証画面へリダイレクト → 302
 * GET  /oauth/{identity}/callback OAuthコールバック受付       → 302
 * POST /oauth/{identity}/login    一時コードによるログイン    → 200 TokenResponse
 */
class OauthFlowTest extends TestCase
{
    /** コールバック後のリダイレクト先 */
    private string $redirectUri = 'https://centralid.win/auth/callback';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSocialiteMocks();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Socialiteモック設定
     */
    private function setUpSocialiteMocks(): void
    {
        if (!class_exists(\Laravel\Socialite\Facades\Socialite::class)) {
            return;
        }

        $fakeGoogleUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $fakeGoogleUser->shouldReceive('getId')->andReturn('google-uid-12345');
        $fakeGoogleUser->shouldReceive('getEmail')->andReturn('google-test@gmail.com');
        $fakeGoogleUser->shouldReceive('getName')->andReturn('Google Test User');

        $fakeGithubUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $fakeGithubUser->shouldReceive('getId')->andReturn('github-uid-67890');
        $fakeGithubUser->shouldReceive('getEmail')->andReturn('github-test@github.com');
        $fakeGithubUser->shouldReceive('getName')->andReturn('GitHub Test User');

        $googleDriver = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $googleDriver->shouldReceive('redirect')->andReturnUsing(function () {
            return redirect('https://accounts.google.com/o/oauth2/auth?client_id=test&state=test');
        });
        $googleDriver->shouldReceive('user')->andReturnUsing(function () use ($fakeGoogleUser) {
            $state = request()->get('state', '');
            if (!DB::table('one_time_states')
                ->where('state', $state)
                ->where('provider', 'google')
                ->where('expires_at', '>', now()->toDateTimeString())
                ->exists()) {
                throw new \Laravel\Socialite\Two\InvalidStateException('Invalid state');
            }
            return $fakeGoogleUser;
        });

        $githubDriver = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $githubDriver->shouldReceive('redirect')->andReturnUsing(function () {
            return redirect('https://github.com/login/oauth/authorize?client_id=test&state=test');
        });
        $githubDriver->shouldReceive('user')->andReturnUsing(function () use ($fakeGithubUser) {
            $state = request()->get('state', '');
            if (!DB::table('one_time_states')
                ->where('state', $state)
                ->where('provider', 'github')
                ->where('expires_at', '>', now()->toDateTimeString())
                ->exists()) {
                throw new \Laravel\Socialite\Two\InvalidStateException('Invalid state');
            }
            return $fakeGithubUser;
        });

        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($googleDriver);
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('github')->andReturn($githubDriver);
    }

    /**
     * one_time_states にステートレコードを挿入するヘルパー
     */
    private function insertState(string $state, string $provider): void
    {
        DB::table('one_time_states')->insert([
            'state'        => $state,
            'provider'     => $provider,
            'redirect_uri' => $this->redirectUri,
            'service_id'   => 'lunchmap',
            'expires_at'   => now()->addMinutes(10)->toDateTimeString(),
            'created_at'   => now()->toDateTimeString(),
        ]);
    }

    /**
     * Location ヘッダーから auth_code クエリパラメータを取得するヘルパー
     */
    private function extractAuthCode(string $location): string
    {
        $parsed = [];
        parse_str((string) parse_url($location, PHP_URL_QUERY), $parsed);
        return $parsed['auth_code'] ?? '';
    }

    // =========================================================
    // GET /oauth/google  リダイレクト
    // =========================================================

    /**
     * Google OAuth リダイレクトの正常系
     *
     * redirect_uri を指定してアクセスしたとき、
     * Google の認証画面（accounts.google.com）へ 302 リダイレクトされること。
     */
    public function test_Google認証リダイレクト_正常系(): void
    {
        // 1. 準備
        $query = http_build_query([
            'redirect_uri' => $this->redirectUri,
            'service_id'   => 'lunchmap',
        ]);

        // 2. 実行
        $response = $this->get('/oauth/google?' . $query);

        // 3. 検証 - Google 認証URLへリダイレクト
        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('accounts.google.com', $location);

        // ステートが DB に保存されていること
        $this->assertGreaterThan(
            0,
            DB::table('one_time_states')->where('provider', 'google')->count()
        );
    }

    /**
     * Google OAuth リダイレクト：redirect_uri 未指定
     *
     * 必須パラメータ redirect_uri を省略した場合に VALIDATION_ERROR が返ること。
     */
    public function test_Google認証リダイレクト_redirect_uri未指定(): void
    {
        // 2. 実行
        $response = $this->get('/oauth/google');

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'VALIDATION_ERROR'],
                 ]);
    }

    /**
     * Google OAuth リダイレクト：存在しないサービスID
     *
     * configs に登録されていない service_id を指定した場合に INVALID_SERVICE が返ること。
     */
    public function test_Google認証リダイレクト_存在しないサービスID(): void
    {
        // 2. 実行
        $response = $this->get('/oauth/google?' . http_build_query([
            'redirect_uri' => $this->redirectUri,
            'service_id'   => 'nonexistent-svc-9999',
        ]));

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'INVALID_SERVICE'],
                 ]);
    }

    // =========================================================
    // GET /oauth/google/callback  コールバック
    // =========================================================

    /**
     * Google OAuth コールバックの正常系
     *
     * 有効なステートを持つコールバックを受け付けたとき、
     * redirect_uri に auth_code を付与してリダイレクトされること。
     */
    public function test_Google認証コールバック_正常系(): void
    {
        // 1. 準備 - ステートをDBに挿入
        $state = 'google-callback-test-' . uniqid();
        $this->insertState($state, 'google');

        // 2. 実行
        $response = $this->get('/oauth/google/callback?' . http_build_query([
            'code'  => 'google-auth-code-dummy',
            'state' => $state,
        ]));

        // 3. 検証 - redirect_uri に auth_code が付与されたリダイレクト
        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString($this->redirectUri, $location);
        $this->assertStringContainsString('auth_code=', $location);

        // ステートが消費（削除）されていること
        $this->assertDatabaseMissing('one_time_states', ['state' => $state]);

        // one_time_codes にコードが発行されていること
        $authCode = $this->extractAuthCode($location);
        $this->assertNotEmpty($authCode);
        $this->assertDatabaseHas('one_time_codes', ['auth_code' => $authCode]);
    }

    /**
     * Google OAuth コールバック：state 不正（CSRF 攻撃の検出）
     *
     * DBに存在しない state を送信した場合に INVALID_STATE が返ること。
     */
    public function test_Google認証コールバック_state不正(): void
    {
        // 2. 実行 - 正規フローを経ずに偽の state を送信
        $response = $this->get('/oauth/google/callback?' . http_build_query([
            'code'  => 'fake-google-auth-code',
            'state' => 'completely-invalid-state-' . uniqid(),
        ]));

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'INVALID_STATE'],
                 ]);
    }

    /**
     * Google OAuth コールバック：期限切れ state
     *
     * 有効期限を超えた state を送信した場合に INVALID_STATE が返ること。
     */
    public function test_Google認証コールバック_state期限切れ(): void
    {
        // 1. 準備 - 期限切れのステートを直接挿入
        $state = 'google-expired-state-' . uniqid();
        DB::table('one_time_states')->insert([
            'state'        => $state,
            'provider'     => 'google',
            'redirect_uri' => $this->redirectUri,
            'service_id'   => 'lunchmap',
            'expires_at'   => now()->subMinutes(1)->toDateTimeString(),
            'created_at'   => now()->subMinutes(11)->toDateTimeString(),
        ]);

        // 2. 実行
        $response = $this->get('/oauth/google/callback?' . http_build_query([
            'code'  => 'dummy-code',
            'state' => $state,
        ]));

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'INVALID_STATE'],
                 ]);
    }

    // =========================================================
    // GET /oauth/github  リダイレクト
    // =========================================================

    /**
     * GitHub OAuth リダイレクトの正常系
     *
     * redirect_uri を指定してアクセスしたとき、
     * GitHub の認証画面（github.com）へ 302 リダイレクトされること。
     */
    public function test_GitHub認証リダイレクト_正常系(): void
    {
        // 2. 実行
        $response = $this->get('/oauth/github?' . http_build_query([
            'redirect_uri' => $this->redirectUri,
            'service_id'   => 'lunchmap',
        ]));

        // 3. 検証
        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('github.com', $location);
    }

    /**
     * GitHub OAuth リダイレクト：redirect_uri 未指定
     */
    public function test_GitHub認証リダイレクト_redirect_uri未指定(): void
    {
        // 2. 実行
        $response = $this->get('/oauth/github');

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'VALIDATION_ERROR'],
                 ]);
    }

    // =========================================================
    // GET /oauth/github/callback  コールバック
    // =========================================================

    /**
     * GitHub OAuth コールバックの正常系
     */
    public function test_GitHub認証コールバック_正常系(): void
    {
        // 1. 準備 - GitHub 用のステートを挿入
        $state = 'github-callback-test-' . uniqid();
        $this->insertState($state, 'github');

        // 2. 実行
        $response = $this->get('/oauth/github/callback?' . http_build_query([
            'code'  => 'github-auth-code-dummy',
            'state' => $state,
        ]));

        // 3. 検証
        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertStringContainsString($this->redirectUri, $location);
        $this->assertStringContainsString('auth_code=', $location);

        $authCode = $this->extractAuthCode($location);
        $this->assertNotEmpty($authCode);
        $this->assertDatabaseHas('one_time_codes', ['auth_code' => $authCode]);
    }

    /**
     * GitHub OAuth コールバック：state 不正
     */
    public function test_GitHub認証コールバック_state不正(): void
    {
        // 2. 実行
        $response = $this->get('/oauth/github/callback?' . http_build_query([
            'code'  => 'fake-github-auth-code',
            'state' => 'completely-invalid-state-' . uniqid(),
        ]));

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'INVALID_STATE'],
                 ]);
    }

    // =========================================================
    // POST /oauth/{identity}/login  一時コードによるログイン
    // =========================================================

    /**
     * OAuthログイン：正常系
     *
     * コールバックで発行された auth_code を送信したとき、
     * アクセストークン・リフレッシュトークン・ユーザー情報が返ること。
     */
    public function test_OAuthログイン_正常系(): void
    {
        // 1. 準備 - テスト専用の有効コードを挿入（シード済みコードを消費しない）
        $authCode = 'oauth-login-valid-' . uniqid();
        DB::table('one_time_codes')->insert([
            'central_id' => DatabaseSeeder::TEST_CENTRAL_ID,
            'auth_code'  => $authCode,
            'expires_at' => now()->addMinutes(5)->toDateTimeString(),
            'used_at'    => null,
            'created_at' => now()->toDateTimeString(),
        ]);

        // 2. 実行
        $response = $this->postJson('/oauth/google/login', [
            'auth_code' => $authCode,
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
                         'is_new_user',
                         'user' => [
                             'central_id',
                             'public_id',
                             'user_name',
                             'display_name',
                             'created_at',
                         ],
                     ],
                 ]);
    }

    /**
     * OAuthログイン：無効なコード
     *
     * 存在しないまたは使用済みの auth_code を送信した場合に INVALID_CODE が返ること。
     */
    public function test_OAuthログイン_無効なコード(): void
    {
        // 2. 実行
        $response = $this->postJson('/oauth/google/login', [
            'auth_code' => 'completely-invalid-code-xyz',
        ]);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'INVALID_CODE'],
                 ]);
    }

    /**
     * OAuthログイン：期限切れコード
     *
     * 有効期間（5分）を超えた auth_code を送信した場合に CODE_EXPIRED が返ること。
     */
    public function test_OAuthログイン_期限切れコード(): void
    {
        // 2. 実行 - シード済みの期限切れコードを使用
        $response = $this->postJson('/oauth/google/login', [
            'auth_code' => 'expired-auth-code-for-testing',
        ]);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'CODE_EXPIRED'],
                 ]);
    }

    /**
     * OAuthログイン：使用済みコードの再利用
     *
     * 一度使用した auth_code を再送信した場合に INVALID_CODE が返ること。
     * コードは使用後に即時無効化される。
     */
    public function test_OAuthログイン_使用済みコードの再利用(): void
    {
        // 1. 準備 - 1回目のログイン（成功）
        $authCode = 'one-time-auth-code-for-testing';
        $this->postJson('/oauth/google/login', ['auth_code' => $authCode]);

        // 2. 実行 - 同じコードで2回目を試みる
        $response = $this->postJson('/oauth/google/login', ['auth_code' => $authCode]);

        // 3. 検証 - 使用済みのため無効化されていること
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'INVALID_CODE'],
                 ]);
    }

    /**
     * OAuthログイン：auth_code 未指定
     */
    public function test_OAuthログイン_auth_code未指定(): void
    {
        // 2. 実行
        $response = $this->postJson('/oauth/google/login', []);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'VALIDATION_ERROR'],
                 ]);
    }

    // =========================================================
    // 完全フロー
    // =========================================================

    /**
     * Google OAuth 完全フロー
     *
     * リダイレクト（DB ステート保存）→ コールバック（auth_code 発行）→
     * ログイン（トークン発行）の End-to-End フローを確認する。
     */
    public function test_Google_完全フロー(): void
    {
        // 1. リダイレクト前に既存 Google ステートの ID を記録
        $existingIds = DB::table('one_time_states')
            ->where('provider', 'google')
            ->pluck('id')
            ->toArray();

        $this->get('/oauth/google?' . http_build_query([
            'redirect_uri' => $this->redirectUri,
            'service_id'   => 'lunchmap',
        ]))->assertStatus(302);

        // 2. 新規挿入されたステートを ID で特定
        $stateRecord = DB::table('one_time_states')
            ->where('provider', 'google')
            ->whereNotIn('id', $existingIds)
            ->first();
        $this->assertNotNull($stateRecord, 'リダイレクト後にステートが DB に保存されていること');

        // 3. コールバック - auth_code が発行されること
        $callbackResponse = $this->get('/oauth/google/callback?' . http_build_query([
            'code'  => 'google-dummy-code',
            'state' => $stateRecord->state,
        ]));
        $callbackResponse->assertStatus(302);
        $authCode = $this->extractAuthCode($callbackResponse->headers->get('Location'));
        $this->assertNotEmpty($authCode, 'auth_code がリダイレクト先URLに含まれること');

        // 4. ログイン - auth_code をトークンに交換
        $this->postJson('/oauth/google/login', ['auth_code' => $authCode])
             ->assertStatus(200)
             ->assertJson(['success' => true])
             ->assertJsonStructure([
                 'data' => ['access_token', 'refresh_token', 'expires_in', 'user'],
             ]);
    }

    /**
     * GitHub OAuth 完全フロー
     *
     * Google と同様のフローを GitHub プロバイダーで確認する。
     */
    public function test_GitHub_完全フロー(): void
    {
        // 1. リダイレクト前に既存 GitHub ステートの ID を記録
        $existingIds = DB::table('one_time_states')
            ->where('provider', 'github')
            ->pluck('id')
            ->toArray();

        $this->get('/oauth/github?' . http_build_query([
            'redirect_uri' => $this->redirectUri,
            'service_id'   => 'lunchmap',
        ]))->assertStatus(302);

        // 2. 新規挿入されたステートを ID で特定
        $stateRecord = DB::table('one_time_states')
            ->where('provider', 'github')
            ->whereNotIn('id', $existingIds)
            ->first();
        $this->assertNotNull($stateRecord);

        // 3. コールバック
        $callbackResponse = $this->get('/oauth/github/callback?' . http_build_query([
            'code'  => 'github-dummy-code',
            'state' => $stateRecord->state,
        ]));
        $callbackResponse->assertStatus(302);
        $authCode = $this->extractAuthCode($callbackResponse->headers->get('Location'));
        $this->assertNotEmpty($authCode);

        // 4. ログイン
        $this->postJson('/oauth/github/login', ['auth_code' => $authCode])
             ->assertStatus(200)
             ->assertJson(['success' => true]);
    }
}
