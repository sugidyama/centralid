<?php

namespace App\Services;

use App\DataObjects\TokenResponseData;
use App\DataObjects\UserData;
use App\Models\TokenModel;
use App\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * アクセストークン・リフレッシュトークン管理サービス
 */
class TokenService
{
    /** アクセストークン有効期限（分） */
    private const ACCESS_EXPIRES_MINUTES = 60;

    /** リフレッシュトークン有効期限（日） */
    private const REFRESH_EXPIRES_DAYS = 30;

    /**
     * トークンペアを新規発行
     */
    public function issue(string $centralId, bool $isNewUser): TokenResponseData
    {
        [$accessToken, $refreshToken] = $this->generateTokenPair();
        $expiresIn = self::ACCESS_EXPIRES_MINUTES * 60;

        try
        {
            DB::transaction(function () use ($centralId, $accessToken, $refreshToken): void
            {
                TokenModel::insert([
                    'central_id'               => $centralId,
                    'access_token'             => $accessToken,
                    'refresh_token'            => $refreshToken,
                    'access_token_expires_at'  => now()->addMinutes(self::ACCESS_EXPIRES_MINUTES)->toDateTimeString(),
                    'refresh_token_expires_at' => now()->addDays(self::REFRESH_EXPIRES_DAYS)->toDateTimeString(),
                    'revoked_at'               => null,
                    'created_at'               => now()->toDateTimeString(),
                ]);
            });
        }
        catch (\Throwable $e)
        {
            Log::channel('error')->error('トークン発行失敗', ['central_id' => $centralId, 'error' => $e->getMessage()]);
            throw $e;
        }

        $user     = UserModel::findByCentralId($centralId);
        $userData = new UserData(
            centralId:   $user->central_id,
            publicId:    (int) $user->public_id,
            userName:    $user->user_name,
            displayName: $user->display_name,
            createdAt:   $user->created_at,
        );

        return new TokenResponseData($accessToken, $refreshToken, $expiresIn, $isNewUser, $userData);
    }

    /**
     * リフレッシュトークンによるトークンローテーション
     *
     * @return array{accessToken: string, refreshToken: string}
     */
    public function rotate(object $tokenRecord): array
    {
        [$newAccess, $newRefresh] = $this->generateTokenPair();

        try
        {
            DB::transaction(function () use ($tokenRecord, $newAccess, $newRefresh): void
            {
                TokenModel::revoke($tokenRecord->id);
                TokenModel::insert([
                    'central_id'               => $tokenRecord->central_id,
                    'access_token'             => $newAccess,
                    'refresh_token'            => $newRefresh,
                    'access_token_expires_at'  => now()->addMinutes(self::ACCESS_EXPIRES_MINUTES)->toDateTimeString(),
                    'refresh_token_expires_at' => now()->addDays(self::REFRESH_EXPIRES_DAYS)->toDateTimeString(),
                    'revoked_at'               => null,
                    'created_at'               => now()->toDateTimeString(),
                ]);
            });
        }
        catch (\Throwable $e)
        {
            Log::channel('error')->error('トークンローテーション失敗', ['id' => $tokenRecord->id, 'error' => $e->getMessage()]);
            throw $e;
        }

        return ['accessToken' => $newAccess, 'refreshToken' => $newRefresh];
    }

    /**
     * トークンペア生成
     *
     * @return array{string, string}
     */
    private function generateTokenPair(): array
    {
        return [Str::random(64), Str::random(64)];
    }
}
