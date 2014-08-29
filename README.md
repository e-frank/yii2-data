yii2-data
=========

Hierarchical Hydrator for ActiveRecord with support for relations.



---
Usage
-----

```php
use efrank\data\Hydrator;

// base model
$model    = new MyModel();

// create the hydrator. 
$hydrator = new Hydrator(['relations' => ['friends' => ['incremental' => true, 'delete' => false], 'author' => []]]);

// save data with all related models
$hydrator->hydrate($model, $dataFromPost);
```

in the configuration array you tell the hydrator which relational properties to use and what to do with existing related items - link/unlink and delete/skip.


| option        | value         | description  |
| ------------- |:-------------:| -----        |
| ```incremental```   | ```true```          | only uses existing, related models, which could be identified by the data array  |
|               | ```false```         | uses all models in the given relation |
| ```delete```        | ```true```          | models missing in the relation are unlinked and **deleted** |
| ```delete```        | ```false```         | models missing in the relation are unlinked |








---
Configuration Examples
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

```php
$config = [
	'relations' => [
		'orderItems' => [
			'incremental' 	=> false,
			'delete' 		=> true,
		]
	]
];

```

```php
$order = new Order();
$hydrator = new Hydrator($config);
$hydrator->hydrate($order, $data);
```

1. ```incremental => false``` (default) unlinks all orderItems except the ones passed in ```$data```.
2. additionally to unlink, ```delete => true``` also deletes these models. it has no effect on ```incremental => true```