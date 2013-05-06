<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2011 Daniel Garner
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class DataSet extends Data
{
    /**
     * Add a data set
     * @param <type> $dataSet
     * @param <type> $description
     * @param <type> $userId
     * @return <type>
     */
    public function Add($dataSet, $description, $userId)
    {
        $db =& $this->db;

        // Validation
        if (strlen($dataSet) > 50 || strlen($dataSet) < 1)
        {
            $this->SetError(25001, __("Name must be between 1 and 50 characters"));
            return false;
        }

        if (strlen($description) > 254)
        {
            $this->SetError(25002, __("Description can not be longer than 254 characters"));
            return false;
        }

        // Ensure there are no layouts with the same name
        $SQL = sprintf("SELECT DataSet FROM dataset WHERE DataSet = '%s' ", $dataSet);

        if ($db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25004, sprintf(__("There is already dataset called '%s'. Please choose another name."), $dataSet));
            return false;
        }
        // End Validation

        $SQL = "INSERT INTO dataset (DataSet, Description, UserID) ";
        $SQL .= " VALUES ('%s', '%s', %d) ";

        if (!$id = $db->insert_query(sprintf($SQL, $dataSet, $description, $userId)))
        {
            trigger_error($db->error());
            $this->SetError(25005, __('Could not add DataSet'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'Complete', 'DataSet', 'Add');

        return $id;
    }

    /**
     * Edit a DataSet
     * @param <type> $dataSetId
     * @param <type> $dataSet
     * @param <type> $description
     */
    public function Edit($dataSetId, $dataSet, $description)
    {
        $db =& $this->db;

        // Validation
        if (strlen($dataSet) > 50 || strlen($dataSet) < 1)
        {
            $this->SetError(25001, __("Name must be between 1 and 50 characters"));
            return false;
        }

        if (strlen($description) > 254)
        {
            $this->SetError(25002, __("Description can not be longer than 254 characters"));
            return false;
        }

        // Ensure there are no layouts with the same name
        $SQL = sprintf("SELECT DataSet FROM dataset WHERE DataSet = '%s' AND DataSetID <> %d ", $dataSet, $dataSetId);

        if ($db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25004, sprintf(__("There is already a dataset called '%s'. Please choose another name."), $dataSet));
            return false;
        }
        // End Validation

        $SQL = "UPDATE dataset SET DataSet = '%s', Description = '%s' WHERE DataSetID = %d ";
        $SQL = sprintf($SQL, $dataSet, $description, $dataSetId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25005, sprintf(__('Cannot edit dataset %s'), $dataSet));
            return false;
        }

        return true;
    }

    /**
     * Delete DataSet
     * @param <type> $dataSetId
     */
    public function Delete($dataSetId)
    {
        $db =& $this->db;

        $SQL = "SELECT * FROM `datasetdata` INNER JOIN `datasetcolumn` ON datasetcolumn.DataSetColumnID = datasetdata.DataSetColumnID WHERE datasetcolumn.DataSetID = %d";

        // First check to see if we have any data
        if ($db->GetCountOfRows(sprintf($SQL, $dataSetId)) > 0)
            return $this->SetError(25005, __('There is data assigned to this data set, cannot delete.'));

        // Delete security
        Kit::ClassLoader('datasetgroupsecurity');
        $security = new DataSetGroupSecurity($db);
        $security->UnlinkAll($dataSetId);

        // Delete columns
        $dataSetObject = new DataSetColumn($db);
        if (!$dataSetObject->DeleteAll($dataSetId))
            return $this->SetError(25005, __('Cannot delete dataset, columns could not be deleted.'));

        // Delete data set
        $SQL = "DELETE FROM dataset WHERE DataSetID = %d";
        $SQL = sprintf($SQL, $dataSetId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25005, __('Cannot delete dataset'));
            return false;
        }
        
        return true;
    }

    /**
     * Data Set Results
     * @param <type> $dataSetId
     * @param <type> $columnIds
     * @param <type> $filter
     * @param <type> $ordering
     * @param <type> $lowerLimit
     * @param <type> $upperLimit
     * @return <type>
     */
    public function DataSetResults($dataSetId, $columnIds, $filter = '', $ordering = '', $lowerLimit = 0, $upperLimit = 0, $displayId = 0)
    {
        $db =& $this->db;

        $selectSQL = '';
        $outserSelect = '';
        $results = array();
        $headings = array();
        $depends = array();
        $selectedCols = array();
        
        $columns = explode(',', $columnIds);

        // Get the Latitude and Longitude ( might be used in a formula )
        if ($displayId == 0)
            $displayGeoLocation = "GEOMFROMTEXT('POINT(51.504 -0.104)')";
        else
            $displayGeoLocation = sprintf("(SELECT GeoLocation FROM `display` WHERE DisplayID = %d)", $displayId);

        // Get all columns for the cross tab
        $allColumns = $db->GetArray(sprintf('SELECT DataSetColumnID, Heading, DataSetColumnTypeID, Formula FROM datasetcolumn WHERE DataSetID = %d' , $dataSetId));

        foreach($allColumns as $col)
        {
            $heading = $col;
            
            if (!in_array($col['DataSetColumnID'], $columns))
                continue;                

            // Is this column a formula column or a value column?
            if ($col['DataSetColumnTypeID'] == 2) {
                // Formula
                $heading['Heading'] = str_replace('[DisplayGeoLocation]', $displayGeoLocation, $col['Formula']) . ' AS ' . $heading['Heading'];

                // Capture any source columns
                preg_match_all('/\`(.*?)\`/', $col['Formula'], $matches);
                
                $first = true;
                foreach($matches as $match) {

                    if ($first) {
                        $first = false;
                        continue;
                    }

                    foreach($match as $item) {

                        $depends[] = $item;
                    }
                }
            }
            else {
                // Value
                $selectSQL .= sprintf("MAX(CASE WHEN DataSetColumnID = %d THEN `Value` ELSE null END) AS '%s', ", $col['DataSetColumnID'], $heading['Heading']);
            }

            $headings[] = $heading;
            $selectedCols[] = $heading['Heading'];
        }

        // For each heading, put it in the correct order (according to $columns)
        foreach($columns as $visibleColumn)
        {
            // Check to see if this column is in the headings
            foreach($headings as $heading)
            {
                if ($heading['DataSetColumnID'] == $visibleColumn)
                {
                    if ($heading['DataSetColumnTypeID'] == 2)
                        // This is a formula, so the heading has been morphed into some SQL to run
                        $outserSelect .= sprintf(' %s,', $heading['Heading']);
                    else
                        $outserSelect .= sprintf(' `%s`,', $heading['Heading']);

                    $results['Columns'][] = $heading['Heading'];
                }
            }
        }

        // Add any additional dependants to the inner select sql
        // they cannot already be there.
        foreach(array_diff(array_unique($depends), $selectedCols) as $heading) {

            // Get the data set column id for this dependant column
            $depColumnId = $db->GetSingleValue(sprintf("SELECT DataSetColumnID FROM datasetcolumn WHERE DataSetID = %d AND Heading = '%s'", $dataSetId, $heading), 'DataSetColumnID', _INT);

            $selectSQL .= sprintf("MAX(CASE WHEN DataSetColumnID = %d THEN `Value` ELSE null END) AS '%s', ", $depColumnId, $heading);
        }

        $outserSelect = rtrim($outserSelect, ',');

        // We are ready to build the select and from part of the SQL
        $SQL  = "SELECT $outserSelect ";
        $SQL .= "  FROM ( ";
        $SQL .= "   SELECT $selectSQL ";
        $SQL .= "       RowNumber ";
        $SQL .= "     FROM (";
        $SQL .= "       SELECT datasetcolumn.DataSetColumnID, datasetdata.RowNumber, datasetdata.`Value` ";
        $SQL .= "         FROM datasetdata ";
        $SQL .= "           INNER JOIN datasetcolumn ";
        $SQL .= "           ON datasetcolumn.DataSetColumnID = datasetdata.DataSetColumnID ";
        $SQL .= sprintf("       WHERE datasetcolumn.DataSetID = %d ", $dataSetId);
        $SQL .= "       ) datasetdatainner ";
        $SQL .= "   GROUP BY RowNumber ";
        $SQL .= " ) datasetdata ";
        if ($filter != '')
        {
            $where = ' WHERE 1 = 1 ';

            $filter = explode(',', $filter);

            foreach ($filter as $filterPair)
            {
                $filterPair = explode('=', $filterPair);

                // Validate filter pair 1 doesn't contain any disallowed words
                $disallowedKeywords = array('AND', 'OR');

                if (in_array($filterPair[1], $disallowedKeywords))
                    continue;

                $where .= sprintf(" AND %s = %s ", $filterPair[0], $filterPair[1]);
            }

            $SQL .= $where . ' ';
        }

        if ($ordering != '')
        {
            $order = ' ORDER BY ';

            $ordering = explode(',', $ordering);

            foreach ($ordering as $orderPair)
            {
                if (strripos($orderPair, ' DESC'))
                {
                    $orderPair = str_replace(' DESC', '', $orderPair);
                    $order .= sprintf(" `%s` DESC,", $db->escape_string($orderPair));
                }
                else
                {
                    $order .= sprintf(" `%s`,", $db->escape_string($orderPair));
                }
            }

            $SQL .= trim($order, ',');
        }
        else
        {
            $SQL .= "ORDER BY RowNumber ";
        }

        if ($lowerLimit != 0 || $upperLimit != 0)
        {
            // Lower limit should be 0 based
            if ($lowerLimit != 0)
                $lowerLimit = $lowerLimit - 1;

            // Upper limit should be the distance between upper and lower
            $upperLimit = $upperLimit - $lowerLimit;

            // Substitute in
            $SQL .= sprintf('LIMIT %d, %d ', $lowerLimit, $upperLimit);
        }

        Debug::LogEntry($db, 'audit', $SQL);

        if (!$rows = $db->GetArray($SQL, false))
            trigger_error($db->error());

        if (!is_array($rows))
            $rows = array();
            
        $results['Rows'] = $rows;

        return $results;
    }
}
?>