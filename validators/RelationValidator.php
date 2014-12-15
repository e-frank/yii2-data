<?
namespace x1\data\validators;

use yii\helpers\ArrayHelper;


class RelationValidator extends \yii\validators\Validator {

	const ERROR_MSG             = 'Relation has Errors';
	const RELATIONS             = 'relations';
	const IGNORE_ERROR          = 'ignoreError';

	public $config              = [];
	public $activeDocumentClass = null;


	// validate sub model
	private function validateModel(&$model, &$config) {
		$valid = $model->validate();

		// avoid deep validate, if we already have an ActiveDocument
		if (!($model instanceof $this->activeDocumentClass)) {
			$relations = ArrayHelper::getValue($config, self::RELATIONS, []);
			foreach ($relations as $key => $value) {
				$valid = $this->validateRelation($model, $key, $value) && $valid;
			}
		}

		return $valid;

	}


	// validate relation
	private function validateRelation(&$model, $attribute, &$config) {
		// var_dump([__METHOD__ => $attribute, 'key' => $model->getPrimaryKey(true), 'config' => $config]);
		
		if ($model == null)
			return;

		$pattern   = null;
		$valid     = true;
		$rel       = $model->getRelation($attribute);


		if ($rel->multiple) {
			$submodels = $model->$attribute;
			if ($submodels !== null)
			{
				foreach ($submodels as $key => $submodel) {
					$valid = $valid && $this->validateModel($submodel, $config);
				}
			}
		} else {
			$submodel = $model->$attribute;
			if ($submodel !== null)
			{
				$valid = $this->validateModel($submodel, $config);
			}
		}

		if (!ArrayHelper::getValue($config, self::IGNORE_ERROR, false) && !$valid) {
			$model->addError($attribute, self::ERROR_MSG);
		}

		return $valid;
	}

	// the validator applied to the attribute
	public function validateAttribute($object, $attribute) {
		$relations = ArrayHelper::getValue($this->config, self::RELATIONS, []);
		$config    = ArrayHelper::getValue($relations, $attribute, []);
		$this->validateRelation($object, $attribute, $config);
	}

}

?>