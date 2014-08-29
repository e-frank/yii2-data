<?
namespace efrank\data;

use yii\helpers\ArrayHelper;

class Hydrator {

	public $config = [];
	private $_keys = [];

	public function __construct($config = []) {
		$this->config = $config;
	}


	// caches primary keys
	private function getPrimaryKeyFor($class, $model) {
		if (!in_array($class, $this->_keys)) {
			$this->_keys[$class] = array_flip($model->tableSchema->primaryKey);
		}
		return $this->_keys[$class];
	}

	// load model or create new
	public function loadModel(&$model, $data, $class = '') {
		if (empty($class))
			$class = get_class($model);
		$keys  = $this->getPrimaryKeyFor($class, $model);
		$pk    = array_filter(array_intersect_key($data, $keys));

		// try find by primary key
		if ($pk) {
			$m = $model->findOne($pk);
			if (!empty($m))
				$model = $m;
		}
		return $model->load($data, false);
	}


	// delete model and relational data
	public function delete(&$model, $config) {
		$relations = ArrayHelper::getValue($config, 'relations', []);
		foreach ($relations as $relation => $subconfig) {
			$delete      = ArrayHelper::getValue($subconfig, 'delete', false);
			$rel         = $model->getRelation($relation);
			foreach ($rel->all() as $subitem) {
				$model->unlink($relation, $subitem, true);
				if ($delete) {
					$this->delete($subitem, $subconfig);
				}
			}
		}

		return $model->delete();
	}


	// dig into connected relations to load data at once at root level
	private function loadRelations($base, $config) {
		$relations    = ArrayHelper::getValue($config, 'relations', []);
		$complete     = [];

		foreach ($relations as $relation => $subconfig) {
			$incremental           = ArrayHelper::getValue($subconfig, 'incremental', false);
			if (!$incremental) {
				$next       = empty($base) ? $relation : $base . '.' . $relation;
				$complete[] = $next;
				$complete   = array_merge($complete, $this->loadRelations($next, $subconfig));
			}
		}

		return $complete;
	}


/**
 * hydrates model with hierarchical data from request
 * if primary keys are set, the model is loaded, otherwise a new object is created
 * @param  Model  $model     model instance to be filled
 * @param  array  $data      hierarchical data from request
 * @param  array  $config    used for recursion
 * @param  boolean $first     used for recursion
 * @param  Model  $modeldata used for recursion
 * @return boolean             ok
 */
	public function hydrate(&$model, $data, $config = null, $first = true, &$modeldata = null) {

		$db       = $model->getDb();
		$config   = empty($config) ? $this->config : $config;
		$formName = ArrayHelper::getValue($config, 'formName', $model->formName());
		$data     = empty($formName) ? $data : $data[$formName];

		// begin transaction at root level
		if ($first) {
			$transaction = $db->beginTransaction();

			if ($ok = $this->loadModel($model, $data)) {
				$model->save();
			} else {
				$transaction->rollback();
				return false;
			}
		}


		$relations    = ArrayHelper::getValue($config, 'relations', []);
		

		// preload non-incremental relations at once
		if ($modeldata === null && count($relations) > 0) {
			$withs = $this->loadRelations(null, $config);
			if (!empty($withs)) {
				$find = $model::find();
				$keys = $this->getPrimaryKeyFor(get_class($model), $model);
				$q    = [];

				foreach ($keys as $key => $value) {
					$q[] = sprintf('%s=%s', $key, $db->quoteValue($model->$key));
				}
				$find->where(implode(' AND ', $q));
				foreach ($withs as $value) {
					$find->with($value);
				}
				$modeldata = $find->one();
			}
		}

		// assign relational data
		foreach ($relations as $relation => $subconfig) {
			$rel                   = $model->getRelation($relation);

			$class                 = $rel->modelClass;
			$subdata               = ArrayHelper::getValue($data, $relation, []);
			$subconfig['formName'] = ArrayHelper::getValue($subconfig, 'formName', false);
			$incremental           = $subconfig['incremental'] = ArrayHelper::getValue($subconfig, 'incremental', false);
			$delete                = $subconfig['delete'] = ArrayHelper::getValue($subconfig, 'delete', false);
			if (empty($modeldata))
				$submodeldata = null;
			else {
				$submodeldata = $modeldata->$relation;
			}

			// 1:n relations
			if ($rel->multiple) {

				$submodel = new $class;
				$db       = $submodel->getDb();
				$keys     = $this->getPrimaryKeyFor($class, $submodel);


				//	apply updates to existing models
				//	do not delete associated child items, if incremental = true
				if (!$incremental) {

					$composite      = count($keys) > 1;
					$akeys          = array_keys($keys);

					if (!empty($submodeldata)) {

						foreach ($submodeldata as $kkk => $existing) {
							$existingKey = $existing->getAttributes($akeys);

							// update already linked and existing models
							$found = false;
							foreach ($subdata as $key => $subitemdata) {
								$pk    = array_filter(array_intersect_key($subitemdata, $keys));
								if (!empty($pk)) {
									if (count(array_diff_assoc($existingKey, $pk)) == 0) {
										$existing->load($subitemdata, false);
										$existing->save();
										$this->hydrate($existing, $subitemdata, $subconfig, false, $existing);
										unset($subdata[$key]);
										$found = true;
										break;
									}
								}
							}
							if (!$found) {
								if ($rel->via !== null)
									$model->unlink($relation, $existing, true);
								if ($delete)
									$this->delete($existing, $subconfig);
							}
						}
					}
				}


				foreach ($subdata as $subitemdata) {
					$submodel = new $class;
					$ok       = $this->loadModel($submodel, $subitemdata, $class);
					if (!$ok)
						break;

					if ($rel->via !== null && $submodel->isNewRecord) {
						$submodel->save();
						$model->link($relation, $submodel);
					} else {


						if ($rel->via !== null) {

							$keys      = array_keys($this->getPrimaryKeyFor($class, $submodel));
							$keyValues = $submodel->getAttributes($keys);

							$a = array_map(function($a, $b) use ($db) { return sprintf('%s=%s', $a, $db->quoteValue($b)); }, $keys, $keyValues );
							$b = implode(' AND ', $a);
							$find = $model->getRelation($relation)->andWhere($b)->exists();
							if (!$find) {
								$model->link($relation, $submodel);
							}
							$submodel->save();
						} else {
							$model->link($relation, $submodel);
						}
					}

					$this->hydrate($submodel, $subitemdata, $subconfig, false, $submodeldata);
				}
			} else {
				// 1:1 relations
				if (!empty($subdata)) {
					if (empty($model->$relation)) {
						$submodel = new $class;
						$ok       = $this->loadModel($submodel, $subdata, $class);

						if ($rel->via !== null)
							$ok = $submodel->save();
						if ($ok && $submodel->isNewRecord) 
							$ok = $model->link($relation, $submodel);

					} else {
						$submodel = $model->$relation;
						$ok       = $submodel->load($subdata, false);
						if ($ok) {
							$ok = $submodel->save();
						}
					}
					if (!$ok)
						break;

					$this->hydrate($submodel, $subdata, $subconfig, false, $submodeldata);
				}
			}
		}

		if ($first) {
			$ok ? $transaction->commit() : $transaction->rollback();
		}

		return true;
	}

