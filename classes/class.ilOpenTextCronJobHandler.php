<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */


use Swagger\Client\Model\VersionsInfo;

/**
 * Class ilOpenTextCronJobHandler
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilOpenTextCronJobHandler
{
    /**
     * @var \ilCronJobResult|null
     */
    private $result = null;

    /**
     * @var null | \ilOpenTextPlugin
     */
    private $plugin = null;

    /**
     * @var \ilLogger|null
     */
    private $logger = null;

    public function __construct(ilCronJobResult $result)
    {
        global $DIC;

        $this->logger = $DIC->logger()->otxt();
        $this->result = $result;
        $this->plugin = \ilOpenTextPlugin::getInstance();
    }

    /**
     * Run cron job
     * @return bool
     */
    public function run() : bool
    {
        $num_items = 0;
        try {
            $this->pingServer();
            $this->updateInfoItems();
            $num_items = $this->synchronizeItems();
        } catch (\ilOpenTextConnectionException $e) {
            $this->result->setCode(\ilCronJobResult::STATUS_INVALID_CONFIGURATION);
            $this->result->setMessage($e->getMessage());
            return false;
        } catch (Throwable $e) {
            $this->result->setStatus(\ilCronJobResult::STATUS_CRASHED);
            $this->result->setMessage($e->getMessage());
            return false;
        }

        $this->result->setStatus(\ilCronJobResult::STATUS_OK);
        $this->result->setMessage($this->plugin->txt('cron_synchronized_items') . ' ' . (string) $num_items);
        return true;
    }

    /**
     * Synchronize info items
     * @throws Exception
     */
    protected function synchronizeItems() : ?int
    {
        $num_items = 0;
        $info = ilOpenTextSynchronisationInfo::getInstance();
        foreach ($info->getItemsForSynchronization() as $info_item) {
            $this->synchronizeItem($info_item);
            $num_items++;
        }
        return $num_items;
    }

    /**
     * @param ilOpenTextSynchronisationInfoItem $item
     * @return false|void
     * @throws ilDatabaseException
     * @throws ilObjectNotFoundException
     * @throws ilOpenTextConnectionException
     * @throws ilOpenTextRuntimeException
     */
    protected function synchronizeItem(\ilOpenTextSynchronisationInfoItem $item) : ?bool
    {
        $item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_IN_PROGRESS);
        $item->save();

        $file = \ilObjectFactory::getInstanceByObjId($item->getObjId(), false);
        if (!$file instanceof  ilObjFile) {
            $this->logger->notice('Cannot create file instance for obj_id: ' . $item->getObjId());
            $item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_FAILURE);
            $item->save();
            return;
        }

        $versions = $file->getVersions();
        $this->logger->dump($versions, \ilLogLevel::NOTICE);

        // if no version is available => set status to synchronized
        if (!count($versions)) {
            $item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_SYNCHRONISED);
            $item->save();
            return;
        }
        // if no otxt_id is available => create document node

        $status_ok = false;

        if (!$item->getOpenTextId()) {
            try {
                $this->createFirstVersion($item, $file);
                $status_ok = true;
            } catch (\ilOpenTextRuntimeException $e) {
                $this->logger->error('Create initial version failed with message: ' . $e->getMessage());
                $item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_FAILURE);
                $item->save();
                return false;
            } catch (Exception $e) {
                $item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_FAILURE);
                $item->save();
                throw $e;
            }
        }

        try {
            $this->synchronizeVersions($item, $file);
            $status_ok = true;
        } catch (\ilOpenTextConnectionException $e) {
            $this->logger->error('Version synchronization failed with message: ' . $e->getMessage());
            $item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_FAILURE);
            $item->save();
            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Version synchronization failed with message: ' . $e->getMessage());
            $status_ok = false;
        }
        if ($status_ok) {
            $item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_SYNCHRONISED);
            $item->save();
        } else {
            $item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_FAILURE);
            $item->save();
        }
    }


    /**
     * @param \ilOpenTextSynchronisationInfoItem $item
     * @param \ilObjFile $file
     * @throws \Exception
     */
    protected function synchronizeVersions(\ilOpenTextSynchronisationInfoItem $item, \ilObjFile $file)
    {
        $open_text_versions = null;
        try {
            $open_text_versions = \ilOpenTextConnector::getInstance()->getVersions($item->getOpenTextId());
            $this->logger->dump($open_text_versions, ilLogLevel::INFO);
        } catch (Exception $e) {
            throw $e;
        }

        // iterate through all file versions and check whether they are sychronized.
        $file_versions = array_reverse($file->getVersions());
        foreach ($file_versions as $file_version) {
            if ($this->isFileVersionSynchronized($file, $file_version, $open_text_versions)) {
                $this->logger->debug('File version already synchronized: ' . $file_version['info_params']);
                $this->logger->dump($file_version);
                continue;
            }
            // add new version
            $this->createVersion($item, $file, $file_version);
        }
    }

    /**
     * @param ilObjFile    $file
     * @param array        $file_version
     * @param VersionsInfo $versions
     * @throws RuntimeException
     * @throws LogicException
     * @return bool
     */
    protected function isFileVersionSynchronized(\ilObjFile $file, array $file_version, VersionsInfo $versions) : bool
    {
        foreach ($versions->getData() as $idx => $version) {
            $compare = $version->getFileName() . '_' . $version->getFileSize();
            $version_compare = '';

            try {
                $spl_file = new \SplFileObject(
                    $file->getDirectory($file_version['version']) . '/' . $file_version['filename']
                );
                $version_compare = $file_version['filename'] . '_' . $spl_file->getSize();
            } catch (RuntimeException | LogicException $e) {
                $this->logger->notice('Cannot open file: ' . $file->getDirectory($file_version['version'] . '/' . $file_version['filename']));
                $this->logger->warning($e->getMessage());
                return false;
            }
            $this->logger->info('Comparing files: ' . $compare . ' -> ' . $version_compare);
            if (strcmp($compare, $version_compare) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create additional file version
     * @param \ilOpenTextSynchronisationInfoItem $item
     * @param \ilObjFile                         $file
     * @param array                              $file_version
     * @return bool
     * @throws ilOpenTextConnectionException
     * @throws ilOpenTextRuntimeException
     */
    protected function createVersion(\ilOpenTextSynchronisationInfoItem $item, \ilObjFile $file, array $file_version) : bool
    {
        $name = $file_version['filename'];

        $spl_file = null;
        try {
            $this->logger->info('File path is: ' . $file->getDirectory($file_version['version']) . '/' . $name);
            $spl_file = new \SplFileObject($file->getDirectory($file_version['version']) . '/' . $name);

            if ($spl_file->isReadable()) {
                $this->logger->debug('File is readable');
            } else {
                $this->logger->warning('File is not readable');
                return false;
            }
        } catch (\RuntimeException $e) {
            // do not throw, since this would block the sync of other planned items
            $this->logger->warning('Cannot open file: ' . $file->getDirectory($file_version['version']) . '/' . $name);
            throw $e;
        } catch (\LogicException $e) {
            // do not throw, since this would block the sync of other planned items
            $this->logger->warning('Cannot open file: ' . $file->getDirectory($file_version['version']) . '/' . $name);
            throw new \RuntimeException($e->getMessage());
        }

        try {
            \ilOpenTextConnector::getInstance()->addVersion($item->getOpenTextId(), $file, $file_version, $spl_file);
        } catch (ilOpenTextConnectionException $e) {
            $this->logger->notice('Version creation failed with message: ' . $e->getMessage());
            throw $e;
        }
        return true;
    }


    /**
     * @param \ilOpenTextSynchronisationInfoItem $item
     * @param \ilObjFile $file
     * @return bool
     * @throws \ilOpenTextConnectionException
     * @throws \ilOpenTextRuntimeException
     */
    protected function createFirstVersion(\ilOpenTextSynchronisationInfoItem $item, \ilObjFile $file) : bool
    {
        $versions = array_reverse($file->getVersions());
        foreach ($versions as $version) {
            $name = $file->getId() . '_' . $version['filename'];
            $version_id = $version['version'];

            $directory = $file->getDirectory($version_id);
            $this->logger->info('Using file absolute path: ' . $directory);

            try {
                $spl_file = new \SplFileObject($directory . '/' . $version['filename']);
                $new_document_id = ilOpenTextConnector::getInstance()->addDocument(
                    $name,
                    $file,
                    $version,
                    $spl_file
                );
                $item->setOpenTextId($new_document_id);
                $item->save();
            } catch (\RuntimeException | \LogicException $e) {
                $this->logger->warning('Cannot create initial file version: ' . $e->getMessage());
                throw new \ilOpenTextRuntimeException($e->getMessage());
            } catch (\ilOpenTextConnectionException $e) {
                throw $e;
            }
            break;
        }
        return true;
    }

    /**
     * Update info items
     */
    protected function updateInfoItems()
    {
        $info = ilOpenTextSynchronisationInfo::getInstance();
        $info->createMissingItems();
    }

    /**
     * @throws ilOpenTextConnectionException
     */
    protected function pingServer()
    {
        try {
            $connector = \ilOpenTextConnector::getInstance();
            $connector->ping();
        } catch (\ilOpenTextConnectionException $e) {
            throw new \ilOpenTextConnectionException('Cannot communicate with opentext server: ' . $e->getMessage());
        }
    }
}
