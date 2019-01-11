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

namespace Xibo\Storage;

use Xibo\Service\LogServiceInterface;

/**
 * Interface TimeSeriesStoreInterface
 * @package Xibo\Service
 */
interface TimeSeriesStoreInterface
{
    /**
     * Time series constructor.
     * @param array $config
     */
    public function __construct($config = null);

    /**
     * Set Time series Dependencies
     * @param LogServiceInterface $logger
     */
    public function setDependencies($logger);

    /**
     * Add Media statistics
     * @param $statData array
     */
    public function addMediaStat($statData);

    /**
     * Add Layout statistics
     * @param $statData array
     */
    public function addLayoutStat($statData);

    /**
     * Add Tag statistics
     * @param $statData array
     */
    public function addTagStat($statData);

    /**
     * Retrieve statistics
     * @param $fromDt string
     * @param $toDt string
     * @param $displayIds array
     * @param $layoutIds array[mixed]|null
     * @param $mediaIds array[mixed]|null
     * @param $type mixed
     * @param $columns array
     * @param $start int
     * @param $length int
     * @return array[array statData, int count, int totalStats]
     */
    public function getStatsReport($fromDt, $toDt, $displayIds, $layoutIds, $mediaIds, $type, $columns, $start = null, $length = null);

    /**
     * Get the earliest date
     * @return array
     */
    public function getEarliestDate();

    /**
     * Get statistics
     * @param $fromDt string
     * @param $toDt string
     * @param $displayIds array
     * @return array[array statData]
     */
    public function getStats($fromDt, $toDt, $displayIds = null);

    /**
     * Delete statistics
     * @param $fromDt string|null
     * @param $toDt string
     * @param $options array
     * @return int number of deleted stat records
     * @throws \PDOException
     */
    public function deleteStats($toDt, $fromDt = null, $options = []);



}