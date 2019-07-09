<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */


use Swagger\Client\Configuration;
use Swagger\Client\Api\DefaultApi;
use Swagger\Client\ApiException;

/**
 * Connector for all rest api calls.
 *
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 *
 */
class ilOpenTextConnector
{
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
		$config->setHost($this->settings->getUri());

		$this->api = new DefaultApi(
			null,
			$config,
			$selector
		);
	}
}