<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use Swagger\Client\HeaderSelector;

/**
 * Header selector which uses Content type x-www-url-encoded
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilOpenTextUtils
{
    /**
     * @var int
     */
    private const MAX_EXPONENT = 3;

    /**
     * @var int
     */
    private const FACTOR = 100;

    /**
     * @var null | \ilOpenTextUtils
     */
    private static $instance = null;

    /**
     * @var null | \ilDBInterface
     */
    private $db = null;

    /**
     * @var null | \ilOpenTextPlugin
     */
    private $plugin = null;

    /**
     * @var null | \ilOpenTextSettings
     */
    private $settings = null;

    /**
     * @var null | \ilLogger
     */
    private $logger = null;

    /**
     * ilOpenTextUtils constructor.
     */
    private function __construct()
    {
        global $DIC;

        $this->plugin = \ilOpenTextPlugin::getInstance();
        $this->settings = \ilOpenTextSettings::getInstance();
        $this->logger = $DIC->logger()->otxt();
        $this->db = $DIC->database();
    }

    /**
     * @return \ilOpenTextUtils
     */
    public static function getInstance() : ilOpenTextUtils
    {
        if (!self::$instance instanceof \ilOpenTextUtils) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param int $a_obj_id
     * @return string
     */
    public function buildPathFromId(int $a_obj_id) : string
    {
        $path_array = [];
        $path_string = '';
        $found = false;

        $num = $a_obj_id;

        for ($i = self::MAX_EXPONENT; $i > 0; $i--) {
            $factor = pow(self::FACTOR, $i);
            if (($tmp = (int) ($num / $factor)) || $found) {
                $path[] = $tmp;
                $num = $num % $factor;
                $found = true;
            }
        }

        if (count($path)) {
            $path_string = implode('/', $path);
        }

        $this->logger->dump('Create path: ' . $path_string . ' for id ' . $a_obj_id);

        return $path_string;
    }

    /**
     * Generate a permanent link
     * @param int $version_id
     * @return string
     */
    public function generateOpenTextDirectLink(int $version_id) : string
    {
        $settings = \ilOpenTextSettings::getInstance();
        $base_url = $settings->getUri();

        $uri = $base_url . '?func=ll&objaction=overview&objid=';
        $uri .= $version_id;

        return $uri;
    }

    /**
     * @param string|null $name
     * @return string|null
     */
    public function generateQueryFromFilter(string $name = '', string $author = '', \ilDate $from = null, \ilDate $until = null) : ?string
    {
        $query = '';
        if (strlen($name)) {
            $query .= ('(' . $name . ' ) ');
        }
        if (strlen($author)) {

            // check if external_account
            $user_id = \ilObjUser::_lookupId($author);
            $ext_account = \ilObjUser::_lookupExternalAccount($user_id);
            if ($ext_account) {
                $author = $ext_account;
            }

            $author_query = 'OTExternalIdentity: ' . $author;
            $query .= ('AND ' . $author_query . ' ');
        }

        // add location part
        $query .= 'AND OTLocation:' . $this->settings->getBaseFolderId() . ' ';
        $query .= 'AND OTSubType:' . \ilOpenTextConnector::OTXT_DOCUMENT_TYPE . ' ';

        if ($from instanceof \ilDate) {
            $query .= 'AND OTExternalCreateDate: >=' . $from->get(IL_CAL_FKT_DATE, 'Ymd') . ' ';
        }
        if ($until instanceof \ilDate) {
            $query .= 'AND OTExternalCreateDate: <=' . $until->get(IL_CAL_FKT_DATE, 'Ymd') . ' ';
        }
        $this->logger->info('Parsed query is: ' . $query);
        return $query;
    }

    /**
     * @throws ilDatabaseException
     * @return int[]
     */
    public function readSynchronisableCategories() : array
    {
        $query = 'select id from container_settings ' .
            'where keyword = ' . $this->db->quote('cont_skydoc', \ilDBConstants::T_TEXT);
        $res = $this->db->query($query);

        $category_obj_ids = [];
        while ($row = $res->fetchRow(\ilDBConstants::FETCHMODE_OBJECT)) {
            $category_obj_ids[] = $row->id;
        }

        // transfer to ref_id
        $category_ref_ids = [];
        foreach ($category_obj_ids as $category_ref_id) {
            $refs = \ilObject::_getAllReferences($category_ref_id);
            $category_ref_ids[] = end($refs);
        }
        return $category_ref_ids;
    }

    /**
     * @param int[] $ids
     */
    public function resetSyncStatus(array $ids)
    {
        foreach ($ids as $id) {
            $obj_id = \ilObject::_lookupObjId($id);

            $query = 'delete from container_settings ' .
                'where id = ' . $this->db->quote($obj_id, \ilDBConstants::T_INTEGER) . ' and ( ' .
                'keyword = ' . $this->db->quote(\ilObjectServiceSettingsGUI::PL_SKYDOC, \ilDBConstants::T_TEXT) . ' or ' .
                'keyword = ' . $this->db->quote(\ilObjectServiceSettingsGUI::PL_SKYDOC_DISABLED, \ilDBConstants::T_TEXT) .
                ')';
            $this->db->manipulate($query);
        }
    }

    /**
     * @return int[]
     */
    public function readDisabledCategories() : array
    {
        $query = 'select id from container_settings ' .
            'where keyword = ' . $this->db->quote('cont_skydoc_disabled', \ilDBConstants::T_TEXT);
        $res = $this->db->query($query);

        $category_obj_ids = [];
        while ($row = $res->fetchRow(\ilDBConstants::FETCHMODE_OBJECT)) {
            $category_obj_ids[] = $row->id;
        }

        // transfer to ref_id
        $category_ref_ids = [];
        foreach ($category_obj_ids as $category_ref_id) {
            $refs = \ilObject::_getAllReferences($category_ref_id);
            $category_ref_ids[] = end($refs);
        }
        return $category_ref_ids;
    }

    /**
     * @param SplFileObject $file
     * @return SplFileObject
     * @throws Exception
     */
    public function sanitizeFile(SplFileObject $file) : SplFileObject
    {
        $name = $file->getFilename();
        $tmp_name = \ilUtil::ilTempnam();

        copy(
            $file->getPathname(),
            $tmp_name
        );

        try {
            $tmp_file = new SplTempFileObject($tmp_name);
        } catch (Exception $e) {
            $this->logger->warning('Cannot create tem file object: ' . $e->getMessage());
            throw $e;
        }
        return $tmp_file;
    }

    /**
     * @param null|SplTempFileObject $file
     */
    public function removeTemporaryFile(SplTempFileObject $file = null)
    {
        if ($file instanceof SplTempFileObject) {
            $path = $file->getPathname();
            unset($file);
            unlink($path);
        }
    }
}
