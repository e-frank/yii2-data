<?php
namespace x1\data;

use Yii;
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

    	\yii\validators\Validator::$builtInValidators['datetime'] = [
    		'class'  => 'yii\validators\DateValidator',
    		'format' => 'yyyy-M-d H:m:s',
    	];

    	\yii\validators\Validator::$builtInValidators['date'] = [
    		'class'  => 'yii\validators\DateValidator',
    		'format' => 'yyyy-M-d',
    	];

    }
}
