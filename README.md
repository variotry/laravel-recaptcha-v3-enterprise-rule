# laravel-recaptcha-v3-enterprise-rule
reCAPTCHA v3 Enterprise の laravelバリデーションルールを定義

## Installation

```bash
composer require variotry/laravel-recaptcha-v3-enterprise-rule
```

## 設定ファイルと言語ファイルの公開（Publish）

パッケージ導入後、必要に応じて設定ファイルや言語ファイルをアプリにコピーできます。

### 設定ファイル（Config）

```bash
php artisan vendor:publish --tag=vt-recaptcha-v3-enterprise:config
```
- コピー先： config/recaptcha-V3-enterprise.php
- ここでログチャネルや ReCAPTCHA サイトキーなどを設定可能
- .env で環境ごとに値を変更できます(以下は設定が必須）
    ```dotenv
    GOOGLE_SERVICE_ACCOUNT_BASE64="サービスアカウントのjsonをbase64変換した文字列"
    RECAPTCHAV3_ENTERPRISE_SITEKEY="recaptcha v3 enterprise版の SiteKey"
    ```
- jsonの base64文字列取得
    ```bash
    base64 -i service_account.json | tr -d '\n'
    ```

### 言語ファイル（Lang）

```bash
php artisan vendor:publish --tag=vt-recaptcha-v3-enterprise:lang
```
- コピー先： resources/lang/vendor/variotry/
-  バリデーションメッセージや通知文などをカスタマイズ可能

## 使い方
バリデーション定義する箇所で
```php
use Variotry\Recaptcha\V3\Enterprise\Rules\RecaptchaV3Enterprise;


'g-recaptcha-response' => ['required', new RecaptchaV3Enterprise('login') ]
```
のようにルールを設定。Recapchaルールでは action を指定してください。（サーバー側のルール判定に使われます）

## フロントエンド側に関して
npmパッケージ `recaptcha-v3` などを利用してトークン発行し、サーバーに投げて上記ルールを適用してください。
https://www.npmjs.com/package/recaptcha-v3

