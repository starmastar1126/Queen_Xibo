<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ScheduleFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Schedule;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class ScheduleFactory extends BaseFactory
{
    /**
     * @param int $eventId
     * @return Schedule
     * @throws NotFoundException
     */
    public function getById($eventId)
    {
        $events = $this->query(null, ['disableUserCheck' => 1, 'eventId' => $eventId]);

        if (count($events) <= 0)
            throw new NotFoundException();

        return $events[0];
    }

    /**
     * @param int $displayGroupId
     * @return array[Schedule]
     * @throws NotFoundException
     */
    public function getByDisplayGroupId($displayGroupId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId]);
    }

    /**
     * Get by Campaign ID
     * @param int $campaignId
     * @return array[Schedule]
     * @throws NotFoundException
     */
    public function getByCampaignId($campaignId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'campaignId' => $campaignId]);
    }

    /**
     * Get by OwnerId
     * @param int $ownerId
     * @return array[Schedule]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'ownerId' => $ownerId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Schedule]
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = [];
        $params = [];

        $useDetail = Sanitize::getInt('useDetail', $filterBy) == 1;

        $sql = '
        SELECT `schedule`.eventId, `schedule`.eventTypeId, ';

        if ($useDetail) {
            $sql .= '
            `schedule_detail`.fromDt,
            `schedule_detail`.toDt,
            ';
        } else {
            $sql .= '
            `schedule`.fromDt,
            `schedule`.toDt,
            ';
        }

        $sql .= '
            `schedule`.userId,
            `schedule`.displayOrder,
            `schedule`.is_priority AS isPriority,
            `schedule`.recurrence_type AS recurrenceType,
            `schedule`.recurrence_detail AS recurrenceDetail,
            `schedule`.recurrence_range AS recurrenceRange,
            campaign.campaignId,
            campaign.campaign,
            `command`.commandId,
            `command`.command,
            `schedule`.dayPartId
          FROM `schedule`
            LEFT OUTER JOIN `campaign`
            ON campaign.CampaignID = `schedule`.CampaignID
            LEFT OUTER JOIN `command`
            ON `command`.commandId = `schedule`.commandId
        ';

        if ($useDetail) {
            $sql .= '
            INNER JOIN `schedule_detail`
            ON schedule_detail.EventID = `schedule`.EventID
            ';
        }

        $sql .= '
          WHERE 1 = 1
        ';

        if (Sanitize::getInt('eventId', $filterBy) !== null) {
            $sql .= ' AND `schedule`.eventId = :eventId ';
            $params['eventId'] = Sanitize::getInt('eventId', $filterBy);
        }

        if (Sanitize::getInt('campaignId', $filterBy) !== null) {
            $sql .= ' AND `schedule`.campaignId = :campaignId ';
            $params['campaignId'] = Sanitize::getInt('campaignId', $filterBy);
        }

        if (Sanitize::getInt('ownerId', $filterBy) !== null) {
            $sql .= ' AND `schedule`.userId = :ownerId ';
            $params['ownerId'] = Sanitize::getInt('ownerId', $filterBy);
        }

        // Only 1 date
        if (!$useDetail && Sanitize::getInt('fromDt', $filterBy) !== null && Sanitize::getInt('toDt', $filterBy) === null) {
            $sql .= ' AND schedule.fromDt > :fromDt ';
            $params['fromDt'] = Sanitize::getInt('fromDt', $filterBy);
        }

        if (!$useDetail && Sanitize::getInt('toDt', $filterBy) !== null && Sanitize::getInt('fromDt', $filterBy) === null) {
            $sql .= ' AND IFNULL(schedule.toDt, schedule.fromDt) <= :toDt ';
            $params['toDt'] = Sanitize::getInt('toDt', $filterBy);
        }

        if ($useDetail && Sanitize::getInt('fromDt', $filterBy) !== null && Sanitize::getInt('toDt', $filterBy) === null) {
            $sql .= ' AND schedule_detail.fromDt > :fromDt ';
            $params['fromDt'] = Sanitize::getInt('fromDt', $filterBy);
        }

        if ($useDetail && Sanitize::getInt('toDt', $filterBy) !== null && Sanitize::getInt('fromDt', $filterBy) === null) {
            $sql .= ' AND IFNULL(schedule_detail.toDt, schedule_detail.fromDt) <= :toDt ';
            $params['toDt'] = Sanitize::getInt('toDt', $filterBy);
        }
        // End only 1 date

        // Both dates
        if (!$useDetail && Sanitize::getInt('fromDt', $filterBy) !== null && Sanitize::getInt('toDt', $filterBy) !== null) {
            $sql .= ' AND schedule.fromDt > :fromDt ';
            $sql .= ' AND IFNULL(schedule.toDt, schedule.fromDt) <= :toDt ';
            $params['fromDt'] = Sanitize::getInt('fromDt', $filterBy);
            $params['toDt'] = Sanitize::getInt('toDt', $filterBy);
        }

        if ($useDetail && Sanitize::getInt('fromDt', $filterBy) !== null && Sanitize::getInt('toDt', $filterBy) !== null) {
            $sql .= ' AND schedule_detail.fromDt < :toDt ';
            $sql .= ' AND IFNULL(schedule_detail.toDt, schedule_detail.fromDt) >= :fromDt ';
            $params['fromDt'] = Sanitize::getInt('fromDt', $filterBy);
            $params['toDt'] = Sanitize::getInt('toDt', $filterBy);
        }
        // End both dates

        if (Sanitize::getIntArray('displayGroupIds', $filterBy) != null) {
            $sql .= ' AND `schedule`.eventId IN (SELECT `lkscheduledisplaygroup`.eventId FROM `lkscheduledisplaygroup` WHERE displayGroupId IN (' . implode(',', Sanitize::getIntArray('displayGroupIds', $filterBy)) . ')) ';
        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Schedule())->hydrate($row, ['intProperties' => ['isPriority']]);
        }

        return $entries;
    }
}