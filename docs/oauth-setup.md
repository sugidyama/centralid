# OAuth 認証情報 取得・設定手順

本番サーバー（centralid.win）に Google / GitHub OAuth を設定するための手順書。

---

## Google OAuth

### 1. Google Cloud Console でプロジェクトを作成

1. [Google Cloud Console](https://console.cloud.google.com/) にアクセスしてログイン
2. 画面上部のプロジェクト選択ドロップダウン → **「新しいプロジェクト」** をクリック
3. プロジェクト名を入力（例: `centralid`）→ **「作成」**

### 2. OAuth 同意画面を設定

1. 左メニュー → **「APIとサービス」** → **「OAuth 同意画面」**
2. User Type: **「外部」** を選択 → **「作成」**
3. 以下を入力：
   - アプリ名: `セントラルID`
   - ユーザーサポートメール: 自分のメールアドレス
   - デベロッパーの連絡先メールアドレス: 自分のメールアドレス
4. **「保存して次へ」** を3回クリックして完了

### 3. OAuth 2.0 クライアントIDを作成

1. 左メニュー → **「APIとサービス」** → **「認証情報」**
2. 上部 **「＋ 認証情報を作成」** → **「OAuth クライアント ID」**
3. アプリケーションの種類: **「ウェブ アプリケーション」**
4. 名前: `centralid-production`（任意）
5. **「承認済みのリダイレクト URI」** に以下を追加：
   ```
   https://centralid.win/oauth/google/callback
   ```
6. **「作成」** をクリック
7. ダイアログに表示される値、または **「JSON をダウンロード」** で取得したファイルから以下の2つをコピーして保管：
   - `client_id` の値 → `GOOGLE_CLIENT_ID`
   - `client_secret` の値 → `GOOGLE_CLIENT_SECRET`

   ```json
   {
     "web": {
       "client_id": "ここをコピー",
       "client_secret": "ここをコピー",
       ...
     }
   }
   ```

---

## GitHub OAuth

### 1. OAuth App を作成

1. GitHub にログイン → 右上アイコン → **「Settings」**
2. 左メニュー最下部 **「Developer settings」** → **「OAuth Apps」**
3. **「New OAuth App」** をクリック
4. 以下を入力：
   - Application name: `セントラルID`（任意）
   - Homepage URL: `https://centralid.win`
   - Authorization callback URL:
     ```
     https://centralid.win/oauth/github/callback
     ```
5. **「Register application」** をクリック

### 2. クライアントシークレットを発行

1. 作成した OAuth App の設定画面を開く
2. **「Client ID」** をコピーして保管
3. **「Generate a new client secret」** をクリック
4. 表示された **クライアントシークレット** をコピーして保管（再表示不可）

---

## サーバーへの設定

### 1. .env に追記

本番サーバーの `backend/.env` を開いて以下を追記・更新：

```dotenv
GOOGLE_CLIENT_ID=（取得したクライアントID）
GOOGLE_CLIENT_SECRET=（取得したクライアントシークレット）
GOOGLE_REDIRECT_URI=https://centralid.win/oauth/google/callback

GITHUB_CLIENT_ID=（取得したクライアントID）
GITHUB_CLIENT_SECRET=（取得したクライアントシークレット）
GITHUB_REDIRECT_URI=https://centralid.win/oauth/github/callback
```

### 2. キャッシュをクリア

```bash
cd /var/www/backend   # サーバー上のプロジェクトパス
php artisan config:clear
php artisan cache:clear
```

### 3. 動作確認

```bash
# Google: 302リダイレクトが返れば成功
curl -s -o /dev/null -w "%{http_code}" \
  "https://centralid.win/oauth/google?redirect_uri=https://centralid.win"

# GitHub: 302リダイレクトが返れば成功
curl -s -o /dev/null -w "%{http_code}" \
  "https://centralid.win/oauth/github?redirect_uri=https://centralid.win"
```

両方とも `302` が返ればOK。その後 `pytest -v` を再実行してテストが全て通ることを確認する。
