<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2010-2013 Daniel Garner
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

class Media extends Data
{
    private $moduleInfoLoaded;
    private $regionSpecific;
    private $validExtensions;

    /**
     * Adds a new media record
     * @param <type> $fileId
     * @param <type> $type
     * @param <type> $name
     * @param <type> $duration
     * @param <type> $fileName
     * @param <type> $userId
     * @param <int> [$oldMediaId] [The old media id during a file revision]
     * @return <type>
     */
    public function Add($fileId, $type, $name, $duration, $fileName, $userId, $oldMediaId = 0)
    {
        $db =& $this->db;

        // Check we have room in the library
        $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');

        if ($libraryLimit > 0) {
            $fileSize = $this->db->GetSingleValue('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', 'SumSize', _INT);

            if (($fileSize / 1024) > $libraryLimit) {
                return $this->SetError(sprintf(__('Your library is full. Library Limit: %s K'), $libraryLimit));
            }
        }

        $extension = strtolower(substr(strrchr($fileName, '.'), 1));

        // Check that is a valid media type
        if (!$this->IsValidType($type))
            return false;

        // Check the extension is valid for that media type
        if (!$this->IsValidFile($extension))
            return $this->SetError(18, __('Invalid file extension'));

        // Validation
        if (strlen($name) > 100)
            return $this->SetError(10, __('The name cannot be longer than 100 characters'));

        // Test the duration (except for video and localvideo which can have a 0)
        if ($duration == 0 && $type != 'video' && $type != 'localvideo')
            return $this->SetError(11, __('You must enter a duration.'));

        // Check the naming of this item to ensure it doesnt conflict
        $checkSQL = sprintf("SELECT name FROM media WHERE name = '%s' AND userid = %d ", $db->escape_string($name), $userId);
        
        if ($oldMediaId != 0)
            $checkSQL .= sprintf(" AND mediaid <> %d  AND IsEdited = 0 ", $oldMediaId);

        Debug::LogEntry('audit', 'Checking the name is unique: ' . $checkSQL, 'media', 'Add');

        if ($db->GetSingleRow($checkSQL))
            return $this->SetError(12, __('Media you own already has this name. Please choose another.'));

        // All OK to insert this record
        $SQL  = "INSERT INTO media (name, type, duration, originalFilename, userID, retired ) ";
        $SQL .= "VALUES ('%s', '%s', '%s', '%s', %d, 0) ";

        $SQL = sprintf($SQL, $db->escape_string($name), $db->escape_string($type),
            $db->escape_string($duration), $db->escape_string($fileName), $userId);

        if (!$mediaId = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(13, __('Error inserting media.'));
            return false;
        }

        // Now move the file
        $libraryFolder 	= Config::GetSetting('LIBRARY_LOCATION');

        if (!@rename($libraryFolder . 'temp/' . $fileId, $libraryFolder . $mediaId . '.' . $extension))
        {
            // If we couldnt move it - we need to delete the media record we just added
            $SQL = sprintf("DELETE FROM media WHERE mediaID = %d ", $mediaId);

            if (!$db->query($SQL))
                return $this->SetError(14, 'Error cleaning up after failure.');

            return $this->SetError(15, 'Error storing file.');
        }

        // Calculate the MD5 and the file size
        $storedAs   = $libraryFolder . $mediaId . '.' . $extension;
        $md5        = md5_file($storedAs);
        $fileSize   = filesize($storedAs);

        // Update the media record to include this information
        $SQL = sprintf("UPDATE media SET storedAs = '%s', `MD5` = '%s', FileSize = %d WHERE mediaid = %d", $db->escape_string($mediaId . '.' . $extension), $db->escape_string($md5), $fileSize, $mediaId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(16, 'Updating stored file location and MD5');
        }

        // What permissions should we assign this with?
        if (Config::GetSetting('MEDIA_DEFAULT') == 'public')
        {
            Kit::ClassLoader('mediagroupsecurity');

            $security = new MediaGroupSecurity($db);
            $security->LinkEveryone($mediaId, 1, 0, 0);
        }

        return $mediaId;
    }

    /**
     * Edit Media Record
     * @param <type> $mediaId
     * @param <type> $name
     * @param <type> $duration
     * @return <bool>
     */
    public function Edit($mediaId, $name, $duration, $userId)
    {
        $db =& $this->db;

        // Look up the type
        if (!$type = $db->GetSingleValue(sprintf("SELECT type FROM `media` WHERE MediaID = %d", $mediaId), 'type', _WORD))
            return $this->SetError(12, __('Unable to find media type'));

        // Validation
        if (strlen($name) > 100)
            return $this->SetError(10, __('The name cannot be longer than 100 characters'));

        if ($duration == 0 && $type != 'video' && $type != 'localvideo')
            return $this->SetError(11, __('You must enter a duration.'));

        // Any media (not this one) already has this name?
        if ($db->GetSingleRow(sprintf("SELECT name FROM media WHERE name = '%s' AND userid = %d AND mediaid <> %d AND IsEdited = 0", $db->escape_string($name), $userId, $mediaId)))
            return $this->SetError(12, __('Media you own already has this name. Please choose another.'));
       
        $SQL = "UPDATE media SET name = '%s', duration = %d WHERE MediaID = %d";
        $SQL = sprintf($SQL, $db->escape_string($name), $duration, $mediaId);

        if (!$db->query($SQL))
        {
           trigger_error($db->error());
           return $this->SetError(30, 'Database failure updating media');
        }

        return true;
    }

