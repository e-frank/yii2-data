<?
namespace x1\data\behaviors;

use yii;
use yii\base\Behavior;
use yii\helpers\ArrayHelper;
use yii\db\ActiveRecord;

/**
 * If this behavior is attached to an ActiveRecord,
 * all relations defined in the configuration array are 
 * subsequently managed (load/link/unlink/delete).
 * 
 * Example:
 *
 * 	public function behaviors() {
 *		return [
 *			'ActiveDocument' => [
 *			    'class'              => ActiveDocumentBehavior::className(),
 *			   	'defaultDelete'      => false,
 *			   	'defaultIgnoreError' => false,
 *			    'config'             => [
 *				        'relations' => [
 *				            'mySubItems' => [
 *				            	'incremental' => false, 
 *				            	'delete'      => true, 
 *				            	'relations'   => [
 *									'moreSubItems' => [
 *										'incremental' => false, 
 *									],
 *				            	],
 *				            ],
 *				        ],
 *					],
 *			    ]
 *		];
 *	}
 * 
 */
class ActiveDocumentBehavior extends Behavior
{
	const TRANSACTION         = 'transaction';
	const RELATIONS           = 'relations';
	const SCENARIO            = 'scenario';
	const FORMNAME            = 'formName';
	const DELETE              = 'delete';
	const REMOVE              = '_remove';
	const LINK                = '_link';
	const INCREMENTAL         = 'incremental';
	const IGNORE_ERROR        = 'ignoreError';
	const SKIP_UPDATE         = 'skipUpdate';
	const SORTABLE            = 'sortable';
	const CONFIG              = 'config';
	const DEFAULT_DELETE      = 'defaultDelete';
	const DEFAULT_SCENARIO    = 'defaultScenario';
	const DEFAULT_SKIP_UPDATE = 'defaultSkipUpdate';


	public $relations          = [];
	public $useTransaction     = true;
	public $defaultIncremental = false;
	public $defaultIgnoreError = false;
	public $defaultDelete      = true;
	public $defaultScenario    = null;
	public $defaultSkipUpdate  = false;
	private $_delete           = [];
	private $_relations        = [];
	private $_transaction      = null;

	/**
	 * Marker function to detect if this behavior is enabled on a model
	 * @return boolean [description]
	 */
	public function isActiveDocument() {
		return true;
	}


	/**
	 * Registers events.
	 * @internal
	 */
	public function events()
	{
		return [
			ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeActiveDocumentSave',
			ActiveRecord::EVENT_AFTER_UPDATE  => 'afterActiveDocumentSave',
			ActiveRecord::EVENT_BEFORE_INSERT => 'beforeActiveDocumentSave',
			ActiveRecord::EVENT_AFTER_INSERT  => 'afterActiveDocumentSave',
		];
	}

	/**
	 * Begins a new transaction on ActiveRecord::EVENT_BEFORE_INSERT and ActiveRecord::EVENT_BEFORE_UPDATE
	 * @param  yii\base\ModelEvent   $event 
	 * @internal
	 */
	public function beforeActiveDocumentSave($event) {
		if ($this->useTransaction)
			$this->_transaction = $this->owner->db->beginTransaction();
		else
			$this->_transaction = null;
	}

	/**
	 * Do commit or rollback on save.
	 * @param  yii\base\ModelEvent   $event
	 * @internal
	 */
	public function afterActiveDocumentSave($event) {
		$config = [self::RELATIONS => &$this->relations];
		$result = $this->saveRelations($this->owner, $config);
		
		if (!empty($this->_transaction)) {
			if ($result) {
				$this->_transaction->commit();
			} else {
				$this->_transaction->rollback();
			}
		}
	}


	/**
	 * Do commit or rollback on save.
	 * @param  yii\base\ModelEvent   $event
	 * @internal
	 */
	private function saveRelations(&$model, &$config) {
		// var_dump(['method' => __METHOD__ , 'model' => get_class($model), 'config' => $config]);
		if (empty($config)) {
			return true;
		}

		$result = true;


		if (array_key_exists(self::RELATIONS, $config))
			$relations =& $config[self::RELATIONS];
		else
			$relations = [];

		foreach ($relations as $relation => & $subconfig) {
			$remove = ArrayHelper::getValue($subconfig, self::REMOVE, []);
			$delete = ArrayHelper::getValue($subconfig, self::DELETE, $this->defaultDelete);

			foreach ($remove as $key => $itemToRemove) {
				$model->unlink($relation, $itemToRemove, $delete);
			}

			$result = $result && $this->saveRelation($model, $relation, $subconfig);
		}
		return $result;
	}


