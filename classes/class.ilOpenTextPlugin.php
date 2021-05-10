<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

use Monolog\Handler\StreamHandler;

/**
 * openText event plugin base class
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilOpenTextPlugin extends ilEventHookPlugin
{
    private static $instance = null;

    const PNAME = 'OpenText';
    const CTYPE = 'Services';
    const CNAME = 'EventHandling';
    const SLOT_ID = 'evhk';

    /**
     * Get singleton instance
     * @global ilPluginAdmin $ilPluginAdmin
     * @return ilOpenTextPlugin
     */
    public static function getInstance() : ilOpenTextPlugin
    {
        global $ilPluginAdmin;

        if (self::$instance) {
            return self::$instance;
        }
        return self::$instance = ilPluginAdmin::getPluginObject(
            self::CTYPE,
            self::CNAME,
            self::SLOT_ID,
            self::PNAME
        );
    }

    /**
     * @inheritDoc
     */
    public function handleEvent($a_component, $a_event, $a_parameter)
    {
        switch ($a_component) {
            case 'Services/Object':

                switch ($a_event) {

                    case 'create':
                    case 'update':
                        ilLoggerFactory::getLogger('otxt')->notice('Handling event: ' . $a_component . ' ' . $a_event);
                        $this->handleUpdateEvent((array) $a_parameter);
                        break;
                }
        }
    }

    /**
     * @param ilCronJobResult $result
     * @throws ilDatabaseException
     */
    public function runCronJob(ilCronJobResult $result)
    {
        global $DIC;

        $logger = $DIC->logger()->otxt();
        $logger->debug('Cron job started ... ');

        // add missing info items
        $info = ilOpenTextSynchronisationInfo::getInstance();
        $info->createMissingItems();

        $cron_handler = new ilOpenTextCronJobHandler($result);
        $cron_handler->run();

        $logger->debug('Cron job finished');
    }

    /**
     * @param array $a_parameter
     * @throws ilDatabaseException
     */
    protected function handleUpdateEvent(array $a_parameter)
    {
        if (
            is_array($a_parameter) &&
            array_key_exists('obj_type', $a_parameter) &&
            $a_parameter['obj_type'] == 'file' &&
            array_key_exists('obj_id', $a_parameter)
        ) {
            ilLoggerFactory::getLogger('otxt')->debug('Added new update command for obj_id ' . $a_parameter['obj_id']);
            $info = \ilOpenTextSynchronisationInfo::getInstance();
            $item = $info->getItemForObjId($a_parameter['obj_id']);
            $item->determineAndSetStatus();
            $item->save();
        }
    }


    /**
     * Get plugin name
     * @return string
     */
    public function getPluginName() : string
    {
        return self::PNAME;
    }

    /**
     * Init auto load
     */
    protected function init()
    {
        global $DIC;

        $logger = $DIC->logger()->otxt();

        require($this->getDirectory() . '/vendor/autoload.php');
        $this->initAutoLoad();

        $settings = \ilOpenTextSettings::getInstance();
        $logger->debug('Set log level to: ' . $settings->getLogLevel());

        if (
            $settings->getLogLevel() != \ilLogLevel::OFF &&
            $settings->getLogFile() != ''
        ) {
            $stream_handler = new StreamHandler(
                $settings->getLogFile(),
                $settings->getLogLevel(),
                true
            );
            $line_formatter = new ilLineFormatter(\ilLoggerFactory::DEFAULT_FORMAT, 'Y-m-d H:i:s.u', true, true);
            $stream_handler->setFormatter($line_formatter);
            $logger->getLogger()->pushHandler($stream_handler);
        }

        // format lines
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            $handler->setLevel($settings->getLogLevel());
        }
    }

    /**
     * Init auto loader
     */
    protected function initAutoLoad()
    {
        spl_autoload_register(
            array($this, 'autoLoad')
        );
    }


    /**
     * Auto load implementation
     *
     * @param string class name
     */
    private function autoLoad(string $a_classname)
    {
        $class_file = $this->getClassesDirectory() . '/class.' . $a_classname . '.php';
        if (file_exists($class_file) && include_once($class_file)) {
            return;
        }
        $class_file = $this->getExceptionDirectory() . '/class.' . $a_classname . '.php';
        if (file_exists($class_file) && include_once($class_file)) {
            return;
        }
    }

    /**
     * @return string
     */
    private function getExceptionDirectory() : string
    {
        return $this->getDirectory() . '/exceptions';
    }
}
