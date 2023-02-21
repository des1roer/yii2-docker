<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Publish to predis
 */
class PubController extends Controller
{
    /**
     * This command echoes what you have entered as the message.
     * @return int Exit code
     */
    public function actionIndex()
    {
        $redisClient = new \Predis\Client([
            'scheme' => 'tcp',
            'host'   => '127.0.0.1',
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

        echo $message . "\n";

        return ExitCode::OK;
    }
}
