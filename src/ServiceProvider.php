<?php

namespace Variotry\Recaptcha\V3\Enterprise;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    protected string $config = __DIR__ . '/config/recaptcha-V3-enterprise.php';
    protected string $lang = __DIR__ . '/lang';

    public function register(): void
    {
        // config merge, bind
        $this->mergeConfigFrom( $this->config, 'recaptcha-V3-enterprise' );
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom( $this->lang, 'variotry' );

        $this->publishes( [
            $this->config => config_path( 'recaptcha-V3-enterprise.php' ),
        ], 'vt-recaptcha-v3-enterprise:config' );

        $this->publishes( [
            $this->lang => resource_path( 'lang/vendor/variotry' ),
        ], 'vt-recaptcha-v3-enterprise:lang' );

        // logging設定
        $channel = config( 'recaptcha-V3-enterprise.log_channel' );
        if ( !config( "logging.channels.$channel" ) )
        {
            config( [ "logging.channels.$channel" => config( 'recaptcha-V3-enterprise.logging' ), ] );
        }
    }
}