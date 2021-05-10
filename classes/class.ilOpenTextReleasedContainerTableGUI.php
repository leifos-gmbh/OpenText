<?php

/**
 * Class ilOpenTextReleasedContainerTableGUI
 */
class ilOpenTextReleasedContainerTableGUI extends \ilObjectTableGUI
{
    /**
     * @var string
     */
    private const TABLE_ID = 'skydoc_released';

    /**
     * @var int
     */
    private const CONT_SYKDOC_ENABLED = 1;

    /**
     * @var int
     */
    private const CONT_SKYDOC_DISABLED = 2;

    /**
     * @var null | \ilOpenTextPlugin
     */
    private $plugin = null;

    /**
     * @var null | ilDBInterface
     */
    private $db = null;

    /**
     * @var \ilTree | null
     */
    private $tree = null;

    /**
     * @var null | \ilLogger
     */
    private $logger = null;


    /**
     * ilOpenTextReleasedContainerTableGUI constructor.
     * @param object $a_parent_obj
     * @param string $a_parent_cmd
     */
    public function __construct($a_parent_obj, $a_parent_cmd)
    {
        global $DIC;

        parent::__construct($a_parent_obj, $a_parent_cmd, self::TABLE_ID);

        $this->plugin = \ilOpenTextPlugin::getInstance();
        $this->db = $DIC->database();
        $this->tree = $DIC->repositoryTree();
        $this->logger = $DIC->logger()->otxt();
    }

    /**
     * @param ilPathGUI $path
     * @return ilPathGUI
     */
    public function customizePath(ilPathGUI $path)
    {
        $path->enableTextOnly(false);
        $path->textOnly(false);
        return $path;
    }

    /**
     *
     */
    public function init()
    {
        $this->enableRowSelectionInput(true);
        parent::init();

        $this->setFormAction($this->ctrl->getFormAction($this->getParentObject()));
        $this->addColumn($this->plugin->txt('released_container_status'), 'status');
        $this->setRowTemplate('tpl.released_container_row.html', $this->plugin->getDirectory());

        $this->addMultiCommand('resetSyncStatus', $this->plugin->txt('reset_sync_status'));
    }

    /**
     * @param array $set
     */
    public function fillRow($set)
    {
        parent::fillRow($set);

        if ($set['status'] == self::CONT_SYKDOC_ENABLED) {
            $this->tpl->setVariable('TXT_STATUS', $this->plugin->txt('status_active_sync'));
        } else {
            $this->tpl->setVariable('TXT_STATUS', $this->plugin->txt('status_inactive_sync'));
        }
    }

    /**
     * Parse objects
     */
    public function parse()
    {
        $this->readObjects();


        $counter = 0;
        $set = array();
        foreach ($this->getObjects() as $ref_id) {
            $type = ilObject::_lookupType(ilObject::_lookupObjId($ref_id));
            if ($type == 'rolf') {
                continue;
            }
            $set[$counter]['ref_id'] = $ref_id;
            $set[$counter]['obj_id'] = ilObject::_lookupObjId($ref_id);
            $set[$counter]['type'] = ilObject::_lookupType(ilObject::_lookupObjId($ref_id));
            $set[$counter]['title'] = ilObject::_lookupTitle(ilObject::_lookupObjId($ref_id));
            $set[$counter]['status'] = \ilContainer::_lookupContainerSetting(
                $set[$counter]['obj_id'],
                ilObjectServiceSettingsGUI::PL_SKYDOC,
                false
            ) ?
                self::CONT_SYKDOC_ENABLED :
                self::CONT_SKYDOC_DISABLED
            ;
            $counter++;
        }
        $this->setMaxCount($counter);
        $this->setData($set);
    }

    /**
     *
     */
    protected function readObjects()
    {
        $query = 'select * from container_settings ' .
            'where ( keyword = ' . $this->db->quote('cont_skydoc', \ilDBConstants::T_TEXT) . ' OR ' .
            'keyword = ' . $this->db->quote('cont_skydoc_disabled', \ilDBConstants::T_TEXT) . ') AND ' .
            'value = ' . $this->db->quote(1, \ilDBConstants::T_INTEGER);
        $res = $this->db->query($query);

        $objects = [];
        while ($row = $res->fetchRow(\ilDBConstants::FETCHMODE_OBJECT)) {
            $refs = \ilObject::_getAllReferences($row->id);
            $ref = end($refs);

            if (!$this->tree->isDeleted($ref)) {
                $objects[] = $ref;
            }
        }
        $this->setObjects($objects);
    }
}
