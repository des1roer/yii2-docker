<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
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
        return $this->render('index');
    }

    public function actionPublish()
    {
        $redisClient = new \Predis\Client([
            'scheme' => 'tcp',
            'host'   => 'bd_redis',
            'port'   => 6379
        ]);

        // Accept message
        $message = json_encode(['message' => time()]);
        $success = false;

        if ($message) {
            try {
                // Publish to 'message_update' channle whenever there is a new message
                $redisClient->publish('message_update', $message);
                $success = true;
            } catch (\Exception $e) {
                $message = $e->getMessage();
            }
        }

        header("Content-Type: application/json");

        return json_encode(['success' => $success, 'message' => ($message)]);
    }

    public function actionSse()
    {
        $redisClient = new \Predis\Client([
            'scheme' => 'tcp',
            'host'   => 'bd_redis',
            'port'   => 6379,
        ]);

        ini_set('default_socket_timeout', -1);


        set_time_limit(0);
        header('Content-Type: text/event-stream');
        header('Connection: keep-alive');
        header('Cache-Control: no-store');

        header('Access-Control-Allow-Origin: *');
        echo 'retry: 30000' . PHP_EOL;

        $pubsub = $redisClient->pubSubLoop();
        $pubsub->subscribe('message_update');  // Subscribe to channel named 'message_update'

        foreach ($pubsub as $message) {
            file_put_contents(__DIR__.DIRECTORY_SEPARATOR.date('Y-m-d').'_log.log', '['.date('H:i:s').']'. __METHOD__ .__LINE__.PHP_EOL.print_r([
                $message
            ], true).PHP_EOL, FILE_APPEND | LOCK_EX);
            switch ($message->kind) {
                case 'subscribe':
                    $data = "Subscribed to {$message->channel}";
                    break;

                case 'message':
                    $data = date('Y-m-d H:i:s') . ": " . $message->payload;
                    break;
            }

            echo "data: " . $data . "\n\n";

            ob_flush();
            flush();
        }

        unset($pubsub);
    }

    public function actionSimpleSse()
    {
        $this->setHeaders();
        self::sendMsg(time(), time());
    }

    /**
     * Constructs the SSE data format and flushes that data to the client.
     *
     * @param string $id Timestamp/id of this connection.
     * @param string $msg Line of text that should be transmitted.
     **/
    private static function sendMsg(string $id, string $msg): void
    {
        echo "data: $msg" . PHP_EOL;
        echo "id: $id" . PHP_EOL;
        echo PHP_EOL;
        ob_flush();
        flush();
    }

    /**
     * @return void
     */
    public function setHeaders(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache'); // recommended to prevent caching of event data.
        echo 'retry: 1000' . PHP_EOL;
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        var_dump(
          1
        );die();
        return $this->render('about');
    }
}
