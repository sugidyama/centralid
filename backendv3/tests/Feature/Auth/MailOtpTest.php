<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * メールOTP認証エンドポイント統合テスト
 *
 * テスト環境では OTP コードは常に '123456' を発行する前提。
 *
 * 対象エンドポイント：
 * POST /auth/mail        OTP発行・送信          → 204
 * PUT  /auth/mail        OTP再送信              → 204
 * POST /auth/mail/login  OTP検証・ログイン       → 200 TokenResponse
 */
class MailOtpTest extends TestCase
{
    /**
     * テストごとにユニークなメールアドレスを生成
     */
    private function uniqueEmail(string $tag): string
    {
        return "mail-otp-{$tag}-" . substr(uniqid(), -8) . '@test.local';
    }

    // =========================================================
    // POST /auth/mail  OTP発行・送信
    // =========================================================

    /**
     * OTP発行：正常系
     *
     * 有効なメールアドレスを送信したとき、204 が返り
     * DBにコードが発行されること。
     */
    public function test_OTP発行_正常系(): void
    {
        // 1. 準備
        $email = $this->uniqueEmail('send');

        // 2. 実行
        $response = $this->postJson('/auth/mail', [
            'email'      => $email,
            'service_id' => 'lunchmap',
        ]);

        // 3. 検証
        $response->assertStatus(204);

        $this->assertDatabaseHas('one_time_passwords', [
            'email'   => $email,
            'code'    => '123456',
            'used_at' => null,
        ]);
    }

    /**
     * OTP発行：サービスID省略でも成功すること
     */
    public function test_OTP発行_サービスID省略(): void
    {
        // 1. 準備
        $email = $this->uniqueEmail('nosvc');

        // 2. 実行
        $response = $this->postJson('/auth/mail', ['email' => $email]);

        // 3. 検証
        $response->assertStatus(204);
    }

