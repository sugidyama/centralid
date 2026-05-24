<?php

namespace App\DataObjects;

/**
 * トークン発行レスポンス値オブジェクト
 */
readonly class TokenResponseData
{
    /**
     * コンストラクタ
     */
    public function __construct(
        public string   $accessToken,
        public string   $refreshToken,
        public int      $expiresIn,
        public bool     $isNewUser,
        public UserData $user,
    ) {}

    /**
     * 配列変換
     */
    public function toArray(): array
    {
        return [
            'access_token'  => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_in'    => $this->expiresIn,
            'is_new_user'   => $this->isNewUser,
            'user'          => $this->user->toArray(),
        ];
    }
}
