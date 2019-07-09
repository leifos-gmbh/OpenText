<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *
 */
class ilOpenTextSynchronisationInfoItem
{
	const TABLE_ITEMS = 'evnt_evhk_otxt_items';

	const STATUS_SCHEDULED = 0;
	const STATUS_IN_PROGRESS = 1;
	const STATUS_SYNCHRONISED = 2;
	const STATUS_FAILURE = 3;

	/**
	 * @var ilDBInterface
	 */
	private $db;

	/**
	 * @var int
	 */
	private $id = 0;

	/**
	 * @var int
	 */
	private $obj_id = 0;

	/**
	 * @var int
	 */
	private $opentext_id = 0;

	/**
	 * @var int
	 */
	private $status = self::STATUS_SCHEDULED;


	/**
	 * ilOpenTextSynchronisationInfoItem constructor.
	 * @param $ilias_id
	 * @param $opentext_id
	 */
	public function __construct($obj_id, $opentext_id = 0, $status = self::STATUS_SCHEDULED)
	{
		global $DIC;

		$this->db = $DIC->database();
		$this->obj_id = $obj_id;
		$this->opentext_id = $opentext_id;
		$this->status = $status;

	}

	/**
	 * @param int $a_status
	 * @return string
	 */
	public static function statusToLangKey($a_status)
	{
		$lang_key = '';
		switch($a_status) {
			case self::STATUS_SCHEDULED:
				$lang_key = 'status_scheduled';
				break;
			case self::STATUS_IN_PROGRESS:
				$lang_key = 'status_in_progress';
				break;
			case self::STATUS_SYNCHRONISED:
				$lang_key = 'status_synchronized';
				break;
			case self::STATUS_FAILURE:
				$lang_key = 'status_failure';
				break;
		}
		return $lang_key;
	}

	/**
	 * @param int $a_id
	 */
	public function setOpenTextId($a_id)
	{
		$this->opentext_id = $a_id;
	}

	/**
	 * @return int
	 */
	public function getOpenTextId()
	{
		return $this->opentext_id;
	}

	/**
	 * @return int
	 */
	public function getObjId()
	{
		return $this->obj_id;
	}

	/**
	 * @param $status
	 */
	public function setStatus($status)
	{
		$this->status = $status;
	}

	/**
	 * @return int
	 */
	public function getStatus()
	{
		return $this->status;
	}

	/**
	 * Save entry
	 */
	public function save()
	{
		$query = 'select * from ' . self::TABLE_ITEMS. ' '.
			'where obj_id = ' . $this->db->quote($this->getObjId(),'integer');
		$res = $this->db->query($query);
		if($res->numRows() > 0) {
			$this->update();
		}
		else {
			$this->create();
		}
	}

	/**
	 * Create new entry
	 */
	protected function create()
	{
		$query = 'insert into ' . self::TABLE_ITEMS . ' '.
			'(obj_id, otxt_id, status, last_update) '.
			'values( '.
			$this->db->quote($this->getObjId(),'integer').', '.
			$this->db->quote($this->getOpenTextId(),'integer'). ', '.
			$this->db->quote($this->getStatus(),'integer'). ', '.
			$this->db->now().' '.
			')';
		$this->db->manipulate($query);
	}

	/**
	 * Update existing entry
	 */
	protected function update()
	{
		$query = 'update ' . self::TABLE_ITEMS. ' '.
			'set '.
			'otxt_id = ' .$this->db->quote($this->getOpenTextId(),'integer'). ', '.
			'status = ' . $this->db->quote($this->getStatus(),'integer') . ', '.
			'last_update = '.$this->db->now() .' '.
			'where obj_id = ' . $this->db->quote($this->getObjId(),'integer');
		$this->db->manipulate($query);

	}
}