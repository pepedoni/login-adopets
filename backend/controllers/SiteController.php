<?php
namespace backend\controllers;

use Yii;
use yii\filters\AccessControl;
use common\models\LoginForm;
use common\models\AuthorizationCodes;
use common\models\AccessTokens;

use backend\models\SignupForm;
use backend\behaviours\Verbcheck;
use backend\behaviours\Apiauth;

use common\models\User;
use Twilio\Rest\Client;
/**
 * Site controller
 */
class SiteController extends RestController
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {

        $behaviors = parent::behaviors();

        return $behaviors + [
            'apiauth' => [
                'class' => Apiauth::className(),
                'exclude' => ['authorize', 'register', 'accesstoken', 'index', 'confirm', 'requestresetpassword', 'resetpassword'],
            ],
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'signup'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout', 'me'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['authorize', 'register', 'accesstoken'],
                        'allow' => true,
                        'roles' => ['*'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => Verbcheck::className(),
                'actions' => [
                    'logout' => ['GET'],
                    'authorize' => ['POST'],
                    'register' => ['POST'],
                    'accesstoken' => ['POST'],
                    'me' => ['GET'],
                ],
            ],
        ];
    }


    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        Yii::$app->api->sendSuccessResponse(['Adopets']);
        //  return $this->render('index');
    }

    public function actionRegister()
    {

        $model = new SignupForm();
        $model->attributes = $this->request;

        if( isset( $this->request["two_steps"] )) {
            if($this->request["two_steps"]) {
                if(! $this->request["phone"]) {
                    Yii::$app->api->sendFailedResponse("Phone cannot be blank");
                }
                else {
                    $model->phone = $this->request["phone"];
                }
            }
        } 

        if ($user = $model->signup()) {

            $data=$user->attributes;
            unset($data['auth_key']);
            unset($data['password_hash']);
            //unset($data['activation_key']);
            unset($data['password_reset_token']);

            $url = Yii::$app->urlManager->createAbsoluteUrl(
                ['site/confirm','id'=>$user->id, 'key' => $user->auth_key]);


            $username = $user->username;

            $text = "Ola $username, confirme seu e-mail clicando no link: $url";

            $confirmation_mail = $this->sendMail($user->email, "phpedromoutinho@gmail.com", $user->username, "Pedro Moutinho", "Confirmação do e-mail", $text);

            $data['confirmation_mail'] = $confirmation_mail;

            Yii::$app->api->sendSuccessResponse($data);
            
        }

    }

    public function sendMail($to, $from, $toName, $fromName, $subject, $text) {
        $email = new \SendGrid\Mail\Mail();
        $email->setFrom($from, $fromName);
        $email->setSubject($subject);
        $email->addTo($to, $toName);
        $email->addContent(
            "text/plain", $text
        );

        $env = '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';

        $sendgrid = new \SendGrid($env);
        
        try {
            $response = $sendgrid->send($email);
            return ($response->statusCode() == 200);

        } catch (Exception $e) {
            
            return false;

        }
    }

    public function actionConfirm($id, $key)
    {
        $user = \common\models\User::find()->where([
            'id'      => $id,
            'auth_key'=> $key,
            'status'  => 0
        ])->one();
        
        if(!empty($user)){
            $user->status=10;
            $user->save();
            Yii::$app->getSession()->setFlash('success','Success!');
        }
        else{
            Yii::$app->getSession()->setFlash('warning','Failed');
        }

        return $this->goHome();
    }


    public function actionRequestresetpassword() {

        if( isset( $this->request["username"] )) {
            if( ! $this->request["username"]) {
                Yii::$app->api->sendFailedResponse("Username cannot be blank");
            }
        }
    
        $user = User::findByUsername($this->request["username"]);
        if(!$user) {
            $user =  User::findByEmail($this->request["username"]);
        }
        
        if(!$user) {
            Yii::$app->api->sendFailedResponse("User not found");
        }
       
        $username = $user->username;

        $token = $user->generatePasswordResetToken();
        $user->save();

        $url = Yii::$app->urlManager->createAbsoluteUrl(
            ['site/resetpassword','id'=>$user->id, 'key' => $user->password_reset_token]);

        $text = "Ola $username, resete sua senha clicando no link: $url";

        $confirmation_mail = $this->sendMail($user->email, "phpedromoutinho@gmail.com", $user->username, "Pedro Moutinho", "Resetar senha", $text);
     
        if($confirmation_mail) {
            Yii::$app->getSession()->setFlash('success','Success!');
        }
    }

    public function actionResetpassword($id, $key) {
        $user = User::findByPasswordResetToken($key);

        if($user->isPasswordResetTokenValid($key)) {
            $user->setPassword('123456');
            $user->removePasswordResetToken();
            $user->save();
            $data = array('password' => '123456');
            Yii::$app->api->sendSuccessResponse($data);
        }
        else {
            Yii::$app->api->sendFailedResponse("Reset token expired.");
        }
    }

    public function actionMe()
    {
        $data = Yii::$app->user->identity;
        $data = $data->attributes;
        unset($data['auth_key']);
        unset($data['password_hash']);
        unset($data['password_reset_token']);

        Yii::$app->api->sendSuccessResponse($data);
    }

    public function actionAccesstoken()
    {

        if (!isset($this->request["authorization_code"])) {
            Yii::$app->api->sendFailedResponse("Authorization code missing");
        }

        $two_steps = !! ( Yii::$app->user->identity['two_steps'] );

        if($two_steps) {
            if ( ! isset( $this->request["confirmation_code"] ) ) {
                Yii::$app->api->sendFailedResponse("Confirmation code missing.");
            }
        }

        $authorization_code = $this->request["authorization_code"];
        $confirmation_code  = ( $this->request["confirmation_code"] ) ? $this->request["confirmation_code"] : '';

        $auth_code = AuthorizationCodes::isValid($authorization_code, $two_steps, $confirmation_code);

        if (!$auth_code) {
            Yii::$app->api->sendFailedResponse("Invalid Authorization Code");
        }

        $accesstoken = Yii::$app->api->createAccesstoken($authorization_code);

        $data = [];
        $data['access_token'] = $accesstoken->token;
        $data['expires_at'] = $accesstoken->expires_at;
        Yii::$app->api->sendSuccessResponse($data);

    }

    public function actionAuthorize()
    {
        $model = new LoginForm();

        $model->attributes = $this->request;

        if ($model->validate() && $model->login()) {
            $auth_code = Yii::$app->api->createAuthorizationCode(Yii::$app->user->identity['id']);

            if(Yii::$app->user->identity['two_steps']) {
                $this->sendMessage($auth_code->confirmation_code, Yii::$app->user->identity['phone']);
            }

            $data = [];
            $data['authorization_code'] = $auth_code->code;
            $data['expires_at'] = $auth_code->expires_at;
            $data['two_steps']  = !!(Yii::$app->user->identity['two_steps']);

            Yii::$app->api->sendSuccessResponse($data);
        } else {
            Yii::$app->api->sendFailedResponse($model->errors);
        }
    }

    public function sendMessage($code, $phone) {
        $sid    = 'AC7b282a69e5bbe1f65656d6b68b90aec4';
        $token  = '2edb22c05e4f936d4709887fa7445a76';
        $client = new Client($sid, $token);

        // Use the client to do fun stuff like send text messages!
        return $client->messages->create(
            "+$phone",
            array(
                'from' => '+14058885864',
                'body' => "Adopets. Seu código de acesso  é: $code"
            )
        );
    }

    public function actionLogout()
    {
        $headers = Yii::$app->getRequest()->getHeaders();
        $access_token = $headers->get('x-access-token');

        if(!$access_token){
            $access_token = Yii::$app->getRequest()->getQueryParam('access-token');
        }

        $model = AccessTokens::findOne(['token' => $access_token]);

        if ($model->delete()) {

            Yii::$app->api->sendSuccessResponse(["Logged Out Successfully"]);

        } else {
            Yii::$app->api->sendFailedResponse("Invalid Request");
        }
    }
}
