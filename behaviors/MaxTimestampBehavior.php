<?
namespace x1\data\behaviors;

use yii;
use yii\base\Behavior;
use yii\behaviors\AttributeBehavior;
use yii\base\InvalidCallException;
use yii\db\BaseActiveRecord;
use yii\db\Expression;

class MaxTimestampBehavior extends Behavior
{

    public function init()
    {
        parent::init();
    }	
    
	public function getMaxTimestamp() {
        $self = $this->owner;
        $date = (empty($self->updated_at) ? $self->created_at : $self->updated_at);
        return $date;
	}

}

?>