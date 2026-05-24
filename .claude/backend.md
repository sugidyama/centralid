# Laravel バックエンド設計書

---

## 開発フロー

このプロジェクトでは以下の3フェーズで開発を進める。**フェーズの移行はユーザーが明示的に指示する。**

```
フェーズ1: AIが動く実装を作る
     ↓（ユーザーが「テストを書く」と宣言）
フェーズ2: ユーザーがテストコードを書く（AIは待機）
     ↓（ユーザーが「テストを通して」と指示）
フェーズ3: AIがテストをパスするようにコードを修正する
```

---

### フェーズ1 — 初期実装（AI担当）

**目的:** まず動くものを作る。美しさより動作を優先。

#### AIの行動規則
- Controller / Service / Model の3層構成で実装する
- 本ドキュメント下部のコーディングテンプレートに従う
- **このフェーズではテストコードを書かない**
- 実装完了後、ユーザーに「動作確認してください」と伝える
- ユーザーが動作を確認し「テストを書く」と言うまで次のフェーズに進まない

#### 成果物
- `app/Http/Controllers/XxxController.php`
- `app/Services/XxxService.php`
- `app/Models/XxxModel.php`
- `app/Data/XxxData.php`（値オブジェクト）
- `routes/api.php` へのルート追加

---

### フェーズ2 — テストコード作成（ユーザー担当）

**目的:** ユーザーが期待する振る舞いをテストコードで明文化する。

#### AIの行動規則
- **このフェーズでは実装コードを一切変更しない**
- ユーザーからテストコードについて質問があれば答える
- テストの書き方を聞かれた場合はサンプルを提示するが、ユーザーの意図を先読みして実装はしない

#### テストコードの配置と種類

```
tests/
├── Unit/
│   ├── Models/       # Modelのクエリロジックテスト（実DBを使う）
│   │   └── UserModelTest.php
│   └── Services/     # Serviceのビジネスロジックテスト（Modelをモック）
│       └── UserServiceTest.php
└── Feature/          # エンドポイントのHTTPテスト（リクエスト〜レスポンス全体）
    ├── Auth/
    │   └── LoginTest.php
    └── User/
        └── UserTest.php
```

#### テストの種類と使い分け

| 種類 | 配置 | 何をテストするか | DBへの接続 |
|---|---|---|---|
| **Unit/Models** | `tests/Unit/Models/` | クエリの結果・絞り込み・ソートなどDBアクセスの振る舞い | **実テストDBを使う** |
| **Unit/Services** | `tests/Unit/Services/` | ビジネスロジック・条件分岐・計算など | Modelをモックする |
| **Feature** | `tests/Feature/` | HTTPリクエストからJSONレスポンスまでの一気通貫 | **実テストDBを使う** |

> `Unit/Models` と `Feature` は実DB（テスト用DB）を使う。
> `Unit/Services` はModelをモックして、ビジネスロジックだけを検証する。

#### テストの実行コマンド
```bash
php artisan test                               # 全テスト
php artisan test tests/Unit/Models/            # Modelテストのみ
php artisan test tests/Unit/Services/          # Serviceテストのみ
php artisan test tests/Feature/                # Featureテストのみ
php artisan test --filter UserModelTest        # クラス単位
php artisan test --filter test_xxx             # メソッド単位
```

---

### フェーズ3 — テストパス対応（AI担当）

**目的:** ユーザーが書いたテストをすべてパスさせる。

#### AIの行動規則
1. まずテストコードを読み、期待する振る舞いを正確に把握する
2. **テストコードは変更しない**（テストを通すためにテストを書き換えることは禁止）
3. 実装コード（Controller / Service / Model）のみ修正する
4. 修正後は `php artisan test` を実行してパスを確認する
5. すべてパスしたらユーザーに報告する
6. テストが意図的に通らない設計になっている場合（仕様が変わった場合など）は、勝手に判断せずユーザーに確認する

#### 修正の優先順位
1. テストが示す仕様に従ってロジックを修正
2. 必要であればDBクエリを修正
3. バリデーションルールを修正
4. レスポンス形式を修正

---

## ディレクトリ構成

```
backend/
└── app/
    ├── Http/
    │   └── Controllers/   # リクエスト受付・レスポンス返却
    ├── Models/            # DB クエリメソッド定義
    ├── Data/            # read only class, valueobjectなどのデータ
    └── Services/          # ビジネスロジック・ユーティリティ
```

依存方向は **Controller → Service → Model の一方向のみ**。逆方向の参照は禁止。

---

## 層の責務

