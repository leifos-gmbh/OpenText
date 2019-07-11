<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */


use Swagger\Client\Configuration;
use Swagger\Client\Api\DefaultApi;
use Swagger\Client\ApiException;
use Swagger\Client\Model\V2ResponseElement;
use Swagger\Client\Model\VersionsInfo;
use GuzzleHttp\Client;

/**
 * Connector for all rest api calls.
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 *
 */
class ilOpenTextConnector
{
	const OTXT_DOCUMENT_TYPE = 144;

	/**
	 * @var null
	 */
	private static $instance = null;

	/**
	 * @var \ilLogger
	 */
	private $logger = null;

	/**
	 * @var \ilOpenTextSettings|null
	 */
	private $settings = null;

	/**
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * @var DefaultApi|null
	 */
	private $api = null;

	/**
	 * ilOpenTextConnector constructor.
	 */
	private function __construct()
	{
		global $DIC;

		$this->logger = $DIC->logger()->otxt();
		$this->settings = ilOpenTextSettings::getInstance();
	}

	/**
	 * @return \ilOpenTextConnector|null
	 */
	public static function getInstance()
	{
		if(!self::$instance instanceof \ilOpenTextConnector) {
			self::$instance  = new self();
		}
		return self::$instance;
	}

	/**
	 * Ping service
	 * @throws \ilOpenTextConnectionException
	 */
	public function ping()
	{
		$this->prepareApiCall();
	}


	/**
	 * @param $node_id
	 * @return \Swagger\Client\Model\V2ResponseElement
	 * @throws \ilOpenTextConnectionException
	 */
	public function getNode($node_id)
	{
		$this->prepareApiCall();

		try {
			$res = $this->api->getNode($node_id);
			return $res;
		}
		catch(Exception $e) {
			$this->logger->error('Api get node failed with message: ' . $e->getMessage());
			$this->logger->error($e->getResponseHeaders());
			throw new \ilOpenTextConnectionException($e->getMessage());
		}
	}

	/**
	 * @param int $node_id
	 * @return \Swagger\Client\Model\VersionsInfo
	 * @throws \ilOpenTextConnectionException
	 */
	public function getVersions($node_id)
	{
		$this->prepareApiCall();

		try {
			$res = $this->api->getVersions($node_id);
			return $res;
		}
		catch(Exception $e) {
			$this->logger->error('Api get versions failed with message: ' . $e->getMessage());
			$this->logger->error($e->getResponseHeaders());
			throw new \ilOpenTextConnectionException($e->getMessage());
		}

	}

	/**
	 * @param $a_name
	 * @param \SplFileObject $file
	 * @return int id
	 * @throws \ilOpenTextConnectionException
	 */
	public function addDocument($a_name, \SplFileObject $file)
	{
		$this->prepareApiCall();

		try {
			$res = $this->api->addDocument(
				self::OTXT_DOCUMENT_TYPE,
				$this->settings->getBaseFolderId(),
				$a_name,
				$file
			);
			$this->logger->notice($res);
			return $res->getResults()->getData()->getProperties()->getId();
		}
		catch(Exception $e) {
			$this->logger->error('Api add document failed with message: ' . $e->getMessage());
			$this->logger->error($e->getResponseHeaders());
			throw new \ilOpenTextConnectionException($e->getMessage());
		}
	}



	/**
	 * Prepare api call
	 * @throws \ilOpenTextConnectionException on connection failure
	 */
	private function prepareApiCall()
	{
		if(!$this->api instanceof DefaultApi) {
			$this->initialize();
		}
		if(!$this->api->getConfig()->getApiKey(ilOpenTextSettings::OCTS_HEADER_TICKET_NAME)) {
			$this->login();
		}
	}

	/**
	 * Login and receive api key.
	 * @throws \ilOpenTextConnectionException
	 */
	private function login()
	{
		try {
			$res = $this->api->apiV1AuthPost(
				$this->settings->getUsername(),
				$this->settings->getPassword(),
				$this->settings->getDomain()
			);

			$this->logger->info('received api ticket: ' . $res->getTicket());

			$this->api->getConfig()->setApiKey(
				ilOpenTextSettings::OCTS_HEADER_TICKET_NAME,
				$res->getTicket()
			);
		}
		catch(ApiException $e) {
			$this->logger->warning('Api login failed with message: ' . $e->getMessage());
			$this->logger->warning($e->getResponseHeaders());
			throw new \ilOpenTextConnectionException($e->getMessage(), ilOpenTextConnectionException::ERR_LOGIN_FAILED);
		}
	}


	/**
	 * Initialize rest api
	 */
	private function initialize()
	{
		$this->logger->debug('Initializing rest api.');

		// init header selector
		$selector = new \ilOpenTextAuthHeaderSelector();
		$config = new Configuration();
		$client = new Client(
			[
				'verify' => false,
				'allow_redirects' => true
		]);


		$config->setHost($this->settings->getUri());

		$this->api = new DefaultApi(
			$client,
			$config,
			$selector
		);
	}
}