    /**
     * OTP発行：メールアドレス形式不正
     *
     * email が RFC 準拠でない場合に VALIDATION_ERROR が返ること。
     */
    public function test_OTP発行_メールアドレス形式不正(): void
    {
        // 2. 実行
        $response = $this->postJson('/auth/mail', ['email' => 'not-a-valid-email']);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'VALIDATION_ERROR'],
                 ]);
    }

    /**
     * OTP発行：メールアドレス未指定
     *
     * email が省略された場合に VALIDATION_ERROR が返ること。
     */
    public function test_OTP発行_メールアドレス未指定(): void
    {
        // 2. 実行
        $response = $this->postJson('/auth/mail', []);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'VALIDATION_ERROR'],
                 ]);
    }

    /**
     * OTP発行：存在しないサービスID
     *
     * configs に登録されていない service_id を指定した場合に INVALID_SERVICE が返ること。
     */
    public function test_OTP発行_存在しないサービスID(): void
    {
        // 2. 実行
        $response = $this->postJson('/auth/mail', [
            'email'      => 'valid@example.com',
            'service_id' => 'nonexistent-svc-9999',
        ]);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'INVALID_SERVICE'],
                 ]);
    }

    /**
     * OTP発行：同一メールに2回送信すると既存コードを無効化して新規発行すること
     */
    public function test_OTP発行_既存コードを無効化して新規発行(): void
    {
        // 1. 準備 - 1回目の発行
        $email = $this->uniqueEmail('reinit');
        $this->postJson('/auth/mail', ['email' => $email]);

        $firstOtp = DB::table('one_time_passwords')
            ->where('email', $email)
            ->whereNull('used_at')
            ->first();
        $firstId = $firstOtp->id;

        // 2. 実行 - 2回目の発行
        $this->postJson('/auth/mail', ['email' => $email]);

        // 3. 検証 - 1回目のOTPが無効化（used_at設定）されていること
        $oldOtp = DB::table('one_time_passwords')->where('id', $firstId)->first();
        $this->assertNotNull($oldOtp->used_at, '旧OTPが無効化されていること');

        // 新しいOTPが1件だけ存在すること
        $newCount = DB::table('one_time_passwords')
            ->where('email', $email)
            ->whereNull('used_at')
            ->count();
        $this->assertSame(1, $newCount, '有効なOTPが1件だけ存在すること');
    }

    // =========================================================
    // PUT /auth/mail  OTP再送信
    // =========================================================

    /**
     * OTP再送信：前回OTPから60秒超過後は成功すること
     *
     * 直前のOTPが61秒前に発行されている場合、再送信が成功すること。
     */
    public function test_OTP再送信_60秒超過後は成功(): void
    {
        // 1. 準備 - 61秒前に発行済みのOTPを直接挿入
        $email = $this->uniqueEmail('resend-ok');
        DB::table('one_time_passwords')->insert([
            'email'      => $email,
            'code'       => '111111',
            'attempts'   => 0,
            'expires_at' => now()->addMinutes(10)->toDateTimeString(),
            'used_at'    => null,
            'created_at' => now()->subSeconds(61)->toDateTimeString(),
        ]);

        // 2. 実行
        $response = $this->putJson('/auth/mail', ['email' => $email]);

        // 3. 検証
        $response->assertStatus(204);
    }

    /**
     * OTP再送信：前回OTPから60秒未満の場合は拒否されること
     *
     * 30秒前に発行済みの場合に RESEND_TOO_SOON が返ること。
     */
    public function test_OTP再送信_60秒以内は拒否(): void
    {
        // 1. 準備 - 30秒前に発行済みのOTPを直接挿入
        $email = $this->uniqueEmail('resend-soon');
        DB::table('one_time_passwords')->insert([
            'email'      => $email,
            'code'       => '222222',
            'attempts'   => 0,
            'expires_at' => now()->addMinutes(10)->toDateTimeString(),
            'used_at'    => null,
            'created_at' => now()->subSeconds(30)->toDateTimeString(),
        ]);

        // 2. 実行
        $response = $this->putJson('/auth/mail', ['email' => $email]);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'RESEND_TOO_SOON'],
                 ]);
    }

    /**
     * OTP再送信：存在しないサービスID
     */
    public function test_OTP再送信_存在しないサービスID(): void
    {
        // 2. 実行
        $response = $this->putJson('/auth/mail', [
            'email'      => 'valid@example.com',
            'service_id' => 'nonexistent-svc-9999',
        ]);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'INVALID_SERVICE'],
                 ]);
    }

    /**
     * OTP再送信：メールアドレス形式不正
     */
    public function test_OTP再送信_メールアドレス形式不正(): void
    {
        // 2. 実行
        $response = $this->putJson('/auth/mail', ['email' => 'invalid-format']);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'VALIDATION_ERROR'],
                 ]);
    }

    // =========================================================
    // POST /auth/mail/login  OTP検証・ログイン
    // =========================================================

    /**
     * OTP検証：新規ユーザー登録フロー
     *
     * 未登録のメールアドレスでOTPを検証すると、
     * アカウントが作成され is_new_user=true でトークンが発行されること。
     */
    public function test_OTP検証_新規ユーザー登録(): void
    {
        // 1. 準備 - OTP発行
        $email = $this->uniqueEmail('new-user');
        $this->postJson('/auth/mail', [
            'email'      => $email,
            'service_id' => 'lunchmap',
        ]);

        // 2. 実行
        $response = $this->postJson('/auth/mail/login', [
            'email'      => $email,
            'code'       => '123456',
            'service_id' => 'lunchmap',
        ]);

        // 3. 検証
        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'data' => ['is_new_user' => true]])
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

        // user_identities にメール認証手段が登録されていること
        $this->assertDatabaseHas('user_identities', [
            'identity_type' => 'email',
            'identity'      => $email,
        ]);

        // register イベントが記録されていること
        $identity = DB::table('user_identities')
            ->where('identity_type', 'email')
            ->where('identity', $email)
            ->first();
        $this->assertDatabaseHas('user_events', [
            'central_id' => $identity->central_id,
            'event_type' => 'register',
        ]);
    }

    /**
     * OTP検証：既存ユーザーログインフロー
     *
     * 登録済みのメールアドレスでOTPを検証すると、
     * is_new_user=false でトークンが発行されること。
     */
    public function test_OTP検証_既存ユーザーログイン(): void
    {
        // 1. 準備 - 1回目：新規ユーザーを作成
        $email = $this->uniqueEmail('existing');
        $this->postJson('/auth/mail', ['email' => $email]);
        $this->postJson('/auth/mail/login', ['email' => $email, 'code' => '123456']);

        // 2回目：新しいOTPを発行
        $this->postJson('/auth/mail', ['email' => $email]);

        // 2. 実行 - 同じメールアドレスで再ログイン
        $response = $this->postJson('/auth/mail/login', [
            'email' => $email,
            'code'  => '123456',
        ]);

        // 3. 検証
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data'    => ['is_new_user' => false],
                 ]);
    }

    /**
     * OTP検証：コード不一致
     *
     * 正しくないコードを送信した場合に INVALID_CODE が返ること。
     * 検証試行回数（attempts）がインクリメントされること。
     */
    public function test_OTP検証_コード不一致(): void
    {
        // 1. 準備 - OTP発行（コードは '123456'）
        $email = $this->uniqueEmail('wrong-code');
        $this->postJson('/auth/mail', ['email' => $email]);

        // 2. 実行 - 誤ったコードで検証
        $response = $this->postJson('/auth/mail/login', [
            'email' => $email,
            'code'  => '999999',
        ]);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'INVALID_CODE'],
                 ]);

        // attempts が 1 にインクリメントされていること
        $otp = DB::table('one_time_passwords')
            ->where('email', $email)
            ->whereNull('used_at')
            ->first();
        $this->assertSame(1, (int) $otp->attempts);
    }

    /**
     * OTP検証：OTP未発行状態では INVALID_CODE が返ること
     */
    public function test_OTP検証_OTP未発行(): void
    {
        // 1. 準備 - OTP発行なし（未登録のメールアドレスを使用）
        $email = $this->uniqueEmail('no-otp');

        // 2. 実行
        $response = $this->postJson('/auth/mail/login', [
            'email' => $email,
            'code'  => '123456',
        ]);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'INVALID_CODE'],
                 ]);
    }

    /**
     * OTP検証：コード期限切れ
     *
     * 有効期限を超えたコードを送信した場合に CODE_EXPIRED が返ること。
     */
    public function test_OTP検証_コード期限切れ(): void
    {
        // 1. 準備 - 期限切れOTPを直接DBに挿入
        $email = $this->uniqueEmail('expired');
        DB::table('one_time_passwords')->insert([
            'email'      => $email,
            'code'       => '123456',
            'attempts'   => 0,
            'expires_at' => now()->subMinutes(1)->toDateTimeString(),
            'used_at'    => null,
            'created_at' => now()->subMinutes(11)->toDateTimeString(),
        ]);

        // 2. 実行
        $response = $this->postJson('/auth/mail/login', [
            'email' => $email,
            'code'  => '123456',
        ]);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'CODE_EXPIRED'],
                 ]);
    }

    /**
     * OTP検証：試行回数超過（5回以上でロック）
     *
     * 誤ったコードで5回試行した後、次の試行で MAX_ATTEMPTS_EXCEEDED が返ること。
     */
    public function test_OTP検証_試行回数超過(): void
    {
        // 1. 準備 - OTP発行
        $email = $this->uniqueEmail('lockout');
        $this->postJson('/auth/mail', ['email' => $email]);

        // 誤ったコードで5回試行してロック状態を作る
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/mail/login', ['email' => $email, 'code' => '000000']);
        }

        // 2. 実行 - 6回目の試行
        $response = $this->postJson('/auth/mail/login', [
            'email' => $email,
            'code'  => '000000',
        ]);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'MAX_ATTEMPTS_EXCEEDED'],
                 ]);
    }

    /**
     * OTP検証：コードは一度のみ有効（使用後は無効化）
     *
     * 検証成功後に同じコードを再送信した場合に INVALID_CODE が返ること。
     */
    public function test_OTP検証_使用済みコード再利用不可(): void
    {
        // 1. 準備 - OTP発行・1回目の検証（成功）
        $email = $this->uniqueEmail('oneuse');
        $this->postJson('/auth/mail', ['email' => $email]);
        $this->postJson('/auth/mail/login', ['email' => $email, 'code' => '123456']);

        // 2. 実行 - 新規OTP発行なしで2回目の検証を試みる
        $response = $this->postJson('/auth/mail/login', [
            'email' => $email,
            'code'  => '123456',
        ]);

        // 3. 検証 - 使用済みのため有効なOTPが存在しないこと
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'INVALID_CODE'],
                 ]);
    }

    /**
     * OTP検証：バリデーションエラー（英字コード）
     *
     * 6桁数字以外の値を code に指定した場合に VALIDATION_ERROR が返ること。
     */
    public function test_OTP検証_バリデーションエラー_英字コード(): void
    {
        // 2. 実行
        $response = $this->postJson('/auth/mail/login', [
            'email' => 'valid@example.com',
            'code'  => 'abcdef',
        ]);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'VALIDATION_ERROR'],
                 ]);
    }

    /**
     * OTP検証：バリデーションエラー（5桁・桁数不足）
     */
    public function test_OTP検証_バリデーションエラー_5桁コード(): void
    {
        // 2. 実行
        $response = $this->postJson('/auth/mail/login', [
            'email' => 'valid@example.com',
            'code'  => '12345',
        ]);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'VALIDATION_ERROR'],
                 ]);
    }

    /**
     * OTP検証：バリデーションエラー（email未指定）
     */
    public function test_OTP検証_バリデーションエラー_メール未指定(): void
    {
        // 2. 実行
        $response = $this->postJson('/auth/mail/login', ['code' => '123456']);

        // 3. 検証
        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'error'   => ['code' => 'VALIDATION_ERROR'],
                 ]);
    }

    /**
     * 完全フロー：OTP送信→再送信→検証の一連の流れ
     *
     * 実際のユーザー操作（送信→60秒超過→再送信→新コードで検証）を再現する。
     */
    public function test_完全フロー_送信から再送信を経て検証(): void
    {
        // 1. 準備 - 初回OTP送信
        $email = $this->uniqueEmail('fullflow');
        $this->postJson('/auth/mail', ['email' => $email])->assertStatus(204);

        // 2. 61秒超過をシミュレート: OTPの created_at を61秒前に更新
        DB::table('one_time_passwords')
            ->where('email', $email)
            ->update(['created_at' => now()->subSeconds(61)->toDateTimeString()]);

        // 3. 再送信（成功）
        $this->putJson('/auth/mail', ['email' => $email])->assertStatus(204);

        // 4. 最新コードで検証
        $verifyRes = $this->postJson('/auth/mail/login', [
            'email' => $email,
            'code'  => '123456',
        ]);

        // 5. 最終検証
        $verifyRes->assertStatus(200)
                  ->assertJson(['success' => true])
                  ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'is_new_user']]);
    }
}