### Controller（`app/Http/Controllers/`）
- HTTP リクエスト受け取りとレスポンス返却のみ
- ビジネスロジックは書かない
- Service / Model を呼び出す

### Model（`app/Models/`）
- `DB` ファサードを使ったクエリメソッドのみ定義
- Eloquent ORM は**絶対に使用しない**

### Service（`app/Services/`）
- ビジネスロジック記述
- 共通ユーティリティは `AppService` など単一クラスにまとめてよい

---

## 禁止事項

| 禁止 | 理由 |
|------|------|
| `User::find()` `User::create()` `$model->save()` など Eloquent 操作 | DB アクセスは DB ファサード経由に統一 |
| `DB::select('SELECT ...', [...])` などの生 SQL 文字列 | メソッドチェーンを必ず使う |
| Controller 内での DB 操作 | 層の責務を分離する |
| トランザクション外での書き込み操作（INSERT / UPDATE / DELETE） | 整合性保証のため |

---

## DB ファサード — メソッドチェーン規則

```php
use Illuminate\Support\Facades\DB;

// ✅ 正しい（メソッドチェーン）
DB::table('users')
    ->where('id', $id)
    ->whereNull('deleted_at')
    ->first();

DB::table('login_histories')
    ->where('user_id', $userId)
    ->orderByDesc('created_at')
    ->paginate(20);

// ❌ 禁止（生 SQL）
DB::select('SELECT * FROM users WHERE id = ?', [$id]);

// ❌ 禁止（Eloquent）
User::find($id);
```

### 主要クエリメソッド早見表

| 操作 | メソッド |
|------|---------|
| 全件取得 | `->get()` |
| 1件取得 | `->first()` / `->find($id)` |
| 件数 | `->count()` |
| 挿入 | `->insert([...])` |
| 挿入＋ID取得 | `->insertGetId([...])` |
| 更新 | `->where(...)->update([...])` |
| 削除（物理） | `->where(...)->delete()` |
| 論理削除 | `->where(...)->update(['status' => 0])` |
| 結合 | `->join(...)` / `->leftJoin(...)` |
| 絞り込み | `->where(...)` / `->whereIn(...)` / `->whereNull(...)` |
| ソート | `->orderBy(...)` / `->orderByDesc(...)` |
| ページング | `->paginate($n)` / `->offset()->limit()` |

---

## トランザクション・エラーハンドリング

- **INSERT / UPDATE / DELETE を含む処理は必ず `DB::transaction()` 内に書く**
- 例外は `try/catch (\Throwable $e)` で捕捉し `Log::error()` で記録
- `catch` ブロックでは必ず例外を再スローする

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

try
{
    DB::transaction(function () use ($input) {
        $userId = DB::table('users')->insertGetId([
            'id'                 => \Illuminate\Support\Str::uuid(),
            'username'           => $input->username,
            'registered_app_id'  => $input->registeredAppId,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        DB::table('auth_providers')->insert([
            'user_id'      => $userId,
            'provider'     => $input->provider,
            'provider_uid' => $input->providerUid,
            'created_at'   => now(),
        ]);

        return $userId;
    });
}
catch (\Throwable $e)
{
    Log::channel('error')->error('ユーザー登録失敗', [
        'message' => $e->getMessage(),
        'trace'   => $e->getTraceAsString(),
    ]);
    throw $e;
}
```

---

## ログ設定

### access.log / error.log の分離

`config/logging.php` に以下の 2 チャンネルを追加する。

```php
'channels' => [
    // アクセスログ（API リクエスト記録）
    'access' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/access.log'),
        'level'  => 'info',
        'days'   => 30,
    ],

    // エラーログ（例外・DB エラー記録）
    'error' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/error.log'),
        'level'  => 'error',
        'days'   => 60,
    ],
],
```

### 使い分け

```php
// アクセスログ（Controller でリクエスト記録）
Log::channel('access')->info('ログインリクエスト', [
    'provider' => $request->provider,
    'ip'       => $request->ip(),
]);

// エラーログ（catch ブロックで例外記録）
Log::channel('error')->error('ユーザー登録失敗', [
    'message' => $e->getMessage(),
    'trace'   => $e->getTraceAsString(),
]);
```

---

## Sentry 監視

### インストール

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=https://xxxxx@xxxxx.ingest.sentry.io/xxxxx
```

### `.env` 設定

```dotenv
SENTRY_LARAVEL_DSN=https://xxxxx@xxxxx.ingest.sentry.io/xxxxx

# パフォーマンストレース（1.0 = 100% / 本番は 0.1 など調整）
SENTRY_TRACES_SAMPLE_RATE=1.0
```

