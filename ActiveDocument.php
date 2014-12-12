<?php
namespace x1\data;

use Yii;
use yii\base\NotSupportedException;
use yii\db\ActiveRecord;


/**
 * The ActiveDocument class provides a wrapper/helpers to attach
 * the ActiveDocumentBehavior at runtime to ActiveRecords.
 * 
 * Example:
 *
 * $config =  [
 *              'defaultDelete'      => true,
 *              'defaultIgnoreError' => false,
 *              'config'             => [
 *                      'relations' => [
 *                          'mySubItems' => [
 *                              'incremental' => false,
 *                              'delete'      => true,
 *                              'relations'   => [
 *                                  'moreSubItems' => [
 *                                      'incremental' => false, 
 *                                  ],
 *                              ],
 *                          ],
 *                      ],
 *                  ],
 *              ];
 *
 * ActiveDocument::find($model, $config, ['id' => 1])
 * 
 */
class ActiveDocument extends yii\base\Model
{
    const ACTIVE_DOCUMENT = 'ActiveDocument';

    public $config = [];
    public $model  = [];



    public static function attach(&$model, $options) {
        $model->attachBehavior(self::ACTIVE_DOCUMENT, new \common\models\behaviors\ActiveDocumentBehavior($options));
    }


    public static function find($model, $options, $key) {
        if (is_string($model))
            $model = new $model();

        self::attach($model, $options);
        $model = $model->findWithRelations($key);
        if ($model) {
            self::attach($model, $options);
        }
        return $model;
    }

}
