<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Factory;

use Stash\Interfaces\PoolInterface;
use Xibo\Entity\ReportSchedule;
use Xibo\Exception\NotFoundException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ReportScheduleFactory
 * @package Xibo\Factory
 */
class ReportScheduleFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /** @var PoolInterface  */
    private $pool;

    /** @var  DateServiceInterface */
    private $dateService;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param DateServiceInterface $date
     */
    public function __construct($store, $log, $sanitizerService, $config, $pool, $date)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->config = $config;
        $this->pool = $pool;
        $this->dateService = $date;
    }

    /**
     * Create Empty
     * @return ReportSchedule
     */
    public function createEmpty()
    {
        return new ReportSchedule(
            $this->getStore(),
            $this->getLog()
        );
    }

    /**
     * Loads only the reportSchedule information
     * @param int $reportScheduleId
     * @return ReportSchedule
     * @throws NotFoundException
     */
    public function getById($reportScheduleId)
    {

        if ($reportScheduleId == 0)
            throw new NotFoundException();

        $reportSchedules = $this->query(null, ['reportScheduleId' => $reportScheduleId]);

        if (count($reportSchedules) <= 0) {
            throw new NotFoundException(\__('Report Schedule not found'));
        }

        // Set our reportSchedule
        return $reportSchedules[0];
    }

    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder == null)
            $sortOrder = ['name'];

        $entries = [];
        $params = [];
        $sql = 'SELECT reportScheduleId, name, reportName, filterCriteria, schedule, lastRunDt, createdDt, `reportschedule`.userId,
               `user`.UserName AS owner FROM `reportschedule` ';

        $sql .= "   LEFT OUTER JOIN `user` ON `user`.userId = `reportschedule`.userId ";

        $sql .= " WHERE 1 = 1 ";

        if ($this->getSanitizer()->getInt('reportScheduleId', $filterBy) != null) {
            $params['reportScheduleId'] = $this->getSanitizer()->getInt('reportScheduleId', $filterBy);
            $sql .= ' AND `reportScheduleId` = :reportScheduleId ';
        }
        if ($this->getSanitizer()->getString('reportName', $filterBy) != null) {
            $params['reportName'] = $this->getSanitizer()->getString('reportName', $filterBy);
            $sql .= ' AND `reportName` = :reportName ';
        }

        if ($this->getSanitizer()->getString('name', $filterBy) != null) {
            $params['name'] = $this->getSanitizer()->getString('name', $filterBy);
            $sql .= ' AND `name` = :name ';
        }

        if ($this->getSanitizer()->getInt('userId', $filterBy) != null) {
            $params['userId'] = $this->getSanitizer()->getInt('userId', $filterBy);
            $sql .= ' AND `userId` = :userId ';
        }

        // Sorting?
        $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}