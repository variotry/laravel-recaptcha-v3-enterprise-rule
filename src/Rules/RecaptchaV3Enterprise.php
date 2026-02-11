<?php

namespace Variotry\Recaptcha\V3\Enterprise\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

use Google\Cloud\RecaptchaEnterprise\V1\Client\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason;
use Google\Cloud\RecaptchaEnterprise\V1\CreateAssessmentRequest;

/**
 * @property Closure(int, int): string one
 */
class RecaptchaV3Enterprise implements ValidationRule
{
    protected string $action;

    /**
     * 引数 Ruleの $fail Closerを渡す。戻り値は false or float。
     * false を返すとアセスメントを実行しない。floatを返すときは、reCAPTCHAの評価基準。
     * @var callable|null
     */
    protected $scoreResolver;

    protected ?string $siteKey;
    protected ?string $service_account_base64;

    protected readonly string $loggingChannel;

    /**
     * @param string $action reCAPTCHAのアクションを指定
     * @param Closure|null $scoreResolver 引数 Ruleの $fail Closerを渡す。戻り値は false or float。
     * false を返すとアセスメントを実行しない。floatを返すときは、reCAPTCHAの評価基準。
     */
    public function __construct( string $action, ?callable $scoreResolver = null )
    {
        $this->action = $action;
        $this->scoreResolver = $scoreResolver;

        if ( empty( $this->scoreResolver ) )
        {
            // 未設定の場合は、configのスコア値をそのまま利用
            $this->scoreResolver = function( Closure $fail ) {
                return config( 'recaptcha-V3-enterprise.min_score' );
            };
        }
        $this->siteKey = config( 'recaptcha-V3-enterprise.site_key' );
        $this->service_account_base64 = config( 'recaptcha-V3-enterprise.service_account_base64' );
        $this->loggingChannel = config( 'recaptcha-V3-enterprise.log_channel' );
    }

    /**
     * Run the validation rule.
     *
     * @param \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate( string $attribute, mixed $value, Closure $fail ): void
    {
        $judgeScore = ($this->scoreResolver)( $fail );
        if ( $judgeScore === false )
        {
            // reCAPTCHAのアセスメント実行を抑えられるように
            // 評価が不必要な場合はfalseを返すことを期待
            return;
        }


        // わかりやすくするため $token変数に単純コピー
        $token = $value;

        if ( empty( $this->siteKey ) )
        {
            $code = 'RC-5011';
            \Log::channel( $this->loggingChannel )->error( $this->getLangLoggingMessage( $code ) );
            $fail( $this->getLangValidationMessage( $code ) );
            return;
        }
        if ( empty( $this->service_account_base64 ) )
        {
            $code = 'RC-5012';
            \Log::channel( $this->loggingChannel )->error( $this->getLangLoggingMessage( $code ) );
            $fail( $this->getLangValidationMessage( $code ) );
            return;
        }


        // reCAPTCHA クライアントを作成する。
        // TODO: クライアント生成コードをキャッシュに保存するか（推奨）、メソッドを終了する前に client.close() を呼び出す。
        $json = json_decode( base64_decode( $this->service_account_base64 ), true );
        $client = new RecaptchaEnterpriseServiceClient( [ 'credentials' => $json ] );
        $projectName = $client->projectName( $json[ 'project_id' ] );


        // 追跡するイベントのプロパティを設定する。
        $event = ( new Event() )
            ->setSiteKey( $this->siteKey )
            ->setToken( $token );

        // 評価リクエストを作成する。
        $assessment = ( new Assessment() )
            ->setEvent( $event );

        $request = new CreateAssessmentRequest();
        $request->setAssessment( $assessment );
        $request->setParent( $projectName );

        try
        {
            // 評価（ 10,000件/月 の無料枠などが消費される）
            $response = $client->createAssessment( $request );

            // Tokenが有効かどうか
            if ( $response->getTokenProperties()->getValid() == false )
            {
                $code = 'RC-4001';
                $msg = $this->getLangLoggingMessage( $code ) . '：';
                $msg .= InvalidReason::name( $response->getTokenProperties()->getInvalidReason() );
                \Log::channel(  $this->loggingChannel )->warning( $msg );
                $fail( $this->getLangValidationMessage( $code ) );
                return;
            }

            if ( $response->getTokenProperties()->getAction() != $this->action )
            {
                $code = 'RC-4002';
                \Log::channel(  $this->loggingChannel )->warning( $this->getLangLoggingMessage( $code ) );
                $fail( $this->getLangValidationMessage( $code ) );
                return;
            }

            //  0.7の場合、0.69999998807907 のような値になるので 四捨五入
            $score = round( $response->getRiskAnalysis()->getScore(), 1 );
            $passed = $score >= $judgeScore;

            $logging = config( 'recaptcha-V3-enterprise.score_logging' );
            if ( $logging === 'always' || ( $logging === 'on_fail' && !$passed ) )
            {
                \Log::channel(  $this->loggingChannel )->log( $passed ? 'info' : 'warning', __( 'variotry::recaptcha_rule.logging.score' ) . "：" . implode( ',', [ $this->action, $score ] ) );
            }

            // リスクスコアと理由を取得する。
            // 評価の解釈の詳細については、以下を参照:
            // https://cloud.google.com/recaptcha/docs/interpret-assessment
            if ( !$passed )
            {
                $fail( $this->getLangValidationMessage( 'RC-4003' ) );
            }
            //** ここまできたらOK **//
        }
        catch ( \Google\ApiCore\ApiException $e )
        {
            $code = 'RC-5001';
            \Log::channel(  $this->loggingChannel )->error( "($code)" . $e->getMessage() );
            // ユーザーへのエラーにおいて、「recaptcha・site key・tokenといったワードは出さないほうが良いみたい」
            $fail( $this->getLangValidationMessage( $code ) );
        }
        catch ( \Exception $e )
        {
            $code = 'RC-5002';
            \Log::channel(  $this->loggingChannel )->error( "($code)" . $e->getMessage() );
            $fail( $this->getLangValidationMessage( $code ) );
        }
        finally
        {
            $client->close();
        }
    }

    public function getLangValidationMessage( string $key ): string
    {
        // namespace付きのキーで \Lang::has() を読んだ際に
        // false結果（キーが存在しない）となった場合、バリデーションで
        // $fail('message') を実行しても、 422が返らず
        // なぜかリダイレクトが発生するので、 \Lang::has を使わない方法にしている

        $langArr = \Lang::get( 'variotry::recaptcha_rule' );
        if ( array_key_exists( $key, $langArr ) )
        {
            $msg = $langArr[ $key ];
        }
        else
        {
            $msg = __( 'variotry::recaptcha_rule.default' );
        }
        return $msg . "($key)";
    }

    public function getLangLoggingMessage( string $key ): string
    {
        return "($key) " . __( 'variotry::recaptcha_rule.logging.' . $key, [], config( 'recaptcha-V3-enterprise.log_locale' ) );
    }
}
