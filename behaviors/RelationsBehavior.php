<?
namespace x1\data\behaviors;

use yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use common\models\Page;

class RelationsBehavior extends Behavior
{
	public $related = [];

	private $_collections = [];
	private $_relations = [];

	public $relations = [];

	public function events()
	{
		return [
			ActiveRecord::EVENT_AFTER_VALIDATE => 'afterValidate',
		];
	}

	public function getCollections() {
		return $this->_collections;
	}

	public function setCollections($collections) {
		foreach ($collections as $key => $value) {
			var_dump($key);
			$this->$key = 123;
		}
		$this->_collections = array_merge_recursive($this->_collections, $collections);

		return $this->_collections;
	}

	public function setTest2s($x) {
return $x . $x . print_r($this->relations, true);
	}

	public function afterValidate() {

	}

	public function bar() {
		// var_dump($this->_relations);
		return 'my behavior foo' . print_r($this->_relations);
	}


	public function __set($name, $value) {
		if (in_array($name, $this->relations)) {
			$this->_relations[$name] = $value;
		}
	}

	public function __get($name) {
		var_dump('behav asd'); die();
		if (in_array($name, $this->relations)) {
			return $this->_relations[$name];
		}
	}

	public function __call($name, $arguments) {
		var_dump($name);
		die('asd');
	}

}

?>