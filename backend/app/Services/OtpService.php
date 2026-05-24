<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\OtpModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * OTP（ワンタイムパスワード）管理サービス
 */
class OtpService
{
    /** OTP有効期限（分） */
    private const EXPIRES_MINUTES = 10;

    /** 再送信インターバル（秒） */
    private const RESEND_INTERVAL_SECONDS = 60;

    /** 最大試行回数 */
    private const MAX_ATTEMPTS = 5;

    /**
     * OTPコード生成（テスト環境では常に '123456'）
     */
    public function generateCode(): string
    {
        if (app()->environment('testing'))
        {
            return '123456';
        }

        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * OTP発行（既存を無効化してから新規発行）
     */
    public function issue(string $email): void
    {
        $code = $this->generateCode();

        try
        {
            DB::transaction(function () use ($email, $code): void
            {
                OtpModel::invalidateAllUnused($email);
                OtpModel::insert([
                    'email'      => $email,
                    'code'       => $code,
                    'attempts'   => 0,
                    'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES)->toDateTimeString(),
                    'used_at'    => null,
                    'created_at' => now()->toDateTimeString(),
                ]);
            });
        }
        catch (\Throwable $e)
        {
            Log::channel('error')->error('OTP発行失敗', ['email' => $email, 'error' => $e->getMessage()]);
            throw $e;
        }

        Log::channel('access')->info('OTP発行', ['email' => $email]);

        Mail::to($email)->send(new OtpMail($code));
    }

    /**
     * 再送信可否確認（true = 再送信可）
     */
    public function canResend(string $email): bool
    {
        $otp = OtpModel::findLatestUnused($email);
        if (!$otp)
        {
            return true;
        }

        $elapsed = Carbon::parse($otp->created_at)->diffInSeconds(now());

        return $elapsed >= self::RESEND_INTERVAL_SECONDS;
    }

    /**
     * OTP検証
     *
     * @return array{success: true}|array{error: string}
     */
    public function verify(string $email, string $code): array
    {
        $otp = OtpModel::findLatestUnused($email);

        if (!$otp)
        {
            return ['error' => 'INVALID_CODE'];
        }

        if (now()->isAfter(Carbon::parse($otp->expires_at)))
        {
            return ['error' => 'CODE_EXPIRED'];
        }

        if ((int) $otp->attempts >= self::MAX_ATTEMPTS)
        {
            return ['error' => 'MAX_ATTEMPTS_EXCEEDED'];
        }

        if ($otp->code !== $code)
        {
            try
            {
                DB::transaction(function () use ($otp): void
                {
                    OtpModel::incrementAttempts($otp->id);
                });
            }
            catch (\Throwable $e)
            {
                Log::channel('error')->error('OTP試行回数更新失敗', ['id' => $otp->id, 'error' => $e->getMessage()]);
            }

            return ['error' => 'INVALID_CODE'];
        }

        try
        {
            DB::transaction(function () use ($otp): void
            {
                OtpModel::markUsed($otp->id);
            });
        }
        catch (\Throwable $e)
        {
            Log::channel('error')->error('OTP使用済みマーク失敗', ['id' => $otp->id, 'error' => $e->getMessage()]);
            throw $e;
        }

        return ['success' => true];
    }
}
