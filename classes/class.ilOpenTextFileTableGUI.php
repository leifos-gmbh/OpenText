<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */


/**
 * File object table gui
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 *
 */
class ilOpenTextFileTableGUI extends ilTable2GUI
{
	const TABLE_ID = 'otxt_files';

	/**
	 * @var \ilOpenTextPlugin|null
	 */
	private $plugin = null;

	/**
	 * @var \ilLogger|null
	 */
	private $logger = null;

	/**
	 * ilOpenTextFileTableGUI constructor.
	 * @param object $a_parent_obj
	 * @param string $a_parent_cmd
	 */
	public function __construct($a_parent_obj, string $a_parent_cmd)
	{
		global $DIC;

		$this->setId(self::TABLE_ID);
		parent::__construct($a_parent_obj, $a_parent_cmd);

		$this->plugin = \ilOpenTextPlugin::getInstance();
		$this->logger = $DIC->logger()->otxt();
	}

	/**
	 * Init table
	 */
	public function init()
	{
		$this->addColumn($this->lng->txt('title'), 'title','30%');
		$this->addColumn($this->plugin->txt('file_create_date'),'cdate','15%');
		$this->addColumn($this->plugin->txt('file_last_update'),'mdate','15%');
		$this->addColumn($this->plugin->txt('file_sync_status'),'sync_stat','15%');
		$this->addColumn($this->plugin->txt('file_repository'),'in_repository','15%');
		$this->addColumn($this->plugin->txt('file_actions'),'actions','10%');


		$this->setDefaultOrderField('title');
		$this->setDefaultOrderDirection('asc');

		$this->setRowTemplate('tpl.file_table_row.html', $this->plugin->getDirectory());
	}

	/**
	 * @param array $a_set
	 */
	public function fillRow($file)
	{
		$refs = $this->getReferences($file['obj_id']);
		if(!count($refs)) {
			$this->tpl->setCurrentBlock('title');
			$this->tpl->setVariable('OBJ_TITLE', $file['title']);
			$this->tpl->parseCurrentBlock();
		}
		else {

			$first_ref = $refs[0];

			$this->tpl->setCurrentBlock('title');
			$this->tpl->setVariable('OBJ_TITLE', $file['title']);
			$this->tpl->parseCurrentBlock();
			foreach($refs as $ref) {

				$path = new \ilPathGUI();
				$path->enableTextOnly(false);
				$path->setUseImages(true);

				$this->tpl->setCurrentBlock('path');
				$this->tpl->setVariable('OBJ_PATH', $path->getPath(ROOT_FOLDER_ID, $ref));
				$this->tpl->parseCurrentBlock();
			}
		}

		$create_date = new ilDateTime($file['create_date'], IL_CAL_DATETIME);
		$this->tpl->setVariable('CDATE', ilDatePresentation::formatDate($create_date));

		$last_update = new ilDateTime($file['last_update'], IL_CAL_DATETIME);
		$this->tpl->setVariable('LAST_UPDATE', ilDatePresentation::formatDate($last_update));

	}

	/**
	 *
	 */
	public function parse()
	{
		$files = $this->getFiles();

		$this->logger->dump($files);

		$this->setData($files);
	}

	/**
	 * Read and get files
	 * @return array
	 * @throws \ilDatabaseException
	 */
	private function getFiles()
	{
		global $DIC;

		$db = $DIC->database();

		// all referenced obj type 'file' objects
		$query = 'select distinct(obd.obj_id), title, description, create_date, last_update from object_data obd '.
			'join object_reference obr on obd.obj_id = obr.obj_id '.
			'where type = ' . $db->quote('file','text').' '.
			'group by obd.obj_id';
		$res = $db->query($query);

		$files = [];
		$counter = 0;
		while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {

			$files[$counter]['obj_id'] = $row->obj_id;
			$files[$counter]['title'] = $row->title;
			$files[$counter]['description'] = $row->description;
			$files[$counter]['create_date'] = $row->create_date;
			$files[$counter]['last_update'] = $row->last_update;
			++$counter;
		}
		return $files;
	}

	/**
	 * @param int $obj_id
	 */
	private function getReferences($obj_id)
	{
		global $DIC;

		$tree = $DIC->repositoryTree();

		$refs = ilObject::_getAllReferences($obj_id);

		$valid_references = [];
		foreach($refs as $ref => $unused) {

			if($tree->isInTree($ref) && !$tree->isDeleted($ref)) {
				$valid_references[] = $ref;
			}
		}
		return $valid_references;
	}
}