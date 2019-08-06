<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use Swagger\Client\Model\AuthenticationInfo;


/**
 * Config gui
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilOpenTextConfigGUI extends ilPluginConfigGUI
{
	const TAB_SETTINGS = 'settings';
	const TAB_FILES = 'files';


	/**
	 * @var \ilLogger
	 */
	private $logger = null;


	/**
	 * ilOpenTextConfigGUI constructor.
	 */
	public function __construct()
	{
		global $DIC;

		$this->logger = $DIC->logger()->otxt();
	}


	/**
	* Handles all commands, default is "configure"
	*/
	public function performCommand($cmd)
	{
		global $DIC;

		$ilCtrl = $DIC->ctrl();
		$ilTabs = $DIC->tabs();

		
		$ilCtrl->saveParameter($this, "menu_id");

		$this->logger->debug('Handling command: ' . $cmd);

		switch ($cmd)
		{
			default:
				$this->$cmd();
				break;

		}
	}

	/**
	 * @param string $active_tab
	 */
	protected function handleTabs(string $active_tab)
	{
		global $DIC;

		$tabs = $DIC->tabs();
		$ctrl = $DIC->ctrl();

		$tabs->addTab(
			self::TAB_SETTINGS,
			$this->getPluginObject()->txt('tab_ot_settings'),
			$ctrl->getLinkTarget($this,'configure')
		);
		$tabs->addTab(
			self::TAB_FILES,
			$this->getPluginObject()->txt('tab_ot_files'),
			$ctrl->getLinkTarget($this,'files')
		);
		$tabs->activateTab($active_tab);
	}
	
	/**
	 * Show settings screen
	 * @global \ilTemplate $tpl
	 * @global \ilTabsGUI $ilTabs
	 */
	protected function configure(ilPropertyFormGUI $form = null)
	{
		global $DIC;

		$tpl = $DIC->ui()->mainTemplate();
		$ilTabs = $DIC->tabs();
		$ctrl = $DIC->ctrl();

		$this->handleTabs(self::TAB_SETTINGS);

		if(!$form instanceof ilPropertyFormGUI)
		{
			$form = $this->initConfigurationForm();
		}
		$tpl->setContent($form->getHTML());
	}

	/**
	 * @return ilPropertyFormGUI
	 */
	protected function initConfigurationForm() : \ilPropertyFormGUI
	{
		global $DIC;

		$ilCtrl = $DIC->ctrl();
		$lng = $DIC->language();
		
		$settings = ilOpenTextSettings::getInstance();
		
		$form = new \ilPropertyFormGUI();
		$form->setTitle($this->getPluginObject()->txt('tbl_ot_settings'));
		$form->setFormAction($ilCtrl->getFormAction($this));
		$form->addCommandButton('save', $lng->txt('save'));

		if(strlen($settings->getUsername()))
		{
			$form->addCommandButton('ping', $this->getPluginObject()->txt('ot_ping'));
		}

		$form->setShowTopButtons(false);
		
		$lock = new \ilCheckboxInputGUI($this->getPluginObject()->txt('tbl_ot_settings_active'),'active');
		$lock->setValue(1);
		$lock->setChecked($settings->isActive());
		$form->addItem($lock);

		$lng->loadLanguageModule('log');
		$level = new ilSelectInputGUI($this->getPluginObject()->txt('tbl_ot_settings_loglevel'),'log_level');
		$level->setHideSubForm($settings->getLogLevel() == \ilLogLevel::OFF,'< 1000');
		$level->setOptions(\ilLogLevel::getLevelOptions());
		$level->setValue($settings->getLogLevel());
		$form->addItem($level);

		$log_file = new \ilTextInputGUI($this->getPluginObject()->txt('tbl_ot_settings_logfile'),'log_file');
		$log_file->setValue($settings->getLogFile());
		$log_file->setInfo($this->getPluginObject()->txt('tbl_ot_settings_logfile_info'));
		$level->addSubItem($log_file);

		$uri = new \ilTextInputGUI($this->getPluginObject()->txt('tbl_ot_settings_url'),'uri');
		$uri->setMaxLength(255);
		$uri->setRequired(true);
		$uri->setValue($settings->getUri());
		$form->addItem($uri);

		$base_folder = new \ilNumberInputGUI($this->getPluginObject()->txt('tbl_ot_settings_base_folder'),'base_folder');
		$base_folder->setValue((int) $settings->getBaseFolderId());
		$base_folder->setMinValue(1);
		$base_folder->setRequired(true);
		$base_folder->setInfo($this->getPluginObject()->txt('tbl_ot_settings_base_folder_info'));
		$form->addItem($base_folder);

		$auth = new \ilFormSectionHeaderGUI();
		$auth->setTitle($this->getPluginObject()->txt('tbl_ot_settings_section_auth'));
		$form->addItem($auth);

		$user = new \ilTextInputGUI($this->getPluginObject()->txt('tbl_ot_settings_username'),'username');
		$user->setMaxLength(128);
		$user->setRequired(true);
		$user->setValue($settings->getUsername());
		$user->setRequired(true);
		$form->addItem($user);

		$pass = new \ilPasswordInputGUI($this->getPluginObject()->txt('tbl_ot_settings_password'),'password');
		if(strlen($settings->getPassword()))
		{
			$pass->setValue('******');
		}
		$pass->setSkipSyntaxCheck(true);
		$pass->setRetype(false);
		$pass->setRequired(false);
		$form->addItem($pass);

		$domain = new \ilTextInputGUI($this->getPluginObject()->txt('tbl_ot_settings_domain'), 'domain');
		$domain->setMaxLength(128);
		$domain->setValue($settings->getDomain());
		$form->addItem($domain);


		return $form;
	}

	/**
	 * Save settings
	 */
	protected function save()
	{
		global $DIC;

		$lng = $DIC->language();
		$ilCtrl = $DIC->ctrl();

		$form = $this->initConfigurationForm();
		$settings = ilOpenTextSettings::getInstance();
		
		if($form->checkInput())
		{
			$settings->setActive($form->getInput('active'));
			$settings->setLogLevel($form->getInput('log_level'));
			$settings->setLogFile($form->getInput('log_file'));
			$settings->setUri($form->getInput('uri'));
			$settings->setUsername($form->getInput('username'));
			if(strcmp($form->getInput('password'), '******') !== 0)
			{
				$settings->setPassword($form->getInput('password'));
			}
			$settings->setDomain($form->getInput('domain'));
			$settings->setBaseFolderId((int) $form->getInput('base_folder'));
			$settings->save();
			ilUtil::sendSuccess($lng->txt('settings_saved'),true);
			$ilCtrl->redirect($this,'configure');

		}
		
		$error = $lng->txt('err_check_input');
		$form->setValuesByPost();
		ilUtil::sendFailure($error);
		$this->configure($form);
	}


	/**
	 * Show repository file objects
	 */
	protected function files()
	{
		global $DIC;

		$tpl = $DIC->ui()->mainTemplate();

		$this->handleTabs(self::TAB_FILES);

		$table = new ilOpenTextFileTableGUI($this, 'files');
		$table->init();
		$table->parse();

		$tpl->setContent($table->getHTML());
	}


	/**
	 *
	 */
	protected function ping()
	{
		global $DIC;

		$ctrl = $DIC->ctrl();

		$connector = \ilOpenTextConnector::getInstance();
		try {
			// try to login
			$connector->ping();

			// try to fetch base node
			//$connector->fetchNode(ilOpenTextSettings::getInstance()->getBaseFolderId());

			ilUtil::sendSuccess(ilOpenTextPlugin::getInstance()->txt('success_connection'),true);
			$ctrl->redirect($this, 'configure');
		}
		catch(\ilOpenTextConnectionException $e) {

			ilUtil::sendFailure($e->getMessage(),true);
			$ctrl->redirect($this, 'configure');
		}
	}
}
?>