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
	 * @var string
	 */
	private $url = '';

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
	 * save settings
	 */
	public function save()
	{
		$this->getStorage()->set('active', (int) $this->isActive());
		$this->getStorage()->set('username', $this->getUsername());
		$this->getStorage()->set('password', $this->getPassword());
		$this->getStorage()->set('domain', $this->getDomain());
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
		$this->setUsername((string) $this->getStorage()->get('username'));
		$this->setPassword((string) $this->getStorage()->get('password'));
		$this->setDomain((string) $this->getStorage()->get('domain'));
	}


}
