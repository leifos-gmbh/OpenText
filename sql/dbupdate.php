<#1>
<?php

$ilDB->createTable('evnt_evhk_otxt_items',
	[
		'obj_id' => [
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		],
		'otxt_id' => [
			'type' => 'integer',
			'length' => 4,
			'notnull' => true
		],
		'status' =>
		[
			'type' => 'integer',
			'length' => 2,
			'notnull' => true,
		],
		'last_update' =>
		[
			'type' => 'timestamp',
			'notnull' => false
		]
	]
);

$ilDB->addPrimaryKey('evnt_evhk_otxt_items', ['obj_id','otxt_id']);

?>
