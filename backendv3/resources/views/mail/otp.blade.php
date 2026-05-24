<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>認証コード</title>
    <style>
        body {
            font-family: 'Hiragino Kaku Gothic Pro', 'メイリオ', Meiryo, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .wrapper {
            max-width: 560px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            padding: 40px;
        }
        .logo {
            font-size: 20px;
            font-weight: bold;
            color: #333333;
            margin-bottom: 32px;
        }
        h1 {
            font-size: 18px;
            color: #333333;
            margin-bottom: 16px;
        }
        p {
            font-size: 14px;
            color: #555555;
            line-height: 1.7;
            margin: 0 0 16px;
        }
        .code-box {
            background-color: #f0f4ff;
            border: 1px solid #c7d7ff;
            border-radius: 6px;
            text-align: center;
            padding: 24px;
            margin: 24px 0;
        }
        .code {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #3b5bdb;
        }
        .expires {
            font-size: 13px;
            color: #888888;
            margin-top: 8px;
        }
        .footer {
            margin-top: 32px;
            font-size: 12px;
            color: #aaaaaa;
            border-top: 1px solid #eeeeee;
            padding-top: 16px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="logo">{{ config('app.name') }}</div>

        <h1>認証コードのご案内</h1>

        <p>以下の認証コードをお使いのログイン画面に入力してください。</p>

        <div class="code-box">
            <div class="code">{{ $code }}</div>
            <div class="expires">有効期限：{{ $expiresMinutes }}分</div>
        </div>

        <p>このコードに心当たりがない場合は、このメールを無視してください。<br>
        アカウントのセキュリティは保たれています。</p>

        <div class="footer">
            このメールは自動送信されています。返信はできません。
        </div>
    </div>
</body>
</html>
