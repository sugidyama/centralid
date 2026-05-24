# CLAUDE.md 

## バックエンド コーディングルール

**詳細設計書: [`.claude/backend.md`](.claude/backend.md)**

バックエンドを実装する際は以下のルールを**必ず**守ること。

### ディレクトリ構成

```
backend/app/
├── Http/Controllers/  # リクエスト受付・レスポンス返却のみ
├── Models/            # DB クエリメソッド定義のみ
└── Services/          # ビジネスロジック
```

依存方向: **Controller → Service → Model（一方向のみ）**

### 絶対に守るルール

1. **Eloquent ORM は使用禁止** — `User::find()` `$model->save()` など一切 NG
2. **DB アクセスは DB ファサード + メソッドチェーンのみ** — 生 SQL 文字列の直接記述禁止
   - `use Illuminate\Support\Facades\DB;`
   - `DB::table('lunches')->where(...)->get()` の形式で書く
3. **DB 操作は必ず `DB::transaction()` 内に書く**（INSERT / UPDATE / DELETE すべて）
4. **トランザクション失敗時は `try/catch` で捕捉し `Log::channel('error')->error()` でログ記録**
5. **ログは access.log と error.log を分けて出力**（`config/logging.php` にチャンネル設定）
6. **値オブジェクト（`readonly class`）を活用して構造化**（配列の生渡し禁止）
7. **PHPDoc コメントは日本語・体言止めで全クラス・全メソッドに付ける**
8. **URI 先頭に `/api` や `/v1` は付けない**
9. **画像は `storage/app/public/images/` に保存**（`Storage::disk('public')` 使用）
10. **セッション・キャッシュはファイル保存**（`.env`: `SESSION_DRIVER=file` `CACHE_DRIVER=file`）

### .env 必須設定

```dotenv
DB_CONNECTION=mysql
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
```

## commit
コミットメッセージの書き方は、
**詳細設計書: [`.claude/git-commit.md`](.claude/git-commit.md)**
を必ず参照すること。