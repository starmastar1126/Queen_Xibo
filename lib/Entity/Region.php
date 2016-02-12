<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Region.php) is part of Xibo.
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

namespace Xibo\Entity;


use Xibo\Exception\NotFoundException;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionOptionFactory;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

/**
 * Class Region
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Region implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this region")
     * @var int
     */
    public $regionId;

    /**
     * @SWG\Property(description="The Layout ID this region belongs to")
     * @var int
     */
    public $layoutId;

    /**
     * @SWG\Property(description="The userId of the User that owns this Region")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The name of this Region")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="Width of the region")
     * @var double
     */
    public $width;

    /**
     * @SWG\Property(description="Height of the Region")
     * @var double
     */
    public $height;

    /**
     * @SWG\Property(description="The top coordinate of the Region")
     * @var double
     */
    public $top;

    /**
     * @SWG\Property(description="The left coordinate of the Region")
     * @var double
     */
    public $left;

    /**
     * @SWG\Property(description="The z-index of the Region to control Layering")
     * @var int
     */
    public $zIndex;

    /**
     * @SWG\Property(description="An array of Playlists assigned")
     * @var Playlist[]
     */
    public $playlists = [];

    /**
     * @SWG\Property(description="An array of Region Options")
     * @var RegionOption[]
     */
    public $regionOptions = [];

    /**
     * @SWG\Property(description="An array of Permissions")
     * @var Permission[]
     */
    public $permissions = [];

    /**
     * @SWG\Property(description="When linked from a Playlist, what is the display order of that link")
     * @var int
     */
    public $displayOrder;

    /**
     * @var int
     * @SWG\Property(
     *  description="A read-only estimate of this Regions's total duration in seconds. This is valid when the parent layout status is 1 or 2."
     * )
     */
    public $duration;

    /**
     * Temporary Id used during import/upgrade
     * @var string read only string
     */
    public $tempId = null;

    public function __clone()
    {
        // Clear the IDs and clone the playlist
        $this->regionId = null;
        $this->hash = null;
        $this->permissions = [];

        $this->playlists = array_map(function ($object) { return clone $object; }, $this->playlists);
        $this->regionOptions = array_map(function ($object) { return clone $object; }, $this->regionOptions);
    }

    public function __toString()
    {
        return sprintf('Region %s - %d x %d (%d, %d). RegionId = %d, LayoutId = %d. OwnerId = %d. Duration = %d', $this->name, $this->width, $this->height, $this->top, $this->left, $this->regionId, $this->layoutId, $this->ownerId, $this->duration);
    }

    private function hash()
    {
        return md5($this->name . $this->width . $this->height . $this->top . $this->left . $this->regionId . $this->zIndex . $this->duration);
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->regionId;
    }

    /**
     * Get the OwnerId
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;

        foreach ($this->playlists as $playlist) {
            /* @var Playlist $playlist */
            $playlist->setOwner($ownerId);
        }
    }

    /**
     * Get Option
     * @param string $option
     * @return RegionOption
     * @throws NotFoundException
     */
    public function getOption($option)
    {
        $this->load();

        foreach ($this->regionOptions as $regionOption) {
            /* @var RegionOption $regionOption */
            if ($regionOption->option == $option)
                return $regionOption;
        }

        Log::debug('RegionOption %s not found', $option);

        throw new NotFoundException('Region Option not found');
    }

    /**
     * Get Region Option Value
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    public function getOptionValue($option, $default)
    {
        $this->load();

        try {
            $regionOption = $this->getOption($option);
            return $regionOption->value;
        }
        catch (NotFoundException $e) {
            return $default;
        }
    }

    /**
     * Set Region Option Value
     * @param string $option
     * @param mixed $value
     */
    public function setOptionValue($option, $value)
    {
        try {
            $this->getOption($option)->value = $value;
        }
        catch (NotFoundException $e) {
            $this->regionOptions[] = RegionOptionFactory::create($this->regionId, $option, $value);
        }
    }

    /**
     * Assign this Playlist to a Region
     * @param Playlist $playlist
     */
    public function assignPlaylist($playlist)
    {
        $this->load();

        $playlist->displayOrder = ($playlist->displayOrder == null || $playlist->displayOrder == 0) ? count($this->playlists) + 1 : $playlist->displayOrder ;
        $this->playlists[] = $playlist;
    }

    /**
     * Unassign a Playlist
     * @param $playlist
     */
    public function unassignPlaylist($playlist)
    {
        $this->load();

        $this->playlists = array_udiff($this->playlists, [$playlist], function($a, $b) {
            /**
             * @var Playlist $a
             * @var Playlist $b
             */
            return $a->getId() - $b->getId() + $a->displayOrder - $b->displayOrder;
        });
    }

    /**
     * Load
     * @param array $options
     */
    public function load($options = [])
    {
        if ($this->loaded || $this->regionId == 0)
            return;

        $options = array_merge(['regionIncludePlaylists' => true], $options);

        Log::debug('Load Region with %s', json_encode($options));

        // Load permissions
        $this->permissions = PermissionFactory::getByObjectId(get_class(), $this->regionId);

        // Load all playlists
        if ($options['regionIncludePlaylists']) {
            $this->playlists = PlaylistFactory::getByRegionId($this->regionId);

            foreach ($this->playlists as $playlist) {
                /* @var Playlist $playlist */
                $playlist->load($options);
            }
        }

        // Get region options
        $this->regionOptions = RegionOptionFactory::getByRegionId($this->regionId);

        $this->hash = $this->hash();
        $this->loaded = true;
    }

    /**
     * Save
     * @param array $options
     */
    public function save($options = [])
    {
        $options = array_merge([
            'saveRegionOptions' => true,
            'manageRegionAssignments' => true
        ], $options);

        Log::debug('Saving %s. Options = %s', $this, json_encode($options, JSON_PRETTY_PRINT));

        if ($this->regionId == null || $this->regionId == 0)
            $this->add();
        else if ($this->hash != $this->hash())
            $this->update();

        if ($options['saveRegionOptions']) {
            // Save all Options
            foreach ($this->regionOptions as $regionOption) {
                /* @var RegionOption $regionOption */
                $regionOption->save();
            }
        }

        if ($options['manageRegionAssignments']) {
            // Manage the assignments to regions
            $this->manageAssignments();
        }
    }

    /**
     * Delete Region
     * @param array $options
     */
    public function delete($options = [])
    {
        $options = array_merge([
            'deleteOrphanedPlaylists' => true
        ], $options);

        // We must ensure everything is loaded before we delete
        if ($this->hash == null)
            $this->load();

        Log::debug('Deleting ' . $this);

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // Delete all region options
        foreach ($this->regionOptions as $regionOption) {
            /* @var RegionOption $regionOption */
            $regionOption->delete();
        }

        // Store the playlists locally for use after unlink
        $playlists = $this->playlists;

        // Unlink playlists
        $this->playlists = [];
        $this->unlinkPlaylists();

        // Should we delete orphaned playlists?
        if ($options['deleteOrphanedPlaylists']) {
            Log::debug('We should delete orphaned playlists, checking %d playlists.', count($playlists));

            // Delete
            foreach ($playlists as $playlist) {
                /* @var Playlist $playlist */
                if (!$playlist->hasLayouts()) {
                    Log::debug('Deleting orphaned playlist: %d', $playlist->playlistId);
                    $playlist->delete();
                }
                else {
                    Log::debug('Playlist still linked to Layouts, skipping playlist delete');
                }
            }
        }

        // Delete this region
        PDOConnect::update('DELETE FROM `region` WHERE regionId = :regionId', array('regionId' => $this->regionId));
    }

    // Add / Update
    /**
     * Add
     */
    private function add()
    {
        Log::debug('Adding region to LayoutId ' . $this->layoutId);

        $sql = 'INSERT INTO `region` (`layoutId`, `ownerId`, `name`, `width`, `height`, `top`, `left`, `zIndex`) VALUES (:layoutId, :ownerId, :name, :width, :height, :top, :left, :zIndex)';

        $this->regionId = PDOConnect::insert($sql, array(
            'layoutId' => $this->layoutId,
            'ownerId' => $this->ownerId,
            'name' => $this->name,
            'width' => $this->width,
            'height' => $this->height,
            'top' => $this->top,
            'left' => $this->left,
            'zIndex' => $this->zIndex
        ));
    }

    /**
     * Update Database
     */
    private function update()
    {
        Log::debug('Editing %s', $this);

        $sql = '
          UPDATE `region` SET
            `ownerId` = :ownerId,
            `name` = :name,
            `width` = :width,
            `height` = :height,
            `top` = :top,
            `left` = :left,
            `zIndex` = :zIndex,
            `duration` = :duration
           WHERE `regionId` = :regionId
        ';

        PDOConnect::update($sql, array(
            'ownerId' => $this->ownerId,
            'name' => $this->name,
            'width' => $this->width,
            'height' => $this->height,
            'top' => $this->top,
            'left' => $this->left,
            'zIndex' => $this->zIndex,
            'duration' => $this->duration,
            'regionId' => $this->regionId
        ));
    }

    private function manageAssignments()
    {
        $this->linkPlaylists();
        $this->unlinkPlaylists();
    }

    /**
     * Link regions
     */
    private function linkPlaylists()
    {
        foreach ($this->playlists as $playlist) {
            /* @var Playlist $playlist */

            // The playlist might be new
            if ($playlist->playlistId == 0)
                $playlist->save();

            PDOConnect::insert('INSERT INTO `lkregionplaylist` (regionId, playlistId, displayOrder) VALUES (:regionId, :playlistId, :displayOrder) ON DUPLICATE KEY UPDATE regionId = regionId', array(
                'regionId' => $this->regionId,
                'playlistId' => $playlist->playlistId,
                'displayOrder' => $playlist->displayOrder
            ));
        }
    }

    /**
     * Unlink all Regions
     */
    private function unlinkPlaylists()
    {
        // Unlink any media that is NOT in the collection
        $params = ['regionId' => $this->regionId];

        $sql = '
          DELETE FROM `lkregionplaylist` WHERE regionId = :regionId
        ';

        $i = 0;
        foreach ($this->playlists as $playlist) {
            /* @var Playlist $playlist */

            $sql .= ' AND ( ';

            $i++;
            $sql .= ' (playlistId <> :playlistId' . $i . ' AND displayOrder <> :displayOrder' . $i . '))';
            $params['playlistId' . $i] = $playlist->playlistId;
            $params['displayOrder' . $i] = $playlist->displayOrder;
        }

        Log::sql($sql, $params);

        PDOConnect::update($sql, $params);
    }
}