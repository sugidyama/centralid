<?php

use App\Http\Controllers\Auth\EmailAuthController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\TokenAuthController;
use Illuminate\Support\Facades\Route;

//------------------------------------------
// 認証・認可
//------------------------------------------
// メールOTP認証（ワンタイムパスワード）
Route::prefix('auth/mail')->group(function () {
    Route::post('/',      [EmailAuthController::class, 'issue']);  // OTPの発行・送信
    Route::put('/',       [EmailAuthController::class, 'resend']); // OTPの再送信
    Route::post('/login', [EmailAuthController::class, 'login']);  // OTP検証およびログイン
});

// 外部SNS認証（OAuth）
Route::prefix('oauth/{identity}')->where(['identity' => 'google|github'])->group(function () {
    Route::get('/',          [OAuthController::class, 'redirect']); // 各サービスの認証画面へリダイレクト
    Route::get('/callback',  [OAuthController::class, 'callback']); // 認証後のコールバック受付
    Route::post('/login',    [OAuthController::class, 'login']);    // 外部認証情報によるログイン
});

// セッション・トークン管理
Route::prefix('auth/token')->group(function () {
    Route::get('/',    [TokenAuthController::class, 'me']);      // トークン検証およびログインユーザー情報の取得
    Route::patch('/',  [TokenAuthController::class, 'refresh']); // アクセストークンの有効期限延長（更新）
    Route::delete('/', [TokenAuthController::class, 'logout']);  // ログアウト（トークンの無効化・破棄）
});