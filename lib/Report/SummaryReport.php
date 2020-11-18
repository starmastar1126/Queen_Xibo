<?php

namespace Xibo\Report;

use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;
use Psr\Container\ContainerInterface;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Helper\Translate;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\ReportServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class SummaryReport
 * @package Xibo\Report
 */
class SummaryReport implements ReportInterface
{

    use ReportTrait;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var SavedReportFactory
     */
    private $savedReportFactory;

    /**
     * @var ReportServiceInterface
     */
    private $reportService;

    private $table = 'stat';

    private $periodTable = 'period';

    /**
     * Report Constructor.
     * @param \Xibo\Helper\ApplicationState $state
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param SanitizerService $sanitizer
     */
    public function __construct($state, $store, $timeSeriesStore, $log, $config, $sanitizer)
    {
        $this->setCommonDependencies($state, $store, $timeSeriesStore, $log, $config, $sanitizer);
    }

    /** @inheritDoc */
    public function setFactories(ContainerInterface $container)
    {

        $this->displayFactory = $container->get('displayFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->savedReportFactory = $container->get('savedReportFactory');
        $this->reportService = $container->get('reportService');
        return $this;
    }

    /** @inheritdoc */
    public function getReportEmailTemplate()
    {
        return 'summary-email-template.twig';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return [
            'template' => 'summary-report-form',
            'data' =>  [
                'fromDate' => Carbon::now()->subSeconds(86400 * 35)->format(DateFormatHelper::getSystemFormat()),
                'fromDateOneDay' => Carbon::now()->subSeconds(86400)->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
                'availableReports' => $this->reportService->listReports()

            ]
        ];
    }

    /** @inheritdoc */
    public function getReportScheduleFormData(Request $request)
    {
        $type = $request->getParam('type', '');

        if ($type == 'layout') {
            $selectedId = $request->getParam('layoutId', null);
            $title = __('Add Report Schedule for '). $type. ' - '.
                $this->layoutFactory->getById($selectedId)->layout;

        } else if ($type == 'media') {
            $selectedId = $request->getParam('mediaId', null);
            $title = __('Add Report Schedule for '). $type. ' - '.
                $this->mediaFactory->getById($selectedId)->name;

        } else if ($type == 'event') {
            $selectedId = 0; // we only need eventTag
            $eventTag = $request->getParam('eventTag', null);
            $title = __('Add Report Schedule for '). $type. ' - '. $eventTag;

        }

        $data = ['filters' => []];

        $data['filters'][] = ['name'=> 'Daily', 'filter'=> 'daily'];
        $data['filters'][] = ['name'=> 'Weekly', 'filter'=> 'weekly'];
        $data['filters'][] = ['name'=> 'Monthly', 'filter'=> 'monthly'];
        $data['filters'][] = ['name'=> 'Yearly', 'filter'=> 'yearly'];

        $data['formTitle'] = $title;

        $data['hiddenFields'] =  json_encode([
            'type' => $type,
            'selectedId' => (int) $selectedId,
            'eventTag' => isset($eventTag) ? $eventTag : null
        ]);

        $data['reportName'] = 'summaryReport';

        return [
            'template' => 'summary-report-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData(Request $request)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $filter = $sanitizedParams->getString('filter');

        $hiddenFields = json_decode($sanitizedParams->getString('hiddenFields'), true);

        $type = $hiddenFields['type'];
        $selectedId = $hiddenFields['selectedId'];
        $eventTag = $hiddenFields['eventTag'];

        $filterCriteria['type'] = $type;
        if ($type == 'layout') {
            $filterCriteria['layoutId'] = $selectedId;
        } else if ($type == 'media') {
            $filterCriteria['mediaId'] = $selectedId;
        } else if ($type == 'event') {
            $filterCriteria['eventTag'] = $eventTag;
        }

        $filterCriteria['filter'] = $filter;

        $schedule = '';
        if ($filter == 'daily') {
            $schedule = ReportSchedule::$SCHEDULE_DAILY;
            $filterCriteria['reportFilter'] = 'yesterday';

        } else if ($filter == 'weekly') {
            $schedule = ReportSchedule::$SCHEDULE_WEEKLY;
            $filterCriteria['reportFilter'] = 'lastweek';

        } else if ($filter == 'monthly') {
            $schedule = ReportSchedule::$SCHEDULE_MONTHLY;
            $filterCriteria['reportFilter'] = 'lastmonth';
            $filterCriteria['groupByFilter'] = 'byweek';

        } else if ($filter == 'yearly') {
            $schedule = ReportSchedule::$SCHEDULE_YEARLY;
            $filterCriteria['reportFilter'] = 'lastyear';
            $filterCriteria['groupByFilter'] = 'bymonth';
        }

        $filterCriteria['sendEmail'] = $sanitizedParams->getCheckbox('sendEmail');
        $filterCriteria['nonusers'] = $sanitizedParams->getString('nonusers');

        // Return
        return [
            'filterCriteria' => json_encode($filterCriteria),
            'schedule' => $schedule
        ];
    }

    /** @inheritdoc */
    public function generateSavedReportName($filterCriteria)
    {

        if ($filterCriteria['type'] == 'layout') {
            try {
                $layout = $this->layoutFactory->getById($filterCriteria['layoutId']);

            } catch (NotFoundException $error) {

                // Get the campaign ID
                $campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($filterCriteria['layoutId']);
                $layoutId = $this->layoutFactory->getLatestLayoutIdFromLayoutHistory($campaignId);
                $layout = $this->layoutFactory->getById($layoutId);

            }
            $saveAs = sprintf(__('%s report for Layout %s', ucfirst($filterCriteria['filter']), $layout->layout));

        } else if ($filterCriteria['type'] == 'media') {
            try {
                $media = $this->mediaFactory->getById($filterCriteria['mediaId']);
                $saveAs = sprintf(__('%s report for Media ', ucfirst($filterCriteria['filter']), $media->name));

            } catch (NotFoundException $error) {
                $saveAs = __('Media not found');
            }

        } else if ($filterCriteria['type'] == 'event') {
            $saveAs = sprintf(__('%s report for Event %s', ucfirst($filterCriteria['filter']), $filterCriteria['eventTag']));
        }

        return $saveAs;
    }

    /** @inheritdoc */
    public function getReportChartScript($results)
    {
        return json_encode($results['results']['chart']);
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport)
    {
        // Return data to build chart
        return array_merge($json, [
            'template' => 'summary-report-preview',
            'savedReport' => $savedReport,
            'generatedOn' => Carbon::createFromTimestamp($savedReport->generatedOn)->format(DateFormatHelper::getSystemFormat())
        ]);
    }

    /** @inheritDoc */
    public function getResults($filterCriteria)
    {
        $this->getLog()->debug('Filter criteria: '. json_encode($filterCriteria, JSON_PRETTY_PRINT));

        $sanitizedParams = $this->getSanitizer($filterCriteria);

        $type = strtolower($sanitizedParams->getString('type'));
        $layoutId = $sanitizedParams->getInt('layoutId');
        $mediaId = $sanitizedParams->getInt('mediaId');
        $eventTag = $sanitizedParams->getString('eventTag');
        // Get an array of display id this user has access to.
        $displayIds = [];

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0) {
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');
        }

        //
        // From and To Date Selection
        // --------------------------
        // Our report has a range filter which determins whether or not the user has to enter their own from / to dates
        // check the range filter first and set from/to dates accordingly.
        $reportFilter = $sanitizedParams->getString('reportFilter');

        // Use the current date as a helper
        $now = Carbon::now();

        switch ($reportFilter) {

            case 'today':
                $fromDt = $now->copy()->startOfDay();
                $toDt = $fromDt->copy()->addDay();
                $groupByFilter = 'byhour';
                break;

            case 'yesterday':
                $fromDt = $now->copy()->startOfDay()->subDay();
                $toDt = $now->copy()->startOfDay();
                $groupByFilter = 'byhour';
                break;

            case 'thisweek':
                $fromDt = $now->copy()->locale(Translate::GetLocale())->startOfWeek();
                $toDt = $fromDt->copy()->addWeek();
                $groupByFilter = 'byday';
                break;

            case 'thismonth':
                $fromDt = $now->copy()->startOfMonth();
                $toDt = $fromDt->copy()->addMonth();

                // User can pick their own group by filter when they provide a manual range
                $groupByFilter = $sanitizedParams->getString('groupByFilter');
                break;

            case 'thisyear':
                $fromDt = $now->copy()->startOfYear();
                $toDt = $fromDt->copy()->addYear();

                // User can pick their own group by filter when they provide a manual range
                $groupByFilter = $sanitizedParams->getString('groupByFilter');
                break;

            case 'lastweek':
                $fromDt = $now->copy()->locale(Translate::GetLocale())->startOfWeek()->subWeek();
                $toDt = $fromDt->copy()->addWeek();
                $groupByFilter = 'byday';
                break;

            case 'lastmonth':
                $fromDt = $now->copy()->startOfMonth()->subMonth();
                $toDt = $fromDt->copy()->addMonth();

                // User can pick their own group by filter when they provide a manual range
                $groupByFilter = $sanitizedParams->getString('groupByFilter');
                break;

            case 'lastyear':
                $fromDt = $now->copy()->startOfYear()->subYear();
                $toDt = $fromDt->copy()->addYear();

                // User can pick their own group by filter when they provide a manual range
                $groupByFilter = $sanitizedParams->getString('groupByFilter');
                break;

            case '':
            default:
                // Expect dates to be provided.
                $fromDt = $sanitizedParams->getDate('statsFromDt', ['default' => Carbon::now()->subDay()]);
                $fromDt->startOfDay();

                $toDt = $sanitizedParams->getDate('statsToDt', ['default' =>  Carbon::now()]);
                $toDt->addDay()->startOfDay();

                // What if the fromdt and todt are exactly the same?
                // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
                if ($fromDt == $toDt) {
                    $toDt->addDay();
                }

                // User can pick their own group by filter when they provide a manual range
                $groupByFilter = $sanitizedParams->getString('groupByFilter');

                break;
        }

        //
        // Get Results!
        // -------------
        $timeSeriesStore = $this->getTimeSeriesStore()->getEngine();
        $this->getLog()->debug('Timeseries store is ' . $timeSeriesStore);

        if ($timeSeriesStore == 'mongodb') {
            $result = $this->getSummaryReportMongoDb($fromDt, $toDt, $groupByFilter, $displayIds, $type, $layoutId, $mediaId, $eventTag,  $reportFilter);
        } else {
            $result = $this->getSummaryReportMySql($fromDt, $toDt, $groupByFilter, $displayIds, $type, $layoutId, $mediaId, $eventTag);
        }

        //
        // Output Results
        // --------------
        $labels = [];
        $countData = [];
        $durationData = [];
        $backgroundColor = [];
        $borderColor = [];

        // Summary report result in chart
        if (count($result) > 0) {
            foreach ($result['result'] as $row) {
                // Label
                $labels[] = $row['label'];

                $backgroundColor[] = 'rgb(95, 186, 218, 0.6)';
                $borderColor[] = 'rgb(240,93,41, 0.8)';

                $count = $this->getSanitizer($row)->getInt('NumberPlays');
                $countData[] = ($count == '') ? 0 : $count;

                $duration = $this->getSanitizer($row)->getInt('Duration');
                $durationData[] = ($duration == '') ? 0 : $duration;
            }
        }

        // Return data to build chart
        // this is my structure which gets saved
        return [
            'hasData' => count($durationData) > 0 && count($countData) > 0,
            'chart' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => __('Total duration'),
                            'yAxisID' => 'Duration',
                            'backgroundColor' => $backgroundColor,
                            'data' => $durationData
                        ],
                        [
                            'label' => __('Total count'),
                            'yAxisID' => 'Count',
                            'borderColor' => $borderColor,
                            'type' => 'line',
                            'fill' => false,
                            'data' =>  $countData
                        ]
                    ]
                ],
                'options' => [
                    'scales' => [
                        'yAxes' => [
                            [
                                'id' => 'Duration',
                                'type' => 'linear',
                                'position' =>  'left',
                                'display' =>  true,
                                'scaleLabel' =>  [
                                    'display' =>  true,
                                    'labelString' => __('Duration(s)')
                                ],
                                'ticks' =>  [
                                    'beginAtZero' => true
                                ]
                            ], [
                                'id' => 'Count',
                                'type' => 'linear',
                                'position' =>  'right',
                                'display' =>  true,
                                'scaleLabel' =>  [
                                    'display' =>  true,
                                    'labelString' => __('Count')
                                ],
                                'ticks' =>  [
                                    'beginAtZero' => true
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'periodStart' => Carbon::createFromTimestamp($fromDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => Carbon::createFromTimestamp($toDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
        ];
    }

    /**
     * MySQL summary report
     * @param Carbon $fromDt The filter range from date
     * @param Carbon $toDt The filter range to date
     * @param string $groupByFilter Grouping, byhour, byday, byweek, bymonth
     * @param $displayIds
     * @param $type
     * @param $layoutId
     * @param $mediaId
     * @param $eventTag
     * @return array
     */
    private function getSummaryReportMySql($fromDt, $toDt, $groupByFilter, $displayIds, $type, $layoutId, $mediaId, $eventTag)
    {
        // Only return something if we have the necessary options selected.
        if (
            (($type == 'media') && ($mediaId != ''))
            || (($type == 'layout') && ($layoutId != ''))
            || (($type == 'event') && ($eventTag != ''))
        ) {
            // Create periods covering the from/to dates
            // -----------------------------------------
            try {
                $periods = $this->getTemporaryPeriodsTable($fromDt, $toDt, $groupByFilter);
            } catch (InvalidArgumentException $invalidArgumentException) {
                return [];
            }

            // Join in stats
            // -------------
            $select = '                      
            SELECT start, end, periodsWithStats.id, MAX(periodsWithStats.label) AS label,
                SUM(count) as NumberPlays, 
                CONVERT(SUM(periodsWithStats.actualDiff), SIGNED INTEGER) as Duration
             FROM (
                SELECT
                     *,
                    GREATEST(periods.start, statStart, :fromDt) AS actualStart,
                    LEAST(periods.end, statEnd, :toDt) AS actualEnd,
                    LEAST(stat.duration, LEAST(periods.end, statEnd, :toDt) - GREATEST(periods.start, statStart, :fromDt)) AS actualDiff
                 FROM `' . $periods . '` AS periods
                    LEFT OUTER JOIN (
                        SELECT 
                            layout.Layout,
                            IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) AS Media,
                            stat.mediaId,
                            stat.`start` as statStart,
                            stat.`end` as statEnd,
                            stat.duration,
                            stat.`count`
                          FROM stat
                            LEFT OUTER JOIN layout
                            ON layout.layoutID = stat.layoutID
                            LEFT OUTER JOIN `widget`
                            ON `widget`.widgetId = stat.widgetId
                            LEFT OUTER JOIN `widgetoption`
                            ON `widgetoption`.widgetId = `widget`.widgetId
                                AND `widgetoption`.type = \'attrib\'
                                AND `widgetoption`.option = \'name\'
                            LEFT OUTER JOIN `media`
                            ON `media`.mediaId = `stat`.mediaId
                         WHERE stat.type <> \'displaydown\' 
                            AND stat.start < :toDt
                            AND stat.end >= :fromDt
            ';

            $params = [
                'fromDt' => $fromDt->format('U'),
                'toDt' => $toDt->format('U')
            ];

            // Displays
            if (count($displayIds) > 0) {
                $select .= ' AND stat.displayID IN (' . implode(',', $displayIds) . ') ';
            }

            // Type filter
            if (($type == 'layout') && ($layoutId != '')) {
                // Filter by Layout
                $select .= ' 
                    AND `stat`.type = \'layout\' 
                    AND `stat`.campaignId = (SELECT campaignId FROM layouthistory WHERE layoutId = :layoutId) 
                ';
                $params['layoutId'] = $layoutId;

            } elseif (($type == 'media') && ($mediaId != '')) {
                // Filter by Media
                $select .= '
                    AND `stat`.type = \'media\' AND IFNULL(`media`.mediaId, 0) <> 0 
                    AND `stat`.mediaId = :mediaId ';
                $params['mediaId'] = $mediaId;

            } elseif (($type == 'event') && ($eventTag != '')) {
                // Filter by Event
                $select .= '
                    AND `stat`.type = \'event\'  
                    AND `stat`.tag = :tag ';
                $params['tag'] = $eventTag;
            }

            $select .= ' 
                        ) stat               
                        ON statStart < periods.`end`
                            AND statEnd > periods.`start`
            ';

            // Periods and Stats tables are joined, we should only have periods we're interested in, but it
            // wont hurt to restrict them
            $select .= ' 
             WHERE periods.`start` < :toDt
                AND periods.`end` > :fromDt ';

            // Close out our containing view and group things together
            $select .= '
                ) periodsWithStats 
            GROUP BY periodsWithStats.id
            ORDER BY periodsWithStats.id
            ';

            return [
                'result' => $this->getStore()->select($select, $params),
                'periodStart' => $fromDt->format(DateFormatHelper::getSystemFormat()),
                'periodEnd' => $toDt->format(DateFormatHelper::getSystemFormat())
            ];

        } else {
            return [];
        }
    }

    /**
     * MongoDB summary report
     * @param Carbon $fromDt The filter range from date
     * @param Carbon $toDt The filter range to date
     * @param string $groupByFilter Grouping, byhour, byday, byweek, bymonth
     * @param $displayIds
     * @param $type
     * @param $layoutId
     * @param $mediaId
     * @param $eventTag
     * @param $reportFilter
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function getSummaryReportMongoDb($fromDt, $toDt, $groupByFilter, $displayIds, $type, $layoutId, $mediaId, $eventTag, $reportFilter)
    {
        if ( (($type == 'media') && ($mediaId != '')) ||
            (($type == 'layout') && ($layoutId != '')) ||
            (($type == 'event') && ($eventTag != '')) ) {

            $diffInDays = $toDt->diffInDays($fromDt);

            if ($groupByFilter == 'byhour') {
                $hour = 1;
                $input = range(0,  23);
            } elseif ($groupByFilter == 'byday') {
                $hour = 24;
                $input = range(0, $diffInDays - 1);
            } elseif ($groupByFilter == 'byweek') {
                $hour = 24 * 7;
                $input = range(0, ceil($diffInDays / 7));
            } elseif ($groupByFilter == 'bymonth') {
                $hour = 24;
                $input = range(0, ceil($diffInDays / 30));
            } else {
                $this->getLog()->error('Unknown Grouping Selected ' . $groupByFilter);
                throw new InvalidArgumentException(__('Unknown Grouping ') . $groupByFilter, 'groupByFilter');
            }

            $filterRangeStart = new UTCDateTime($fromDt->format('U') * 1000);
            $filterRangeEnd = new UTCDateTime($toDt->format('U') * 1000);

            // Extend the range
            if (($groupByFilter == 'byhour') || ($groupByFilter == 'byday')) {
                $extendedPeriodStart = $filterRangeStart;
                $extendedPeriodEnd = $filterRangeEnd;
            } elseif ($groupByFilter == 'byweek') {
                // Extend upto the start of the first week of the fromdt, and end of the week of the todt
                $startOfWeek = $fromDt->copy()->locale(Translate::GetLocale())->startOfWeek();
                $endOfWeek = $toDt->copy()->locale(Translate::GetLocale())->endOfWeek()->addSecond();
                $extendedPeriodStart = new UTCDateTime( $startOfWeek->format('U') * 1000);
                $extendedPeriodEnd = new UTCDateTime($endOfWeek->format('U') * 1000);
            } elseif ($groupByFilter == 'bymonth') {
                if ($reportFilter == '') {

                    // We extend the fromDt and toDt range filter
                    // so that we can generate each month period
                    $fromDtStartOfMonth = $fromDt->copy()->startOfMonth();
                    $toDtEndOfMonth = $toDt->copy()->endOfMonth()->addSecond();

                    // Generate all months that lie in the extended range
                    $monthperiods = [];
                    foreach ($input as $key => $value) {

                        $monthPeriodStart = $fromDtStartOfMonth->copy()->addMonth($key);
                        $monthPeriodEnd = $fromDtStartOfMonth->copy()->addMonth($key)->addMonth();

                        // Remove the month period which crossed the extended end range
                        if ($monthPeriodStart >= $toDtEndOfMonth) {
                            continue;
                        }
                        $monthperiods[$key]['start'] =  new UTCDateTime( $monthPeriodStart->format('U') * 1000);
                        $monthperiods[$key]['end'] =    new UTCDateTime( $monthPeriodEnd->format('U') * 1000);
                    }

                    $extendedPeriodStart = new UTCDateTime( $fromDtStartOfMonth->format('U') * 1000);
                    $extendedPeriodEnd = new UTCDateTime( $toDtEndOfMonth->format('U') * 1000);

                } elseif (($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) {
                    $extendedPeriodStart = $filterRangeStart;
                    $extendedPeriodEnd = $filterRangeEnd;

                    $start = $fromDt->copy()->subMonth()->startOfMonth();
                    $end = $fromDt->copy()->startOfMonth();

                    // Generate all 12 months
                    $monthperiods = [];
                    foreach ($input as $key => $value) {
                        $monthperiods[$key]['start'] = new UTCDateTime( $start->addMonth()->format('U') * 1000);
                        $monthperiods[$key]['end'] = new UTCDateTime( $end->addMonth()->format('U') * 1000);
                    }
                }
            }

            $this->getLog()->debug('Period start: '.$filterRangeStart->toDateTime()->format(DateFormatHelper::getSystemFormat()). ' Period end: '. $filterRangeEnd->toDateTime()->format(DateFormatHelper::getSystemFormat()));

            // Type filter
            if (($type == 'layout') && ($layoutId != '')) {

                // Get the campaign ID
                $campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($layoutId);

                $matchType = [
                    '$eq' => [ '$type', 'layout' ]
                ];
                $matchId = [
                    '$eq' => [ '$campaignId', $campaignId ]
                ];
            } elseif (($type == 'media') && ($mediaId != '')) {
                $matchType = [
                    '$eq' => [ '$type', 'media' ]
                ];
                $matchId = [
                    '$eq' => [ '$mediaId', $mediaId ]
                ];
            } elseif (($type == 'event') && ($eventTag != '')) {
                $matchType = [
                    '$eq' => [ '$type', 'event' ]
                ];
                $matchId = [
                    '$eq' => [ '$eventName', $eventTag ]
                ];
            }


            if ($groupByFilter == 'byweek') {

                // PERIOD GENERATION
                // Addition of 7 days from start
                $projectMap = [
                    '$project' => [
                        'periods' =>  [
                            '$map' => [
                                'input' => $input,
                                'as' => 'number',
                                'in' => [
                                    'start' => [
                                        '$add' => [
                                            $extendedPeriodStart,
                                            [
                                                '$multiply' => [
                                                    '$$number',
                                                    $hour * 3600000
                                                ]
                                            ]
                                        ]
                                    ],
                                    'end' => [
                                        '$add' => [
                                            [
                                                '$add' => [
                                                    $extendedPeriodStart,
                                                    $hour * 3600000
                                                ]
                                            ],
                                            [
                                                '$multiply' => [
                                                    '$$number',
                                                    $hour * 3600000
                                                ]
                                            ]
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ]
                ];

            } elseif ($groupByFilter == 'bymonth') {

                $projectMap = [
                    '$project' => [
                        'periods' => [
                            '$map' => [
                                'input' => $monthperiods,
                                'as' => 'number',
                                'in' => [
                                    'start' => '$$number.start',
                                    'end' => '$$number.end',
                                ]
                            ]
                        ]
                    ]
                ];

            } else {

                // PERIOD GENERATION
                // Addition of 1 day/hour from start
                $projectMap = [
                    '$project' => [
                        'periods' =>  [
                            '$map' => [
                                'input' => $input,
                                'as' => 'number',
                                'in' => [
                                    'start' => [
                                        '$add' => [
                                            $extendedPeriodStart,
                                            [
                                                '$multiply' => [
                                                    $hour * 3600000,
                                                    '$$number'
                                                ]
                                            ]
                                        ]
                                    ],
                                    'end' => [
                                        '$add' => [
                                            [
                                                '$add' => [
                                                    $extendedPeriodStart,
                                                    [
                                                        '$multiply' => [
                                                            $hour * 3600000,
                                                            '$$number'
                                                        ]
                                                    ]
                                                ]
                                            ]
                                            , $hour * 3600000
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ]
                ];
            }

            // GROUP BY
            $groupBy = [
                'period_start' => '$period_start',
                'period_end' => '$period_end'
            ];

            // PERIODS QUERY
            $cursorPeriodQuery = [

                $projectMap,

                // periods needs to be unwind to merge next
                [
                    '$unwind' => '$periods'
                ],

                // replace the root to eliminate _id and get only periods
                [
                    '$replaceRoot' => [
                        'newRoot' => '$periods'
                    ]
                ],

                [
                    '$project' => [
                        'start' => 1,
                        'end' => 1,
                    ]
                ],

                [
                    '$match' => [
                        'start' => [
                            '$lt' => $extendedPeriodEnd
                        ],
                        'end' => [
                            '$gt' => $extendedPeriodStart
                        ]
                    ]
                ],

            ];

            // Periods result
            $periods = $this->getTimeSeriesStore()->executeQuery(['collection' => $this->periodTable, 'query' => $cursorPeriodQuery]);

            // STAT AGGREGATION QUERY
            $statQuery = [

                [
                    '$match' => [
                        'start' =>  [
                            '$lt' => $filterRangeEnd
                        ],
                        'end' => [
                            '$gt' => $filterRangeStart
                        ],
                    ]
                ],

                [
                    '$lookup' => [
                        'from' => 'period',
                        'let' => [
                            'stat_start' => '$start',
                            'stat_end' => '$end',
                            'stat_duration' => '$duration',
                            'stat_count' => '$count',
                        ],
                        'pipeline' => [
                            $projectMap,
                            [
                                '$unwind' => '$periods'
                            ],

                        ],
                        'as' => 'statdata'
                    ]
                ],

                [
                    '$unwind' => '$statdata'
                ],

                [
                    '$match' => [
                        '$expr' => [
                            '$and' => [

                                // match media id / layout id
                                $matchType,
                                $matchId,

                                // display ids
                                [
                                    '$in' => [ '$displayId', $displayIds ]
                                ],

                                // stat.start < period end AND stat.end > period start
                                // for example, when report filter 'today' is selected
                                // where start is less than last hour of the day + 1 hour (i.e., nextday of today)
                                // and end is greater than or equal first hour of the day
                                [
                                    '$lt' => [ '$start', '$statdata.periods.end' ]
                                ],
                                [
                                    '$gt' => [ '$end', '$statdata.periods.start' ]
                                ],
                            ]
                        ]
                    ]
                ],

                [
                    '$project' => [
                        '_id' => 1,
                        'count' => 1,
                        'duration' => 1,
                        'start' => 1,
                        'end' => 1,
                        'period_start' => '$statdata.periods.start',
                        'period_end' => '$statdata.periods.end',
                        'monthNo' => [
                            '$month' =>  '$statdata.periods.start'
                        ],
                        'yearDate' => [
                            '$isoWeekYear' =>  '$statdata.periods.start'
                        ],
                        'weekNo' => [
                            '$isoWeek' =>  '$statdata.periods.start'
                        ],
                        'actualStart' => [
                            '$max' => [ '$start', '$statdata.periods.start', $filterRangeStart ]
                        ],
                        'actualEnd' => [
                            '$min' => [ '$end', '$statdata.periods.end', $filterRangeEnd ]
                        ],
                        'actualDiff' => [
                            '$min' => [
                                '$duration',
                                [
                                    '$divide' => [
                                        [
                                            '$subtract' => [
                                                ['$min' => [ '$end', '$statdata.periods.end', $filterRangeEnd ]],
                                                ['$max' => [ '$start', '$statdata.periods.start', $filterRangeStart ]]
                                            ]
                                        ], 1000
                                    ]
                                ]
                            ]
                        ],

                    ]

                ],

                [
                    '$group' => [
                        '_id' => $groupBy,
                        'period_start' => ['$first' => '$period_start'],
                        'period_end' => ['$first' => '$period_end'],
                        'NumberPlays' => ['$sum' => '$count'],
                        'Duration' => ['$sum' => '$actualDiff'],
                        'start' => ['$first' => '$start'],
                        'end' => ['$first' => '$end'],
                    ]
                ],

                [
                    '$project' => [
                        'start' => '$start',
                        'end' => '$end',
                        'period_start' => 1,
                        'period_end' => 1,
                        'NumberPlays' => 1,
                        'Duration' => 1,
                    ]
                ],

            ];

            // Stats result
            $results = $this->getTimeSeriesStore()->executeQuery(['collection' => $this->table, 'query' => $statQuery]);

            // Run period loop and map the stat result for each period
            $resultArray = [];

            foreach ($periods as $key => $period) {

                // UTC date format
                $period_start_u = $period['start']->toDateTime()->format('U');
                $period_end_u = $period['end']->toDateTime()->format('U');

                // CMS date
                $period_start = Carbon::createFromTimestamp($period_start_u);
                $period_end = Carbon::createFromTimestamp($period_end_u);
                
                if ($groupByFilter == 'byhour'){
                    $label = $period_start->format('g:i A');
                } elseif ($groupByFilter == 'byday') {
                    if ( ($reportFilter == 'thisweek') || ($reportFilter == 'lastweek') ) {
                        $label = $period_start->format('D');
                    } else {
                        $label = $period_start->format('Y-m-d');
                    }
                } elseif ($groupByFilter == 'byweek') {
                    $weekstart = $period_start->format('M d');
                    $weekend = $period_end->format('M d');
                    $weekno = $period_start->locale(Translate::GetLocale())->week();

                    if ($period_start_u < $fromDt->copy()->format('U')) {
                        $weekstart = $fromDt->copy()->format('M-d');
                    }

                    if ($period_end_u > $toDt->copy()->format('U')) {
                        $weekend = $toDt->copy()->format('M-d');
                    }
                    $label = $weekstart . ' - ' . $weekend . ' (w' . $weekno . ')';
                } elseif ($groupByFilter == 'bymonth') {
                    $label = $period_start->format('M');
                    if ($reportFilter == '') {
                        $label .= ' ' .$period_start->format('Y');
                    }
                } else {
                    $label = 'N/A';
                }

                $matched = false;
                foreach ($results as $k => $result) {
                    if( $result['period_start'] == $period['start'] ) {
                        $NumberPlays = $result['NumberPlays'];
                        $Duration = $result['Duration'];
                        $matched = true;
                        break;
                    }
                }

                // Chart label
                $resultArray[$key]['label'] = $label;
                if($matched == true) {
                    $resultArray[$key]['NumberPlays'] = $NumberPlays;
                    $resultArray[$key]['Duration'] = $Duration;
                } else {
                    $resultArray[$key]['NumberPlays'] = 0;
                    $resultArray[$key]['Duration'] = 0;
                }
            }

            return [
                'result' => $resultArray,
                'periodStart' => $fromDt->format(DateFormatHelper::getSystemFormat()),
                'periodEnd' => $toDt->format(DateFormatHelper::getSystemFormat())
            ];

        } else {
            return [];
        }
    }

    /** @inheritDoc */
    public function restructureSavedReportOldJson($result)
    {
        $durationData = $result['durationData'];
        $countData = $result['countData'];
        $labels = $result['labels'];
        $backgroundColor = $result['backgroundColor'];
        $borderColor = $result['borderColor'];
        $periodStart = $result['periodStart'];
        $periodEnd = $result['periodEnd'];

        // Return data to build chart
        // this is my structure which gets saved
        return [
            'hasData' => count($durationData) > 0 && count($countData) > 0,
            'chart' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => __('Total duration'),
                            'yAxisID' => 'Duration',
                            'backgroundColor' => $backgroundColor,
                            'data' => $durationData
                        ],
                        [
                            'label' => __('Total count'),
                            'yAxisID' => 'Count',
                            'borderColor' => $borderColor,
                            'type' => 'line',
                            'fill' => false,
                            'data' =>  $countData
                        ]
                    ]
                ],
                'options' => [
                    'scales' => [
                        'yAxes' => [
                            [
                                'id' => 'Duration',
                                'type' => 'linear',
                                'position' =>  'left',
                                'display' =>  true,
                                'scaleLabel' =>  [
                                    'display' =>  true,
                                    'labelString' => __('Duration(s)')
                                ],
                                'ticks' =>  [
                                    'beginAtZero' => true
                                ]
                            ], [
                                'id' => 'Count',
                                'type' => 'linear',
                                'position' =>  'right',
                                'display' =>  true,
                                'scaleLabel' =>  [
                                    'display' =>  true,
                                    'labelString' => __('Count')
                                ],
                                'ticks' =>  [
                                    'beginAtZero' => true
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
        ];
    }
}