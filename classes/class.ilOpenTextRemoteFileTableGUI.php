<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use Swagger\Client\Model\VersionsDataResultsData;

/**
 * Remote File object table gui
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 *
 */
class ilOpenTextRemoteFileTableGUI extends ilTable2GUI
{
    /**
     * @var string
     */
    private const OT_FORM_NAME = 'ot_remote_files';

    /**
     * @var string
     */
	private const TABLE_ID = 'otxt_remote_files';

    /**
     * @var string
     */
	private const OT_FILTER_NAME = 'name';

    /**
     * @var string
     */
	private const OT_FILTER_CREATION = 'creation';

    /**
     * @var string
     */
	private const OT_FILTER_AUTHOR = 'author';

    /**
     * @var int
     */
	private const OT_LIMIT_SEARCH = 100;


	/**
	 * @var \ilOpenTextPlugin|null
	 */
	private $plugin = null;

    /**
     * @var null | \ilOpenTextUtils
     */
	private $utils = null;

    /**
     * @var null | \ilOpenTextSettings
     */
    private $settings = null;

    /**
     * @var null | \ilOpenTextConnector
     */
	private $connector = null;

	/**
	 * @var \ilLogger|null
	 */
	private $logger = null;

    /**
     * @var array
     */
	private $current_filter = [];

	/**
	 * ilOpenTextFileTableGUI constructor.
	 * @param object $a_parent_obj
	 * @param string $a_parent_cmd
	 */
	public function __construct($a_parent_obj, string $a_parent_cmd)
	{
		global $DIC;

		$this->setId(self::TABLE_ID);
		parent::__construct($a_parent_obj, $a_parent_cmd);

		$this->plugin = \ilOpenTextPlugin::getInstance();
		$this->logger = $DIC->logger()->otxt();
		$this->utils = \ilOpenTextUtils::getInstance();
		$this->connector = \ilOpenTextConnector::getInstance();
		$this->settings = \ilOpenTextSettings::getInstance();
	}

	/**
	 * Init table
	 */
	public function init()
	{
	    $this->setFormName(self::OT_FORM_NAME);
	    $this->setFormAction($this->ctrl->getFormAction($this->getParentObject(),$this->getParentCmd()));
        $this->initFilter();

        $this->addColumn('','');
		$this->addColumn($this->plugin->txt('remote_file_name'), 'title','40%');
		$this->addColumn($this->plugin->txt('tbl_remote_files_skydoc_id'),'id', '10%');
		$this->addColumn($this->plugin->txt('file_create_date'),'cdate','20%');
		$this->addColumn($this->plugin->txt('remote_file_author'),'author','20%');
		$this->addColumn($this->plugin->txt('file_actions'),'actions','10%');


		$this->setDefaultOrderField('title');
		$this->setDefaultOrderDirection('asc');

		$this->setRowTemplate('tpl.remote_file_table_row.html', $this->plugin->getDirectory());

		$this->setExternalSegmentation(true);
		$this->setExternalSorting(true);

		$this->determineOffsetAndOrder();
		$this->setSelectAllCheckbox('remote_file_id');
		$this->setEnableNumInfo(true);

	}

    /**
     * Init table filter
     */
	public function initFilter()
    {
        $this->setDefaultFilterVisiblity(true);

        $status = $this->addFilterItemByMetaType(
            self::OT_FILTER_NAME,
            ilTable2GUI::FILTER_TEXT,
            false,
            $this->plugin->txt('remote_file_name')
        );
        $this->current_filter[self::OT_FILTER_NAME] = $status->getValue();

        $creation = $this->addFilterItemByMetaType(
            self::OT_FILTER_CREATION,
            \ilTable2GUI::FILTER_DATE_RANGE,
            false,
            $this->plugin->txt('file_create_date')
        );
        $this->current_filter[self::OT_FILTER_CREATION] = $creation->getValue();

        $author = $this->addFilterItemByMetaType(
            self::OT_FILTER_AUTHOR,
            \ilTable2GUI::FILTER_TEXT,
            false,
            $this->plugin->txt('remote_file_author')
        );
        $this->current_filter[self::OT_FILTER_AUTHOR] = $author->getValue();

    }

