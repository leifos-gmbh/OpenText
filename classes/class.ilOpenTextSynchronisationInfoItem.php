<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *
 */
class ilOpenTextSynchronisationInfoItem
{
	const TABLE_ITEMS = 'evnt_evhk_otxt_items';

	const STATUS_SCHEDULED = 1;
	const STATUS_IN_PROGRESS = 2;
	const STATUS_SYNCHRONISED = 3;
	const STATUS_FAILURE = 4;

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
				$lang_key = 'status_failed';
				break;
		}
		return $lang_key;
	}

    /**
     * @return array
     */
	public static function getStatusSelectOptions()
    {
        global $DIC;

        $lng = $DIC->language();
        $options = [];

        $options[-1] = \ilOpenTextPlugin::getInstance()->txt('ilias_file_table_filter_status_all');
        $options[self::STATUS_SCHEDULED] = \ilOpenTextPlugin::getInstance()->txt(self::statusToLangKey(self::STATUS_SCHEDULED));
        $options[self::STATUS_IN_PROGRESS] = \ilOpenTextPlugin::getInstance()->txt(self::statusToLangKey(self::STATUS_IN_PROGRESS));
        $options[self::STATUS_SYNCHRONISED] = \ilOpenTextPlugin::getInstance()->txt(self::statusToLangKey(self::STATUS_SYNCHRONISED));
        $options[self::STATUS_FAILURE] = \ilOpenTextPlugin::getInstance()->txt(self::statusToLangKey(self::STATUS_FAILURE));
        return $options;
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
			'(obj_id, otxt_id, status) '.
			'values( '.
			$this->db->quote($this->getObjId(),'integer').', '.
			$this->db->quote($this->getOpenTextId(),'integer'). ', '.
			$this->db->quote($this->getStatus(),'integer'). ' ' .
			')';
		$this->db->manipulate($query);
	}

	/**
	 * Update existing entry
	 */
	protected function update()
	{
	    if($this->status == self::STATUS_SYNCHRONISED) {
            $query = 'update ' . self::TABLE_ITEMS. ' '.
                'set '.
                'otxt_id = ' .$this->db->quote($this->getOpenTextId(),'integer'). ', '.
                'status = ' . $this->db->quote($this->getStatus(),'integer') . ', '.
                'last_update = '.$this->db->now() .' '.
                'where obj_id = ' . $this->db->quote($this->getObjId(),'integer');
        }
	    else {
	        // keep the last_update date
            $query = 'update ' . self::TABLE_ITEMS. ' '.
                'set ' .
                'otxt_id = ' . $this->db->quote($this->getOpenTextId(), 'integer') . ', ' .
                'status = ' . $this->db->quote($this->getStatus(), 'integer') . ' ' .
                'where obj_id = ' . $this->db->quote($this->getObjId(), 'integer');
        }
		$this->db->manipulate($query);
	}
}