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

<#2>
<?php

if(!$ilDB->tableExists('evnt_evhk_otxt_path')) {

	$ilDB->createTable(
		'evnt_evhk_otxt_path',
		[
			'path' => [
				'type' => \ilDBConstants::T_TEXT,
				'length' => 255,
				'notnull' => true,
			],
            'otxt_id' => [
                'type' => \ilDBConstants::T_INTEGER,
                'length' => 4,
                'notnull' => true
            ]
		]
	);
}
?>

<#3>
<?php

// dummy because of error

?>

<#3>
<?php

if($ilDB->tableExists('evnt_evhk_otxt_path')) {

    $ilDB->addPrimaryKey('evnt_evhk_otxt_path', ['path']);
}
?>
