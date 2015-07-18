yii2-data
=========

Hierarchical hydrator for ActiveRecord with support for relations and sorting.



---
Usage
-----


```php
$document = new \x1\data\ActiveDocument([
    'model'     => Order::className(),
    
    // 'useTransaction'     => true,
    // 'defaultIncremental' => false,
    // 'defaultIgnoreError' => false,
    // 'defaultDelete'      => true,
    // 'defaultScenario'    => null,
    // 'defaultSkipUpdate'  => false,
    // 'defaultIgnoreError' => false,

    'relations' => [
        'orderItems' => [
            // 'incremental' => false,   // sets relations as passed by data and unlinks omitted rows
            // 'skipUpdate'  => false,   // models are save, otherwise they are skipped
            // 'delete'      => true,    // deletes dropped models, otherwise they are only unlinked
            // 'scenario'    => null,	 // the scenario to use for validation
            // 'useTransaction' => true, // wraps all operations in a transaction
            // 'sortable' => null,       // (string) name of the order column (=int field)
            'relations' => ['supplier'], // other relations of 'orderItem', maybe nested
        ]
    ],
]);

$model = $document->findOne(1); // find the model and quietly attach ActiveDocumentBehavior
$model->load($data);		// relations are set!
```


1. ```incremental => false``` (default) unlinks all orderItems except the ones passed in ```$data```.
2. additionally to unlink, ```delete => true``` also deletes these models. it has no effect on ```incremental => true```
3. if relation configuration values are not explicitly set, the default values at root level are used


pass all needed relations to the configurations array. you can customize the processing of related data as shown below, like link/unlink and delete/skip behavior. 


| option        | value         | description  |
| ------------- |:-------------:| -----        |
| ```incremental```   | ```true```          | updates and creates, but does not remove omitted models  |
|               | ```false```         | sets the relation's models only to the ones passed by load(), all others are removed |
| ```delete```        | ```true```          | models missing in the relation are unlinked and **deleted** |
| ```delete```        | ```false```         | models missing in the relation are unlinked |









---
Data Example
----------------------

```php
// data from post
$data = [
	'Order' => [
		'id' 	=> 1,
		'title'	=> 'Order #1'
		'orderItems' => [
			['id' => 1, 'msg' => 'order item #1'],
			['id' => 2, 'msg' => 'order item #2'],
			[           'msg' => 'unsaved order item #3'],
		]
	]
]
```

Behind the scene
----------------------
1. The ```ActiveDocument``` helper class just attaches the ```ActiveDocumentBehavior``` to an ActiveRecord
2. For each relation, the ```RelationValidator``` is attached. This allows capturing the relation's setter (when loading)
3. Now ```$model->load($data)``` also cares about relations.
4. On before save, a transaction is opened
5. If everything is valid (ignoring those models, who skipError), we can finally save
6. ```Commit``` or ```Rollback``` the transaction
7. use ```$model->getErrorDocument()``` and ```$model->getDocument()```
