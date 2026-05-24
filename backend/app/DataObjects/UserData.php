<?php

namespace App\DataObjects;

/**
 * ユーザー情報値オブジェクト
 */
readonly class UserData
{
    /**
     * コンストラクタ
     */
    public function __construct(
        public string  $centralId,
        public int     $publicId,
        public ?string $userName,
        public ?string $displayName,
        public string  $createdAt,
    ) {}

    /**
     * 配列変換
     */
    public function toArray(): array
    {
        return [
            'central_id'   => $this->centralId,
            'public_id'    => $this->publicId,
            'user_name'    => $this->userName,
            'display_name' => $this->displayName,
            'created_at'   => $this->createdAt,
        ];
    }
}