	// TODO: incomplete
	public function validate(&$model, $data, $config = null, $first = true, &$result = null, &$errors = null) {
		if ($result == null) {
			$result = [];
		}
		if ($errors == null) {
			$errors = [];
		}

		$db        = $model->getDb();
		$config    = empty($config) ? $this->config : $config;
		$formName  = ArrayHelper::getValue($config, 'formName', $model->formName());
		$data      = empty($formName) ? $data : $data[$formName];
		$relations = ArrayHelper::getValue($config, 'relations', []);

		if ($first) {
			// var_dump($config);
			// var_dump($data);
		}

		$this->loadModel($model, $data);
		$model->validate();

		$result = array_merge_recursive($result, $model->attributes);
		$errors = array_merge_recursive($errors, $model->getErrors());

		foreach ($relations as $relation => $subconfig) {

			$rel                   = $model->getRelation($relation);
			$class                 = $rel->modelClass;
			$subdata               = ArrayHelper::getValue($data, $relation, []);
			$subconfig['formName'] = ArrayHelper::getValue($subconfig, 'formName', false);

			if ($rel->multiple) {
				$result[$relation] = [];
				$errors[$relation] = [];
				foreach ($subdata as $subitemdata) {
					$submodel = new $class;
					$this->loadModel($submodel, $subitemdata);
					$submodel->validate();
					$subresult = [];
					$suberrors = [];
					$this->validate($submodel, $subitemdata, $subconfig, false, $subresult, $suberrors);
					$result[$relation][] = array_merge_recursive($submodel->attributes, $subresult);
					$errors[$relation][] = array_merge_recursive($submodel->getErrors(), $suberrors);
				}
			} else {

			}


		}

		return ['model' => $result, 'errors' => $errors];
	}

}

?>