<?php
return [
    'service_account_base64' => env('GOOGLE_SERVICE_ACCOUNT_BASE64' ),
    'site_key' => env('RECAPTCHAV3_ENTERPRISE_SITEKEY', ''),
    'min_score' => env ('RECAPTCHAV3_ENTERPRISE_MIN_SCORE', 0.5 ),

    /**
     * スコアのロギング設定
     * always   常にログに記録
     * on_fail  スコア判定がNG
     * never    記録しない（上記どちらにも該当しなければnever扱い）
     */
    'score_logging' => env('RECAPTCHAV3_ENTERPRISE_SCORE_LOGGING', 'on_fail' ),

    // logging
    'log_channel' => env('RECAPTCHAV3_ENTERPRISE_LOGGING_CHANNEL', 'recaptcha' ),
    'log_locale' => env( 'RECAPTCHAV3_ENTERPRISE_LOGGING_LOCALE', config('app.locale') ),

    'logging' => [
        'driver' => 'daily',
        'path' => storage_path( 'logs/recaptcha/recaptcha.log' ),
        'level' => 'info',
        'days' => 14,
        'tap' => [ Variotry\Recaptcha\V3\Enterprise\Logging\AddRequestIp::class ],
        'permission' => 0664
    ]
];
