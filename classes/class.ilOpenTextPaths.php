<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */



/**
 * OpenText path map
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 *
 */
class ilOpenTextPaths
{
    /**
     * @var null | \ilOpenTextPaths
     */
    private static $instance = null;

    /**
     * @var \ilOpenTextPath[]
     */
    private $paths = [];

    /**
     * @var null|ilDBInterface
     */
    private $db = null;

    /**
     * ilOpenTextPathes constructor.
     */
    private function __construct()
    {
        global $DIC;

        $this->db = $DIC->database();
        $this->readPathMap();
    }

    /**
     * @return \ilOpenTextPaths
     */
    public static function getInstance() : ilOpenTextPaths
    {
        if (!self::$instance instanceof \ilOpenTextPaths) {
            self::$instance = new self();
        }
        return self::$instance;
    }



    /**
     * @param string $a_path
     * @return int|null
     */
    public function lookupOpenTextId(string $a_path) : ?int
    {
        foreach ($this->paths as $path) {
            if ($path->getPath() == $a_path) {
                return $path->getOpenTextId();
            }
        }
        return null;
    }

    /**
     * @param ilOpenTextPath $path
     */
    public function addPath(\ilOpenTextPath $path)
    {
        if (!$path->isStored()) {
            $path->save();
        }
        $this->paths[] = $path;
    }

    /**
     * Read existing path maps
     */
    private function readPathMap()
    {
        $query = 'SELECT * from ' . \ilOpenTextPath::TABLE_NAME;
        $res = $this->db->query($query);
        while ($row = $res->fetchRow(\ilDBConstants::FETCHMODE_OBJECT)) {
            $this->paths[] = new \ilOpenTextPath($row->path, $row->otxt_id);
        }
    }
}
