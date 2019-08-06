<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */


/**
 * Open text settings
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilOpenTextSettings
{
	const SETTINGS_MODULE = 'xopentext';

	const OCTS_HEADER_TICKET_NAME = 'OTCSTicket';

	/**
	 * @var \ilOpenTextSettings
	 */
	private static $instance = null;


	/**
	 * @var \ilSetting
	 */
	private $storage = null;

	/**
	 * @var bool
	 */
	private $active = false;

	/**
	 * @var int
	 */
	private $log_level = \ilLogLevel::OFF;

	/**
	 * @var string
	 */
	private $log_file = '';

	/**
	 * @var string
	 */
	private $uri = '';

	/**
	 * @var string
	 */
	private $username = '';

	/**
	 * @var string
	 */
	private $password = '';

	/**
	 * @var string
	 */
	private $domain = '';

	/**
	 * @var int
	 */
	private $base_folder_id = 0;

	/**
	 * ilOpenTextSettings constructor.
	 */
	private function __construct()
	{
		$this->storage = new \ilSetting(self::SETTINGS_MODULE);
		$this->loadFromDb();
	}

	/**
	 * @return ilOpenTextSettings
	 */
	public static function getInstance() : ilOpenTextSettings
	{
		if(!self::$instance instanceof ilOpenTextSettings)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function setActive(bool $a_status)
	{
		$this->active = $a_status;
	}

	/**
	 * @return bool
	 */
	public function isActive() : bool
	{
		return $this->active;
	}

	/**
	 * @return int
	 */
	public function getLogLevel() : int
	{
		return $this->log_level;
	}

	/**
	 * @param int $level
	 */
	public function setLogLevel(int $level)
	{
		$this->log_level = $level;
	}

	/**
	 * @param $a_file
	 */
	public function setLogFile($a_file)
	{
		$this->log_file = $a_file;
	}

	/**
	 * @return string
	 */
	public function getLogFile() : string
	{
		return $this->log_file;
	}


	/**
	 * @param string $a_uri
	 */
	public function setUri(string $a_uri)
	{
		$this->uri = $a_uri;
	}

	/**
	 * @return string
	 */
	public function getUri() : string
	{
		return $this->uri;
	}

	/**
	 * @param string $a_username
	 */
	public function setUsername(string $a_username)
	{
		$this->username = $a_username;
	}

	/**
	 * @return string
	 */
	public function getUsername() : string
	{
		return $this->username;
	}

	/**
	 * @param string $a_password
	 */
	public function setPassword(string $a_password)
	{
		$this->password = $a_password;
	}

	/**
	 * @return string
	 */
	public function getPassword() : string
	{
		return $this->password;
	}

	/**
	 * @param string $a_domain
	 */
	public function setDomain(string $a_domain)
	{
		$this->domain = $a_domain;
	}

	/**
	 * return string
	 */
	public function getDomain()
	{
		return $this->domain;
	}

	/**
	 * @param int $id
	 */
	public function setBaseFolderId($id)
	{
		$this->base_folder_id = $id;
	}

	/**
	 * @return int
	 */
	public function getBaseFolderId()
	{
		return $this->base_folder_id;
	}


	/**
	 * save settings
	 */
	public function save()
	{
		$this->getStorage()->set('active', (int) $this->isActive());
		$this->getStorage()->set('level', (int) $this->getLogLevel());
		$this->getStorage()->set('file', (string) $this->getLogFile());
		$this->getStorage()->set('uri',$this->getUri());
		$this->getStorage()->set('username', $this->getUsername());
		$this->getStorage()->set('password', $this->getPassword());
		$this->getStorage()->set('domain', $this->getDomain());
		$this->getStorage()->set('base_folder_id', $this->getBaseFolderId());
	}

	/**
	 * @return \ilSetting
	 */
	protected function getStorage() : \ilSetting
	{
		return $this->storage;
	}

	/**
	 * Load settings from db
	 */
	protected function loadFromDb()
	{
		$this->setActive((bool) $this->getStorage()->get('active'));
		$this->setLogLevel((int) $this->getStorage()->get('level', $this->getLogLevel()));
		$this->setLogFile((string) $this->getStorage()->get('file', $this->getLogFile()));
		$this->setUri((string) $this->getStorage()->get('uri'));
		$this->setUsername((string) $this->getStorage()->get('username'));
		$this->setPassword((string) $this->getStorage()->get('password'));
		$this->setDomain((string) $this->getStorage()->get('domain'));
		$this->setBaseFolderId((string) $this->getStorage()->get('base_folder_id'));
	}


}
