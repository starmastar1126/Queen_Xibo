<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2016 Daniel Garner
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
namespace Xibo\Controller;

use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Report\ProofOfPlay;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\ReportServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;

/**
 * Class Stats
 * @package Xibo\Controller
 */
class Stats extends Base
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var TimeSeriesStoreInterface
     */
    private $timeSeriesStore;

    /**
     * @var ReportServiceInterface
     */
    private $reportService;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  UserFactory */
    private $userFactory;

    /** @var  UserGroupFactory */
    private $userGroupFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param ReportServiceInterface $reportService
     * @param DisplayFactory $displayFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     * @param UserFactory $userFactory
     * @param UserGroupFactory $userGroupFactory
     * @param DisplayGroupFactory $displayGroupFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $timeSeriesStore, $reportService, $displayFactory, $layoutFactory, $mediaFactory, $userFactory, $userGroupFactory, $displayGroupFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->reportService = $reportService;
        $this->displayFactory = $displayFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->userFactory = $userFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->displayGroupFactory = $displayGroupFactory;
    }

    /**
     * Stats page
     */
    function displayPage()
    {
        $data = [
            // List of Displays this user has permission for
            'defaults' => [
                'fromDate' => $this->getDate()->getLocalDate(time() - (86400 * 35)),
                'fromDateOneDay' => $this->getDate()->getLocalDate(time() - 86400),
                'toDate' => $this->getDate()->getLocalDate()
            ]
        ];

        $this->getState()->template = 'statistics-page';
        $this->getState()->setData($data);
    }

    /**
     * Stats page
     */
    function displayProofOfPlayPage()
    {
        $data = [
            // List of Displays this user has permission for
            'defaults' => [
                'fromDate' => $this->getDate()->getLocalDate(time() - (86400 * 35)),
                'fromDateOneDay' => $this->getDate()->getLocalDate(time() - 86400),
                'toDate' => $this->getDate()->getLocalDate(),
                'availableReports' => $this->reportService->listReports()
            ]
        ];

        $this->getState()->template = 'stats-proofofplay-page';
        $this->getState()->setData($data);
    }

    /**
     * @SWG\Definition(
     *  definition="StatisticsData",
     *  @SWG\Property(
     *      property="type",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="display",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="displayId",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="layout",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="layoutId",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="media",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="mediaId",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="widgetId",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="numberPlays",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="duration",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="minStart",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="maxEnd",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="start",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="end",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="statDate",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="tag",
     *      type="string"
     *  )
     * )
     *
     *
     * Shows the stats grid
     *
     * @SWG\Get(
     *  path="/stats",
     *  operationId="statsSearch",
     *  tags={"statistics"},
     *  @SWG\Parameter(
     *      name="type",
     *      in="formData",
     *      description="The type of stat to return. Layout|Media|Widget or All",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="fromDt",
     *      in="formData",
     *      description="The start date for the filter. Default = 24 hours ago",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="toDt",
     *      in="formData",
     *      description="The end date for the filter. Default = now.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="formData",
     *      description="An optional display Id to filter",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="layoutId",
     *      description="An optional array of layout Id to filter",
     *      in="formData",
     *      required=false,
     *      type="array",
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *   @SWG\Parameter(
     *      name="mediaId",
     *      description="An optional array of media Id to filter",
     *      in="formData",
     *      required=false,
     *      type="array",
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *   @SWG\Parameter(
     *      name="campaignId",
     *      in="formData",
     *      description="An optional Campaign Id to filter",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(
     *              ref="#/definitions/StatisticsData"
     *          )
     *      )
     *  )
     * )
     */
    public function grid()
    {
        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getSanitizer()->getDate('statsFromDt', $this->getDate()->parse()->addDay(-1)));
        $toDt = $this->getSanitizer()->getDate('toDt', $this->getSanitizer()->getDate('statsToDt', $this->getDate()->parse()));
        $type = strtolower($this->getSanitizer()->getString('type'));

        $displayId = $this->getSanitizer()->getInt('displayId');
        $layoutIds = $this->getSanitizer()->getIntArray('layoutId');
        $mediaIds = $this->getSanitizer()->getIntArray('mediaId');
        $statDate = $this->getSanitizer()->getDate('statDate');
        $campaignId = $this->getSanitizer()->getInt('campaignId');

        $start = $this->getSanitizer()->getInt('start', 0);
        $length = $this->getSanitizer()->getInt('length', 10);

        $fromDt->startOfDay();
        $toDt->addDay()->startOfDay();

        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt == $toDt) {
            $toDt->addDay(1);
        }

        // Do not filter by display if super admin and no display is selected
        // Super admin will be able to see stat records of deleted display, we will not filter by display later
        $displayIds = [];
        if (!$this->getUser()->isSuperAdmin()) {
            // Get an array of display id this user has access to.
            foreach ($this->displayFactory->query() as $display) {
                $displayIds[] = $display->displayId;
            }

            if (count($displayIds) <= 0)
                throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

            // Set displayIds as [-1] if the user selected a display for which they don't have permission
            if ($displayId != 0) {
                if (!in_array($displayId, $displayIds)) {
                    $displayIds = [-1];
                } else {
                    $displayIds = [$displayId];
                }
            }
        } else {
            if ($displayId != 0) {
                $displayIds = [$displayId];
            }
        }

        // Call the time series interface getStats
        $resultSet =  $this->timeSeriesStore->getStats(
            [
                'fromDt'=> $fromDt,
                'toDt'=> $toDt,
                'type' => $type,
                'displayIds' => $displayIds,
                'layoutIds' => $layoutIds,
                'mediaIds' => $mediaIds,
                'statDate' => $statDate,
                'campaignId' => $campaignId,
                'start' => $start,
                'length' => $length,
            ]);

        // Get results as array
        $result = $resultSet->getArray();

        $rows = [];
        foreach ($result['statData'] as $row) {
            $entry = [];

            $widgetId = $this->getSanitizer()->int($row['widgetId']);
            $widgetName = $this->getSanitizer()->string($row['media']);
            $widgetName = ($widgetName == '' &&  $widgetId != 0) ? __('Deleted from Layout') : $widgetName;

            $displayName = isset($row['display']) ? $this->getSanitizer()->string($row['display']) : '';
            $layoutName = isset($row['layout']) ? $this->getSanitizer()->string($row['layout']) : '';
            $entry['type'] = $this->getSanitizer()->string($row['type']);
            $entry['displayId'] = $this->getSanitizer()->int(($row['displayId']));
            $entry['display'] = ($displayName != '') ? $displayName : __('Not Found');
            $entry['layout'] = ($layoutName != '') ? $layoutName :  __('Not Found');
            $entry['media'] = $widgetName;
            $entry['numberPlays'] = $this->getSanitizer()->int($row['count']);
            $entry['duration'] = $this->getSanitizer()->int($row['duration']);
            $entry['minStart'] = $this->getDate()->parse($row['start'], 'U')->format('Y-m-d H:i:s');
            $entry['maxEnd'] = $this->getDate()->parse($row['end'], 'U')->format('Y-m-d H:i:s');
            $entry['start'] = $this->getDate()->parse($row['start'], 'U')->format('Y-m-d H:i:s');
            $entry['end'] = $this->getDate()->parse($row['end'], 'U')->format('Y-m-d H:i:s');
            $entry['layoutId'] = $this->getSanitizer()->int($row['layoutId']);
            $entry['widgetId'] = $this->getSanitizer()->int($row['widgetId']);
            $entry['mediaId'] = $this->getSanitizer()->int($row['mediaId']);
            $entry['tag'] = $this->getSanitizer()->string($row['tag']);
            $entry['statDate'] = isset($row['statDate']) ? $this->getDate()->parse($row['statDate'], 'U')->format('Y-m-d H:i:s') : '';

            $rows[] = $entry;
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);
    }


    /**
     * Bandwidth Data
     */
    public function bandwidthData()
    {
        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getSanitizer()->getDate('bandwidthFromDt'));
        $toDt = $this->getSanitizer()->getDate('toDt', $this->getSanitizer()->getDate('bandwidthToDt'));

        // Get an array of display id this user has access to.
        $displayIds = [];

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

        // Get some data for a bandwidth chart
        $dbh = $this->store->getConnection();

        $displayId = $this->getSanitizer()->getInt('displayId');
        $params = array(
            'month' => $this->getDate()->getLocalDate($fromDt->setDateTime($fromDt->year, $fromDt->month, 1, 0, 0), 'U'),
            'month2' => $this->getDate()->getLocalDate($toDt->addMonth(1)->setDateTime($toDt->year, $toDt->month, 1, 0, 0), 'U')
        );

        $SQL = 'SELECT display.display, IFNULL(SUM(Size), 0) AS size ';

        if ($displayId != 0)
            $SQL .= ', bandwidthtype.name AS type ';

        $SQL .= ' FROM `bandwidth`
                LEFT OUTER JOIN `display`
                ON display.displayid = bandwidth.displayid AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayId != 0)
            $SQL .= '
                    INNER JOIN bandwidthtype
                    ON bandwidthtype.bandwidthtypeid = bandwidth.type
                ';

        $SQL .= '  WHERE month > :month
                AND month < :month2 ';

        if ($displayId != 0) {
            $SQL .= ' AND display.displayid = :displayid ';
            $params['displayid'] = $displayId;
        }

        $SQL .= 'GROUP BY display.display ';

        if ($displayId != 0)
            $SQL .= ' , bandwidthtype.name ';

        $SQL .= 'ORDER BY display.display';

        $sth = $dbh->prepare($SQL);

        $sth->execute($params);

        // Get the results
        $results = $sth->fetchAll();

        $maxSize = 0;
        foreach ($results as $library) {
            $maxSize = ($library['size'] > $maxSize) ? $library['size'] : $maxSize;
        }

        // Decide what our units are going to be, based on the size
        $base = floor(log($maxSize) / log(1024));

        $labels = [];
        $data = [];
        $backgroundColor = [];

        foreach ($results as $row) {

            // label depends whether we are filtered by display
            if ($displayId != 0) {
                $labels[] = $row['type'];
            } else {
                $labels[] = $row['display'] === null ? __('Deleted Displays') : $row['display'];
            }
            $backgroundColor[] = ($row['display'] === null) ? 'rgb(255,0,0)' : 'rgb(11, 98, 164)';
            $data[] = round((double)$row['size'] / (pow(1024, $base)), 2);
        }

        // Set up some suffixes
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');

        $this->getState()->extra = [
            'labels' => $labels,
            'data' => $data,
            'backgroundColor' => $backgroundColor,
            'postUnits' => (isset($suffixes[$base]) ? $suffixes[$base] : '')
        ];
    }

    /**
     * Output CSV Form
     */
    public function exportForm()
    {
        $this->getState()->template = 'statistics-form-export';
    }

    /**
     * Outputs a CSV of stats
     */
    public function export()
    {
        // We are expecting some parameters
        $fromDt = $this->getSanitizer()->getDate('fromDt');
        $toDt = $this->getSanitizer()->getDate('toDt');
        $displayId = $this->getSanitizer()->getInt('displayId');

        // Do not filter by display if super admin and no display is selected
        // Super admin will be able to see stat records of deleted display, we will not filter by display later
        $displayIds = [];
        if (!$this->getUser()->isSuperAdmin()) {
            // Get an array of display id this user has access to.
            foreach ($this->displayFactory->query() as $display) {
                $displayIds[] = $display->displayId;
            }

            if (count($displayIds) <= 0)
                throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

            // Set displayIds as [-1] if the user selected a display for which they don't have permission
            if ($displayId != 0) {
                if (!in_array($displayId, $displayIds)) {
                    $displayIds = [-1];
                } else {
                    $displayIds = [$displayId];
                }
            }
        } else {
            if ($displayId != 0) {
                $displayIds = [$displayId];
            }
        }

        if ($fromDt == null || $toDt == null) {
            throw new InvalidArgumentException(__("Both fromDt/toDt should be provided"), 'fromDt/toDt');
        }

        $fromDt->startOfDay();
        $toDt->addDay()->startOfDay();

        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt == $toDt) {
            $toDt->addDay();
        }

        // Get result set
        $resultSet =  $this->timeSeriesStore->getStats([
            'fromDt'=> $fromDt,
            'toDt'=> $toDt,
            'displayIds' => $displayIds,
        ]);

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Stat Date', 'Type', 'FromDT', 'ToDT', 'Layout', 'Display', 'Media', 'Tag', 'Duration', 'Count']);

        while ($row = $resultSet->getNextRow() ) {

            $displayName = isset($row['display']) ? $this->getSanitizer()->string($row['display']) : '';
            $layoutName = isset($row['layout']) ? $this->getSanitizer()->string($row['layout']) : '';

            // Read the columns
            $type = $this->getSanitizer()->string($row['type']);
            if ($this->timeSeriesStore->getEngine() == 'mongodb') {

                $statDate = isset($row['statDate']) ? $this->getDate()->parse($row['statDate']->toDateTime()->format('U'), 'U')->format('Y-m-d H:i:s') : null;
                $fromDt = $this->getDate()->parse($row['start']->toDateTime()->format('U'), 'U')->format('Y-m-d H:i:s');
                $toDt = $this->getDate()->parse($row['end']->toDateTime()->format('U'), 'U')->format('Y-m-d H:i:s');
            } else {

                $statDate = isset($row['statDate']) ?$this->getDate()->parse($row['statDate'], 'U')->format('Y-m-d H:i:s') : null;
                $fromDt = $this->getDate()->parse($row['start'], 'U')->format('Y-m-d H:i:s');
                $toDt = $this->getDate()->parse($row['end'], 'U')->format('Y-m-d H:i:s');
            }

            $layout = ($layoutName != '') ? $layoutName :  __('Not Found');
            $display = ($displayName != '') ? $displayName : __('Not Found');
            $media = isset($row['media']) ? $this->getSanitizer()->string($row['media']): '';
            $tag = isset($row['tag']) ? $this->getSanitizer()->string($row['tag']): '';

            $duration = isset($row['duration']) ? $this->getSanitizer()->string($row['duration']): '';
            $count = isset($row['count']) ? $this->getSanitizer()->string($row['count']): '';

            fputcsv($out, [$statDate, $type, $fromDt, $toDt, $layout, $display, $media, $tag, $duration, $count]);
        }

        fclose($out);

        // We want to output a load of stuff to the browser as a text file.
        $app = $this->getApp();
        $app->response()->header('Content-Type', 'text/csv');
        $app->response()->header('Content-Disposition', 'attachment; filename="stats.csv"');
        $app->response()->header('Content-Transfer-Encoding', 'binary"');
        $app->response()->header('Accept-Ranges', 'bytes');
        $this->setNoOutput(true);
    }

    /**
     * Stats page
     */
    function displayLibraryPage()
    {
        $this->getState()->template = 'stats-library-page';
        $data = [];

        // Set up some suffixes
        $suffixes = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');

        // Widget for the library usage pie chart
        try {
            if ($this->getUser()->libraryQuota != 0) {
                $libraryLimit = $this->getUser()->libraryQuota * 1024;
            } else {
                $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;
            }

            // Library Size in Bytes
            $params = [];
            $sql = 'SELECT IFNULL(SUM(FileSize), 0) AS SumSize, type FROM `media` WHERE 1 = 1 ';
            $this->mediaFactory->viewPermissionSql('Xibo\Entity\Media', $sql, $params, '`media`.mediaId', '`media`.userId');
            $sql .= ' GROUP BY type ';

            $sth = $this->store->getConnection()->prepare($sql);
            $sth->execute($params);

            $results = $sth->fetchAll();

            // Do we base the units on the maximum size or the library limit
            $maxSize = 0;
            if ($libraryLimit > 0) {
                $maxSize = $libraryLimit;
            } else {
                // Find the maximum sized chunk of the items in the library
                foreach ($results as $library) {
                    $maxSize = ($library['SumSize'] > $maxSize) ? $library['SumSize'] : $maxSize;
                }
            }

            // Decide what our units are going to be, based on the size
            $base = ($maxSize == 0) ? 0 : floor(log($maxSize) / log(1024));

            $libraryUsage = [];
            $libraryLabels = [];
            $totalSize = 0;
            foreach ($results as $library) {
                $libraryUsage[] = round((double)$library['SumSize'] / (pow(1024, $base)), 2);
                $libraryLabels[] = ucfirst($library['type']) . ' ' . $suffixes[$base];

                $totalSize = $totalSize + $library['SumSize'];
            }

            // Do we need to add the library remaining?
            if ($libraryLimit > 0) {
                $remaining = round(($libraryLimit - $totalSize) / (pow(1024, $base)), 2);

                $libraryUsage[] = $remaining;
                $libraryLabels[] = __('Free') . ' ' . $suffixes[$base];
            }

            // What if we are empty?
            if (count($results) == 0 && $libraryLimit <= 0) {
                $libraryUsage[] = 0;
                $libraryLabels[] = __('Empty');
            }

            $data['libraryLimitSet'] = ($libraryLimit > 0);
            $data['libraryLimit'] = (round((double)$libraryLimit / (pow(1024, $base)), 2)) . ' ' . $suffixes[$base];
            $data['librarySize'] = ByteFormatter::format($totalSize, 1);
            $data['librarySuffix'] = $suffixes[$base];
            $data['libraryWidgetLabels'] = json_encode($libraryLabels);
            $data['libraryWidgetData'] = json_encode($libraryUsage);

        } catch (\Exception $exception) {
            $this->getLog()->error('Error rendering the library stats page widget');
        }

        $data['users'] = $this->userFactory->query();
        $data['groups'] = $this->userGroupFactory->query();

        $this->getState()->setData($data);
    }

    public function libraryUsageGrid()
    {
        $params = [];
        $select = '
            SELECT `user`.userId,
                `user`.userName,
                IFNULL(SUM(`media`.FileSize), 0) AS bytesUsed,
                COUNT(`media`.mediaId) AS numFiles
        ';
        $body = '     
              FROM `user`
                LEFT OUTER JOIN `media`
                ON `media`.userID = `user`.UserID
              WHERE 1 = 1
        ';

        // Restrict on the users we have permission to see
        // Normal users can only see themselves
        $permissions = '';
        if ($this->getUser()->userTypeId == 3) {
            $permissions .= ' AND user.userId = :currentUserId ';
            $filterBy['currentUserId'] = $this->getUser()->userId;
        }
        // Group admins can only see users from their groups.
        else if ($this->getUser()->userTypeId == 2) {
            $permissions .= '
                AND user.userId IN (
                    SELECT `otherUserLinks`.userId
                      FROM `lkusergroup`
                        INNER JOIN `group`
                        ON `group`.groupId = `lkusergroup`.groupId
                            AND `group`.isUserSpecific = 0
                        INNER JOIN `lkusergroup` `otherUserLinks`
                        ON `otherUserLinks`.groupId = `group`.groupId
                     WHERE `lkusergroup`.userId = :currentUserId
                )
            ';
            $params['currentUserId'] = $this->getUser()->userId;
        }

        // Filter by userId
        if ($this->getSanitizer()->getInt('userId') !== null) {
            $body .= ' AND user.userId = :userId ';
            $params['userId'] = $this->getSanitizer()->getInt('userId');
        }

        // Filter by groupId
        if ($this->getSanitizer()->getInt('groupId') !== null) {
            $body .= ' AND user.userId IN (SELECT userId FROM `lkusergroup` WHERE groupId = :groupId) ';
            $params['groupId'] = $this->getSanitizer()->getInt('groupId');
        }

        $body .= $permissions;
        $body .= '            
            GROUP BY `user`.userId,
              `user`.userName
        ';


        // Sorting?
        $filterBy = $this->gridRenderFilter();
        $sortOrder = $this->gridRenderSort();

        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;
        $rows = [];

        foreach ($this->store->select($sql, $params) as $row) {
            $entry = [];

            $entry['userId'] = $this->getSanitizer()->int($row['userId']);
            $entry['userName'] = $this->getSanitizer()->string($row['userName']);
            $entry['bytesUsed'] = $this->getSanitizer()->int($row['bytesUsed']);
            $entry['bytesUsedFormatted'] = ByteFormatter::format($this->getSanitizer()->int($row['bytesUsed']), 2);
            $entry['numFiles'] = $this->getSanitizer()->int($row['numFiles']);

            $rows[] = $entry;
        }

        // Paging
        if ($limit != '' && count($rows) > 0) {
            $results = $this->store->select('SELECT COUNT(*) AS total FROM `user` ' . $permissions, $params);
            $this->getState()->recordsTotal = intval($results[0]['total']);
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function timeDisconnectedGrid()
    {
        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getSanitizer()->getDate('availabilityFromDt'));
        $toDt = $this->getSanitizer()->getDate('toDt', $this->getSanitizer()->getDate('availabilityToDt'));

        $displayId = $this->getSanitizer()->getInt('displayId');
        $displayGroupId = $this->getSanitizer()->getInt('displayGroupId');
        $tags = $this->getSanitizer()->getString('tags');
        $onlyLoggedIn = $this->getSanitizer()->getCheckbox('onlyLoggedIn') == 1;

        $currentDate = $this->getDate()->parse()->startOfDay()->format('Y-m-d');

        // fromDt is always start of selected day
        $fromDt = $this->getDate()->parse($fromDt)->startOfDay();

        // If toDt is current date then make it current datetime
        if ($this->getDate()->parse($toDt)->startOfDay()->format('Y-m-d') == $currentDate) {
            $toDt = $this->getDate()->parse();
        } else {
            $toDt = $this->getDate()->parse()->startOfDay();
        }

        // Get an array of display id this user has access to.
        $displayIds = [];

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

        // Get an array of display groups this user has access to
        $displayGroupIds = [];

        foreach ($this->displayGroupFactory->query(null, ['isDisplaySpecific' => -1]) as $displayGroup) {
            $displayGroupIds[] = $displayGroup->displayGroupId;
        }

        if (count($displayGroupIds) <= 0)
            throw new InvalidArgumentException(__('No display groups with View permissions'), 'displayGroup');

        $params = array(
            'start' => $fromDt->format('U'),
            'end' => $toDt->format('U')
        );

        $select = '
            SELECT display.display, display.displayId,
            SUM(LEAST(IFNULL(`end`, :end), :end) - GREATEST(`start`, :start)) AS duration,
            :end - :start as filter ';

        if ($tags != '') {
            $select .= ', (SELECT GROUP_CONCAT(DISTINCT tag)
              FROM tag
                INNER JOIN lktagdisplaygroup
                  ON lktagdisplaygroup.tagId = tag.tagId
                WHERE lktagdisplaygroup.displayGroupId = displaygroup.DisplayGroupID
                GROUP BY lktagdisplaygroup.displayGroupId) AS tags ';
        }

        $body = 'FROM `displayevent`
                INNER JOIN `display`
                ON display.displayId = `displayevent`.displayId ';

        if ($displayGroupId != 0) {
            $body .= 'INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayID = display.displayid ';
        }

        if ($tags != '') {
            $body .= 'INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayID = display.displayid
                     INNER JOIN `displaygroup`
                        ON displaygroup.displaygroupId = lkdisplaydg.displaygroupId
                         AND `displaygroup`.isDisplaySpecific = 1 ';
        }

        $body .= 'WHERE `start` <= :end
                  AND IFNULL(`end`, :end) >= :start
                  AND :end <= UNIX_TIMESTAMP(NOW())
                  AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayGroupId != 0) {
            $body .= '
                     AND lkdisplaydg.displaygroupid = :displayGroupId ';
            $params['displayGroupId'] = $displayGroupId;
        }

        if ($tags != '') {
            if (trim($tags) === '--no-tag') {
                $body .= ' AND `displaygroup`.displaygroupId NOT IN (
                    SELECT `lktagdisplaygroup`.displaygroupId
                     FROM tag
                        INNER JOIN `lktagdisplaygroup`
                        ON `lktagdisplaygroup`.tagId = tag.tagId
                    )
                ';
            } else {
                $operator = $this->getSanitizer()->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';

                $body .= " AND `displaygroup`.displaygroupId IN (
                SELECT `lktagdisplaygroup`.displaygroupId
                  FROM tag
                    INNER JOIN `lktagdisplaygroup`
                    ON `lktagdisplaygroup`.tagId = tag.tagId
                ";
                $i = 0;

                foreach (explode(',', $tags) as $tag) {
                    $i++;

                    if ($i == 1)
                        $body .= ' WHERE `tag` ' . $operator . ' :tags' . $i;
                    else
                        $body .= ' OR `tag` ' . $operator . ' :tags' . $i;

                    if ($operator === '=')
                        $params['tags' . $i] = $tag;
                    else
                        $params['tags' . $i] = '%' . $tag . '%';
                }

                $body .= " ) ";
            }
        }

        if ($displayId != 0) {
            $body .= ' AND display.displayId = :displayId ';
            $params['displayId'] = $displayId;
        }

        if ($onlyLoggedIn) {
            $body .= ' AND `display`.loggedIn = 1 ';
        }

        $body .= '
            GROUP BY display.display
        ';

        // Sorting?
        $filterBy = $this->gridRenderFilter();
        $sortOrder = $this->gridRenderSort();

        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';

        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }


        $sql = $select . $body . $order . $limit;
        $maxDuration = 0;
        $rows = [];

        foreach ($this->store->select($sql, $params) as $row) {
            $maxDuration = $maxDuration + $this->getSanitizer()->double($row['duration']);
        }

        if ($maxDuration > 86400) {
            $postUnits = __('Days');
            $divisor = 86400;
        }
        else if ($maxDuration > 3600) {
            $postUnits = __('Hours');
            $divisor = 3600;
        }
        else {
            $postUnits = __('Minutes');
            $divisor = 60;
        }

        foreach ($this->store->select($sql, $params) as $row) {
            $entry = [];
            $entry['displayId'] = $this->getSanitizer()->int(($row['displayId']));
            $entry['display'] = $this->getSanitizer()->string(($row['display']));
            $entry['timeDisconnected'] =  round($this->getSanitizer()->double($row['duration']) / $divisor, 2);
            $entry['timeConnected'] =  round($this->getSanitizer()->double($row['filter'] / $divisor) - $entry['timeDisconnected'], 2);
            $entry['postUnits'] = $postUnits;

            $rows[] = $entry;
        }

        // Paging
        if ($limit != '' && count($rows) > 0) {
            $results = $this->store->select($select . $body, $params);
            $this->getState()->recordsTotal = count($results);
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);
    }
}
