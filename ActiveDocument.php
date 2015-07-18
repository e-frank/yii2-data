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

    public $relations          = [];
    public $useTransaction     = true;
    public $defaultIncremental = false;
    public $defaultIgnoreError = false;
    public $defaultDelete      = false;
    public $defaultScenario    = null;
    public $defaultSkipUpdate  = false;

    public $model              = null;



    public static function attach(&$model, $options) {
        $model->attachBehavior(self::ACTIVE_DOCUMENT, new \x1\data\behaviors\ActiveDocumentBehavior($options));
    }


    private function getConfig() {
        return [
            'useTransaction'     => $this->useTransaction,
            'defaultIncremental' => $this->defaultIncremental,
            'defaultIgnoreError' => $this->defaultIgnoreError,
            'defaultDelete'      => $this->defaultDelete,
            'defaultScenario'    => $this->defaultScenario,
            'defaultSkipUpdate'  => $this->defaultSkipUpdate,
            'relations'          => $this->relations,
        ];
    }


    private function checkModel() {
        if (empty($this->model))
            throw new \yii\base\InvalidConfigException("model missing");
    }

    public function init() {
        if (is_string($this->model))
            $this->model = new $this->model();

        $this->checkModel();
        self::attach($this->model, $this->getConfig());
    }

    public function findOne($key) {
        $result = $this->model->findWithRelations($key);
        if ($result)
            self::attach($result, $this->getConfig());
        return $result;
    }

    public function load($data, $formName = null) {
        $this->checkModel();
        return $this->model->load($data, $formName);
    }

}
