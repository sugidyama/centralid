<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * user_events テーブルクエリ定義
 */
class UserEventModel
{
    /**
     * イベントログ記録
     */
    public static function log(string $centralId, string $eventType, array $extras = []): void
    {
        DB::table('user_events')->insert(array_merge([
            'central_id' => $centralId,
            'event_type' => $eventType,
            'created_at' => now()->toDateTimeString(),
        ], $extras));
    }
}
