<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use Swagger\Client\Model\AuthenticationInfo;

/**
 * Config gui
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 * @ilCtrl_isCalledBy: ilOpenTextConfigGUI: ilObjFileAccessSettingsGUI
 */
class ilOpenTextConfigGUI extends ilPluginConfigGUI
{
    const TAB_SETTINGS = 'settings';
    const TAB_RELEASED_CONTAINERS = 'released';
    const TAB_FILES = 'files';
    const TAB_SKYDOC = 'skydoc';

    /**
     * @var bool
     */
    private $is_plugin_context = true;

    /**
     * @var ilLogger
     */
    private $logger = null;

    /**
     * @var null | ilCtrl
     */
    private $ctrl = null;

    /**
     * @var ilTabsGUI
     */
    private $tabs;

    /**
     * @var ilLanguage
     */
    private $lng;


    /**
     * ilOpenTextConfigGUI constructor.
     */
    public function __construct()
    {
        global $DIC;

        $this->logger = $DIC->logger()->otxt();
        $this->ctrl = $DIC->ctrl();
        $this->tabs = $DIC->tabs();
        $this->lng = $DIC->language();
    }

    public function executeCommand()
    {
        // @dirty hack
        // if the plugin object is not initialised this call comes from ilObjFileAccessSettingsGUI
        if ($this->plugin_object instanceof \ilPlugin) {
            $this->is_plugin_context = true;
            return parent::executeCommand();
        }
        $this->is_plugin_context = false;
        $this->setPluginObject(\ilOpenTextPlugin::getInstance());
        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd(self::TAB_FILES);

        switch ($next_class) {
            default:
                $this->$cmd();
                break;
        }
    }

