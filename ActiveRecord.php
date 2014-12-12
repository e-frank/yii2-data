<?php
namespace x1\data;

use Yii;
use yii\helpers\Url;

class ActiveRecord extends \yii\db\ActiveRecord
{

    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => \yii\behaviors\TimestampBehavior::className(),
            ],
            'group' => [
                'class' => \common\behaviors\GroupBehavior::className(),
            ],
            'owner' => [
                'class' => \common\behaviors\OwnerBehavior::className(),
            ],
        ];
    }

	public static function checkAccess() {
		if (!Yii::$app->user->identity)
			return Yii::$app->getResponse()->redirect(Url::to('site/login'));
	}

    public function beforeSave($insert) {
    	self::checkAccess();
        if(parent::beforeSave($insert))
        {
            if($this->isNewRecord) {
                $this->group_id   = Yii::$app->user->identity->group_id;
                $this->created_at = time();
            }
            else
            {
                $this->updated_at = time();
            }
            return true;
        }

        return false;
    }


	public static function current($query)
	{
    	self::checkAccess();
        $alias = self::tableName();
        return $query->where($alias.'.group_id IS NULL OR '.$alias.'.group_id=' .Yii::$app->user->identity->group_id);
        // return $query->andWhere([$alias.'.group_id' => Yii::$app->user->identity->group_id]);
	}

    public static function find() {
    	self::checkAccess();
    	$query = parent::find();
        return self::current($query);
    }

}

?>