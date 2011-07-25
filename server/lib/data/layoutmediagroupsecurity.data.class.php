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
defined('XIBO') or die('Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.');

class LayoutMediaGroupSecurity extends Data
{
    public function __construct(database $db)
    {
        parent::__construct($db);
    }

    /**
     * Links a Display Group to a Group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Link($layoutId, $regionId, $mediaId, $groupId, $view, $edit, $del)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutMediaGroupSecurity', 'Link');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lklayoutmediagroup ";
        $SQL .= "       ( ";
        $SQL .= "              LayoutID, ";
        $SQL .= "              RegionID, ";
        $SQL .= "              MediaID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= "       VALUES ";
        $SQL .= "       ( ";
        $SQL .= sprintf("  %d, '%s', '%s', %d, %d, %d, %d ", $layoutId, $regionId, $mediaId, $groupId, $view, $edit, $del);
        $SQL .= "       )";

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25026, __('Could not Link Layout Media to Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'LayoutMediaGroupSecurity', 'Link');

        return true;
    }

    /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Unlink($layoutId, $regionId, $mediaId, $groupId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutMediaGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lklayoutmediagroup ";
        $SQL .= sprintf("  WHERE LayoutID = %d AND RegionID = '%s' AND MediaID = '%s' AND GroupID = %d ", $layoutId, $regionId, $mediaId, $groupId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25027, __('Could not Unlink Layout Media from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'LayoutMediaGroupSecurity', 'Unlink');

        return true;
    }

        /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function UnlinkAll($layoutId, $regionId, $mediaId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'LayoutMediaGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lklayoutmediagroup ";
        $SQL .= sprintf("  WHERE LayoutID = %d AND RegionID = '%s' AND MediaID = '%s' ", $layoutId, $regionId, $mediaId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25028, __('Could not Unlink Layout Media from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'LayoutMediaGroupSecurity', 'Unlink');

        return true;
    }
}
?>