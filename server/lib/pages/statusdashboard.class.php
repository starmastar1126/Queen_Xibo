<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
 * along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
 
class statusdashboardDAO extends baseDAO {

    function displayPage() {

        // Get some data for a bandwidth chart
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT MONTHNAME(FROM_UNIXTIME(month)) AS month, IFNULL(SUM(Size), 0) AS size FROM `bandwidth` WHERE month > :month GROUP BY MONTHNAME(FROM_UNIXTIME(month)) ORDER BY MIN(month);');
            $sth->execute(array('month' => time() - (86400 * 365)));

            $results = $sth->fetchAll();

            // Monthly bandwidth - optionally tested against limits
            $xmdsLimit = Config::GetSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB');

            if ($xmdsLimit > 0) {
                // Convert to MB
                $xmdsLimit = $xmdsLimit / 1024 / 1024;
            }

            $output = array();

            foreach ($results as $row) {
                $size = ((double)$row['size']) / 1024 / 1024;
                $remaining = $xmdsLimit - $size;
                $output[] = array(
                        'label' => __($row['month']), 
                        'value' => round($size, 2),
                        'limit' => round($remaining, 2)
                    );
            }

            // Set the data
            Theme::Set('xmdsLimitSet', ($xmdsLimit > 0));
            Theme::Set('bandwidthWidget', json_encode($output));

            // We would also like a library usage pie chart!
            $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');

            // Library Size in Bytes
            $sth = $dbh->prepare('SELECT IFNULL(SUM(FileSize), 0) AS SumSize, type FROM media GROUP BY type;');
            $sth->execute();
            
            $output = array();
            $totalSize = 0;
            foreach ($sth->fetchAll() as $library) {
                $output[] = array(
                    'value' => round((double)$library['SumSize'] / 1024 / 1024, 2),
                    'label' => ucfirst($library['type'])
                );
                $totalSize = $totalSize + $library['SumSize'];
            }

            // Do we need to add the library remaining?
            if ($libraryLimit > 0) {
                $remaining = $libraryLimit - $totalSize;
                $output[] = array(
                    'value' => round($remaining / 1024 / 1024),
                    'label' => __('Free')
                );
            }

            Theme::Set('libraryWidget', json_encode($output));

            // Also a display widget
            $sort_order = array('display');
            $displays = $this->user->DisplayList($sort_order);

            $rows = array();

            if (is_array($displays) && count($displays) > 0) {
                // Output a table showing the displays
                foreach($displays as $row) {
                    
                    $row['mediainventorystatus'] = ($row['mediainventorystatus'] == 1) ? 'success' : (($row['mediainventorystatus'] == 2) ? 'error' : 'warning');
                    
                    // Assign this to the table row
                    $rows[] = $row;
                }
            }

            Theme::Set('display-widget-rows', $rows);
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            // Show the error in place of the bandwidth chart
            Theme::Set('widget-error', 'Unable to get widget details');
        }

        // Do we have an embedded widget?
        Theme::Set('embedded-widget', html_entity_decode(Config::GetSetting('EMBEDDED_STATUS_WIDGET')));

        // Render the Theme and output
        Theme::Render('status_dashboard');
    }
}
?>