    /**
     * Revises the file for this media id
     * @param <type> $mediaId
     * @param <type> $fileId
     * @param <type> $fileName
     */
    public function FileRevise($mediaId, $fileId, $fileName)
    {
        $db =& $this->db;

        // Check we have room in the library
        $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');

        if ($libraryLimit > 0) {
            $fileSize = $this->db->GetSingleValue('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', 'SumSize', _INT);

            if (($fileSize / 1024) > $libraryLimit) {
                return $this->SetError(sprintf(__('Your library is full. Library Limit: %s K'), $libraryLimit));
            }
        }

        // Call add with this file Id and then update the existing mediaId with the returned mediaId
        // from the add call.
        // Will need to get some information about the existing media record first.
        $SQL = "SELECT name, duration, UserID, type FROM media WHERE MediaID = %d";
        $SQL = sprintf($SQL, $mediaId);

        if (!$row = $db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(31, 'Unable to get information about existing media record.');
        }

        // Pass in the old media id ($mediaid) so that we don't validate against it during the name check
        if (!$newMediaId = $this->Add($fileId, $row['type'], $row['name'], $row['duration'], $fileName, $row['UserID'], $mediaId))
            return false;

        // We need to assign all permissions for the old media id to the new media id
        Kit::ClassLoader('mediagroupsecurity');

        $security = new MediaGroupSecurity($db);
        $security->Copy($mediaId, $newMediaId);

        // Update the existing record with the new record's id
        $SQL =  "UPDATE media SET isEdited = 1, editedMediaID = %d ";
        $SQL .= " WHERE IFNULL(editedMediaID, 0) <> %d AND mediaID = %d ";
        $SQL = sprintf($SQL, $newMediaId, $newMediaId, $mediaId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(32, 'Unable to update existing media record');
        }

        return $newMediaId;
    }

    public function Retire($mediaId)
    {
        $db =& $this->db;

        // Retire the media
        $SQL = sprintf("UPDATE media SET retired = 1 WHERE MediaID = %d", $mediaId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(19, __('Error retiring media.'));
        }

        return true;
    }

    public function Delete($mediaId)
    {
        $db =& $this->db;

        // Check for links
        $SQL = sprintf("SELECT * FROM lklayoutmedia WHERE MediaID = %d", $mediaId);

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(20, __('Error checking if media can be deleted.'));
        }

        // If any links are found, then we cannot delete
        if ($db->num_rows($results) > 0)
            return $this->SetError(21, __('This media is in use, please retire it instead.'));

        // Get the file name
        $SQL = sprintf("SELECT StoredAs FROM media WHERE mediaID = %d", $mediaId);

        if (!$fileName = $db->GetSingleValue($SQL, 'StoredAs', _STRING))
            return $this->SetError(22, __('Cannot locate the files for this media. Unable to delete.'));

        // Remove permission assignments
        Kit::ClassLoader('mediagroupsecurity');
        $security = new MediaGroupSecurity($db);

        if (!$security->UnlinkAll($mediaId))
            trigger_error($security->GetErrorMessage(), E_USER_ERROR);

        // Delete the media
        $SQL = sprintf("DELETE FROM media WHERE MediaID = %d", $mediaId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(23, __('Error deleting media.'));
        }

        // Delete the file itself (and any thumbs, etc)
        $this->DeleteMediaFile($fileName);

        // Bring back the previous revision of this media (if there is one)
        $editedMediaRow = $db->GetSingleRow(sprintf('SELECT IFNULL(MediaID, 0) AS MediaID FROM media WHERE EditedMediaID = %d', $mediaId));

        if (count($editedMediaRow) > 0)
        {
            // Unretire this edited record
            $editedMediaId = Kit::ValidateParam($editedMediaRow['MediaID'], _INT);
            $db->query(sprintf('UPDATE media SET IsEdited = 0, EditedMediaID = NULL WHERE mediaid = %d', $editedMediaId));
        }

