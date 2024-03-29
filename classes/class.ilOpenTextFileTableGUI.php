<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */


/**
 * File object table gui
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 *
 */
class ilOpenTextFileTableGUI extends ilTable2GUI
{
    /**
     * @var string
     */
    private const OT_FORM_NAME = 'ot_ilias_files';

    /**
     * @var string
     */
    private const TABLE_ID = 'otxt_files';

    /**
     * @var string
     */
    private const OT_FILTER_STATUS = 'status';

    /**
     * @var \ilOpenTextPlugin|null
     */
    private $plugin = null;

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
    }

    /**
     * Init table
     */
    public function init()
    {
        $this->setFormName(self::OT_FORM_NAME);
        $this->setFormAction($this->ctrl->getFormAction($this->getParentObject(), $this->getParentCmd()));
        $this->initFilter();

        $this->addColumn('', '');
        $this->addColumn($this->lng->txt('title'), 'title', '25%');
        $this->addColumn($this->plugin->txt('tbl_remote_files_skydoc_id'), 'otxt_id', '10%');
        $this->addColumn($this->plugin->txt('file_create_date'), 'cdate', '15%');
        $this->addColumn($this->plugin->txt('file_sync_status'), 'status_num', '15%');
        $this->addColumn($this->plugin->txt('file_last_update'), 'mdate', '15%');
        $this->addColumn($this->plugin->txt('file_repository'), 'in_repository', '10%');
        $this->addColumn($this->plugin->txt('file_actions'), 'actions', '10%');


        $this->setDefaultOrderField('title');
        $this->setDefaultOrderDirection('asc');

        $this->setRowTemplate('tpl.file_table_row.html', $this->plugin->getDirectory());

        $this->setExternalSegmentation(true);
        $this->setExternalSorting(true);

        $this->determineOffsetAndOrder();

        $this->addMultiCommand(
            'filesStatusPlanned',
            $this->plugin->txt('ilias_file_table_filter_status_update')
        );

        $this->setSelectAllCheckbox('file_id');
    }

    /**
     * Init table filter
     */
    public function initFilter()
    {
        $this->setDefaultFilterVisiblity(true);

        $status = $this->addFilterItemByMetaType(
            self::OT_FILTER_STATUS,
            ilTable2GUI::FILTER_SELECT,
            false,
            $this->plugin->txt('file_sync_status')
        );
        $status->setOptions(\ilOpenTextSynchronisationInfoItem::getStatusSelectOptions());
        $this->current_filter[self::OT_FILTER_STATUS] = $status->getValue();
    }

    /**
     * @param array $a_set
     * @throws ilDateTimeException
     */
    public function fillRow($a_set)
    {
        $this->tpl->setVariable('VAL_ID', $a_set['obj_id']);

        if ($a_set['otxt_id']) {
            $this->tpl->setCurrentBlock('with_link');
            $this->tpl->setVariable('SKYDOC_LINK', \ilOpenTextUtils::getInstance()->generateOpenTextDirectLink($a_set['otxt_id']));
            $this->tpl->setVariable('SKYDOC_NAME', (string) $a_set['otxt_id']);
            $this->tpl->parseCurrentBlock();
        }

        $refs = $this->getReferences($a_set['obj_id']);
        if (!count($refs)) {
            $this->tpl->setCurrentBlock('title');
            $this->tpl->setVariable('OBJ_TITLE', $a_set['title']);
            $this->tpl->parseCurrentBlock();
        } else {
            $first_ref = $refs[0];

            $link = \ilLink::_getLink($first_ref);

            $this->tpl->setCurrentBlock('title');
            $this->tpl->setVariable('OBJ_LINKED_TITLE', $a_set['title']);
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

        $create_date = new ilDateTime($a_set['create_date'], IL_CAL_DATETIME);
        $this->tpl->setVariable('CDATE', ilDatePresentation::formatDate($create_date));

        $last_update = new ilDateTime($a_set['last_update'], IL_CAL_DATETIME);
        $this->tpl->setVariable('LAST_UPDATE', ilDatePresentation::formatDate($last_update));

        if (array_key_exists('deleted', $a_set) && $a_set['deleted'] instanceof ilDateTime) {
            $this->tpl->setVariable('DELETION_DATE', ilDatePresentation::formatDate($a_set['deleted']));
        }

        $this->tpl->setVariable(
            'STATUS',
            $this->plugin->txt(\ilOpenTextSynchronisationInfoItem::statusToLangKey($a_set['status']))
        );

        // show file download
        if (
            $a_set['status'] == \ilOpenTextSynchronisationInfoItem::STATUS_SYNCHRONISED &&
            (int) $a_set['otxt_id'] > 0
        ) {
            $selection = new \ilAdvancedSelectionListGUI();
            $selection->setId('sync_item_' . $a_set['obj_id']);
            $selection->setListTitle($this->lng->txt('actions'));

            $this->ctrl->setParameter($this->getParentObject(), 'otxt_id', $a_set['otxt_id']);
            $selection->addItem(
                $this->plugin->txt('open_in_opentext'),
                '',
                \ilOpenTextUtils::getInstance()->generateOpenTextDirectLink($a_set['otxt_id']),
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
     * @throws ilDateTimeException
     * @throws ilDatabaseException
     */
    private function getFiles() : array
    {
        $file_data = $this->getFilteredFiles();

        $files = [];
        $counter = 0;
        foreach ($file_data as $key => $row) {
            $files[$counter]['obj_id'] = $row->obj_id;
            $status = \ilOpenTextSynchronisationInfoItem::STATUS_SCHEDULED;
            if ($row->status_num) {
                $status = $row->status_num;
            }
            $files[$counter]['status'] = $status;
            $files[$counter]['status_num'] = $row->status_num;
            $files[$counter]['otxt_id'] = $row->otxt_id;
            $files[$counter]['title'] = $row->title;
            $files[$counter]['description'] = $row->description;
            $files[$counter]['create_date'] = $row->cdate;
            $files[$counter]['last_update'] = $row->mdate;
            $files[$counter]['deleted'] = null;
            if (strlen($row->in_repository)) {
                $files[$counter]['deleted'] = new ilDateTime($row->in_repository, IL_CAL_DATETIME, ilTimeZone::UTC);
            }
            ++$counter;
        }
        return $files;
    }

    /**
     * Get filtered files (offset,limit,order)
     * @return array
     * @throws ilDatabaseException
     */
    private function getFilteredFiles() : array
    {
        global $DIC;

        $db = $DIC->database();

        $fields = 'title, description, create_date cdate , otxt.last_update mdate , deleted in_repository , status status_num, otxt_id ';
        $query_fields = 'select distinct(obd.obj_id),' . $fields;
        $query_count = 'select count(distinct(obd.obj_id)) files, ' . $fields;


        $query =
            'from object_data obd ' .
            'join object_reference obr on obd.obj_id = obr.obj_id ' .
            'join ' . \ilOpenTextSynchronisationInfo::TABLE_ITEMS . ' otxt on obr.obj_id = otxt.obj_id ' .
            'where type = ' . $db->quote('file', 'text') . ' ' .
            'and otxt.status != ' . $db->quote(\ilOpenTextSynchronisationInfoItem::STATUS_SYNC_DISABLED, \ilDBConstants::T_INTEGER) . ' ';

        if ($this->current_filter[self::OT_FILTER_STATUS] >= \ilOpenTextSynchronisationInfoItem::STATUS_SCHEDULED) {
            $filter = 'and status = ' . $db->quote($this->current_filter[self::OT_FILTER_STATUS], \ilDBConstants::T_INTEGER);
            $query .= $filter . ' ';
        }


        $query_order = 'ORDER BY  ' .
            ($this->getOrderField() ? $this->getOrderField() : $this->getDefaultOrderField()) .
            ' ' . strtoupper($this->getOrderDirection() ? $this->getOrderDirection() : $this->getDefaultOrderDirection()) . ' ';
        $query_group = 'GROUP BY obd.obj_id ';

        // count query
        $count_query = $query_count . $query . $query_order;
        $this->logger->debug('Querying: ' . $count_query);

        $file_query = $query_fields . $query . $query_order;
        $this->logger->debug('Querying: ' . $file_query);

        $res = $db->query($count_query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $this->setMaxCount($row->files);
        }

        $db->setLimit($this->getLimit(), $this->getOffset());
        $res = $db->query($file_query);

        $rows = [];
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param int $obj_id
     * @return array
     */
    private function getReferences(int $obj_id) : array
    {
        global $DIC;

        $tree = $DIC->repositoryTree();

        $refs = ilObject::_getAllReferences($obj_id);

        $valid_references = [];
        foreach ($refs as $ref => $unused) {
            if ($tree->isInTree($ref) && !$tree->isDeleted($ref)) {
                $valid_references[] = $ref;
            }
        }
        return $valid_references;
    }
}
