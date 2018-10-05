<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayProfileFactory.php)
 */


namespace Xibo\Factory;


use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\DisplayProfile;
use Xibo\Exception\NotFoundException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DisplayProfileFactory
 * @package Xibo\Factory
 */
class DisplayProfileFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /** @var EventDispatcherInterface  */
    private $dispatcher;

    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param ConfigServiceInterface $config
     * @param EventDispatcherInterface $dispatcher
     * @param CommandFactory $commandFactory
     */
    public function __construct($store, $log, $sanitizerService, $config, $dispatcher, $commandFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);

        $this->config = $config;
        $this->dispatcher = $dispatcher;
        $this->commandFactory = $commandFactory;
    }

    /**
     * @return DisplayProfile
     */
    public function createEmpty()
    {
        return new DisplayProfile(
            $this->getStore(),
            $this->getLog(),
            $this->config,
            $this->dispatcher,
            $this->commandFactory
        );
    }

    /**
     * @param int $displayProfileId
     * @return DisplayProfile
     * @throws NotFoundException
     */
    public function getById($displayProfileId)
    {
        $profiles = $this->query(null, ['disableUserCheck' => 1, 'displayProfileId' => $displayProfileId]);

        if (count($profiles) <= 0)
            throw new NotFoundException();

        $profile = $profiles[0];
        /* @var DisplayProfile $profile */

        $profile->load();
        return $profile;
    }

    /**
     * @param string $type
     * @return DisplayProfile
     * @throws NotFoundException
     */
    public function getDefaultByType($type)
    {
        $profiles = $this->query(null, ['disableUserCheck' => 1, 'type' => $type, 'isDefault' => 1]);

        if (count($profiles) <= 0)
            throw new NotFoundException();

        $profile = $profiles[0];
        /* @var DisplayProfile $profile */

        $profile->load();
        return $profile;
    }

    /**
     * @param $clientType
     * @return DisplayProfile
     */
    public function getUnknownProfile($clientType)
    {
        $profile = $this->createEmpty();
        $profile->type = 'unknown';
        $profile->setClientType($clientType);
        $profile->load();
        return $profile;
    }

    /**
     * Get by Command Id
     * @param $commandId
     * @return array[DisplayProfile]
     * @throws NotFoundException
     */
    public function getByCommandId($commandId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'commandId' => $commandId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return DisplayProfile[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $profiles = array();

        if ($sortOrder === null)
            $sortOrder = ['name'];

        try {
            $params = array();
            $select = 'SELECT displayProfileId, name, type, config, isDefault, userId ';

            $body = ' FROM `displayprofile` WHERE 1 = 1 ';

            if ($this->getSanitizer()->getInt('displayProfileId', $filterBy) !== null) {
                $body .= ' AND displayProfileId = :displayProfileId ';
                $params['displayProfileId'] = $this->getSanitizer()->getInt('displayProfileId', $filterBy);
            }

            if ($this->getSanitizer()->getInt('isDefault', $filterBy) !== null) {
                $body .= ' AND isDefault = :isDefault ';
                $params['isDefault'] = $this->getSanitizer()->getInt('isDefault', $filterBy);
            }

            // Filter by DisplayProfile Name?
            if ($this->getSanitizer()->getString('displayProfile', $filterBy) != null) {
                // convert into a space delimited array
                $names = explode(' ', $this->getSanitizer()->getString('displayProfile', $filterBy));

                $i = 0;
                foreach ($names as $searchName) {
                    $i++;
                    // Not like, or like?
                    if (substr($searchName, 0, 1) == '-') {
                        $body .= " AND  `displayprofile`.name NOT LIKE :search$i ";
                        $params['search' . $i] = '%' . ltrim(($searchName), '-') . '%';
                    }
                    else {
                        $body .= " AND  `displayprofile`.name LIKE :search$i ";
                        $params['search' . $i] = '%' . $searchName . '%';
                    }
                }
            }

            if ($this->getSanitizer()->getString('type', $filterBy) != null) {
                $body .= ' AND type = :type ';
                $params['type'] = $this->getSanitizer()->getString('type', $filterBy);
            }

            if ($this->getSanitizer()->getInt('commandId', $filterBy) !== null) {
                $body .= '
                    AND `displayprofile`.displayProfileId IN (
                        SELECT `lkcommanddisplayprofile`.displayProfileId
                          FROM `lkcommanddisplayprofile`
                         WHERE `lkcommanddisplayprofile`.commandId = :commandId
                    )
                ';

                $params['commandId'] = $this->getSanitizer()->getInt('commandId', $filterBy);
            }

            // Sorting?
            $order = '';
            if (is_array($sortOrder))
                $order .= 'ORDER BY ' . implode(',', $sortOrder);

            $limit = '';
            // Paging
            if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
                $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
            }

            $sql = $select . $body . $order . $limit;



            foreach ($this->getStore()->select($sql, $params) as $row) {
                $profiles[] = $this->createEmpty()->hydrate($row);
            }

            // Paging
            if ($limit != '' && count($profiles) > 0) {
                $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
                $this->_countLast = intval($results[0]['total']);
            }

            return $profiles;

        } catch (\Exception $e) {

            $this->getLog()->error($e);

            throw new NotFoundException();
        }
    }
}