### 例外ハンドラへの組み込み（`bootstrap/app.php`）

Laravel 11 以降は `bootstrap/app.php` で設定する。

```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->reportable(function (\Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    })
    ->create();
```

### `catch` ブロックでの使い分け

`catch` ブロックでは **`Log::channel('error')` と Sentry の両方に送る**。
ファイルログは手元で確認でき、Sentry はリアルタイム通知・集約・トレースに使う。

```php
}
catch (\Throwable $e)
{
    Log::channel('error')->error('ユーザー作成失敗', [
        'message' => $e->getMessage(),
        'trace'   => $e->getTraceAsString(),
    ]);

    if (app()->bound('sentry'))
    {
        app('sentry')->captureException($e);
    }

    throw $e;
}
```

### コンテキスト情報の付与

認証済みユーザーの情報を Sentry に渡すことで、エラーの影響ユーザーを特定しやすくする。
ミドルウェアまたは `AppServiceProvider` で設定する。

```php
use Sentry\State\Scope;

\Sentry\configureScope(function (Scope $scope) use ($user): void {
    $scope->setUser([
        'id'    => $user->id,
        'email' => $user->email ?? null,
    ]);
});
```

### 動作確認

```bash
php artisan sentry:test
```

---

## 画像ストレージ

- アップロード画像は `storage/app/public/images/` に保存
- シンボリックリンク: `php artisan storage:link` で `public/storage` に公開
- ファイル名は `uniqid()` + 拡張子でユニーク化

```php
use Illuminate\Support\Facades\Storage;

// 保存
$path = $request->file('image')->store('images', 'public');
// → storage/app/public/images/xxxxx.jpg

// 公開 URL
$url = Storage::url($path);
// → /storage/images/xxxxx.jpg
```

---

## ブレース（波括弧）スタイル

- **class・メソッド・try・catch・if・foreach・while など、すべての構造の開き波括弧は改行して次の行に書く（Allman スタイル）**
- 無名関数（クロージャ）を引数として渡す場合も改行して次の行に書く（Allman スタイル）

```php
// ✅ 正しい（Allman スタイル）
class UserModel
{
    public function findById(string $id): ?\stdClass
    {
        try
        {
            // ...
        }
        catch (\Throwable $e)
        {
            // ...
        }
    }
}

// ✅ 無名関数を引数で渡す場合は同一行でよい
DB::transaction(function () use ($input) 
{
    // ...
});

Route::prefix('auth')->group(function () 
{
    // ...
});
```

---

## PHPDoc コメント規約

- **全クラス・全メソッドに必ず付ける**
- **日本語で記載する**
- **体言止め**（「〜する」「〜します」は不可。「〜処理」「〜取得」「〜生成」など名詞形で終わる）
- 1行コメントを優先し、不要な詳細説明は省く

```php
/**
 * ユーザーモデル
 */
class UserModel
{
    /**
     * IDによるユーザー取得
     *
     * @param  string  $id  ユーザーUUID
     * @return \stdClass|null
     */
    public function findById(string $id): ?\stdClass
    {
        return DB::table('users')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();
    }
}
```

### コメント体言止め例

| NG（動詞形） | OK（体言止め） |
|---|---|
| ユーザーを取得する | ユーザー取得 |
| ユーザーを作成する | ユーザー作成 |
| トークンを発行する | トークン発行 |
| エラーを記録する | エラー記録 |
| バリデーションを行う | バリデーション |

---

## 値オブジェクト（`readonly class`）

Controller → Service 間のデータ受け渡しに使用する。配列の生渡しは禁止。

```php
/**
 * ユーザー登録入力値オブジェクト
 */
readonly class RegisterUserInput
{
    public function __construct(
        public string  $provider,
        public string  $providerUid,
        public ?string $username,
        public ?string $registeredAppId,
    ) {}
}
```

```php
/**
 * ワンタイムコード送信入力値オブジェクト
 */
readonly class SendOtpInput
{
    public function __construct(
        public string $email,
        public string $ipAddress,
    ) {}
}
```

---

## API URI 設計

- **先頭に `/api` や `/v1` は付けない**（`auth.centralid.win` などのサブドメインで分離するため）
- Route ファイルは `routes/api.php` を使用する