	/**
	 * Saves a single model within a relation.
	 * via: 			item is saved, then linked
	 * direct link:		item is saved by linking
	 * existing models:	
	 * @param  ActiveRecord $model  	base model
	 * @param  string $relationName		name of the relation
	 * @param  boolean $isVia        	relation via table
	 * @param  ActiveRecord $item       the model in the relation
	 * @param  array $config       		configuration array
	 * @return boolean               	save success
	 * @internal
	 */
	private function saveRelationModel(&$model, $relationName, $isVia, &$item, &$config) {
		$result     = true;
		$skipUpdate = ArrayHelper::getValue($config, self::SKIP_UPDATE, $this->defaultSkipUpdate);

		if ($isVia) {
			if ($item->isNewRecord) {
				$result = $result && $item->save();
				$model->link($relationName, $item);
			} else {

				if (!$skipUpdate) {
					$result = $result && $item->save();
				}

				$findInRelation = $model->getRelation($relationName)->andWhere($item->getPrimaryKey(true))->exists();
				if (!$findInRelation) {
					$model->link($relationName, $item);
				}
			}
		} else {
			if ($item->isNewRecord) {
				$model->link($relationName, $item);
			} else {
				if (!$skipUpdate) {
					// save linked item only, if something changed
					if (count($item->getDirtyAttributes()) > 0)
						$item->save();
				}
			}
		}

		$result = $result && $this->saveRelations($item, $config);

		return $result;
	}

	
	/**
	 * Saves an 1:n or 1:1 relation for an ActiveRecord and maintains its links.
	 * @param  ActiveRecord   $model 			current model
	 * @param  string         $relationName		name of the models relation
	 * @param  array          $config 			config for the relation
	 * @return boolean							update success
	 * @internal
	 */
	private function saveRelation(&$model, $relationName, &$config) {

		$result                  = true;
		$rel                     = $model->getRelation($relationName);

		if ($rel->multiple) {
			foreach ($model->$relationName as $item) {
				$result = $result && $this->saveRelationModel($model, $relationName, $rel->via, $item, $config);
			}
		} else {
			$item   = $model->$relationName;
			$result = $result && $this->saveRelationModel($model, $relationName, $rel->via, $item, $config);
		}

		return $result;
	}


	private function getConfig() {
		return [
		'relations'          => &$this->relations,
		'useTransaction'     => $this->useTransaction,
		'defaultIncremental' => $this->defaultIncremental,
		'defaultIgnoreError' => $this->defaultIgnoreError,
		'defaultDelete'      => $this->defaultDelete,
		'defaultScenario'    => $this->defaultScenario,
		'defaultSkipUpdate'  => $this->defaultSkipUpdate,
		];		
	}

	/**
	 * Prepare config when the model is attached.
	 * @param  Model   $owner
	 * @internal
	 */
	public function attach($owner) {
		$this->_relations = array_keys($this->relations);
		parent::attach($owner);
		$config = $this->getConfig();
		$this->addRelationValidator($this->owner, $config);
	}


	/**
	 * Adds the RelationValidator to the model.
	 * @param  yii\base\ModelEvent   $event
	 * @internal
	 */
	private function addRelationValidator(&$model, &$config) {
		$relations = ArrayHelper::getValue($config, self::RELATIONS, []);
		$keys      = array_keys($relations);

		$validators = $model->getValidators();
		$validators->append(\yii\validators\Validator::createValidator(\x1\data\validators\RelationValidator::className(), $model, (array) $keys, [
			'config'              => $config,
			'activeDocumentClass' => get_class($this),
			'on'                  => ArrayHelper::getValue($config, self::SCENARIO, null),
			]));
	}



	/**
	 * Gets the connected, non-incremental relation names.
	 * So you can use the base model's 'with' to preload (left join) relations in one select statement.
	 * @param  string $base prefix for the relation name
	 * @param array $config configuration array
	 * @internal
	 */
	private function loadRelations($base, &$config) {
		$relations    = ArrayHelper::getValue($config, self::RELATIONS, []);
		$complete     = [];

		foreach ($relations as $relation => $subconfig) {
			if (!ArrayHelper::getValue($subconfig, self::INCREMENTAL, false)) {
				$next       = empty($base) ? $relation : $base . '.' . $relation;
				$complete[] = $next;
				$complete   = array_merge($complete, $this->loadRelations($next, $subconfig));
			}
		}

		return $complete;
	}


