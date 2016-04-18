<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Widget.php) is part of Xibo.
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
use Xibo\Factory\WidgetAudioFactory;
use Xibo\Factory\WidgetMediaFactory;
use Xibo\Factory\WidgetOptionFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Widget\ModuleWidget;

/**
 * Class Widget
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Widget implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Widget ID")
     * @var int
     */
    public $widgetId;

    /**
     * @SWG\Property(description="The ID of the Playlist this Widget belongs to")
     * @var int
     */
    public $playlistId;

    /**
     * @SWG\Property(description="The ID of the User that owns this Widget")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The Module Type Code")
     * @var string
     */
    public $type;

    /**
     * @SWG\Property(description="The duration in seconds this widget should be shown")
     * @var int
     */
    public $duration;

    /**
     * @SWG\Property(description="The display order of this widget")
     * @var int
     */
    public $displayOrder;

    /**
     * @SWG\Property(description="Flag indicating if this widget has a duration that should be used")
     * @var int
     */
    public $useDuration;

    /**
     * @SWG\Property(description="Calculated Duration of this widget after taking into account the useDuration flag")
     * @var int
     */
    public $calculatedDuration = 0;

    /**
     * @SWG\Property(description="An array of Widget Options")
     * @var WidgetOption[]
     */
    public $widgetOptions = [];

    /**
     * @SWG\Property(description="An array of MediaIds this widget is linked to")
     * @var int[]
     */
    public $mediaIds = [];

    /**
     * @SWG\Property(description="An array of Audio MediaIds this widget is linked to")
     * @var WidgetAudio[]
     */
    public $audio = [];

    /**
     * @SWG\Property(description="An array of permissions for this widget")
     * @var Permission[]
     */
    public $permissions = [];

    /**
     * @SWG\Property(description="The Module Object for this Widget")
     * @var ModuleWidget $module
     */
    public $module;

    /**
     * Hash Key of Media Assignments
     * @var string
     */
    private $mediaHash = null;

    /**
     * Temporary Id used during import/upgrade
     * @var string read only string
     */
    public $tempId = null;

    /**
     * Flag to indicate whether the widget is newly added
     * @var bool
     */
    public $isNew = false;

    /**
     * Minimum duration for widgets
     * @var int
     */
    public static $widgetMinDuration = 1;

    /** @var  DateServiceInterface */
    private $dateService;

    /**
     * @var WidgetOptionFactory
     */
    private $widgetOptionFactory;

    /**
     * @var WidgetMediaFactory
     */
    private $widgetMediaFactory;

    /** @var  WidgetAudioFactory */
    private $widgetAudioFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param DateServiceInterface $date
     * @param WidgetOptionFactory $widgetOptionFactory
     * @param WidgetMediaFactory $widgetMediaFactory
     * @param WidgetAudioFactory $widgetAudioFactory
     * @param PermissionFactory $permissionFactory
     */
    public function __construct($store, $log, $date, $widgetOptionFactory, $widgetMediaFactory, $widgetAudioFactory, $permissionFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->excludeProperty('module');
        $this->dateService = $date;
        $this->widgetOptionFactory = $widgetOptionFactory;
        $this->widgetMediaFactory = $widgetMediaFactory;
        $this->widgetAudioFactory = $widgetAudioFactory;
        $this->permissionFactory = $permissionFactory;
    }

    /**
     * @param $playlistFactory
     * @return $this
     */
    public function setChildObjectDepencencies($playlistFactory)
    {
        $this->playlistFactory = $playlistFactory;
        return $this;
    }

    public function __clone()
    {
        $this->hash = null;
        $this->widgetId = null;
        $this->widgetOptions = array_map(function ($object) { return clone $object; }, $this->widgetOptions);
        $this->permissions = [];

        // No need to clone the media
    }

    public function __toString()
    {
        return sprintf('Widget. %s on playlist %d in position %d. WidgetId = %d', $this->type, $this->playlistId, $this->displayOrder, $this->widgetId);
    }

    private function hash()
    {
        return md5($this->widgetId . $this->playlistId . $this->ownerId . $this->type . $this->duration . $this->displayOrder . $this->useDuration . $this->calculatedDuration);
    }

    private function mediaHash()
    {
        return md5(implode(',', $this->mediaIds));
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->widgetId;
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
     * Set the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;
    }

    /**
     * Get Option
     * @param string $option
     * @return WidgetOption
     * @throws NotFoundException
     */
    public function getOption($option)
    {
        foreach ($this->widgetOptions as $widgetOption) {
            /* @var WidgetOption $widgetOption */
            if (strtolower($widgetOption->option) == strtolower($option))
                return $widgetOption;
        }

        throw new NotFoundException('Widget Option not found');
    }

    /**
     * Get Widget Option Value
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    public function getOptionValue($option, $default)
    {
        try {
            $widgetOption = $this->getOption($option);
            $widgetOption = (($widgetOption->value) === null) ? $default : $widgetOption->value;

            if (is_integer($default))
                $widgetOption = intval($widgetOption);

            return $widgetOption;
        }
        catch (NotFoundException $e) {
            return $default;
        }
    }

    /**
     * Set Widget Option Value
     * @param string $option
     * @param string $type
     * @param mixed $value
     */
    public function setOptionValue($option, $type, $value)
    {
        try {
            $widgetOption = $this->getOption($option);
            $widgetOption->type = $type;
            $widgetOption->value = $value;
        }
        catch (NotFoundException $e) {
            $this->widgetOptions[] = $this->widgetOptionFactory->create($this->widgetId, $type, $option, $value);
        }
    }

    /**
     * Assign File Media
     * @param int $mediaId
     */
    public function assignMedia($mediaId)
    {
        $this->load();

        if (!in_array($mediaId, $this->mediaIds))
            $this->mediaIds[] = $mediaId;
    }

    /**
     * Unassign File Media
     * @param int $mediaId
     */
    public function unassignMedia($mediaId)
    {
        $this->load();

        $this->mediaIds = array_diff($this->mediaIds, [$mediaId]);
    }

    /**
     * Count media
     * @return int count of media
     */
    public function countMedia()
    {
        $this->load();
        return count($this->mediaIds);
    }

    /**
     * Clear Media
     */
    public function clearMedia()
    {
        $this->load();
        $this->mediaIds = [];
    }

    /**
     * Assign Audio Media
     * @param WidgetAudio $audio
     */
    public function assignAudio($audio)
    {
        $this->load();

        if (!in_array($audio, $this->audio))
            $this->audio[] = $audio;

        // Assign the media
        $this->assignMedia($audio->mediaId);
    }

    /**
     * Unassign Audio Media
     * @param WidgetAudio $audio
     */
    public function unassignAudio($audio)
    {
        $this->load();

        $this->audio = array_diff($this->audio, [$audio]);

        // Unassign the media
        $this->unassignMedia($audio->mediaId);
    }

    /**
     * Unassign Audio Media
     * @param int $mediaId
     */
    public function unassignAudioById($mediaId)
    {
        $this->load();

        foreach ($this->audio as $audio) {

            if ($audio->mediaId == $mediaId)
                $this->unassignAudio($audio);
        }
    }

    /**
     * Count Audio
     * @return int
     */
    public function countAudio()
    {
        return count($this->audio);
    }

    /**
     * Have the media assignments changed.
     */
    public function hasMediaChanged()
    {
        return ($this->mediaHash != $this->mediaHash());
    }

    /**
     * Load the Widget
     */
    public function load()
    {
        if ($this->loaded || $this->widgetId == null || $this->widgetId == 0)
            return;

        // Load permissions
        $this->permissions = $this->permissionFactory->getByObjectId(get_class(), $this->widgetId);

        // Load the widget options
        $this->widgetOptions = $this->widgetOptionFactory->getByWidgetId($this->widgetId);

        // Load any media assignments for this widget
        $this->mediaIds = $this->widgetMediaFactory->getByWidgetId($this->widgetId);

        // Load any widget audio assignments
        $this->audio = $this->widgetAudioFactory->getByWidgetId($this->widgetId);

        $this->hash = $this->hash();
        $this->mediaHash = $this->mediaHash();
        $this->loaded = true;
    }

    /**
     * Save the widget
     * @param array $options
     */
    public function save($options = [])
    {
        // Default options
        $options = array_merge([
            'saveWidgetOptions' => true,
            'saveWidgetAudio' => true,
            'notify' => true
        ], $options);

        $this->getLog()->debug('Saving widgetId %d with options. %s', $this->getId(), json_encode($options, JSON_PRETTY_PRINT));

        // Add/Edit
        if ($this->widgetId == null || $this->widgetId == 0)
            $this->add();
        else if ($this->hash != $this->hash())
            $this->update();

        // Save the widget options
        if ($options['saveWidgetOptions']) {
            foreach ($this->widgetOptions as $widgetOption) {
                /* @var \Xibo\Entity\WidgetOption $widgetOption */

                // Assert the widgetId
                $widgetOption->widgetId = $this->widgetId;
                $widgetOption->save();
            }
        }

        // Save the widget audio
        if ($options['saveWidgetAudio']) {
            foreach ($this->audio as $audio) {
                /* @var \Xibo\Entity\WidgetAudio $audio */

                // Assert the widgetId
                $audio->widgetId = $this->widgetId;
                $audio->save();
            }
        }

        // Manage the assigned media
        $this->linkMedia();
        $this->unlinkMedia();

        if ($options['notify'])
            $this->notify();
    }

    /**
     * @param array $options
     */
    public function delete($options = [])
    {
        $options = array_merge([
            'notify' => true
        ], $options);

        // We must ensure everything is loaded before we delete
        $this->load();

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // Delete all Options
        foreach ($this->widgetOptions as $widgetOption) {
            /* @var \Xibo\Entity\WidgetOption $widgetOption */

            // Assert the widgetId
            $widgetOption->widgetId = $this->widgetId;
            $widgetOption->delete();
        }

        // Delete the widget audio
        foreach ($this->audio as $audio) {
            /* @var \Xibo\Entity\WidgetAudio $audio */

            // Assert the widgetId
            $audio->widgetId = $this->widgetId;
            $audio->delete();
        }

        // Unlink Media
        $this->mediaIds = [];
        $this->unlinkMedia();

        // Delete this
        $this->getStore()->update('DELETE FROM `widget` WHERE widgetId = :widgetId', array('widgetId' => $this->widgetId));

        if ($options['notify'])
            $this->notify();

        $this->getLog()->debug('Delete Widget Complete');
    }

    /**
     * Notify
     */
    private function notify()
    {
        $this->getLog()->debug('Notifying upstream playlist');

        // Notify the Layout
        $this->getStore()->update('
            UPDATE `layout` SET `status` = 3, `modifiedDT` = :modifiedDt WHERE layoutId IN (
              SELECT `region`.layoutId
                FROM `lkregionplaylist`
                  INNER JOIN `region`
                  ON region.regionId = `lkregionplaylist`.regionId
               WHERE `lkregionplaylist`.playlistId = :playlistId
            )
        ', [
            'playlistId' => $this->playlistId,
            'modifiedDt' => $this->dateService->getLocalDate()
        ]);
    }

    private function add()
    {
        $this->getLog()->debug('Adding Widget ' . $this->type . ' to PlaylistId ' . $this->playlistId);

        $this->isNew = true;

        $sql = '
            INSERT INTO `widget` (`playlistId`, `ownerId`, `type`, `duration`, `displayOrder`, `useDuration`, `calculatedDuration`)
            VALUES (:playlistId, :ownerId, :type, :duration, :displayOrder, :useDuration, :calculatedDuration)
        ';

        $this->widgetId = $this->getStore()->insert($sql, array(
            'playlistId' => $this->playlistId,
            'ownerId' => $this->ownerId,
            'type' => $this->type,
            'duration' => $this->duration,
            'displayOrder' => $this->displayOrder,
            'useDuration' => $this->useDuration,
            'calculatedDuration' => $this->calculatedDuration
        ));
    }

    private function update()
    {
        $this->getLog()->debug('Saving Widget ' . $this->type . ' on PlaylistId ' . $this->playlistId . ' WidgetId: ' . $this->widgetId);

        $sql = '
          UPDATE `widget` SET `playlistId` = :playlistId,
            `ownerId` = :ownerId,
            `type` = :type,
            `duration` = :duration,
            `displayOrder` = :displayOrder,
            `useDuration` = :useDuration,
            `calculatedDuration` = :calculatedDuration
           WHERE `widgetId` = :widgetId
        ';

        $this->getStore()->update($sql, array(
            'playlistId' => $this->playlistId,
            'ownerId' => $this->ownerId,
            'type' => $this->type,
            'duration' => $this->duration,
            'widgetId' => $this->widgetId,
            'displayOrder' => $this->displayOrder,
            'useDuration' => $this->useDuration,
            'calculatedDuration' => $this->calculatedDuration
        ));
    }

    /**
     * Link Media
     */
    private function linkMedia()
    {
        // TODO: Make this more efficient by storing the prepared SQL statement
        $sql = 'INSERT INTO `lkwidgetmedia` (widgetId, mediaId) VALUES (:widgetId, :mediaId) ON DUPLICATE KEY UPDATE mediaId = :mediaId2';

        foreach ($this->mediaIds as $mediaId) {

            $this->getLog()->debug('Inserting %d', $mediaId);

            $this->getStore()->insert($sql, array(
                'widgetId' => $this->widgetId,
                'mediaId' => $mediaId,
                'mediaId2' => $mediaId
            ));
        }
    }

    /**
     * Unlink Media
     */
    private function unlinkMedia()
    {
        // Unlink any media that isn't in the collection
        if (count($this->mediaIds) <= 0)
            $this->mediaIds = [0];

        $params = ['widgetId' => $this->widgetId];

        $sql = 'DELETE FROM `lkwidgetmedia` WHERE widgetId = :widgetId AND mediaId NOT IN (0';

        $i = 0;
        foreach ($this->mediaIds as $mediaId) {
            $i++;
            $sql .= ',:mediaId' . $i;
            $params['mediaId' . $i] = $mediaId;
        }

        $sql .= ')';



        $this->getStore()->update($sql, $params);
    }
}