```php
// routes/api.php

Route::get('/health', [HealthController::class, 'index']);

// OTP（ワンタイムコード）
Route::post('/otp/send',   [OtpController::class, 'send']);
Route::post('/otp/verify', [OtpController::class, 'verify']);

// 認証
Route::prefix('auth')->group(function () {
    Route::post('/social/{provider}', [AuthController::class, 'social']);
    Route::post('/logout',            [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/token/refresh',     [AuthController::class, 'refresh']);
});

// ユーザー
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/me',                   [UserController::class, 'me']);
    Route::patch('/users/me',                 [UserController::class, 'update']);
    Route::get('/users/me/histories',         [UserController::class, 'histories']);
});

// 管理者
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/users',                      [AdminUserController::class, 'index']);
    Route::get('/users/{id}',                 [AdminUserController::class, 'show']);
    Route::post('/users/{id}/tags',           [AdminUserController::class, 'attachTag']);
    Route::delete('/users/{id}/tags/{tag}',   [AdminUserController::class, 'detachTag']);
    Route::get('/apps',                       [AdminAppController::class, 'index']);
    Route::post('/apps',                      [AdminAppController::class, 'store']);
});
```

### CORS 設定（`config/cors.php`）

```php
'allowed_origins' => ['https://centralid.win', 'http://localhost:3000'],
```

---

## .env 設定

```dotenv
# アプリ
APP_NAME=CentralId
APP_ENV=local
APP_KEY=          # php artisan key:generate で生成
APP_DEBUG=true
APP_URL=http://localhost

# データベース（MySQL）
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=centralid
DB_USERNAME=root
DB_PASSWORD=

# セッション（ファイル保存）
SESSION_DRIVER=file
SESSION_LIFETIME=120

# キャッシュ（ファイル保存）
CACHE_DRIVER=file

# キュー（同期処理）
QUEUE_CONNECTION=sync

# ログ
LOG_CHANNEL=stack
LOG_LEVEL=debug

# ファイルストレージ
FILESYSTEM_DISK=public
```

> `SESSION_DRIVER=file` / `CACHE_DRIVER=file` により、セッション・キャッシュは
> `storage/framework/sessions` / `storage/framework/cache` にファイル保存される。
> データベースには依頼したテーブル以外保存しない。

---

## 必須インポート一覧

| 用途 | use 文 |
|------|--------|
| DB 操作 | `use Illuminate\Support\Facades\DB;` |
| ログ記録 | `use Illuminate\Support\Facades\Log;` |
| ファイルストレージ | `use Illuminate\Support\Facades\Storage;` |
| HTTP レスポンス | `use Illuminate\Http\JsonResponse;` |
| リクエスト | `use Illuminate\Http\Request;` |

---

## コーディングテンプレート

### Controller

```php
<?php

namespace App\Http\Controllers;

use App\Data\SendOtpInput;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * OTP認証コントローラ
 */
class OtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    /**
     * ワンタイムコード送信
     */
    public function send(Request $request): JsonResponse
    {
        Log::channel('access')->info('OTP送信リクエスト', [
            'ip' => $request->ip(),
        ]);

        $this->otpService->send(new SendOtpInput(
            email:     $request->string('email')->toString(),
            ipAddress: $request->ip(),
        ));

        return response()->json(['message' => 'sent']);
    }
}
```

### Service

```php
<?php

namespace App\Services;

use App\Data\SendOtpInput;
use App\Models\OtpModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OTP認証サービス
 */
class OtpService
{
    public function __construct(
        private readonly OtpModel $otpModel,
    ) {}

    /**
     * ワンタイムコード発行・送信
     *
     * @throws \Throwable
     */
    public function send(SendOtpInput $input): void
    {
        try
        {
            DB::transaction(function () use ($input)
            {
                // 同一メールの未使用コードを無効化
                $this->otpModel->invalidateByEmail($input->email);
                // 新規コード発行
                $this->otpModel->create($input->email);
            });
        }
        catch (\Throwable $e)
        {
            Log::channel('error')->error('OTP発行失敗', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
```

### Model

```php
<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * ワンタイムコードモデル
 */
class OtpModel
{
    /** @var string テーブル名 */
    private const TABLE = 'one_time_codes';

    /**
     * 同一メールの未使用コード無効化
     *
     * @param  string  $email  メールアドレス
     */
    public function invalidateByEmail(string $email): void
    {
        DB::table(self::TABLE)
            ->where('email', $email)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    /**
     * ワンタイムコード新規作成
     *
     * @param  string  $email  メールアドレス
     * @return int 発行ID
     */
    public function create(string $email): int
    {
        return DB::table(self::TABLE)->insertGetId([
            'email'      => $email,
            'code'       => str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);
    }
}
```
