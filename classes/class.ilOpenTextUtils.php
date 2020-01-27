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
     * @var null | \ilOpenTextPlugin
     */
    private $plugin = null;

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
        $this->logger = $DIC->logger()->otxt();
    }

    /**
     * @return \ilOpenTextUtils
     */
    public static function getInstance()
    {
        if(!self::$instance instanceof \ilOpenTextUtils) {
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
}
