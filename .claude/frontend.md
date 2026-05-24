# フロントエンド開発ルール・前提条件

このドキュメントは、本プロジェクト（および今後の類似プロジェクト）におけるフロントエンドの実装ルールと前提となる技術スタックをまとめたものです。
AIにフロントエンドの実装・修正を依頼する際、本ドキュメントをコンテキストとして渡すことで、プロジェクトの意図に沿ったコードを出力させることができます。

## 1. 前提とする技術スタックと方針

* **ビルドツール不使用**: Webpack, Vite, Babel, TypeScript などの Node.js ベースのビルド環境は使用しません（`npm run dev` や `npm run build` は不要）。
* **FW不使用**: React, Vue.js, Angular などのモダンフレームワークは使用しません。
* **基本スタック**: **静的 HTML / Vanilla CSS / jQuery (v4.0.0)** のみで構成されます。
* **ローカルでの動作**: サーバーを立てずとも、HTMLファイルを直接ブラウザで開くだけで（または簡易的なローカルサーバーで）画面が機能・確認できることを前提とします。
* **デザインシステム前提の制作**: 各画面のHTML/CSSを実装する際は、必ず `.claude/design-system.md` および共通の `design-system/style.css` で定義されたコンポーネント・CSS変数を活用し、場当たり的なスタイル直書きを避けることを前提とします。

## 2. ディレクトリ・ファイル構成ルール

* **機能ごとのディレクトリ分割**: TOPページはカレントディレクトリ、各画面（例: ログイン、お問い合わせ入力、詳細ページ）ごとにディレクトリを作成します（例: `frontend/auth/`, `frontend/contact`）。
* **ファイル配置**: 原則として各ディレクトリには以下のファイルを配置します。
  * `index.html` (画面のマークアップ)
  * `style.css` (その画面固有のスタイル)
  * `script.js` (その画面固有のDOM操作・イベントロジック)
* **共通ファイルへの依存**: 全画面で共通するスタイルやスクリプトは、ルートに近い共通ディレクトリやファイルから読み込みます（例: `../../design-system/style.css`, `../../common.js`, `../../jquery-4.0.0.min.js`）。

## 3. コーディングルール

### HTML
* **ルート相対パス（絶対パス）の使用**: リンクやリソースの読み込みは、パス管理の煩雑さを避けるため、必ずルート相対パス（`/` から始まる絶対パス）を使用します。
* **モジュール化の回避**: `<script type="module">` は原則使用しません。
* **キャッシュバスティングの必須化**: ブラウザのキャッシュが残るのを防ぐため、独自実装するCSSやJSファイル（外部ライブラリ以外）を読み込む際は、JavaScriptを用いて現在時刻のタイムスタンプをクエリパラメータとして付与し、`document.write` で動的に出力する形式を**必ず**使用します。
  ```html
  <script>
    (function () {
      const n = new Date();
      const v = n.getFullYear() +
        ("00" + (n.getMonth() + 1)).slice(-2) +
        ("00" + n.getDate()).slice(-2) +
        ("00" + n.getHours()).slice(-2) +
        ("00" + n.getMinutes()).slice(-2) +
        ("00" + n.getSeconds()).slice(-2);
      
      // ※必ずルート相対パス（絶対パス）を使用すること
      document.write('<link rel="stylesheet" href="/design-system/style.css?v=' + v + '">');
      document.write('<link rel="stylesheet" href="/auth/style.css?v=' + v + '">');
      document.write('<script src="/common.js?v=' + v + '"><\\/script>');
      document.write('<script src="/auth/script.js?v=' + v + '"><\\/script>');
    })();
  </script>
  ```

### CSS
* **Vanilla CSS**: SCSS/SASSやTailwind CSSなどのツールは使用せず、純粋なCSSを使用します。

### デザイン・アイコン
* **絵文字の使用禁止**: UIデザイン（ボタン、バッジ、ステータス表示、プレースホルダー等）において、**絵文字（Emoji）の使用を固く禁じます**。OSやブラウザごとに見え方が異なり、プロフェッショナルな世界観やデザインの統一感を著しく損なうためです。
* **アイコン実装**: アイコンが必要な場合は、必ず**SVGデータ**（インラインSVGや、アイコン用ディレクトリからのファイル読み込み）を使用し、デザインシステムの一貫性を保つように適宜作成・管理してください。


