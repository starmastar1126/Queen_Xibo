<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (LogFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\LogEntry;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class LogFactory
 * @package Xibo\Factory
 */
class LogFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * Create Empty
     * @return LogEntry
     */
    public function createEmpty()
    {
        return new LogEntry($this->getStore(), $this->getLog());
    }

    /**
     * Query
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[\Xibo\Entity\Log]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $parsedFilter = $this->getSanitizer($filterBy);

        if ($sortOrder == null)
            $sortOrder = ['logId DESC'];

        $entries = [];
        $params = [];
        $order = ''; $limit = '';

        $select = 'SELECT logId, runNo, logDate, channel, page, function, message, display.displayId, display.display, type';

        $body = '
              FROM `log`
                  LEFT OUTER JOIN display
                  ON display.displayid = log.displayid
                  ';
        if ($parsedFilter->getInt('displayGroupId') !== null) {
            $body .= 'INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayID = log.displayid ';
        }

        $body .= ' WHERE 1 = 1 ';


        if ($parsedFilter->getInt('fromDt') !== null) {
            $body .= ' AND logdate > :fromDt ';
            $params['fromDt'] = date("Y-m-d H:i:s", $parsedFilter->getInt('fromDt'));
        }

        if ($parsedFilter->getInt('toDt') !== null) {
            $body .= ' AND logdate <= :toDt ';
            $params['toDt'] = date("Y-m-d H:i:s", $parsedFilter->getInt('toDt'));
        }

        if ($parsedFilter->getString('runNo') != null) {
            $body .= ' AND runNo = :runNo ';
            $params['runNo'] = $parsedFilter->getString('runNo');
        }

        if ($parsedFilter->getString('type') != null) {
            $body .= ' AND type = :type ';
            $params['type'] = $parsedFilter->getString('type');
        }

        if ($parsedFilter->getString('channel') != null) {
            $body .= ' AND channel LIKE :channel ';
            $params['channel'] = '%' . $parsedFilter->getString('channel') . '%';
        }

        if ($parsedFilter->getString('page') != null) {
            $body .= ' AND page LIKE :page ';
            $params['page'] = '%' . $parsedFilter->getString('page') . '%';
        }

        if ($parsedFilter->getString('function') != null) {
            $body .= ' AND function LIKE :function ';
            $params['function'] = '%' . $parsedFilter->getString('function') . '%';
        }

        if ($parsedFilter->getString('message') != null) {
            $body .= ' AND message LIKE :message ';
            $params['message'] = '%' . $parsedFilter->getString('message') . '%';
        }

        if ($parsedFilter->getInt('displayId') !== null) {
            $body .= ' AND log.displayId = :displayId ';
            $params['displayId'] = $parsedFilter->getInt('displayId');
        }

        if ($parsedFilter->getInt('userId') !== null) {
            $body .= ' AND log.userId = :userId ';
            $params['userId'] = $parsedFilter->getInt('userId');
        }

        if ($parsedFilter->getCheckbox('excludeLog') == 1) {
            $body .= ' AND (log.page NOT LIKE \'/log%\' OR log.page = \'/login\') ';
            $body .= ' AND log.page <> \'/user/pref\' AND log.page <> \'/clock\' AND log.page <> \'/library/fontcss\' ';
        }

        // Filter by Display Name?
        if ($parsedFilter->getString('display') != null) {
            $terms = explode(',', $parsedFilter->getString('display'));
            $this->nameFilter('display', 'display', $terms, $body, $params);
        }

        if ($parsedFilter->getInt('displayGroupId') !== null) {
            $body .= ' AND lkdisplaydg.displaygroupid = :displayGroupId ';
            $params['displayGroupId'] = $parsedFilter->getInt('displayGroupId');
        }

        // Sorting?
        if (is_array($sortOrder))
            $order = ' ORDER BY ' . implode(',', $sortOrder);

        // Paging
        if ($filterBy !== null && $parsedFilter->getInt('start') !== null && $parsedFilter->getInt('length', ['default' => 10]) !== null) {
            $limit = ' LIMIT ' . intval($parsedFilter->getInt('start', ['default' => 0]), 0) . ', ' . $parsedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row,  ['htmlStringProperties' => ['message']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}