<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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


use Xibo\Entity\Region;
use Xibo\Helper\SanitizerService;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class RegionFactory
 * @package Xibo\Factory
 */
class RegionFactory extends BaseFactory
{
    /**
     * @var RegionOptionFactory
     */
    private $regionOptionFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;

    /** @var ActionFactory */
    private $actionFactory;

    /** @var CampaignFactory */
    private $campaignFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param PermissionFactory $permissionFactory
     * @param RegionOptionFactory $regionOptionFactory
     * @param PlaylistFactory $playlistFactory
     * @param ActionFactory $actionFactory
     */
    public function __construct($store, $log, $sanitizerService, $permissionFactory, $regionOptionFactory, $playlistFactory, $actionFactory, $campaignFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->permissionFactory = $permissionFactory;
        $this->regionOptionFactory = $regionOptionFactory;
        $this->playlistFactory = $playlistFactory;
        $this->actionFactory = $actionFactory;
        $this->campaignFactory = $campaignFactory;
    }

    /**
     * @return Region
     */
    public function createEmpty()
    {
        return new Region(
            $this->getStore(),
            $this->getLog(),
            $this,
            $this->permissionFactory,
            $this->regionOptionFactory,
            $this->playlistFactory,
            $this->actionFactory,
            $this->campaignFactory
        );
    }

    /**
     * Create a new region
     * @param int $ownerId ;
     * @param string $name
     * @param int $width
     * @param int $height
     * @param int $top
     * @param int $left
     * @param int $zIndex
     * @param int $isDrawer
     * @return Region
     * @throws InvalidArgumentException
     */
    public function create($ownerId, $name, $width, $height, $top, $left, $zIndex = 0, $isDrawer = 0)
    {
        // Validation
        if (!is_numeric($width) || !is_numeric($height) || !is_numeric($top) || !is_numeric($left)) {
            throw new InvalidArgumentException(__('Size and coordinates must be generic'));
        }

        if ($width <= 0) {
            throw new InvalidArgumentException(__('Width must be greater than 0'));
        }

        if ($height <= 0) {
            throw new InvalidArgumentException(__('Height must be greater than 0'));
        }

        $region = $this->createEmpty();
        $region->ownerId = $ownerId;
        $region->name = $name;
        $region->width = $width;
        $region->height = $height;
        $region->top = $top;
        $region->left = $left;
        $region->zIndex = $zIndex;
        $region->isDrawer = $isDrawer;

        return $region;
    }

    /**
     * Get the regions for a layout
     * @param int $layoutId
     * @return array[\Xibo\Entity\Region]
     */
    public function getByLayoutId($layoutId)
    {
        // Get all regions for this layout
        return $this->query([], ['disableUserCheck' => 1, 'layoutId' => $layoutId, 'isDrawer' => 0]);
    }

    /**
     * Get the drawer regions for a layout
     * @param int $layoutId
     * @return array[\Xibo\Entity\Region]
     */
    public function getDrawersByLayoutId($layoutId)
    {
        // Get all regions for this layout
        return $this->query([], ['disableUserCheck' => 1, 'layoutId' => $layoutId, 'isDrawer' => 1]);
    }

    /**
     * Get the regions for a playlist
     * @param int $playlistId
     * @return array[\Xibo\Entity\Region]
     */
    public function getByPlaylistId($playlistId)
    {
        // Get all regions for this layout
        return $this->query([], ['disableUserCheck' => 1, 'playlistId' => $playlistId]);
    }

    /**
     * Load a region
     * @param int $regionId
     * @return Region
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function loadByRegionId($regionId)
    {
        $region = $this->getById($regionId);
        $region->load();
        return $region;
    }

    /**
     * Get by RegionId
     * @param int $regionId
     * @return Region
     * @throws NotFoundException
     */
    public function getById($regionId)
    {
        // Get a region by its ID
        $regions = $this->query(array(), array('disableUserCheck' => 1, 'regionId' => $regionId));

        if (count($regions) <= 0)
            throw new NotFoundException(__('Region not found'));

        return $regions[0];
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Region]
     */
    public function query($sortOrder = [], $filterBy = [])
    {
        $entries = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        $params = [];
        $sql = '
          SELECT `region`.regionId,
              `region`.layoutId,
              `region`.ownerId,
              `region`.name,
              `region`.width,
              `region`.height,
              `region`.top,
              `region`.left,
              `region`.zIndex,
              `region`.duration,
              `region`.isDrawer
        ';

        $sql .= '
            FROM `region`
        ';

        $sql .= ' WHERE 1 = 1 ';

        if ($sanitizedFilter->getInt('regionId') != 0) {
            $sql .= ' AND regionId = :regionId ';
            $params['regionId'] = $sanitizedFilter->getInt('regionId');
        }

        if ($sanitizedFilter->getInt('layoutId') != 0) {
            $sql .= ' AND layoutId = :layoutId ';
            $params['layoutId'] = $sanitizedFilter->getInt('layoutId');
        }

        if ($sanitizedFilter->getInt('playlistId') !== null) {
            $sql .= ' AND regionId IN (SELECT regionId FROM playlist WHERE playlistId = :playlistId) ';
            $params['playlistId'] = $sanitizedFilter->getInt('playlistId');
        }

        if ($sanitizedFilter->getInt('isDrawer') !== null) {
            $sql .= ' AND region.isDrawer = :isDrawer ';
            $params['isDrawer'] = $sanitizedFilter->getInt('isDrawer');
        }

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['zIndex', 'duration', 'isDrawer']]);
        }

        return $entries;
    }
}