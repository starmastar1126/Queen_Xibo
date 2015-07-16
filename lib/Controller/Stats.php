<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2014 Daniel Garner
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

use Xibo\Factory\DisplayFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;
use Xibo\Storage\PDOConnect;


class Stats extends Base
{
    /**
     * Stats page
     */
    function displayPage()
    {
        $data = [
            // List of Displays this user has permission for
            'displays' => DisplayFactory::query(),
            // List of Media this user has permission for
            'media' => MediaFactory::query(),
            'defaults' => [
                'fromDate' => Date::getLocalDate(time() - (86400 * 35), 'Y-m-d'),
                'fromDateOneDay' => Date::getLocalDate(time() - 86400, 'Y-m-d'),
                'toDate' => Date::getLocalDate(null, 'Y-m-d')
            ]
        ];

        $this->getState()->template = 'statistics-page';
        $this->getState()->setData($data);
    }

    /**
     * Shows the stats grid
     */
    public function grid()
    {
        $fromDt = Date::getTimestampFromString(Sanitize::getString('fromdt'));
        $toDt = Date::getTimestampFromString(Sanitize::getString('todt'));
        $displayId = Sanitize::getInt('displayid');
        $mediaId = Sanitize::getInt('mediaid');

        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt == $toDt) {
            $toDt = date("Y-m-d", strtotime($toDt) + 86399);
        }

        Log::debug('Converted Times received are: FromDt=' . $fromDt . '. ToDt=' . $toDt);

        // Get an array of display id this user has access to.
        $display_ids = array();

        foreach (DisplayFactory::query() as $display) {
            $display_ids[] = $display->displayId;
        }

        if (count($display_ids) <= 0)
            trigger_error(__('No displays with View permissions'), E_USER_ERROR);

        // Media on Layouts Ran
        $sql = '
          SELECT stat.type,
              display.Display,
              layout.Layout,
              IFNULL(widgetoption.value, widget.type) AS Name,
              COUNT(StatID) AS NumberPlays,
              SUM(TIME_TO_SEC(TIMEDIFF(end, start))) AS Duration,
              MIN(start) AS MinStart,
              MAX(end) AS MaxEnd
            FROM stat
              INNER JOIN display
              ON stat.DisplayID = display.DisplayID
              INNER JOIN layout
              ON layout.LayoutID = stat.LayoutID
              INNER JOIN `widget`
              ON `widget`.widgetId = stat.MediaID
              LEFT OUTER JOIN `widgetoption`
              ON `widgetoption`.widgetId = `widget`.widgetId
                AND `widgetoption`.type = \'attribute\'
                AND `widgetoption`.option = \'name\'
           WHERE stat.type <> \'displaydown\'
                AND stat.end > :fromDt
                AND stat.start <= :toDt
                AND stat.displayID IN (' . implode(',', $display_ids) . ')
        ';

        $params = [
            'fromDt' => $fromDt,
            'toDt' => $toDt
        ];

        if ($mediaId != 0) {
            $sql .= '  AND widget.widgetId IN (SELECT widgetId FROM `lkwidgetmedia` WHERE mediaId =  :mediaId) ';
            $params['mediaId'] = $mediaId;
        }

        if ($displayId != 0) {
            $sql .= '  AND stat.displayID = :displayId ';
            $params['displayId'] = $displayId;
        }

        $sql .= '
            GROUP BY stat.type, display.Display, layout.Layout, IFNULL(widgetoption.value, widget.type)
            ORDER BY stat.type, display.Display, layout.Layout, IFNULL(widgetoption.value, widget.type)
        ';

        $rows = array();

