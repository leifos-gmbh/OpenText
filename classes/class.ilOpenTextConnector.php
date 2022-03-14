<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */


use Swagger\Client\Configuration;
use Swagger\Client\Api\DefaultApi;
use Swagger\Client\ApiException;
use Swagger\Client\Model\CategoriesInfo;
use Swagger\Client\Model\ResultsData;
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
    const OTXT_FOLDER_TYPE = 0;
    const OTXT_EXTERNAL_SOURCE_TYPE = 'file_system';
    const OTXT_EXTERNAL_USER_LOGIN_TYPE = 'generic_userid';
    const OTXT_EXTERNAL_USER_LDAP_TYPE = 'ldap_name';

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
        if (!self::$instance instanceof \ilOpenTextConnector) {
            self::$instance = new self();
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
     * @param string $node_id
     * @return \Swagger\Client\Model\V2ResponseElement
     * @throws \ilOpenTextConnectionException
     */
    public function getNode(string $node_id) : V2ResponseElement
    {
        $this->prepareApiCall();

        try {
            $res = $this->api->getNode($node_id);
            return $res;
        } catch (Exception $e) {
            $this->logger->error('Api get node failed with message: ' . $e->getMessage());
            $this->logger->error($e->getResponseHeaders());
            throw new \ilOpenTextConnectionException($e->getMessage());
        }
    }

    /**
     * @param string $node_id
     * @return \Swagger\Client\Model\VersionsInfo
     * @throws \ilOpenTextConnectionException
     */
    public function getVersions(string $node_id) : VersionsInfo
    {
        $this->prepareApiCall();

        try {
            $res = $this->api->getVersions($node_id);
            return $res;
        } catch (Exception $e) {
            $this->logger->error('Api get versions failed with message: ' . $e->getMessage());
            $this->logger->error($e->getResponseHeaders());
            throw new \ilOpenTextConnectionException($e->getMessage());
        }
    }

    /**
     * @param string $node_id
     * @return CategoriesInfo
     * @throws ilOpenTextConnectionException
     */
    public function getCategories(string $node_id) : CategoriesInfo
    {
        $this->prepareApiCall();

        try {
            $res = $this->api->getCategory($node_id);
            return $res;
        } catch (Exception $e) {
            $this->logger->error('Api get category failed with message: ' . $e->getMessage());
            $this->logger->error($e->getResponseHeaders());
            throw new \ilOpenTextConnectionException($e->getMessage());
        }
    }

    /**
     * @throws ilOpenTextConnectionException|ilOpenTextRuntimeException
     */
    public function addDocument(string $a_name, \ilObjFile $ilfile, ilObjFileVersion $version, \SplFileObject $file) : int
    {
        $this->prepareApiCall();

        $a_parent_id = $this->buildParentFolders($ilfile->getId());

        $document_id = 0;
        try {
            $create_date = new DateTime($version->getDate());

            list($user_type, $username) = $this->parseUserInfo((int) $version->getUserId());

            $res = $this->api->addDocument(
                self::OTXT_DOCUMENT_TYPE,
                $a_parent_id,
                $a_name,
                $file,
                null,
                null,
                $create_date,
                $create_date,
                self::OTXT_EXTERNAL_SOURCE_TYPE,
                $username,
                $user_type
            );

            $this->logger->dump($res, \ilLogLevel::DEBUG);

            $document_id = $res->getResults()->getData()->getProperties()->getId();
        } catch (Exception $e) {
            $this->logger->error('Api add document failed with message: ' . $e->getMessage());
            $this->logger->error($e->getResponseHeaders());
            throw new \ilOpenTextConnectionException($e->getMessage());
        }

        try {
            $this->updateDocumentCategories(
                $document_id,
                $ilfile->getId(),
                $username
            );
        } catch (\Exception $e) {
            throw new \ilOpenTextRuntimeException($e->getMessage());
        }
        return $document_id;
    }

    /**
     * @param int $user_id
     * @return string[]
     */
    private function parseUserInfo(int $user_id = 0) : array
    {
        if (\ilObjUser::_lookupExternalAccount($user_id)) {
            return [
                self::OTXT_EXTERNAL_USER_LDAP_TYPE,
                \ilObjUser::_lookupExternalAccount($user_id)
            ];
        } else {
            return [
                self::OTXT_EXTERNAL_USER_LOGIN_TYPE,
                \ilObjUser::_lookupLogin($user_id)
            ];
        }
    }

    /**
     * @param string $a_name
     * @param int    $a_parent_id
     * @return int
     * @throws ilOpenTextConnectionException
     */
    public function addFolder(string $a_name, int $a_parent_id) : int
    {
        $this->prepareApiCall();

        try {
            $res = $this->api->addFolder(
                self::OTXT_FOLDER_TYPE,
                $a_parent_id,
                $a_name
            );
            $this->logger->dump($res, \ilLogLevel::DEBUG);
            $this->logger->debug('Received new folder id: ' . $res->getId());
            return $res->getId();
        } catch (Exception $e) {
            $this->logger->error('Api add folder failed with message: ' . $e->getMessage());
            $this->logger->error($e->getResponseHeaders());
            throw new \ilOpenTextConnectionException($e->getMessage());
        }
    }

    /**
     * @param int $a_obj_id
     * @return int
     * @throws ilOpenTextConnectionException
     */
    protected function buildParentFolders(int $a_obj_id) : int
    {
        $utils = \ilOpenTextUtils::getInstance();
        $path = $utils->buildPathFromId($a_obj_id);
        $path_map = \ilOpenTextPaths::getInstance();

        $start_node = $this->settings->getBaseFolderId();
        $current_path = [];
        foreach (explode('/', $path) as $path_item) {
            $current_path[] = $path_item;
            $opentext_id = $path_map->lookupOpenTextId(implode('/', $current_path));

            if (is_null($opentext_id)) {
                $this->logger->debug('Creating new path: ' . implode('/', $current_path));
                $start_node = $this->addFolder($path_item, $start_node);
                $path_map->addPath(new \ilOpenTextPath(implode('/', $current_path), $start_node));
            } else {
                $start_node = $opentext_id;
                $this->logger->debug('Using existing path ' . implode('/', $current_path) . ' with id: ' . $opentext_id);
            }
        }
        return $start_node;
    }

    /**
     * @param int            $a_document_id
     * @param ilObjFile      $ilfile
     * @param array          $version
     * @param \SplFileObject $file
     * @throws ilOpenTextConnectionException
     * @throws ilOpenTextRuntimeException
     */
    public function addVersion(int $a_document_id, \ilObjFile $ilfile, ilObjFileVersion $version, \SplFileObject $file)
    {
        $this->prepareApiCall();
        $create_date = new DateTime($version->getDate());
        list($user_type, $user_name) = $this->parseUserInfo((int) $version->getUserId());

        try {
            $res = $this->api->addVersion(
                $a_document_id,
                $file,
                null,
                null,
                $create_date,
                $create_date,
                self::OTXT_EXTERNAL_SOURCE_TYPE,
                $user_name,
                $user_type
            );
            $this->logger->info($res);
        } catch (ApiException $e) {
            $this->logger->error('Api add version failed with message: ' . $e->getMessage());
            $this->logger->error($e->getResponseHeaders());
            throw new \ilOpenTextConnectionException($e->getMessage());
        } catch (\RuntimeException | \LogicException $e) {
            $this->logger->error('Api add version failed with message: ' . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            throw new \ilOpenTextRuntimeException($e->getMessage());
        }

        try {
            $this->updateDocumentCategories(
                $a_document_id,
                $ilfile->getId(),
                $user_name
            );
        } catch (\Exception $e) {
            throw new \ilOpenTextRuntimeException($e->getMessage());
        }
    }

    /**
     * @param int $document_id
     * @param int $ilias_file_id
     * @param string $username
     * @throws ilOpenTextConnectionException
     */
    protected function updateDocumentCategories(int $document_id, int $ilias_file_id, string $username)
    {
        $this->prepareApiCall();
        try {
            $this->api->deleteCategory($document_id, $this->settings->getDocumentId());
            $this->api->deleteCategory($document_id, $this->settings->getDocumentInfoId());

            $this->api->addCategory($document_id, $this->settings->getDocumentId());
            $this->api->addCategory($document_id, $this->settings->getDocumentInfoId());

            // document
            $body_obj = new stdClass();
            $document_manager_name = (string) $this->settings->getDocumentId() . '_' . (string) $this->settings->getDocumentManagerId();
            $document_owner_name = (string) $this->settings->getDocumentId() . '_' . (string) $this->settings->getDocumentOwnerId();

            $body_obj->$document_manager_name = $username;
            $body_obj->$document_owner_name = $username;

            $body = json_encode($body_obj);

            $this->logger->dump($body);

            $this->api->updateCategory(
                $document_id,
                $this->settings->getDocumentId(),
                $body
            );

            // document info
            $body_obj = new stdClass();
            $info_id_name = (string) $this->settings->getDocumentInfoId() . '_' . (string) $this->settings->getDocumentInfoIdId();
            $body_obj->$info_id_name = (string) $ilias_file_id;
            $body = json_encode($body_obj);

            $this->logger->dump($body);

            $this->api->updateCategory(
                (string) $document_id,
                $this->settings->getDocumentInfoId(),
                $body
            );
        } catch (ApiException $e) {
            $this->logger->error('Api category update failed with message: ' . $e->getMessage());
            $this->logger->error($e->getResponseHeaders());
            throw new \ilOpenTextConnectionException($e->getMessage());
        }
    }

    /**
     * @param string $query
     * @param int    $slice
     * @param int    $limit
     * @return ResultsData[]
     * @throws ilOpenTextConnectionException
     */
    public function search(string $query, int $slice, int $limit) : array
    {
        $this->prepareApiCall();

        try {
            $res = $this->api->search($query, $slice, null, 1, $limit);
            $this->logger->dump($res);
            return $res->getResults();
        } catch (ApiException $e) {
            $this->logger->error('Api search failed with message: ' . $e->getMessage());
            $this->logger->error($e->getResponseHeaders());
            throw new \ilOpenTextConnectionException($e->getMessage());
        } catch (\RuntimeException | \LogicException $e) {
            $this->logger->error('Api search failed with message: ' . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            throw new \ilOpenTextConnectionException($e->getMessage());
        }
    }

    /**
     * @throws ilOpenTextConnectionException
     */
    public function lookupRegions()
    {
        $this->prepareApiCall();
        try {
            $res = $this->api->lookupRegions(true);
            $this->logger->dump($res);
        } catch (ApiException $e) {
            $this->logger->error('Api lookupRegions failed with message: ' . $e->getMessage());
            $this->logger->error($e->getResponseHeaders());
            throw new \ilOpenTextConnectionException($e->getMessage());
        } catch (\RuntimeException | \LogicException $e) {
            $this->logger->error('Api lookupRegions failed with message: ' . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            throw new \ilOpenTextConnectionException($e->getMessage());
        }
    }



    /**
     * Prepare api call
     * @throws \ilOpenTextConnectionException on connection failure
     */
    private function prepareApiCall()
    {
        if (!$this->api instanceof DefaultApi) {
            $this->initialize();
        }
        if (!$this->api->getConfig()->getApiKey(ilOpenTextSettings::OCTS_HEADER_TICKET_NAME)) {
            $this->logger->debug('No api key avalailable: trying to login.');
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
        } catch (ApiException $e) {
            $this->logger->warning('Api login failed with message: ' . $e->getMessage());
            $this->logger->warning($e->getResponseHeaders());
            $this->logger->warning($e->getResponseBody());
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

        if (
            $this->settings->getLogLevel() == \ilLogLevel::DEBUG &&
            $this->settings->getLogFile() != ''
        ) {
            $config->setDebug(false);
            $config->setDebugFile($this->settings->getLogFile());
        }

        $client = new Client(
            [
                'verify' => false,
                'allow_redirects' => true
        ]
        );

        $config->setHost($this->settings->getUri());

        $this->api = new DefaultApi(
            $client,
            $config,
            $selector
        );
    }
}
