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

/*

class myModel extends \yii\db\ActiveRecord
{

    public function behaviors()
    {
        return [
            'group' => [
                'class'     => \x1\data\behaviors\GroupBehavior::className(),
                'map'       => ['gid' => 'group_id'],
                'className' => \common\models\Group::className(),
            ],
        ];
    }

}

 */
class GroupBehavior extends \yii\behaviors\AttributeBehavior
{
    public $map       = ['gid' => 'group_id'];
    public $className = null;
    public $value;


    public function getGroup() {
        return $this->owner->hasOne($this->className, $this->map);
    }


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if ($this->className == null) {
            throw new \yii\base\InvalidConfigException("'className' must be set");
        }

        if (!is_array($this->map)) {
            throw new \yii\base\InvalidConfigException("'map' must be an array; e.g.: ['gid' => 'group_id']");
        } else {
            if (!count($this->map) > 0) {
                throw new \yii\base\InvalidConfigException("'map' must contain the mapping group => local; e.g.: ['gid' => 'group_id']");
            }
        }

        if (!Yii::$app instanceof \yii\console\Application) {
            if (empty($this->attributes)) {
                $this->attributes = [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => array_values($this->map)[0],
                ];
            }
        }
    }

    /**
     * Evaluates the value of the user.
     * The return result of this method will be assigned to the current attribute(s).
     * @param Event $event
     * @return mixed the value of the user.
     */
    protected function getValue($event)
    {
        if ($this->value === null) {
            $user  = Yii::$app->get('user', false);
            $group = array_keys($this->map)[0];
            return ($user && !$user->isGuest) ? $user->identity->group->$group : null;
        } else {
            return call_user_func($this->value, $event);
        }
    }

}