	// get table alias (see ActiveQuery->getQueryTableName)
	private function getQueryAlias($owner, $query) {
		if (empty($query->from)) {
            $tableName = $owner::tableName();
        } else {
            $tableName = '';
            foreach ($query->from as $alias => $tableName) {
                if (is_string($alias)) {
                    return $alias;
                } else {
                    break;
                }
            }
        }

        if (preg_match('/^(.*?)\s+({{\w+}}|\w+)$/', $tableName, $matches)) {
            $alias = $matches[2];
        } else {
            $alias = $tableName;
        }

        return $alias;
	}

	/**
	 * Finds a record with connected, non-incremental relations.
	 * @param  mixed $key primary key as scalar value or array
	 * @return ActiveRecord      found ActiveRecord or null
	 */
	public function findWithRelations($key) {

		$owner = $this->owner;
		$query = $owner::find();
		$alias = $this->getQueryAlias($owner, $query);

		if (!ArrayHelper::isAssociative($key)) {
            // query by primary key
			$primaryKey = $owner::primaryKey();
			if (isset($primaryKey[0])) {
				$key = [$alias . '.' . $primaryKey[0] => $key];
			} else {
				throw new InvalidConfigException(get_called_class() . ' must have a primary key.');
			}
		} else {
			foreach ($key as $k => $value) {
				$key[$alias . '.' .$k] = $value;
				unset($key[$k]);
			}
		}



		$find      = $query->andWhere($key);
		$config    = [self::RELATIONS => &$this->relations];
		$relations = $this->loadRelations(null, $config);

		foreach ($relations as $key => $value) {
			$find->with($value);
		}

		$model = $find->one();
		if (!$model == null) {
			$this->attach($model, $this->getConfig());
		}

		return $model;
	}


	/**
	 * Captures relation setters.
	 * Relation setters are only triggered, if a validation rule is applied to the relation property.
	 * Those validators are automatically added on 'attach'.
	 * 
	 * @param  string  $name      the property's name
	 * @param  boolean $checkVars 
	 * @param  array   $config 
	 * @internal
	 */
	public function canSetProperty($name, $checkVars = true, $checkBehaviors = true)
	{
		if (in_array($name, $this->_relations)) {
			return true;
		}

		return parent::canSetProperty($name, $checkVars, $checkBehaviors);
	}


	/**
	 * Loads a model with passed $data or creates new one
	 * @param  string  $className class name of the model
	 * @param  string  $scenario  scenario of the model
	 * @param  array   $data      data to load, including relational data
	 * @param  array   $config    configuration array
	 * @param  boolean $recursive internal use to detect recursion. Avoids recursive load, if subitems are already ActiveDocuments.
	 * @param  boolean $incrementalParent internal use to preload relations
	 * @return ActiveRecord             existing or fresh model, loaded with $data
	 * @internal
	 */
	private function loadModel($className, $scenario, $data, &$config, $recursive = true, $incrementalParent = false) {
		$model = empty($scenario) ? new $className : new $className([self::SCENARIO => $scenario]);

		if (!is_array($data))
		{
			throw new \yii\base\InvalidParamException(sprintf('data must be of type array for model %s in relation %s', $model->className(), $relationName));
		}
		$keys = array_intersect_key($data, $model->getPrimaryKey(true));

		if ($incrementalParent) {
			$query     = $model->find();
			$find      = $query->andWhere($key);
			$relations = $this->loadRelations(null, $config);

			foreach ($relations as $key => $value) {
				$find->with($value);
			}

			// TODO ???
			$result = $find->one();
		} else {
			$result = $className::findOne($keys);
		}

		// try to find by key
		if ($result !== null) {
			if (!empty($scenario))
				$model->setScenario($scenario);
			$model = $result;
		}

		// $this->addRelationValidator($model, $config);

		return $this->loadExistingModel($model, $scenario, $data, $config, $recursive);
	}

	/**
	 * Loads an already instantiated model with passed $data
	 * @param  string  $className class name of the model
	 * @param  string  $scenario  scenario to use
	 * @param  array   $data      data to load, including relational data
	 * @param  array   $config    configuration array
	 * @param  boolean $recursive internal use to detect recursion. Avoids recursive load, if sub items are ActiveDocuments.
	 * @return ActiveRecord             existing or fresh model, loaded with $data
	 * @internal
	 */
	private function loadExistingModel(&$model, $scenario, $data, &$config, $recursive = true) {
		// set scenario
		if (!empty($scenario))
			$model->setScenario($scenario);


		$safe = array_intersect_key($data, array_flip($model->safeAttributes()));

		if ($recursive) {
			$model->load($safe, '');
			$this->setRelations($model, $data, $config);
		} else {
			$model->load($safe, '');
		}

		return $model;
	}



