<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * configs テーブルクエリ定義
 */
class ConfigModel
{
    /**
     * サービス一覧取得
     */
    public static function getServices(): array
    {
        $record = DB::table('configs')->where('config_name', 'services')->first();
        if (!$record)
        {
            return [];
        }

        return json_decode($record->config_value, true) ?? [];
    }
}