        foreach (PDOConnect::select($sql, $params) as $row) {
            $row['type'] = Sanitize::string($row['type']);
            $row['display'] = Sanitize::string($row['Display']);
            $row['layout'] = Sanitize::string($row['Layout']);
            $row['media'] = Sanitize::string($row['Name']);
            $row['numberPlays'] = Sanitize::int($row['NumberPlays']);
            $row['duration'] = Sanitize::int($row['Duration']);
            $row['minStart'] = Date::getLocalDate(Date::getTimestampFromTimeString(Sanitize::getString($row['MinStart'])));
            $row['maxEnd'] = Date::getLocalDate(Date::getTimestampFromTimeString(Sanitize::getString($row['MaxEnd'])));

            $rows[] = $row;
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);
    }

    public function availabilityData()
    {
        $fromDt = Date::getTimestampFromString(Sanitize::getString('fromdt'));
        $toDt = Date::getTimestampFromString(Sanitize::getString('todt'));
        $displayId = Sanitize::getInt('displayid');

        // Get an array of display id this user has access to.
        $displayIds = array();

        foreach (DisplayFactory::query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            trigger_error(__('No displays with View permissions'), E_USER_ERROR);

        // Get some data for a bandwidth chart
        $dbh = PDOConnect::init();

        $params = array(
            'type' => 'displaydown',
            'start' => date('Y-m-d h:i:s', $fromDt),
            'boundaryStart' => date('Y-m-d h:i:s', $fromDt),
            'end' => date('Y-m-d h:i:s', $toDt),
            'boundaryEnd' => date('Y-m-d h:i:s', $toDt)
        );

        $SQL = '
            SELECT display.display,
                SUM(TIME_TO_SEC(TIMEDIFF(LEAST(end, :boundaryEnd), GREATEST(start, :boundaryStart)))) AS duration
              FROM `stat`
                INNER JOIN `display`
                ON display.displayId = stat.displayId
             WHERE start <= :end
                AND end >= :start
                AND type = :type
                AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayId != 0) {
            $SQL .= ' AND display.displayId = :displayId ';
            $params['displayId'] = $displayId;
        }

        $SQL .= '
            GROUP BY display.display
        ';

        Log::sql($SQL, $params);

        $sth = $dbh->prepare($SQL);

        $sth->execute($params);

        $output = array();

        foreach ($sth->fetchAll() as $row) {

            $output[] = array(
                'label' => Sanitize::string($row['display']),
                'value' => (Sanitize::double($row['duration']) / 60)
            );
        }

        $this->getState()->extra = [
            'data' => $output
        ];
    }

    public function bandwidthData()
    {
        $fromDt = Date::getTimestampFromString(Sanitize::getString('fromdt'));
        $toDt = Date::getTimestampFromString(Sanitize::getString('todt'));

        // Get an array of display id this user has access to.
        $displayIds = array();

        foreach (DisplayFactory::query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            trigger_error(__('No displays with View permissions'), E_USER_ERROR);

        // Get some data for a bandwidth chart
        $dbh = PDOConnect::init();

        $displayId = Sanitize::getInt('displayid');
        $params = array(
            'month' => $fromDt,
            'month2' => $toDt
        );

        $SQL = 'SELECT display.display, IFNULL(SUM(Size), 0) AS size ';

        if ($displayId != 0)
            $SQL .= ', bandwidthtype.name AS type ';

        $SQL .= ' FROM `bandwidth`
                INNER JOIN `display`
                ON display.displayid = bandwidth.displayid';

        if ($displayId != 0)
            $SQL .= '
                    INNER JOIN bandwidthtype
                    ON bandwidthtype.bandwidthtypeid = bandwidth.type
                ';

        $SQL .= '  WHERE month > :month
                AND month < :month2
                AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayId != 0) {
            $SQL .= ' AND display.displayid = :displayid ';
            $params['displayid'] = $displayId;
        }

        $SQL .= 'GROUP BY display.display ';

        if ($displayId != 0)
            $SQL .= ' , bandwidthtype.name ';

        $SQL .= 'ORDER BY display.display';

        //Log::debug($SQL . '. Params = ' . var_export($params, true), get_class(), __FUNCTION__);

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

        $output = array();

        foreach ($results as $row) {

            // label depends whether we are filtered by display
            if ($displayId != 0) {
                $label = $row['type'];
            } else {
                $label = $row['display'];
            }

            $output[] = array(
                'label' => $label,
                'value' => round((double)$row['size'] / (pow(1024, $base)), 2)
            );
        }

        // Set up some suffixes
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');

        $this->getState()->extra = [
            'data' => $output,
            'postUnits' => (isset($suffixes[$base]) ? $suffixes[$base] : '')
        ];
    }

    public function outputCsvForm()
    {
        $response = $this->getState();

        Theme::Set('form_id', 'OutputCsvForm');
        Theme::Set('form_action', 'index.php?p=stats&q=OutputCSV');

        $formFields = array();
        $formFields[] = Form::AddText('fromdt', __('From Date'), Date::getLocalDate(time() - (86400 * 35), 'Y-m-d'), NULL, 'f');
        $formFields[] = Form::AddText('todt', __('To Date'), Date::getLocalDate(null, 'Y-m-d'), NULL, 't');

        // List of Displays this user has permission for
        $formFields[] = Form::AddCombo(
            'displayid',
            __('Display'),
            NULL,
            DisplayFactory::query(),
            'displayid',
            'displaygroup',
            NULL,
            'd');

        Theme::Set('header_text', __('Bandwidth'));
        Theme::Set('form_fields', $formFields);
        Theme::Set('form_class', 'XiboManualSubmit');

        $response->SetFormRequestResponse(NULL, __('Export Statistics'), '550px', '275px');
        $response->AddButton(__('Export'), '$("#OutputCsvForm").submit()');
        $response->AddButton(__('Close'), 'XiboDialogClose()');

    }

    /**
     * Outputs a CSV of stats
     * @return
     */
    public function OutputCSV()
    {
        $db =& $this->db;
        $output = '';

        // We are expecting some parameters
        $fromdt = Date::getIsoDateFromString(Kit::GetParam('fromdt', _POST, _STRING));
        $todt = Date::getIsoDateFromString(Kit::GetParam('todt', _POST, _STRING));
        $displayID = Sanitize::getInt('displayid');

        if ($fromdt == $todt) {
            $todt = date("Y-m-d", strtotime($todt) + 86399);
        }

        // We want to output a load of stuff to the browser as a text file.
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="stats.csv"');
        header("Content-Transfer-Encoding: binary");
        header('Accept-Ranges: bytes');

        // Get an array of display id this user has access to.
        $display_ids = array();

        foreach (DisplayFactory::query() as $display) {
            $display_ids[] = $display['displayid'];
        }

        if (count($display_ids) <= 0) {
            echo __('No displays with View permissions');
            exit;
        }

        $SQL = 'SELECT stat.*, display.Display, layout.Layout, media.Name AS MediaName ';
        $SQL .= '  FROM stat ';
        $SQL .= '  INNER JOIN display ON stat.DisplayID = display.DisplayID ';
        $SQL .= '  LEFT OUTER JOIN layout ON layout.LayoutID = stat.LayoutID ';
        $SQL .= '  LEFT OUTER JOIN media ON media.mediaID = stat.mediaID ';
        $SQL .= ' WHERE 1=1 ';
        $SQL .= sprintf("  AND stat.end > '%s' ", $fromdt);
        $SQL .= sprintf("  AND stat.start <= '%s' ", $todt);

        $SQL .= ' AND stat.displayID IN (' . implode(',', $display_ids) . ') ';

        if ($displayID != 0) {
            $SQL .= sprintf("  AND stat.displayID = %d ", $displayID);
        }

        $SQL .= " ORDER BY stat.start ";

        Log::notice($SQL, 'Stats', 'OutputCSV');

        if (!$result = $db->query($SQL)) {
            trigger_error($db->error());
            trigger_error('Failed to query for Stats.', E_USER_ERROR);
        }

        // Header row
        $output .= "Type, FromDT, ToDT, Layout, Display, Media, Tag\n";

        while ($row = $db->get_assoc_row($result)) {
            // Read the columns
            $type = Sanitize::string($row['Type']);
            $fromdt = Sanitize::string($row['start']);
            $todt = Sanitize::string($row['end']);
            $layout = Sanitize::string($row['Layout']);
            $display = Sanitize::string($row['Display']);
            $media = Sanitize::string($row['MediaName']);
            $tag = Sanitize::string($row['Tag']);

            $output .= "$type, $fromdt, $todt, $layout, $display, $media, $tag\n";
        }

        //Log::debug('Output: ' . $output, 'Stats', 'OutputCSV');

        echo $output;
        exit;
    }
}

?>