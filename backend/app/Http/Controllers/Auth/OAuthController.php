<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OneTimeCodeModel;
use App\Models\OneTimeStateModel;
use App\Services\ConfigService;
use App\Services\TokenService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * OAuth認証コントローラー
 */
class OAuthController extends Controller
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly UserService   $userService,
        private readonly TokenService  $tokenService,
        private readonly ConfigService $configService,
    ) {}

    /**
     * OAuth認証画面へリダイレクト
     */
    public function redirect(string $identity, Request $request): RedirectResponse|JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'redirect_uri' => 'required|url',
            'service_id'   => 'sometimes|string',
        ]);

        if ($validator->fails())
        {
            return $this->validationError();
        }

        $serviceId = $request->query('service_id');
        if ($serviceId !== null && !$this->configService->isValidService($serviceId))
        {
            return $this->invalidService();
        }

        $state = Str::random(64);

        try
        {
            DB::transaction(function () use ($state, $identity, $request, $serviceId): void
            {
                OneTimeStateModel::insert([
                    'state'        => $state,
                    'provider'     => $identity,
                    'redirect_uri' => $request->query('redirect_uri'),
                    'service_id'   => $serviceId,
                    'expires_at'   => now()->addMinutes(10)->toDateTimeString(),
                    'created_at'   => now()->toDateTimeString(),
                ]);
            });
        }
        catch (\Throwable $e)
        {
            Log::channel('error')->error('OAuthステート保存失敗', ['error' => $e->getMessage()]);
            throw $e;
        }

        return Socialite::driver($identity)->stateless()->with(['state' => $state])->redirect();
    }

    /**
     * OAuthコールバック受付
     */
    public function callback(string $identity, Request $request): RedirectResponse|JsonResponse
    {
        $state = $request->get('state', '');

        $stateRecord = OneTimeStateModel::findValid($state, $identity);
        if (!$stateRecord)
        {
            return $this->error('INVALID_STATE');
        }

        try
        {
            $oauthUser = Socialite::driver($identity)->stateless()->user();
        }
        catch (\Laravel\Socialite\Two\InvalidStateException $e)
        {
            return $this->error('INVALID_STATE');
        }
        catch (\Throwable $e)
        {
            Log::channel('error')->error('OAuthユーザー取得失敗', ['error' => $e->getMessage()]);

            return $this->error('INVALID_STATE');
        }

        $redirectUri = $stateRecord->redirect_uri;
        $authCode    = Str::random(64);

        try
        {
            DB::transaction(function () use ($state, $identity, $oauthUser, $authCode): void
            {
                OneTimeStateModel::delete($state, $identity);

                $userResult = $this->userService->findOrCreateByOAuth(
                    $identity,
                    (string) $oauthUser->getId(),
                    (string) $oauthUser->getEmail(),
                );

                OneTimeCodeModel::insert([
                    'central_id' => $userResult['user']->central_id,
                    'auth_code'  => $authCode,
                    'expires_at' => now()->addMinutes(5)->toDateTimeString(),
                    'used_at'    => null,
                    'created_at' => now()->toDateTimeString(),
                ]);
            });
        }
        catch (\Throwable $e)
        {
            Log::channel('error')->error('OAuthコールバック処理失敗', ['error' => $e->getMessage()]);
            throw $e;
        }

        return redirect($redirectUri . '?' . http_build_query(['auth_code' => $authCode]));
    }

    /**
     * 一時コードによるトークン発行
     */
    public function login(string $identity, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'auth_code' => 'required|string',
        ]);

        if ($validator->fails())
        {
            return $this->validationError();
        }

        $codeRecord = OneTimeCodeModel::findUnused($request->input('auth_code'));

        if (!$codeRecord)
        {
            return $this->error('INVALID_CODE');
        }

        if (now()->isAfter(Carbon::parse($codeRecord->expires_at)))
        {
            return $this->error('CODE_EXPIRED');
        }

        try
        {
            DB::transaction(function () use ($codeRecord): void
            {
                OneTimeCodeModel::markUsed($codeRecord->id);
            });
        }
        catch (\Throwable $e)
        {
            Log::channel('error')->error('一時コード使用済みマーク失敗', ['error' => $e->getMessage()]);
            throw $e;
        }

        $tokenResponse = $this->tokenService->issue($codeRecord->central_id, false);

        return $this->success($tokenResponse->toArray());
    }
}
