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

		try {
			$this->synchronizeVersions($item, $file);
		}
		catch(Exception $e) {
			$this->logger->error('Version synchronization failed with message: ' . $e->getMessage());
			$item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_FAILURE);
			$item->save();
			return;
		}
		$item->setStatus(\ilOpenTextSynchronisationInfoItem::STATUS_SYNCHRONISED);
		$item->save();
	}

	/**
	 *
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
		}
		catch (Exception $e) {
			throw $e;
		}

		// iterate through all file versions and check whether they are sychronized.
		$file_versions = array_reverse($file->getVersions());
		foreach ($file_versions as $file_version) {

			if($this->isFileVersionSynchronized($file, $file_version, $open_text_versions)) {
				$this->logger->debug('File version already synchronized: ');
				$this->logger->dump($file_version);
				continue;
			}
			// add new version
			try {
				$this->createVersion($item, $file, $file_version);
			}
			catch(Exception $e) {
				throw $e;
			}
		}
	}

	/**
	 * @param \ilObjFile $file
	 * @param array $file_version
	 * @param \Swagger\Client\Model\VersionsInfo $versions
	 */
	protected function isFileVersionSynchronized(\ilObjFile $file, $file_version, VersionsInfo $versions)
	{
		foreach($versions->getData() as $idx => $version) {

			$compare = $version->getFileName().'_'.$version->getFileSize();
			$version_compare = '';

			try {
				$spl_file = new \SplFileObject(
					$file->getDirectory($file_version['version']).'/'.$file_version['filename']
				);
				$version_compare = $file_version['filename'].'_'.$spl_file->getSize();
			}
			catch(RuntimeException | LogicException $e) {
				$this->logger->notice('Cannot open file: ' . $file->getDirectory($file_version['version'].'/'.$file_version['filename']));
				$this->logger->warning($e->getMessage());
			}
			$this->logger->info('Comparing files: ' . $compare .' -> ' . $version_compare);
			if(strcmp($compare,$version_compare) === 0) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Create additional file version
	 *
	 * @param \ilOpenTextSynchronisationInfoItem $item
	 * @param \ilObjFile $file
	 */
	protected function createVersion(\ilOpenTextSynchronisationInfoItem $item, \ilObjFile $file, $file_version)
	{
		$name = $file_version['filename'];

		$spl_file = null;
		try {
			$this->logger->info('File path is: ' . $file->getDirectory($file_version['version']).'/'.$name);
			$spl_file = new \SplFileObject($file->getDirectory($file_version['version']).'/'.$name);

			if($spl_file->isReadable()) {
				$this->logger->info('File is readable');
			}
			else {
				$this->logger->info('File is not readable');
			}

		}
		catch(\RuntimeException $e) {
            $this->logger->warning('Cannot open file: ' . $file->getDirectory($file_version['version']).'/'.$name);
            throw new \RuntimeException('Cannot open file');
		}
		catch (\LogicException $e) {
            $this->logger->warning('Cannot open file: ' . $file->getDirectory($file_version['version']).'/'.$name);
            throw new \RuntimeException('Cannot open file');
        }

		try {
			\ilOpenTextConnector::getInstance()->addVersion($item->getOpenTextId(), $name, $spl_file);
		}
		catch(ilOpenTextConnectionException $e) {
			$this->logger->notice('Version creation failed with message: ' . $e->getMessage());
			throw new \RuntimeException($e->getMessage());
		}
		return true;

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
			$new_document_id = ilOpenTextConnector::getInstance()->addDocument(
			    $name,
                $file->getId(),
                $spl_file
            );

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