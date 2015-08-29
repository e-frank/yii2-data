<?php
namespace x1\data\validators;
use yii\helpers\ArrayHelper;

class Validator extends \yii\validators\Validator {

    public static $builtInValidators = [
        'boolean'  => 'yii\validators\BooleanValidator',
        'captcha'  => 'yii\captcha\CaptchaValidator',
        'compare'  => 'yii\validators\CompareValidator',
        'date'     => 'x1\data\validators\DateValidator',
        'datetime' => 'x1\data\validators\DatetimeValidator',
        'default'  => 'yii\validators\DefaultValueValidator',
        'double'   => 'yii\validators\NumberValidator',
        'each'     => 'yii\validators\EachValidator',
        'email'    => 'yii\validators\EmailValidator',
        'exist'    => 'yii\validators\ExistValidator',
        'file'     => 'yii\validators\FileValidator',
        'filter'   => 'yii\validators\FilterValidator',
        'image'    => 'yii\validators\ImageValidator',
        'in'       => 'yii\validators\RangeValidator',
        'integer'  => [
            'class'       => 'yii\validators\NumberValidator',
            'integerOnly' => true,
        ],
        'match'    => 'yii\validators\RegularExpressionValidator',
        'number'   => 'yii\validators\NumberValidator',
        'required' => 'yii\validators\RequiredValidator',
        'safe'     => 'yii\validators\SafeValidator',
        'string'   => 'yii\validators\StringValidator',
        'trim'     => [
            'class'       => 'yii\validators\FilterValidator',
            'filter'      => 'trim',
            'skipOnArray' => true,
        ],
        'unique' => 'yii\validators\UniqueValidator',
        'url'    => 'yii\validators\UrlValidator',
    ];

}