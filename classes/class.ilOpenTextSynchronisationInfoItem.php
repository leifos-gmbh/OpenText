<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *
 */
class ilOpenTextSynchronisationInfoItem
{
	const STATUS_SYNCHRONISED = 0;
	const STATUS_IN_PROGRESS = 1;
	const STATUS_SCHEDULED = 2;
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
	private $status = self::STATUS_PENDING;


	/**
	 * ilOpenTextSynchronisationInfoItem constructor.
	 * @param $ilias_id
	 * @param $opentext_id
	 */
	public function __construct($obj_id, $opentext_id = 0, $status = self::STATUS_PENDING)
	{
		global $DIC;

		$this->db = $DIC->database();
		$this->obj_id = $obj_id;
		$this->opentext_id = $opentext_id;
		$this->status = $status;

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
}