        return true;
    }

    public function DeleteMediaFile($fileName)
    {
        $db =& $this->db;

        // Library location
        $databaseDir = Config::GetSetting("LIBRARY_LOCATION");

        //3 things to check for..
        //the actual file, the thumbnail, the background
        if (file_exists($databaseDir . $fileName))
            unlink($databaseDir . $fileName);

        if (file_exists($databaseDir . 'tn_' . $fileName))
            unlink($databaseDir . 'tn_' . $fileName);

        if (file_exists($databaseDir . 'bg_' . $fileName))
            unlink($databaseDir . 'bg_' . $fileName);

        return true;
    }

    private function IsValidType($type)
    {
        $db =& $this->db;

        if (!$this->moduleInfoLoaded)
        {
            if (!$this->LoadModuleInfo($type))
                return false;
        }

        return true;
    }

    private function IsValidFile($extension)
    {
        $db =& $this->db;

        if (!$this->moduleInfoLoaded)
        {
            if (!$this->LoadModuleInfo())
                return false;
        }

        // TODO: Is this search case sensitive?
        return in_array($extension, $this->validExtensions);
    }

    /**
     * Loads some information about this type of module
     * @return <bool>
     */
    private function LoadModuleInfo($type)
    {
        $db =& $this->db;

        if ($type == '')
            return $this->SetError(18, __('No module type given'));

        $SQL = sprintf("SELECT * FROM module WHERE Module = '%s'", $db->escape_string($type));

        if (!$result = $db->query($SQL))
            return $this->SetError(19, __('Database error checking module'));

        if ($db->num_rows($result) != 1)
            return $this->SetError(20, __('No Module of this type found'));

        $row = $db->get_assoc_row($result);

        $this->moduleInfoLoaded = true;
        $this->regionSpecific   = Kit::ValidateParam($row['RegionSpecific'], _INT);
        $this->validExtensions 	= explode(',', Kit::ValidateParam($row['ValidExtensions'], _STRING));
        
        return true;
    }

    /**
     * Valid Extensions
     * @param [string] $type [The Type of Media Item]
     * @return [array] Array containing the valid extensions
     */
    public function ValidExtensions($type) {
        $db =& $this->db;

        if (!$this->moduleInfoLoaded)
        {
            if (!$this->LoadModuleInfo($type))
                return false;
        }

        return $this->validExtensions;
    }

    /**
     * List of available modules
     * @return <array>
     */
    public function ModuleList()
    {
        $db =& $this->db;

        if (!$results = $db->query("SELECT * FROM module WHERE Enabled = 1"))
        {
            trigger_error($db->error());
            return $this->SetError(40, 'Unable to query for modules');
        }

        $modules = array();

        while($row = $db->get_assoc_row($results))
        {
            $module = array();

            $module['module'] = $row['Module'];
            $module['layoutOnly'] = $row['RegionSpecific'];
            $module['description'] = $row['Description'];
            $module['extensions'] = $row['ValidExtensions'];
            
            $modules[] = $module;
        }

        return $modules;
    }

    /**
     * Make a copy of this media record
     * @param <type> $oldMediaId
     */
    public function Copy($oldMediaId, $prefix = '')
    {
        $db =& $this->db;

        // Get the extension from the old media record
        if (!$fileName = $this->db->GetSingleValue(sprintf("SELECT StoredAs FROM media WHERE MediaID = %d", $oldMediaId), 'StoredAs', _STRING))
        {
            trigger_error($db->error());
            return $this->SetError(26, __('Error getting media extension before copy.'));
        }

        $extension = strtolower(substr(strrchr($fileName, '.'), 1));

        $newMediaName = "CONCAT(name, ' ', 2)";

        if ($prefix != '')
            $newMediaName = "CONCAT('$prefix', ' ', name)";

        // All OK to insert this record
        $SQL  = "INSERT INTO media (name, type, duration, originalFilename, userID, retired ) ";
        $SQL .= " SELECT %s, type, duration, originalFilename, userID, retired ";
        $SQL .= "  FROM media ";
        $SQL .= " WHERE MediaID = %d ";

        $SQL = sprintf($SQL, $newMediaName, $oldMediaId);

        if (!$newMediaId = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(26, __('Error copying media.'));
        }

        // Make a copy of the file
        $libraryFolder 	= Config::GetSetting('LIBRARY_LOCATION');

        if (!copy($libraryFolder . $oldMediaId . '.' . $extension, $libraryFolder . $newMediaId . '.' . $extension))
        {
            // If we couldnt move it - we need to delete the media record we just added
            $SQL = sprintf("DELETE FROM media WHERE mediaID = %d ", $newMediaId);

            if (!$db->query($SQL))
                return $this->SetError(14, 'Error cleaning up after failure.');

            return $this->SetError(15, 'Error storing file.');
        }

        // Calculate the MD5 and the file size
        $storedAs   = $libraryFolder . $newMediaId . '.' . $extension;
        $md5        = md5_file($storedAs);
        $fileSize   = filesize($storedAs);

        // Update the media record to include this information
        $SQL = sprintf("UPDATE media SET storedAs = '%s', `MD5` = '%s', FileSize = %d WHERE mediaid = %d", $db->escape_string($newMediaId . '.' . $extension), $db->escape_string($md5), $fileSize, $newMediaId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(16, 'Updating stored file location and MD5');
        }

        return $newMediaId;
    }
}
?>
