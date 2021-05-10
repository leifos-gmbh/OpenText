<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */


/**
 * Open text settings
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilOpenTextRuntimeConfig
{
    /**
     * @var \ilOpenTextRuntimeConfig
     */
    private static $instance = null;

    /**
     * @var null|\ilLogger
     */
    private $logger = null;

    /**
     * @var string
     */
    private $ticket = '';

    /**
     * ilOpenTextSettings constructor.
     */
    protected function __construct()
    {
        global $DIC;

        $this->logger = $DIC->logger()->otxt();
    }

    /**
     * @param string $a_ticket
     */
    public function setTicket(string $a_ticket)
    {
        $this->ticket = $a_ticket;
    }

    /**
     * @return string
     */
    public function getTicket() : string
    {
        return $this->ticket;
    }

    /**
     * @return ilOpenTextRuntimeConfig
     */
    public static function getInstance() : ilOpenTextRuntimeConfig
    {
        if (isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
