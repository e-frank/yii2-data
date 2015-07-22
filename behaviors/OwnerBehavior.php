<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace x1\data\behaviors;

use Yii;
use yii\base\Event;
use yii\db\BaseActiveRecord;

/**
 * OwnerBehavior works just like BlameableBehavior,
 * except the columns for createdby and updater are optional.
 * also, in a console application, both attributes are ignored
 */

/*

class myModel extends \yii\db\ActiveRecord
{

    public function behaviors()
    {
        return [
            'owner' => [
                'class' => \x1\data\behaviors\OwnerBehavior::className(),
            ],
        ];
    }
    
}

 */
class OwnerBehavior extends \yii\behaviors\BlameableBehavior
{
    public $createdByAttribute = 'user_id';
    public $updaterAttribute   = 'updater_id';


    public function getUserName() {
        return ($this->owner->user == null) ? null : $this->owner->user->name;
    }

    public function getUpdaterName() {
        return ($this->owner->updater == null) ? null : $this->owner->updater->name;
    }

    public function getUser() {
        return $this->owner->hasOne(Yii::$app->user->identityClass, ['id' => $this->createdByAttribute]);
    }

    public function getUpdater() {
        return $this->owner->hasOne(Yii::$app->user->identityClass, ['id' => $this->updaterAttribute]);
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (!Yii::$app instanceof \yii\console\Application) {
            if (!empty($this->createdByAttribute))
                $this->attributes[BaseActiveRecord::EVENT_BEFORE_INSERT] = $this->createdByAttribute;

            if (!empty($this->updaterAttribute))
                $this->attributes[BaseActiveRecord::EVENT_BEFORE_UPDATE] = $this->updaterAttribute;
        }
    }

}
