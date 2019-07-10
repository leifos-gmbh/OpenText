<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

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
	 * @var \ilLogger|null
	 */
	private $logger = null;

	public function __construct(ilCronJobResult $result)
	{
		global $DIC;

		$this->logger = $DIC->logger()->otxt();
		$this->result = $result;
	}

	/**
	 * Run cron job
	 */
	public function run()
	{
		$this->updateInfoItems();
		$this->synchronizeItems();
	}

	/**
	 * Synchronize info items
	 */
	protected function synchronizeItems()
	{
		$info = ilOpenTextSynchronisationInfo::getInstance();
		foreach($info->getItemsForSynchronization() as $info_item) {
			$this->synchronizeItem($info_item);
		}
	}

	/**
	 * @param \ilOpenTextSynchronisationInfoItem $item
	 */
	protected function synchronizeItem(\ilOpenTextSynchronisationInfoItem $item)
	{
		$item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_IN_PROGRESS);
		$item->save();

		$file = \ilObjectFactory::getInstanceByObjId($item->getObjId(), false);
		if(!$file instanceof  ilObjFile) {
			$this->logger->notice('Cannot create file instance for obj_id: ' . $item->getObjId());
			$item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_FAILURE);
			$item->save();
			return;
		}

		$versions = $file->getVersions();
		$this->logger->dump($versions, \ilLogLevel::NOTICE);

		// if no version is avalaible => set status to synchronized
		if(!count($versions)) {
			$item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_SYNCHRONISED);
			$item->save();
			return;
		}
		// if no otxt_id is available => create document node
		if(!$item->getOpenTextId()) {
			try {
				$this->createFirstVersion($item, $file);

			}
			catch(Exception $e) {
				$this->logger->error('Create initial version failed with message: '  . $e->getMessage());
				$item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_FAILURE);
				$item->save();
				return;
			}
		}


		$item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_SCHEDULED);
		$item->save();
	}

	/**
	 * @param \ilOpenTextSynchronisationInfoItem $item
	 * @param \ilObjFile $file
	 * @return bool
	 * @throws \ilOpenTextConnectionException
	 */
	protected function createFirstVersion(\ilOpenTextSynchronisationInfoItem $item, \ilObjFile $file)
	{
		$versions = array_reverse($file->getVersions());
		foreach($versions as $version) {

			$name = $file->getId().'_'.$version['filename'];
			$version_id = $version['version'];

			$directory = $file->getDirectory($version_id);
			$this->logger->info('Using file absolute path: ' . $directory);

			$spl_file = new \SplFileObject($directory.'/'.$version['filename']);

			$new_document_id = ilOpenTextConnector::getInstance()->addDocument($name,$spl_file);

			$item->setOpenTextId($new_document_id);
			$item->save();
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

}