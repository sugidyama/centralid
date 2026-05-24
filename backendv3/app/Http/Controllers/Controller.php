<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * 成功レスポンス生成
     */
    protected function success(array $data): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * エラーレスポンス生成
     */
    protected function error(string $code, int $status = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => false, 'error' => ['code' => $code]], $status);
    }

    /**
     * バリデーションエラーレスポンス
     */
    protected function validationError(): \Illuminate\Http\JsonResponse
    {
        return $this->error('VALIDATION_ERROR');
    }

    /**
     * 認証エラーレスポンス
     */
    protected function unauthorized(): \Illuminate\Http\JsonResponse
    {
        return $this->error('UNAUTHORIZED', 401);
    }

    /**
     * 無効サービスエラーレスポンス
     */
    protected function invalidService(): \Illuminate\Http\JsonResponse
    {
        return $this->error('INVALID_SERVICE');
    }

    /**
     * Authorization ヘッダーから Bearer トークン抽出
     */
    protected function extractBearerToken(\Illuminate\Http\Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer '))
        {
            return substr($header, 7);
        }

        return null;
    }
}
