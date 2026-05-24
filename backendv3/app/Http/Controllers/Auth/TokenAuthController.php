<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TokenModel;
use App\Models\UserEventModel;
use App\Models\UserModel;
use App\Services\TokenService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * アクセストークン管理コントローラー
 */
class TokenAuthController extends Controller
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly TokenService $tokenService,
    ) {}

    /**
     * トークン検証・ユーザー情報取得
     */
    public function me(Request $request): JsonResponse
    {
        $token = $this->extractBearerToken($request);
        if (!$token)
        {
            return $this->unauthorized();
        }

        $tokenRecord = TokenModel::findByAccessToken($token);
        if (!$tokenRecord)
        {
            return $this->unauthorized();
        }

        if ($tokenRecord->revoked_at !== null)
        {
            return $this->unauthorized();
        }

        if (now()->isAfter(Carbon::parse($tokenRecord->access_token_expires_at)))
        {
            return $this->error('TOKEN_EXPIRED', 401);
        }

        $user = UserModel::findByCentralId($tokenRecord->central_id);

        return $this->success([
            'central_id'   => $user->central_id,
            'public_id'    => (int) $user->public_id,
            'user_name'    => $user->user_name,
            'display_name' => $user->display_name,
            'created_at'   => $user->created_at,
        ]);
    }

    /**
     * アクセストークン更新（リフレッシュ）
     */
    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');
        if (!$refreshToken)
        {
            return $this->unauthorized();
        }

        $tokenRecord = TokenModel::findByRefreshToken($refreshToken);
        if (!$tokenRecord)
        {
            return $this->unauthorized();
        }

        if ($tokenRecord->revoked_at !== null)
        {
            return $this->unauthorized();
        }

        if (now()->isAfter(Carbon::parse($tokenRecord->refresh_token_expires_at)))
        {
            return $this->error('TOKEN_EXPIRED', 401);
        }

        $tokens = $this->tokenService->rotate($tokenRecord);

        return $this->success([
            'access_token'  => $tokens['accessToken'],
            'refresh_token' => $tokens['refreshToken'],
            'expires_in'    => 15 * 60,
        ]);
    }

    /**
     * ログアウト（トークン失効）
     */
    public function logout(Request $request): Response|JsonResponse
    {
        $token = $this->extractBearerToken($request);
        if (!$token)
        {
            return $this->unauthorized();
        }

        $tokenRecord = TokenModel::findByAccessToken($token);
        if (!$tokenRecord)
        {
            return $this->unauthorized();
        }

        try
        {
            DB::transaction(function () use ($tokenRecord): void
            {
                TokenModel::revoke($tokenRecord->id);
                UserEventModel::log($tokenRecord->central_id, 'logout');
            });
        }
        catch (\Throwable $e)
        {
            Log::channel('error')->error('ログアウト失敗', ['error' => $e->getMessage()]);
            throw $e;
        }

        return response()->noContent();
    }
}