	/**
	 * @param array $a_set
	 */
	public function fillRow($file)
	{

	    $this->tpl->setVariable('VAL_ID', $file['id']);

	    $this->tpl->setVariable('SKYDOC_LINK', \ilOpenTextUtils::getInstance()->generateOpenTextDirectLink($file['id']));
	    $this->tpl->setVariable('SKYDOC_NAME', $file['id']);

        $refs = $this->getReferences($file['obj_id']);
        if (!count($refs)) {
            $this->tpl->setCurrentBlock('title');
            $this->tpl->setVariable('OBJ_TITLE', $file['name']);
            $this->tpl->parseCurrentBlock();
        } else {

            $first_ref = $refs[0];

            $link = \ilLink::_getLink($first_ref);

            $this->tpl->setCurrentBlock('title');
            $this->tpl->setVariable('OBJ_LINKED_TITLE', $file['name']);
            $this->tpl->setVariable('OBJ_LINK', $link);
            $this->tpl->parseCurrentBlock();
            foreach ($refs as $ref) {

                $path = new \ilPathGUI();
                $path->enableTextOnly(false);
                $path->setUseImages(true);

                $this->tpl->setCurrentBlock('path');
                $this->tpl->setVariable('OBJ_PATH', $path->getPath(ROOT_FOLDER_ID, $ref));
                $this->tpl->parseCurrentBlock();
            }
        }

	    $this->tpl->setVariable('CDATE', \ilDatePresentation::formatDate(new \ilDateTime($file['create_date'], IL_CAL_UNIX)));
	    $this->tpl->setVariable('AUTHOR', $file['author']);


		// show file download
		if((int) $file['id'] > 0) {

		    $selection = new \ilAdvancedSelectionListGUI();
		    $selection->setId('sync_item_' . $file['id']);
		    $selection->setListTitle($this->lng->txt('actions'));

		    $this->ctrl->setParameter($this->getParentObject(),'id', $file['id']);
		    $selection->addItem(
		        $this->plugin->txt('open_in_opentext'),
                '',
                \ilOpenTextUtils::getInstance()->generateOpenTextDirectLink($file['id']),
                '',
                '',
                '_blank'
            );
		    $this->tpl->setVariable('ACTIONS', $selection->getHTML());
        }
	}

	/**
	 *
	 */
	public function parse()
	{
		$files = $this->getFiles();
		$this->setData($files);
	}

	/**
	 * Read and get files
	 * @return array
	 * @throws \ilDatabaseException
	 */
	private function getFiles()
	{
		global $DIC;

		$file_versions = $this->getFilteredFiles();

		$files = [];
		foreach ($file_versions as $index => $file_version) {

		    $file['id'] = $file_version->getData()->getProperties()->getId();
		    try {
		        $connector = \ilOpenTextConnector::getInstance();
		        $categories = $connector->getCategories($file['id']);
		        $this->logger->dump($categories);

            }
            catch (Exception $e) {

            }
		    if(preg_match('/^[0-9]+\_/',$file_version->getData()->getProperties()->getName()) === 1) {

		        $parts = explode('_', $file_version->getData()->getProperties()->getName(),2);
		        $this->logger->dump($parts);
		        $file['obj_id'] = $parts[0];
		        $file['name'] = $parts[1];
            }
		    else {
		        $file['obj_id'] = 0;
		        $file['name'] = $file_version->getData()->getProperties()->getName();
            }

		    // overwrite with version info, if available
            if (
                $file_version->getData()->getVersions() instanceof VersionsDataResultsData &&
                strlen($file_version->getData()->getVersions()->getFileName())
            ) {
                $file['name'] = $file_version->getData()->getVersions()->getFileName();
            }
            $file['author'] = $file_version->getData()->getProperties()->getExternalIdentity();
            $file['create_date'] = 0;

		    if(
		        $file_version->getData()->getVersions() instanceof VersionsDataResultsData &&
		        $file_version->getData()->getVersions()->getFileCreateDate() instanceof DateTime) {
		        $file['create_date'] = $file_version->getData()->getVersions()->getFileCreateDate()->getTimestamp();
            }
		    $files[] = $file;
		}
		$this->setMaxCount(count($files));
		return $files;
	}

	/**
	 * Get filtered files (offset,limit,order)
	 */
	private function getFilteredFiles()
	{
	    $from = $until = null;
        if (is_array($this->current_filter[self::OT_FILTER_CREATION])) {
            $from = $this->current_filter[self::OT_FILTER_CREATION]['from'];
            $until = $this->current_filter[self::OT_FILTER_CREATION]['to'];
        }

	    $query = $this->utils->generateQueryFromFilter(
	        $this->current_filter[self::OT_FILTER_NAME],
            $this->current_filter[self::OT_FILTER_AUTHOR],
            $from,
            $until
        );

	    try {
	        $res = $this->connector->search(
	            $query,
                $this->settings->getBaseFolderId(),
                self::OT_LIMIT_SEARCH);
	        return $res;
        }
        catch (\ilOpenTextConnectionException $e) {
	        $this->logger->error('Cannot receive remote file info: ' . $e->getMessage());
        }
        return [];
	}

    /**
     * @param int $obj_id
     * @return int[]
     */
    private function getReferences($obj_id)
    {
        global $DIC;

        $tree = $DIC->repositoryTree();

        $refs = ilObject::_getAllReferences($obj_id);

        $valid_references = [];
        foreach($refs as $ref => $unused) {

            if($tree->isInTree($ref) && !$tree->isDeleted($ref)) {
                $valid_references[] = $ref;
            }
        }
        return $valid_references;
    }



}