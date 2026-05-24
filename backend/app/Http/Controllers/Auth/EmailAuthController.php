<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ConfigService;
use App\Services\OtpService;
use App\Services\TokenService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * メールOTP認証コントローラー
 */
class EmailAuthController extends Controller
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly OtpService    $otpService,
        private readonly UserService   $userService,
        private readonly TokenService  $tokenService,
        private readonly ConfigService $configService,
    ) {}

    /**
     * OTP発行・メール送信
     */
    public function issue(Request $request): Response|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'      => 'required|email',
            'service_id' => 'sometimes|string',
        ]);

        if ($validator->fails())
        {
            return $this->validationError();
        }

        $serviceId = $request->input('service_id');
        if ($serviceId !== null && !$this->configService->isValidService($serviceId))
        {
            return $this->invalidService();
        }

        $this->otpService->issue($request->input('email'));

        return response()->noContent();
    }

    /**
     * OTP再送信
     */
    public function resend(Request $request): Response|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'      => 'required|email',
            'service_id' => 'sometimes|string',
        ]);

        if ($validator->fails())
        {
            return $this->validationError();
        }

        $serviceId = $request->input('service_id');
        if ($serviceId !== null && !$this->configService->isValidService($serviceId))
        {
            return $this->invalidService();
        }

        if (!$this->otpService->canResend($request->input('email')))
        {
            return $this->error('RESEND_TOO_SOON');
        }

        $this->otpService->issue($request->input('email'));

        return response()->noContent();
    }

    /**
     * OTP検証・ログイン
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code'  => 'required|digits:6',
        ]);

        if ($validator->fails())
        {
            return $this->validationError();
        }

        $result = $this->otpService->verify(
            $request->input('email'),
            $request->input('code'),
        );

        if (isset($result['error']))
        {
            return $this->error($result['error']);
        }

        $userResult    = $this->userService->findOrCreateByEmail($request->input('email'));
        $tokenResponse = $this->tokenService->issue($userResult['user']->central_id, $userResult['isNew']);

        return $this->success($tokenResponse->toArray());
    }
}
