<?php

namespace Tests;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /** マイグレーション実行済みフラグ */
    protected static bool $migrated = false;

    /** 永続化SQLiteファイルパス */
    protected static string $dbPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPersistentDatabase();
    }

    /**
     * テスト間でDBが消えないよう、ファイルベースSQLiteへ切り替え
     */
    private function setupPersistentDatabase(): void
    {
        if (static::$dbPath === '') {
            static::$dbPath = sys_get_temp_dir() . '/laravel_centralidv3_test.sqlite';
        }

        if (!file_exists(static::$dbPath)) {
            touch(static::$dbPath);
        }

        config(['database.connections.sqlite.database' => static::$dbPath]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        if (!static::$migrated) {
            $this->artisan('migrate:fresh');
            $this->seed(DatabaseSeeder::class);
            static::$migrated = true;
        }
    }
}
