<?php

namespace App\Services;

use App\Models\ConfigModel;

/**
 * サービス設定検証サービス
 */
class ConfigService
{
    /**
     * サービスIDが有効かどうか確認
     */
    public function isValidService(string $serviceId): bool
    {
        return in_array($serviceId, ConfigModel::getServices(), true);
    }
}