	/**
	 * Converts a model's (composite) primary key to string.
	 * @param  ActiveRecord  $model model
	 * @return string        serialized primary key
	 * @internal
	 */
	private function serializeKey(&$model) {
		return serialize($model->getPrimaryKey());
	}



	/**
	 * Loads all model relations with $data
	 * @param  ActiveRecord       $model model
	 * @param  array   $data      data to load, including relational data
	 * @param  array   $config    configuration array
	 * @internal
	 */
	private function setRelations(&$model, &$data, &$config) {
		if (isset($config[self::RELATIONS]) && !empty($config[self::RELATIONS])) {
			$relations = & $config[self::RELATIONS];

			foreach ($relations as $key => $value) {
				if (array_key_exists($key, $data))
					$this->setRelation($model, $key, $data[$key], $config);
			}
		}
	}


	/**
	 * Loads a specific relation with $data.
	 * incremental: 		existing subitems are neither removed nor unlinked.
	 * non-incremental:		existing (loaded) subitems are unlinked and/or deleted.
	 * 
	 * @param  ActiveRecord  $model 			model
	 * @param  string        $relationName      the relation's name
	 * @param  array         $data              data to load, including relational data
	 * @param  array         $config            configuration array
	 * @internal
	 */
	private function setRelation(&$model, $relationName, &$data, &$config) {
		if (!$model->hasProperty(($relationName)))
			throw new \yii\base\UnknownPropertyException(sprintf('model {%s} has no relation {%s}', $model->className(), $relationName));

		$relation     = & $config[self::RELATIONS][$relationName];
		$formName     = ArrayHelper::getValue($relation, self::FORMNAME, false);
		$scenario     = ArrayHelper::getValue($relation, self::SCENARIO, $this->defaultScenario);
		$incremental  = ArrayHelper::getValue($relation, self::INCREMENTAL, $this->defaultIncremental);
		$delete       = ArrayHelper::getValue($relation, self::DELETE, $this->defaultDelete);
		$relationData = ($formName == false) ? $data : $data[$formName];
		$rel          = $model->getRelation($relationName);
		$pattern      = new $rel->modelClass;
		$recursive    = !$pattern->hasMethod('isActiveDocument');


		$models                 = null;
		$relation[self::REMOVE] = [];
		$relation[self::LINK]   = [];

		// relation is a collection or a single component
		if ($rel->multiple) {
			$models = [];
			if ($incremental) {
				// loop through array data and load sub models
				foreach ($relationData as $key => $value) {
					$m = $this->loadModel($rel->modelClass, $scenario, $value, $relation, $recursive); 
					$models[] = $m;
				}
			} else {

				$sort  = ArrayHelper::getValue($relation, self::SORTABLE, null);
				if ($sort !== null) {
					$index = 0;

					foreach ($relationData as $key => &$value) {
						$relationData[$key][$sort] = $index++;
					}
				}

				// loop through relation data, load data and detect removable sub models
				foreach ($model->$relationName as $item) {

					$keys = $item->getPrimaryKey(true);

					// try to find subitem in data 
					reset($relationData);
					$found = false;
					foreach ($relationData as $key => &$value) {
						// normalize
						if (!empty($formName))
							$value = $value[$formName];


						$modelKeys = array_intersect_key($value, $keys);
						if (count(array_diff_assoc($modelKeys, $keys)) == 0) {

							$m        = $this->loadExistingModel($item, $scenario, $value, $relation, $recursive); 
							$models[] = $m;
							$found    = true;

							// processed, so remove from data array
							unset($relationData[$key]);
							break;
						}
					}

					// we have an existing item, but it was not loaded by $data, so mark for remove.
					if (!$found) {
						$relation[self::REMOVE][] = $item;
					}
				}


				// everything left in $relationData is new model data
				// model might be existing, but not linked
				foreach ($relationData as $key => $value) {
					// normalize
					if (!empty($formName))
						$value = $value[$formName];

					$m        = $this->loadModel($rel->modelClass, $scenario, $value, $relation, $recursive); 
					$models[] = $m;
					$relation[self::LINK][$this->serializeKey($model)][] = $m;
				}

			}
		} else {

			// relation is a single component
			$oldItem = $model->$relationName;
			$models  = $this->loadModel($rel->modelClass, $scenario, $value, $relation, $recursive); 

			if (!$incremental) {
				if ($oldItem !== null) {
					$keys    = $oldItem->getPrimaryKey(true);

					if ($models !== null) {
						$modelKeys = $models->getPrimaryKey(true);
						if (count(array_diff_assoc($keys, $modelKeys)) !== 0) {
							$relation[self::REMOVE][] = $oldItem;
						}
					} else {
						$relation[self::REMOVE][] = $models;
					}
				}
			}
		}

		if ($models !== null)
			$model->populateRelation($relationName, $models);
	}


