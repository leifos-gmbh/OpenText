<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilOpenTextSynchronisationInfo
 */
class ilOpenTextSynchronisationInfo
{
	const TABLE_ITEMS = 'evnt_evhk_otxt_items';

    /**
     * @var int
     */
	const SYNC_LIMIT = 500;

	private static $instance = null;

	/**
	 * @var \ilDBInterface|null
	 */
	protected $db = null;

	private $logger = null;


	/**
	 * @var \ilOpenTextSynchronisationInfoItem[]
	 */
	private $info_items = [];

	private $info_items_initialized = false;


	/**
	 * ilOpenTextSynchronisationInfo constructor.
	 */
	private function __construct()
	{
		global $DIC;

		$this->db = $DIC->database();
		$this->logger = $DIC->logger()->otxt();
	}

	/**
	 * @return \ilOpenTextSynchronisationInfo
	 */
	public static function getInstance()
	{
		if(!self::$instance instanceof \ilOpenTextSynchronisationInfo) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * @param int $a_obj_id
	 * @return \ilOpenTextSynchronisationInfoItem|null
	 * @throws \ilDatabaseException
	 */
	public function getItemForObjId($a_obj_id)
	{
		$query = 'select * from ' . self::TABLE_ITEMS . ' '.
			'where obj_id = ' . $this->db->quote($a_obj_id, 'integer');
		$res = $this->db->query($query);


		$item = null;
		while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {

			$item = new ilOpenTextSynchronisationInfoItem(
				$a_obj_id,
				$row->otxt_id,
				$row->status
			);
		}

		if(!$item instanceof \ilOpenTextSynchronisationInfoItem) {
			$item = new \ilOpenTextSynchronisationInfoItem(
				$a_obj_id
			);
		}
		return $item;
	}

	/**
	 * Get items for synchronization
	 * @return \ilOpenTextSynchronisationInfoItem[]
	 */
	public function getItemsForSynchronization()
	{
		if($this->info_items_initialized) {
			return $this->info_items;
		}

		$query = 'select obj_id, otxt_id, status from ' . \ilOpenTextSynchronisationInfo::TABLE_ITEMS. ' '.
			'where status = ' . $this->db->quote(\ilOpenTextSynchronisationInfoItem::STATUS_SCHEDULED, 'integer');

		$this->db->setLimit(self::SYNC_LIMIT);
		$res = $this->db->query($query);

		$this->info_items = [];
		$this->info_items_initialized = true;
		while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {

			$this->info_items[] = new \ilOpenTextSynchronisationInfoItem(
				$row->obj_id,
				$row->otxt_id,
				$row->status
			);
		}
		return $this->info_items;
	}


	/**
	 * @throws \ilDatabaseException
	 */
	public function createMissingItems()
	{
		$query = 'select distinct(obd.obj_id) from object_data obd '.
			'join object_reference obr on obd.obj_id = obr.obj_id '.
			'left join ' . self::TABLE_ITEMS . ' otxt on obd.obj_id = otxt.obj_id '.
			'where obd.type = ' . $this->db->quote('file', 'text'). ' '.
			'and otxt.obj_id is null '.
			'group by obd.obj_id ';
		$res = $this->db->query($query);
		while($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {

			$new_entry = new \ilOpenTextSynchronisationInfoItem(
				$row->obj_id,
				0,
				\ilOpenTextSynchronisationInfoItem::STATUS_SCHEDULED
			);
			$new_entry->save();
			$this->logger->debug('Added new opentxt item for obj_id : ' . $row->obj_id);
		}
		$this->info_items_initialized = false;
	}
}