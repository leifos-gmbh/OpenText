<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */


/**
 * Config gui
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilOpenTextConfigGUI extends ilPluginConfigGUI
{
	/**
	* Handles all commands, default is "configure"
	*/
	public function performCommand($cmd)
	{
		global $DIC;

		$ilCtrl = $DIC->ctrl();
		$ilTabs = $DIC->tabs();
		
		$ilCtrl->saveParameter($this, "menu_id");
		
		switch ($cmd)
		{
			default:
				$this->$cmd();
				break;

		}
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

		$ilTabs->activateTab('settings');
		
		$ilTabs->addTab(
			'settings',
			$this->getPluginObject()->txt('tab_ot_settings'),
			$ctrl->getLinkTarget($this,'configure')
		);
		

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
		$form->setShowTopButtons(false);
		
		$lock = new \ilCheckboxInputGUI($this->getPluginObject()->txt('tbl_ot_settings_active'),'active');
		$lock->setValue(1);
		$lock->setChecked($settings->isActive());
		$form->addItem($lock);

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
	 *
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
			$settings->setUsername($form->getInput('username'));
			$settings->setPassword($form->getInput('password'));
			$settings->setDomain($form->getInput('domain'));
			$settings->save();
				
			ilUtil::sendSuccess($lng->txt('settings_saved'),true);
			$ilCtrl->redirect($this,'configure');
		}
		
		$error = $lng->txt('err_check_input');
		$form->setValuesByPost();
		ilUtil::sendFailure($error);
		$this->configure($form);
	}
}
?>