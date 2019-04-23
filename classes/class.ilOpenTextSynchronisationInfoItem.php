<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 *
 */
class ilOpenTextSynchronisationInfoItem
{
	const STATUS_SYNCHRONISED = 0;
	const STATUS_IN_PROGRESS = 1;
	const STATUS_PENDING = 2;

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
	private $ilias_id = 0;

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
	public function __construct($ilias_id, $opentext_id = 0, $status = self::STATUS_PENDING)
	{
		global $DIC;

		$this->db = $DIC->database();
		$this->ilias_id = $ilias_id;
		$this->opentext_id = $opentext_id;
		$this->status = $status;

	}

	/**
	 * @param $a_id
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



}