	/**
	 * Magic property setter for relations
	 * @param  string  $name      property name
	 * @param  mixed   $value     value
	 * @internal
	 */
	public function __set($name, $value)
	{

		if (in_array($name, $this->_relations))
		{
			// start setting relation data
			$config = [self::RELATIONS => &$this->relations];

			$this->setRelation($this->owner, $name, $value, $config);
			return $this->owner;
		}

		return parent::__set($name, $value);
	}


	private function relationConfigHelper(&$relations) {
		if (!ArrayHelper::isAssociative($relations)) {
			$r2 = [];
			foreach ($relations as $key) {
				$r2[$key] = [];
			}
			$relations = $r2;
		} else {
			foreach ($relations as $key => &$value) {
				if (isset($relations[$key][self::RELATIONS]))
					$this->relationConfigHelper($relations[$key][self::RELATIONS]);
			}
		}
	}

	public function init() {
		$this->relationConfigHelper($this->relations);
	}

	/**
	 * Recursively gets all errors for a model, including relations.
	 * The structure of the resulting error array matches the data on load.
	 * @param  ActiveRecord  $model model
	 * @param  array   $config    configuration array
	 * @param  boolean $first     indicates root level of recursion
	 * @return array   all errors
	 * @internal
	 */
	public function getErrorDocument(&$model = null) {
		$model = $this->owner;

		if ($model == null) {
			throw new Exception('"model" is not set');
			return null;
		}

		$config = [self::RELATIONS => &$this->relations];
		return $this->getErrorDocumentHelper($model, $config);
	}


	private function getErrorDocumentHelper(&$model, &$config, $first = true) {
		// get errors, except the one generated by RelationValidator
		$errors = array_diff_key($model->getErrors(), ArrayHelper::getValue($config, SELF::RELATIONS, []));

		if (array_key_exists(self::RELATIONS, $config))
			$relations = & $config[self::RELATIONS];
		else 
			$relations = [];

		foreach ($relations as $relation => &$subconfig) {
			$rel      = $model->getRelation($relation);
			$sortable = ArrayHelper::getValue($subconfig, SELF::SORTABLE, null);
			$index    = 0;

			if ($rel->multiple) {
				foreach ($model->$relation as $item) {
					$errors[$relation][$sortable == null ? $index++ : $item->$sortable] = $this->getErrorDocumentHelper($item, $subconfig, false);
				}

				if ($sortable !== null && $errors[$relation] !== null)
					ksort($errors[$relation]);

			} else {
				$errors[$relation] = $this->getErrorDocumentHelper($model->$relation, $subconfig, false);
			}

		}

		return $errors;
	}




	/**
	 * Recursively gets all errors for a model, including relations.
	 * The structure of the resulting error array matches the data on load.
	 * @param  ActiveRecord  $model model
	 * @param  array   $config    configuration array
	 * @param  boolean $first     indicates root level of recursion
	 * @return array   all errors
	 * @internal
	 */
	public function getDocument() {
		$model  = $this->owner;

		if ($model == null) {
			throw new Exception('there is no model');
			return null;
		}

		$config = [self::RELATIONS => &$this->relations];
		return $this->getDocumentHelper($model, $config);
	}

	private function getDocumentHelper(&$model = null, &$config = null) {
		$result = $model->toArray();

		if (array_key_exists(self::RELATIONS, $config))
			$relations = & $config[self::RELATIONS];
		else 
			$relations = [];

		foreach ($relations as $relation => &$subconfig) {

			$rel      = $model->getRelation($relation);
			$sortable = ArrayHelper::getValue($subconfig, SELF::SORTABLE, null);

			if ($rel->multiple) {
				foreach ($model->$relation as $key => $item) {
					$result[$relation][$sortable == null ? $key : $item->$sortable] = $this->getDocumentHelper($item, $subconfig);
				}

			} else {
				$result[$relation] = $this->getDocumentHelper($model->$relation, $subconfig);
			}
		}

		return $result;
	}




}

?>