    /**
    * Handles all commands, default is "configure"
    */
    public function performCommand($cmd)
    {
        $this->ctrl->saveParameter($this, "menu_id");

        $this->logger->debug('Handling command: ' . $cmd);

        switch ($cmd) {
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
        if ($this->is_plugin_context) {
            $this->handlePluginTabs($active_tab);
        } else {
            $this->handleFileAdministrationTabs($active_tab);
        }
    }

    /**
     * @param string $active_tab
     */
    protected function handleFileAdministrationTabs(string $active_tab)
    {
        $this->tabs->addSubTab(
            self::TAB_RELEASED_CONTAINERS,
            $this->getPluginObject()->txt('tab_released_containers'),
            $this->ctrl->getLinkTarget($this, 'released')
        );
        $this->tabs->addSubTab(
            self::TAB_FILES,
            $this->getPluginObject()->txt('tab_ot_files'),
            $this->ctrl->getLinkTarget($this, 'files')
        );
        $this->tabs->addSubTab(
            self::TAB_SKYDOC,
            $this->getPluginObject()->txt('tab_ot_skydoc'),
            $this->ctrl->getLinkTarget($this, 'remoteFiles')
        );

        $this->tabs->activateSubTab($active_tab);
    }

    /**
     * @param string $active_tab
     */
    protected function handlePluginTabs(string $active_tab)
    {
        $this->tabs->addTab(
            self::TAB_SETTINGS,
            $this->getPluginObject()->txt('tab_ot_settings'),
            $this->ctrl->getLinkTarget($this, 'configure')
        );
        $this->tabs->addTab(
            self::TAB_RELEASED_CONTAINERS,
            $this->getPluginObject()->txt('tab_released_containers'),
            $this->ctrl->getLinkTarget($this, 'released')
        );
        $this->tabs->addTab(
            self::TAB_FILES,
            $this->getPluginObject()->txt('tab_ot_files'),
            $this->ctrl->getLinkTarget($this, 'files')
        );
        $this->tabs->addTab(
            self::TAB_SKYDOC,
            $this->getPluginObject()->txt('tab_ot_skydoc'),
            $this->ctrl->getLinkTarget($this, 'remoteFiles')
        );

        $this->tabs->activateTab($active_tab);
    }
    
    /**
     * Show settings screen
     * @param ilPropertyFormGUI|null $form
     */
    protected function configure(?ilPropertyFormGUI $form = null)
    {
        global $DIC;

        $tpl = $DIC->ui()->mainTemplate();

        $this->handleTabs(self::TAB_SETTINGS);

        if (!$form instanceof ilPropertyFormGUI) {
            $form = $this->initConfigurationForm();
        }
        $tpl->setContent($form->getHTML());
    }

    /**
     * @return ilPropertyFormGUI
     */
    protected function initConfigurationForm() : ilPropertyFormGUI
    {
        $settings = ilOpenTextSettings::getInstance();
        
        $form = new \ilPropertyFormGUI();
        $form->setTitle($this->getPluginObject()->txt('tbl_ot_settings'));
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->addCommandButton('save', $this->lng->txt('save'));

        if (strlen($settings->getUsername())) {
            $form->addCommandButton('ping', $this->getPluginObject()->txt('ot_ping'));
        }

        $form->setShowTopButtons(false);
        
        $lock = new \ilCheckboxInputGUI($this->getPluginObject()->txt('tbl_ot_settings_active'), 'active');
        $lock->setValue(1);
        $lock->setChecked($settings->isActive());
        $form->addItem($lock);

        $this->lng->loadLanguageModule('log');
        $level = new ilSelectInputGUI($this->getPluginObject()->txt('tbl_ot_settings_loglevel'), 'log_level');
        $level->setHideSubForm($settings->getLogLevel() == \ilLogLevel::OFF, '< 1000');
        $level->setOptions(\ilLogLevel::getLevelOptions());
        $level->setValue($settings->getLogLevel());
        $form->addItem($level);

        $log_file = new \ilTextInputGUI($this->getPluginObject()->txt('tbl_ot_settings_logfile'), 'log_file');
        $log_file->setValue($settings->getLogFile());
        $log_file->setInfo($this->getPluginObject()->txt('tbl_ot_settings_logfile_info'));
        $level->addSubItem($log_file);

        $uri = new \ilTextInputGUI($this->getPluginObject()->txt('tbl_ot_settings_url'), 'uri');
        $uri->setMaxLength(255);
        $uri->setRequired(true);
        $uri->setValue($settings->getUri());
        $form->addItem($uri);

        $base_folder = new \ilNumberInputGUI($this->getPluginObject()->txt('tbl_ot_settings_base_folder'), 'base_folder');
        $base_folder->setValue((int) $settings->getBaseFolderId());
        $base_folder->setMinValue(1);
        $base_folder->setRequired(true);
        $base_folder->setInfo($this->getPluginObject()->txt('tbl_ot_settings_base_folder_info'));
        $form->addItem($base_folder);

        $auth = new \ilFormSectionHeaderGUI();
        $auth->setTitle($this->getPluginObject()->txt('tbl_ot_settings_section_auth'));
        $form->addItem($auth);

        $user = new \ilTextInputGUI($this->getPluginObject()->txt('tbl_ot_settings_username'), 'username');
        $user->setMaxLength(128);
        $user->setRequired(true);
        $user->setValue($settings->getUsername());
        $user->setRequired(true);
        $form->addItem($user);

        $pass = new \ilPasswordInputGUI($this->getPluginObject()->txt('tbl_ot_settings_password'), 'password');
        if (strlen($settings->getPassword())) {
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

        // meta data categories
        $md = new \ilFormSectionHeaderGUI();
        $md->setTitle($this->getPluginObject()->txt('tbl_ot_settings_section_md'));
        $form->addItem($md);

        $category_document = new \ilNumberInputGUI($this->getPluginObject()->txt('tbl_ot_settings_cat_document'), 'document');
        $category_document->setRequired(true);
        $category_document->setValue($settings->getDocumentId());
        $form->addItem($category_document);

        $field_manager = new \ilNumberInputGUI($this->getPluginObject()->txt('tbl_ot_settings_field_manager'), 'document_manager');
        $field_manager->setRequired(true);
        $field_manager->setValue($settings->getDocumentManagerId());
        $form->addItem($field_manager);

        $field_owner = new \ilNumberInputGUI($this->getPluginObject()->txt('tbl_ot_settings_field_owner'), 'document_owner');
        $field_owner->setRequired(true);
        $field_owner->setValue($settings->getDocumentOwnerId());
        $form->addItem($field_owner);

        $category_document_info = new \ilNumberInputGUI($this->getPluginObject()->txt('tbl_ot_settings_cat_document_info'), 'document_info');
        $category_document_info->setRequired(true);
        $category_document_info->setValue($settings->getDocumentInfoId());
        $form->addItem($category_document_info);

        $field_id = new \ilNumberInputGUI($this->getPluginObject()->txt('tbl_ot_settings_field_id'), 'document_info_id');
        $field_id->setRequired(true);
        $field_id->setValue($settings->getDocumentInfoIdId());
        $form->addItem($field_id);

        return $form;
    }

    /**
     * Save settings
     */
    protected function save()
    {
        $form = $this->initConfigurationForm();
        $settings = ilOpenTextSettings::getInstance();
        
        if ($form->checkInput()) {
            $settings->setActive($form->getInput('active'));
            $settings->setLogLevel($form->getInput('log_level'));
            $settings->setLogFile($form->getInput('log_file'));
            $settings->setUri($form->getInput('uri'));
            $settings->setUsername($form->getInput('username'));
            if (strcmp($form->getInput('password'), '******') !== 0) {
                $settings->setPassword($form->getInput('password'));
            }
            $settings->setDomain($form->getInput('domain'));
            $settings->setBaseFolderId((int) $form->getInput('base_folder'));

            $settings->setDocumentId((int) $form->getInput('document'));
            $settings->setDocumentManagerId((int) $form->getInput('document_manager'));
            $settings->setDocumentOwnerId((int) $form->getInput('document_owner'));
            $settings->setDocumentInfoId((int) $form->getInput('document_info'));
            $settings->setDocumentInfoIdId((int) $form->getInput('document_info_id'));

            $settings->save();
            ilUtil::sendSuccess($this->lng->txt('settings_saved'), true);
            $this->ctrl->redirect($this, 'configure');
        }
        
        $error = $this->lng->txt('err_check_input');
        $form->setValuesByPost();
        ilUtil::sendFailure($error);
        $this->configure($form);
    }

    /**
     * show table of released containers
     */
    protected function released()
    {
        global $DIC;

        $tpl = $DIC->ui()->mainTemplate();

        $this->handleTabs(self::TAB_RELEASED_CONTAINERS);

        $table = $this->initReleasedContainerTable();
        $table->setObjects([5279]);
        $table->parse();

        $tpl->setContent($table->getHTML());
    }

    protected function resetSyncStatus()
    {
        global $DIC;

        $postVar = $DIC->http()->request()->getParsedBody();

        if (empty($postVar['id'])) {
            \ilUtil::sendFailure($this->lng->txt('select_one'), true);
            $this->ctrl->redirect($this, 'released');
            return;
        }

        \ilOpenTextUtils::getInstance()->resetSyncStatus($postVar['id']);
        \ilUtil::sendSuccess($this->lng->txt('settings_saved'), true);
        $this->ctrl->redirect($this, 'released');
    }

    /**
     * @return ilOpenTextReleasedContainerTableGUI
     */
    protected function initReleasedContainerTable() : \ilOpenTextReleasedContainerTableGUI
    {
        $table = new \ilOpenTextReleasedContainerTableGUI($this, 'released');
        $table->init();
        $table->enableObjectPath(true);
        return $table;
    }

    /**
     * Show opentext synchronized files
     */
    protected function remoteFiles()
    {
        global $DIC;

        $tpl = $DIC->ui()->mainTemplate();

        $this->handleTabs(self::TAB_SKYDOC);

        $table = $this->initRemoteFilesTable();
        $table->parse();

        $tpl->setContent($table->getHTML());
    }

    /**
     * Apply filter
     */
    protected function remoteFilesApplyFilter()
    {
        $table = $this->initRemoteFilesTable();
        $table->resetOffset();
        $table->writeFilterToSession();

        $this->remoteFiles();
    }

    /**
     * Reset filter
     */
    protected function remoteFilesResetFilter()
    {
        $table = $this->initRemoteFilesTable();
        $table->resetOffset();
        $table->resetFilter();

        $this->remoteFiles();
    }

    /**
     * @return \ilOpenTextRemoteFileTableGUI
     */
    protected function initRemoteFilesTable() : \ilOpenTextRemoteFileTableGUI
    {
        $table = new ilOpenTextRemoteFileTableGUI($this, 'remoteFiles');
        $table->init();
        $table->setFilterCommand('remoteFilesApplyFilter');
        $table->setResetCommand('remoteFilesResetFilter');

        return $table;
    }

    /**
     * Show repository file objects
     */
    protected function files()
    {
        global $DIC;

        $tpl = $DIC->ui()->mainTemplate();

        $this->handleTabs(self::TAB_FILES);

        $table = $this->initFilesTable();
        $table->parse();

        $tpl->setContent($table->getHTML());
    }

    /**
     * @return \ilOpenTextFileTableGUI
     */
    protected function initFilesTable() : \ilOpenTextFileTableGUI
    {
        $table = new ilOpenTextFileTableGUI($this, 'files');
        $table->init();
        $table->setFilterCommand('filesApplyFilter');
        $table->setResetCommand('filesResetFilter');

        return $table;
    }

    /**
     * @return bool
     * @throws ilDatabaseException
     */
    protected function filesStatusPlanned() : bool
    {
        global $DIC;

        $postVar = $DIC->http()->request()->getParsedBody();

        $files = (array) ($postVar['file_id'] ?? array());
        if (!count($files)) {
            \ilUtil::sendFailure($this->lng->txt('select_one'), true);
            $this->ctrl->redirect($this, 'files');
            return false;
        }

        $info_item = \ilOpenTextSynchronisationInfo::getInstance();
        foreach ($files as $file_id) {
            $info_item_instance = $info_item->getItemForObjId($file_id);
            if ($info_item_instance->getStatus() == \ilOpenTextSynchronisationInfoItem::STATUS_FAILURE) {
                $info_item_instance->determineAndSetStatus();
                $info_item_instance->save();
            }
        }

        \ilUtil::sendSuccess($this->lng->txt('settings_saved'), true);
        $this->ctrl->redirect($this, 'files');
        return true;
    }

    /**
     * Apply filter
     */
    protected function filesApplyFilter()
    {
        $table = $this->initFilesTable();
        $table->resetOffset();
        $table->writeFilterToSession();

        $this->files();
    }

    /**
     * Reset filter
     */
    protected function filesResetFilter()
    {
        $table = $this->initFilesTable();
        $table->resetOffset();
        $table->resetFilter();

        $this->files();
    }


    /**
     *
     */
    protected function downloadLatestVersion()
    {
        global $DIC;

        $getVars = $DIC->http()->request()->getQueryParams();

        $otxt_id = (string) ($getVars['otxt_id'] ?? '');

        $this->logger->debug('Trying to download latest version of : ' . $otxt_id);

        $this->ctrl->redirect($this, 'files');
    }

    /**
     *
     */
    protected function ping()
    {
        $connector = \ilOpenTextConnector::getInstance();
        try {
            // try to login
            $connector->ping();

            //$connector->search('DSC*');

            // try to fetch base node
            //$connector->fetchNode(ilOpenTextSettings::getInstance()->getBaseFolderId());

            ilUtil::sendSuccess(ilOpenTextPlugin::getInstance()->txt('success_connection'), true);
            $this->ctrl->redirect($this, 'configure');
        } catch (\ilOpenTextConnectionException $e) {
            ilUtil::sendFailure($e->getMessage(), true);
            $this->ctrl->redirect($this, 'configure');
        }
    }
}
