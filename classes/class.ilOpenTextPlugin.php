<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * openText event plugin base class
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilOpenTextPlugin extends ilEventHookPlugin
{
	private static $instance = null;

	const PNAME = 'OpenText';
	const CTYPE = 'Services';
	const CNAME = 'EventHandling';
	const SLOT_ID = 'evhk';

	/**
	 * Get singleton instance
	 * @global ilPluginAdmin $ilPluginAdmin
	 * @return ilOpenTextPlugin
	 */
	public static function getInstance()
	{
		global $ilPluginAdmin;

		if (self::$instance) {
			return self::$instance;
		}
		return self::$instance = ilPluginAdmin::getPluginObject(
			self::CTYPE,
			self::CNAME,
			self::SLOT_ID,
			self::PNAME
		);
	}

	/**
	 * Handle event
	 * @param string $a_component
	 * @param string $a_event
	 * @param string $a_parameter
	 */
	public function handleEvent($a_component, $a_event, $a_parameter)
	{
		switch($a_component) {
			case 'Services/Object':

				switch($a_event) {

					case 'create':
					case 'update':
						ilLoggerFactory::getLogger('otxt')->notice('Handling event: ' . $a_component . ' ' . $a_event);
						$this->handleUpdateEvent($a_parameter);
						break;
				}
		}
	}

	/**
	 * @param mixed $a_parameter whatever it is
	 */
	protected function handleUpdateEvent($a_parameter)
	{
		if(
			is_array($a_parameter) &&
			array_key_exists('obj_type', $a_parameter) &&
			$a_parameter['obj_type'] == 'file' &&
			array_key_exists('obj_id', $a_parameter)
		) {
			ilLoggerFactory::getLogger('otxt')->debug('Added new update command for obj_id ' . $a_parameter['obj_id']);
			$info =  \ilOpenTextSynchronisationInfo::getInstance();
			$item = $info->getItemForObjId($a_parameter['obj_id']);
			$item->setStatus(ilOpenTextSynchronisationInfoItem::STATUS_SCHEDULED);
			$item->save();
		}
	}


	/**
	 * Get plugin name
	 * @return string
	 */
	public function getPluginName()
	{
		return self::PNAME;
	}

	/**
	 * Init auto load
	 */
	protected function init()
	{
		require($this->getDirectory().'/vendor/autoload.php');
		$this->initAutoLoad();
	}

	/**
	 * Init auto loader
	 * @return void
	 */
	protected function initAutoLoad()
	{
		spl_autoload_register(
			array($this, 'autoLoad')
		);
	}


	/**
	 * Auto load implementation
	 *
	 * @param string class name
	 */
	private final function autoLoad($a_classname)
	{
		$class_file = $this->getClassesDirectory() . '/class.' . $a_classname . '.php';
		if (@include_once($class_file)) {
			return;
		}
		$class_file = $this->getExceptionDirectory().'/class.' . $a_classname . '.php';
		if (@include_once($class_file)) {
			return;
		}
	}

	/**
	 * @return string
	 */
	private function getExceptionDirectory()
	{
		return $this->getDirectory().'/exceptions';
	}
}