### JavaScript / jQuery
* **jQueryの活用**: DOM操作やイベントハンドリングは、ネイティブAPIではなく jQuery を必ず利用します（`$().empty()`, `$().append()` など）。
* **API通信の共通化**: `$.ajax` を直接叩くのではなく、必ず `common.js` 等で定義されている共通の `api()` 関数を使用します。
  * **共通定数の定義**: `common.js` の先頭で、システム全体で利用するAPIベースURL（`API_BASE`）や環境フラグ（`ENV`）を必ず定義してください（例: `var API_BASE = 'https://api.netherid.com'; var ENV = 'develop';`）。
  * **目的**: 以下の処理を一元化・簡略化するために共通関数化しています。
    1. `ENV === 'develop'` 時のリクエスト/レスポンスの `console.log` への自動出力（デバッグ効率化）
    2. APIのベースURL (`API_BASE`) の適用と一元管理
    3. `Authorization` ヘッダー（認証トークン）や `Content-Type: application/json` の自動付与
    4. `$.ajax` の冗長なオプション記述の簡略化
  * `api()` は jQuery の Deferred (Promise) を返す前提であり、`.done()` / `.fail()` / `.always()` で非同期処理をチェーンします。
* **JSDocコメント**: 関数や重要な変数には、必ず日本語で JSDoc 形式のコメントを記述します。
* **スコープの保護**: 各画面の `script.js` は、グローバル変数の汚染を防ぐため、全体を `$(function () { ... });` で囲みます。
* **イベントデリゲーション**: APIから取得して動的に生成されたDOM要素に対するイベントは、必ず `$(document).on('click', '.selector', function() { ... })` のようにイベントデリゲーションを使用してバインドします。
* **状態管理**: 認証トークンなどの永続化が必要なデータは `localStorage` を使用し、共通関数（例: `getToken()`, `setToken()`）経由でアクセスします。

## api()関数のサンプル
/**
 * APIへのリクエストを送信する共通関数。
 * ENV が 'develop' の場合、リクエスト内容とレスポンスをコンソールに出力する。
 *
 * @param {Object}  options           - リクエストオプション
 * @param {string}  options.method    - HTTPメソッド（'GET' / 'POST' / 'DELETE' など）
 * @param {string}  options.path      - エンドポイントパス（例: '/lunches'）
 * @param {Object}  [options.data]    - リクエストボディ（JSON シリアライズ対象）
 * @param {Object}  [options.params]  - クエリパラメータ（キーと値のオブジェクト）
 * @param {boolean} [options.withAuth=true] - Authorization ヘッダーを付与するか
 * @returns {jQuery.Deferred} .done(res) / .fail(xhr) で受け取る
 */
function api(options) {
  var method  = (options.method || 'GET').toUpperCase();
  var url     = API_BASE + options.path;
  var headers = buildHeaders(options.withAuth);

  /* クエリパラメータを付与 */
  if (options.params && !$.isEmptyObject(options.params)) {
    url += '?' + $.param(options.params);
  }

  var ajaxOptions = {
    url:         url,
    method:      method,
    contentType: 'application/json',
    headers:     headers
  };

  if (options.data) {
    ajaxOptions.data = JSON.stringify(options.data);
  }

  /* developモード: リクエスト内容を出力 */
  if (ENV === 'develop') {
    console.log('[API Request]', method, url, options.data || '');
  }

  var deferred = $.Deferred();

  $.ajax(ajaxOptions)
    .done(function (res) {
      /* developモード: レスポンスを出力 */
      if (ENV === 'develop') {
        console.log('[API Response]', method, url, res);
      }
      deferred.resolve(res);
    })
    .fail(function (xhr) {
      /* developモード: エラーレスポンスを出力 */
      if (ENV === 'develop') {
        console.error('[API Error]', method, url, xhr.status, xhr.responseJSON || xhr.responseText);
      }
      deferred.reject(xhr);
    });

  return deferred.promise();
}
