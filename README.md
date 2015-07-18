yii2-data
=========

Hierarchical Hydrator for ActiveRecord with support for relations.



---
Usage
-----


```php
$document = new \x1\data\ActiveDocument([
    'model'     => Order::className(),
    'defaultDelete'      => false,
    'defaultIgnoreError' => false,

    'relations' => [
        'orderItems' => [
            // 'incremental' => false,	// default = false
            // 'delete' => true,	// default = false
            // 'scenario' => null,	// default = null
            'relations' => ['supplier'],	// other relations of 'orderItem'
        ]
    ],
]);

$model = $document->findOne(1);
$model->load($data);		// relations are set!
```


1. ```incremental => false``` (default) unlinks all orderItems except the ones passed in ```$data```.
2. additionally to unlink, ```delete => true``` also deletes these models. it has no effect on ```incremental => true```


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

