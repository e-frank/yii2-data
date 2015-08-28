<?php
namespace x1\data;

use yii\base\BootstrapInterface;
use yii\base\Application;

class DataBootstrap implements BootstrapInterface
{


/**
 * set default validator formats to iso
 * @param  Application $app Application
 **/
    public function bootstrap($app)
    {

		\Yii::$container->set('yii\validators\DateValidator', [
			'class'  => 'yii\validators\DateValidator',
			'format' => 'yyyy-MM-dd HH:mm:ss',
			]);

    }
}
?>