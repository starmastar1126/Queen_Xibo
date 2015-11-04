<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Schedule.php)
 */


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Helper\Config;
use Xibo\Storage\PDOConnect;

/**
 * Class Schedule
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Schedule implements \JsonSerializable
{
    use EntityTrait;

    public static $LAYOUT_EVENT = 1;
    public static $COMMAND_EVENT = 2;

    /**
     * @SWG\Property(
     *  description="The ID of this Event"
     * )
     * @var int
     */
    public $eventId;

    /**
     * @SWG\Property(
     *  description="The Event Type ID"
     * )
     * @var int
     */
    public $eventTypeId;

    /**
     * @SWG\Property(
     *  description="The CampaignID this event is for"
     * )
     * @var int
     */
    public $campaignId;

    /**
     * @SWG\Property(
     *  description="The CommandId this event is for"
     * )
     * @var int
     */
    public $commandId;

    /**
     * @SWG\Property(
     *  description="Display Groups assigned to this Scheduled Event.",
     *  type="array",
     *  @SWG\Items(ref="#/definitions/DisplayGroup")
     * )
     * @var DisplayGroup[]
     */
    public $displayGroups = [];

    /**
     * @SWG\Property(
     *  description="The userId that owns this event."
     * )
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(
     *  description="A Unix timestamp representing the from date of this event in CMS time."
     * )
     * @var int
     */
    public $fromDt;

    /**
     * @SWG\Property(
     *  description="A Unix timestamp representing the to date of this event in CMS time."
     * )
     * @var int
     */
    public $toDt;

    /**
     * @SWG\Property(
     *  description="Flag indicating whether the event should be considered priority or not."
     * )
     * @var int
     */
    public $isPriority;

    /**
     * @SWG\Property(
     *  description="The display order for this event."
     * )
     * @var int
     */
    public $displayOrder;

    /**
     * @SWG\Property(
     *  description="If this event recurs when what is the recurrence period.",
     *  enum={"None", "Minute", "Hour", "Day", "Week", "Month", "Year"}
     * )
     * @var string
     */
    public $recurrenceType;

    /**
     * @SWG\Property(
     *  description="If this event recurs when what is the recurrence frequency.",
     * )
     * @var int
     */
    public $recurrenceDetail;

    /**
     * @SWG\Property(
     *  description="A Unix timestamp indicating the end time of the recurring events."
     * )
     * @var int
     */
    public $recurrenceRange;

    /**
     * @SWG\Property(
     *  description="The Campaign/Layout Name",
     *  readOnly=true
     * )
     * @var string
     */
    public $campaign;

    /**
     * @SWG\Property(
     *  description="The Command Name",
     *  readOnly=true
     * )
     * @var string
     */
    public $command;

    /**
     * Is this event (as a whole) inside the schedule look ahead period?
     * @var bool
     */
    private $isInScheduleLookAhead = false;


    public function getId()
    {
        return $this->eventId;
    }

    public function getOwnerId()
    {
        return $this->userId;
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->userId = $ownerId;
    }

    /**
     * Are the provided dates within the schedule look ahead
     * @param $fromDt
     * @param $toDt
     * @return bool
     */
    private function datesInScheduleLookAhead($fromDt, $toDt)
    {
        // From Date and To Date are in UNIX format
        $currentDate = time();
        $rfLookAhead = intval($currentDate) + intval(Config::GetSetting('REQUIRED_FILES_LOOKAHEAD'));

        if ($toDt == null)
            $toDt = $fromDt;

        return ($fromDt < $rfLookAhead && $toDt > $currentDate);
    }

    public function load()
    {
        // If we are already loaded, then don't do it again
        if ($this->loaded || $this->eventId == null || $this->eventId == 0)
            return;

        $this->displayGroups = DisplayGroupFactory::getByEventId($this->eventId);

        // We are fully loaded
        $this->loaded = true;
    }

    /**
     * Assign DisplayGroup
     * @param DisplayGroup $displayGroup
     */
    public function assignDisplayGroup($displayGroup)
    {
        $this->load();

        if (!in_array($displayGroup, $this->displayGroups))
            $this->displayGroups[] = $displayGroup;
    }

    /**
     * Unassign DisplayGroup
     * @param DisplayGroup $displayGroup
     */
    public function unassignDisplayGroup($displayGroup)
    {
        $this->load();

        $this->displayGroups = array_udiff($this->displayGroups, [$displayGroup], function ($a, $b) {
            /**
             * @var DisplayGroup $a
             * @var DisplayGroup $b
             */
            return $a->getId() - $b->getId();
        });
    }

    /**
     * Validate
     */
    public function validate()
    {
        if (count($this->displayGroups) <= 0)
            throw new \InvalidArgumentException(__('No display groups selected'));

        if ($this->eventTypeId == Schedule::$LAYOUT_EVENT) {
            // Validate layout
            if (!v::int()->notEmpty()->min(1)->validate($this->campaignId))
                throw new \InvalidArgumentException(__('Please select a Campaign/Layout for this event.'));

            // validate the dates
            if ($this->toDt < $this->fromDt)
                throw new \InvalidArgumentException(__('Can not have an end time earlier than your start time'));

            $this->commandId = null;

        } else if ($this->eventTypeId == Schedule::$COMMAND_EVENT) {
            // Validate command
            if (!v::int()->notEmpty()->min(1)->validate($this->commandId))
                throw new \InvalidArgumentException(__('Please select a Command for this event.'));

            $this->campaignId = null;
            $this->toDt = null;

        } else {
            // No event type selected
            throw new \InvalidArgumentException(__('Please select the Event Type'));
        }
    }

    /**
     * Save
     * @param bool $validate
     */
    public function save($validate = true)
    {
        if ($validate)
            $this->validate();

        if ($this->eventId == null || $this->eventId == 0) {
            $this->add();
            $this->loaded = true;
        }
        else
            $this->edit();

        // Manage display assignments
        if ($this->loaded) {
            // Manage assignments
            $this->manageAssignments();
        }

        // Check the main event dates to see if we are in the schedule look ahead
        $this->isInScheduleLookAhead = $this->datesInScheduleLookAhead($this->fromDt, $this->toDt);

        // Generate the event instances
        $this->generate();

        // Notify
        // Only if the schedule effects the immediate future - i.e. within the RF Look Ahead
        if ($this->isInScheduleLookAhead) {
            foreach ($this->displayGroups as $displayGroup) {
                /* @var DisplayGroup $displayGroup */
                $displayGroup->setCollectRequired();
                $displayGroup->setMediaIncomplete();
            }
        }
    }

    /**
     * Delete this Schedule Event
     */
    public function delete()
    {
        // Delete display group assignments
        $this->displayGroups = [];
        $this->unlinkDisplayGroups();

        // Delete all detail records
        $this->deleteDetail();

        // Delete the event itself
        PDOConnect::update('DELETE FROM `schedule` WHERE eventId = :eventId', ['eventId' => $this->eventId]);
    }

    /**
     * Add
     */
    private function add()
    {
        $this->eventId = PDOConnect::insert('
          INSERT INTO `schedule` (eventTypeId, CampaignId, commandId, userID, is_priority, FromDT, ToDT, DisplayOrder, recurrence_type, recurrence_detail, recurrence_range)
            VALUES (:eventTypeId, :campaignId, :commandId, :userId, :isPriority, :fromDt, :toDt, :displayOrder, :recurrenceType, :recurrenceDetail, :recurrenceRange)
        ', [
            'eventTypeId' => $this->eventTypeId,
            'campaignId' => $this->campaignId,
            'commandId' => $this->commandId,
            'userId' => $this->userId,
            'isPriority' => $this->isPriority,
            'fromDt' => $this->fromDt,
            'toDt' => $this->toDt,
            'displayOrder' => $this->displayOrder,
            'recurrenceType' => $this->recurrenceType,
            'recurrenceDetail' => $this->recurrenceDetail,
            'recurrenceRange' => $this->recurrenceRange
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        PDOConnect::update('
          UPDATE `schedule` SET
            eventTypeId = :eventTypeId,
            campaignId = :campaignId,
            commandId = :commandId,
            is_priority = :isPriority,
            userId = :userId,
            fromDt = :fromDt,
            toDt = :toDt,
            displayOrder = :displayOrder,
            recurrence_type = :recurrenceType,
            recurrence_detail = :recurrenceDetail,
            recurrence_range = :recurrenceRange
          WHERE eventId = :eventId
        ', [
            'eventTypeId' => $this->eventTypeId,
            'campaignId' => $this->campaignId,
            'commandId' => $this->commandId,
            'userId' => $this->userId,
            'isPriority' => $this->isPriority,
            'fromDt' => $this->fromDt,
            'toDt' => $this->toDt,
            'displayOrder' => $this->displayOrder,
            'recurrenceType' => $this->recurrenceType,
            'recurrenceDetail' => $this->recurrenceDetail,
            'recurrenceRange' => $this->recurrenceRange,
            'eventId' => $this->eventId
        ]);

        // Delete detail and regenerate
        $this->deleteDetail();
    }

    /**
     * Generate Instances
     */
    private function generate()
    {
        // TODO: generate 30 days in advance.
        $daysToGenerate = 30;

        // Add the detail for the main event
        $this->addDetail($this->fromDt, $this->toDt);

        // If we don't have any recurrence, we are done
        if ($this->recurrenceType == '')
            return;

        // Set the temp starts
        $t_start_temp = $this->fromDt;
        $t_end_temp = $this->toDt;

        // loop until we have added the recurring events for the schedule
        while ($t_start_temp < $this->recurrenceRange)
        {
            // add the appropriate time to the start and end
            switch ($this->recurrenceType)
            {
                case 'Minute':
                    $t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp) + $this->recurrenceDetail, date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp));
                    $t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp) + $this->recurrenceDetail, date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp));
                    break;

                case 'Hour':
                    $t_start_temp = mktime(date("H", $t_start_temp) + $this->recurrenceDetail, date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp));
                    $t_end_temp = mktime(date("H", $t_end_temp) + $this->recurrenceDetail, date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp));
                    break;

                case 'Day':
                    $t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp)+$this->recurrenceDetail, date("Y", $t_start_temp));
                    $t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp)+$this->recurrenceDetail, date("Y", $t_end_temp));
                    break;

                case 'Week':
                    $t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp) + ($this->recurrenceDetail * 7), date("Y", $t_start_temp));
                    $t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp) + ($this->recurrenceDetail * 7), date("Y", $t_end_temp));
                    break;

                case 'Month':
                    $t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp)+$this->recurrenceDetail ,date("d", $t_start_temp), date("Y", $t_start_temp));
                    $t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp)+$this->recurrenceDetail ,date("d", $t_end_temp), date("Y", $t_end_temp));
                    break;

                case 'Year':
                    $t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp)+$this->recurrenceDetail);
                    $t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp)+$this->recurrenceDetail);
                    break;
            }

            // after we have added the appropriate amount, are we still valid
            if ($t_start_temp > $this->recurrenceRange)
                break;

            if ($this->toDt == null)
                $this->addDetail($t_start_temp, null);
            else
                $this->addDetail($t_start_temp, $t_end_temp);

            // Check these dates
            if (!$this->isInScheduleLookAhead)
                $this->isInScheduleLookAhead = $this->datesInScheduleLookAhead($t_start_temp, $t_end_temp);
        }
    }

    /**
     * Add Detail
     * @param int $fromDt
     * @param int $toDt
     */
    private function addDetail($fromDt, $toDt)
    {
        PDOConnect::insert('INSERT INTO `schedule_detail` (eventId, fromDt, toDt) VALUES (:eventId, :fromDt, :toDt)', [
            'eventId' => $this->eventId,
            'fromDt' => $fromDt,
            'toDt' => $toDt
        ]);
    }

    /**
     * Delete Detail
     */
    private function deleteDetail()
    {
        PDOConnect::update('DELETE FROM `schedule_detail` WHERE eventId = :eventId', ['eventId' => $this->eventId]);
    }

    /**
     * Manage the assignments
     */
    private function manageAssignments()
    {
        $this->linkDisplayGroups();
        $this->unlinkDisplayGroups();
    }

    /**
     * Link Layout
     */
    private function linkDisplayGroups()
    {
        // TODO: Make this more efficient by storing the prepared SQL statement
        $sql = 'INSERT INTO `lkscheduledisplaygroup` (eventId, displayGroupId) VALUES (:eventId, :displayGroupId) ON DUPLICATE KEY UPDATE displayGroupId = displayGroupId';

        $i = 0;
        foreach ($this->displayGroups as $displayGroup) {
            $i++;

            PDOConnect::insert($sql, array(
                'eventId' => $this->eventId,
                'displayGroupId' => $displayGroup->displayGroupId
            ));
        }
    }

    /**
     * Unlink Layout
     */
    private function unlinkDisplayGroups()
    {
        // Unlink any layouts that are NOT in the collection
        $params = ['eventId' => $this->eventId];

        $sql = 'DELETE FROM `lkscheduledisplaygroup` WHERE eventId = :eventId AND displayGroupId NOT IN (0';

        $i = 0;
        foreach ($this->displayGroups as $displayGroup) {
            $i++;
            $sql .= ',:displayGroupId' . $i;
            $params['displayGroupId' . $i] = $displayGroup->displayGroupId;
        }

        $sql .= ')';

        PDOConnect::update($sql, $params);
    }
}