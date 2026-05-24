<?php

namespace App\Services;

use App\Models\UserEventModel;
use App\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ユーザー作成・取得サービス
 */
class UserService
{
    /**
     * メールアドレスでユーザーを検索または新規作成
     *
     * @return array{user: object, isNew: bool}
     */
    public function findOrCreateByEmail(string $email): array
    {
        $user = UserModel::findByIdentity('email', $email);
        if ($user)
        {
            return ['user' => $user, 'isNew' => false];
        }

        $centralId = $this->generateCentralId();
        $publicId  = UserModel::nextPublicId();
        $now       = now()->toDateTimeString();

        try
        {
            DB::transaction(function () use ($centralId, $publicId, $email, $now): void
            {
                UserModel::insert([
                    'central_id'              => $centralId,
                    'public_id'               => $publicId,
                    'user_name'               => null,
                    'display_name'            => null,
                    'user_name_updated_at'    => $now,
                    'display_name_updated_at' => $now,
                    'created_at'              => $now,
                ]);
                UserModel::insertProfile([
                    'central_id' => $centralId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                UserModel::insertIdentity([
                    'central_id'    => $centralId,
                    'identity_type' => 'email',
                    'identity'      => $email,
                    'created_at'    => $now,
                ]);
                UserEventModel::log($centralId, 'register', ['identity_type' => 'email']);
            });
        }
        catch (\Throwable $e)
        {
            Log::channel('error')->error('ユーザー作成失敗', ['email' => $email, 'error' => $e->getMessage()]);
            throw $e;
        }

        Log::channel('access')->info('ユーザー新規作成', ['central_id' => $centralId]);

        return ['user' => UserModel::findByCentralId($centralId), 'isNew' => true];
    }

    /**
     * OAuth情報でユーザーを検索または新規作成
     *
     * @return array{user: object, isNew: bool}
     */
    public function findOrCreateByOAuth(string $provider, string $oauthId, string $email): array
    {
        $identityType = $provider;

        $user = UserModel::findByIdentity($identityType, $oauthId);
        if ($user)
        {
            return ['user' => $user, 'isNew' => false];
        }

        $existingByEmail = UserModel::findByIdentity('email', $email);
        if ($existingByEmail)
        {
            $now = now()->toDateTimeString();
            try
            {
                DB::transaction(function () use ($existingByEmail, $identityType, $oauthId, $now): void
                {
                    UserModel::insertIdentity([
                        'central_id'    => $existingByEmail->central_id,
                        'identity_type' => $identityType,
                        'identity'      => $oauthId,
                        'created_at'    => $now,
                    ]);
                });
            }
            catch (\Throwable $e)
            {
                Log::channel('error')->error('OAuth連携失敗', ['error' => $e->getMessage()]);
                throw $e;
            }

            return ['user' => $existingByEmail, 'isNew' => false];
        }

        $centralId = $this->generateCentralId();
        $publicId  = UserModel::nextPublicId();
        $now       = now()->toDateTimeString();

        try
        {
            DB::transaction(function () use ($centralId, $publicId, $identityType, $oauthId, $now): void
            {
                UserModel::insert([
                    'central_id'              => $centralId,
                    'public_id'               => $publicId,
                    'user_name'               => null,
                    'display_name'            => null,
                    'user_name_updated_at'    => $now,
                    'display_name_updated_at' => $now,
                    'created_at'              => $now,
                ]);
                UserModel::insertProfile([
                    'central_id' => $centralId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                UserModel::insertIdentity([
                    'central_id'    => $centralId,
                    'identity_type' => $identityType,
                    'identity'      => $oauthId,
                    'created_at'    => $now,
                ]);
                UserEventModel::log($centralId, 'register', ['identity_type' => $identityType]);
            });
        }
        catch (\Throwable $e)
        {
            Log::channel('error')->error('OAuthユーザー作成失敗', ['error' => $e->getMessage()]);
            throw $e;
        }

        Log::channel('access')->info('OAuthユーザー新規作成', ['central_id' => $centralId]);

        return ['user' => UserModel::findByCentralId($centralId), 'isNew' => true];
    }

    /**
     * central_id 生成（YYYY-UUID形式・41文字以内）
     */
    private function generateCentralId(): string
    {
        return date('Y') . '-' . (string) Str::uuid();
    }
}
