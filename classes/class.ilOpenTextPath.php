<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */



/**
 * OpenText path map
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 *
 */
class ilOpenTextPath
{
    /**
     * @var string
     */
    public const TABLE_NAME = 'evnt_evhk_otxt_path';

    /**
     * @var string
     */
    private $path = '';

    /**
     * @var bool
     */
    private $stored = false;

    /**
     * @var int
     */
    private $otxt_id = 0;

    private $db = null;

    /**
     * ilOpenTextPath constructor.
     */
    public function __construct(string $path, int $otxt_id)
    {
        global $DIC;

        $this->db = $DIC->database();

        $this->path = $path;
        $this->otxt_id = $otxt_id;
    }

    public function isStored() : bool
    {
        return $this->stored;
    }

    /**
     * @return bool
     */
    public function save() : bool
    {
        $query = 'INSERT INTO ' . self::TABLE_NAME . ' (path, otxt_id) ' .
            'VALUES ( '.
            $this->db->quote($this->path, \ilDBConstants::T_TEXT) . ', ' .
            $this->db->quote($this->otxt_id, \ilDBConstants::T_INTEGER) . ' ' .
            ')';
        $this->db->manipulate($query);
        $this->stored = true;
        return true;
    }

    /**
     * @return string
     */
    public function getPath() : string
    {
        return $this->path;
    }


    /**
     * @return int
     */
    public function getOpentTextId() : int
    {
        return $this->otxt_id;
    }

}
