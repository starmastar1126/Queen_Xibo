<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-11 Daniel Garner
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

class UserGroup extends Data
{
    public function __construct(database $db)
    {
        parent::__construct($db);
    }

    /**
     * Adds a User Group to Xibo
     * @return
     * @param $UserGroup Object
     * @param $isDisplaySpecific Object
     * @param $description Object[optional]
     */
    public function Add($group, $isUserSpecific)
    {
        $db	=& $this->db;

        Debug::LogEntry('audit', 'IN', 'UserGroup', 'Add');

        // Validation
        if ($group == '')
            return $this->SetError(__('Group Name cannot be empty.'));

        // Create the SQL
        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   `group` ";
        $SQL .= "       ( ";
        $SQL .= "              `group`     , ";
        $SQL .= "              IsUserSpecific ";
        $SQL .= "       ) ";
        $SQL .= "       VALUES ";
        $SQL .= "       ( ";
        $SQL .= sprintf("              '%s', ", $db->escape_string($group));
        $SQL .= sprintf("              %d   ", $isUserSpecific);
        $SQL .= "       )";

        if (!$groupID = $db->insert_query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25000, __('Could not add User Group'));
        }

        Debug::LogEntry('audit', 'OUT', 'UserGroup', 'Add');

        return $groupID;
    }

    /**
     * Edits an existing Xibo Display Group
     * @return
     * @param $userGroupID Object
     * @param $UserGroup Object
     */
    public function Edit($userGroupID, $userGroup)
    {
        $db	=& $this->db;

        Debug::LogEntry('audit', 'IN', 'UserGroup', 'Edit');

        // Validation
        if ($userGroupID == 0)
            return $this->SetError(__('User Group not selected'));
        
        if ($userGroup == '')
            return $this->SetError(__('User Group Name cannot be empty.'));

        // Create the SQL
        $SQL  = "";
        $SQL .= "UPDATE `group` ";
        $SQL .= sprintf("SET    `group`   = '%s' ", $db->escape_string($userGroup));
        $SQL .= sprintf("WHERE  GroupID = %d", $userGroupID);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25005, __('Could not edit User Group'));
        }

        Debug::LogEntry('audit', 'OUT', 'UserGroup', 'Edit');

        return true;
    }

    /**
     * Deletes an Xibo User Group
     * @return
     * @param $userGroupID Object
     */
    public function Delete($userGroupID)
    {
        $db	=& $this->db;

        Debug::LogEntry('audit', 'IN', 'UserGroup', 'Delete');

        // Delete all menu links
        $this->db->query(sprintf('DELETE FROM lkmenuitemgroup WHERE GroupID = %d', $userGroupID));

        // Delete all page links
        $this->db->query(sprintf('DELETE FROM lkpagegroup WHERE GroupID = %d', $userGroupID));

        // Delete the user group
        $SQL = sprintf("DELETE FROM `group` WHERE GroupID = %d", $userGroupID);

        Debug::LogEntry('audit', $SQL);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25015,__('Unable to delete User Group.'));
        }

        Debug::LogEntry('audit', 'OUT', 'UserGroup', 'Delete');

        return true;
    }

    /**
     * Links a User to a User Group
     * @return
     * @param $userGroupID Object
     * @param $userID Object
     */
    public function Link($userGroupID, $userID)
    {
        $db	=& $this->db;

        Debug::LogEntry('audit', 'IN', 'UserGroup', 'Link');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lkusergroup ";
        $SQL .= "       ( ";
        $SQL .= "              GroupID, ";
        $SQL .= "              UserID ";
        $SQL .= "       ) ";
        $SQL .= "       VALUES ";
        $SQL .= "       ( ";
        $SQL .= sprintf("              %d, %d ", $userGroupID, $userID);
        $SQL .= "       )";

        if (!$db->query($SQL))
        {
                trigger_error($db->error());
                $this->SetError(25005, __('Could not Link User Group to User'));

                return false;
        }

        Debug::LogEntry('audit', 'OUT', 'UserGroup', 'Link');

        return true;
    }

    /**
     * Unlinks a Display from a Display Group
     * @return
     * @param $userGroupID Object
     * @param $userID Object
     */
    public function Unlink($userGroupID, $userID)
    {
        $db	=& $this->db;

        Debug::LogEntry('audit', 'IN', 'UserGroup', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lkusergroup ";
        $SQL .= sprintf("  WHERE GroupID = %d AND UserID = %d ", $userGroupID, $userID);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25007, __('Could not Unlink User from User Group'));

            return false;
        }

        Debug::LogEntry('audit', 'OUT', 'UserGroup', 'Unlink');

        return true;
    }

    /**
     * Unlinks all users from the speficied group
     * @param <type> $userGroupId
     */
    public function UnlinkAllUsers($userGroupId)
    {
        $db =& $this->db;

        Debug::LogEntry('audit', 'IN', 'UserGroup', 'UnlinkAllUsers');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lkusergroup ";
        $SQL .= sprintf("  WHERE GroupID = %d ", $userGroupId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25007, __('Could not Unlink all Users from User Group'));

            return false;
        }

        Debug::LogEntry('audit', 'OUT', 'UserGroup', 'UnlinkAllUsers');

        return true;
    }

    /**
     * Unliks all groups from the specified user
     * @param <type> $userId
     */
    public function UnlinkAllGroups($userId)
    {
        $db =& $this->db;

        Debug::LogEntry('audit', 'IN', 'UserGroup', 'UnlinkAllGroups');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lkusergroup ";
        $SQL .= sprintf("  WHERE UserID = %d ", $userId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25007, __('Could not Unlink Groups from User'));

            return false;
        }

        Debug::LogEntry('audit', 'OUT', 'UserGroup', 'UnlinkAllGroups');

        return true;
    }

    /**
     * Edits the User Group associated with a User
     * @return
     * @param $userID Object
     * @param $userName Object
     */
    public function EditUserGroup($userID, $userName)
    {
        $db	=& $this->db;

        Debug::LogEntry('audit', 'IN', 'UserGroup', 'EditUserGroup');

        // Get the UserGroupID for this UserID
        $SQL  = "";
        $SQL .= "SELECT `group`.GroupID ";
        $SQL .= "FROM   `group` ";
        $SQL .= "       INNER JOIN lkusergroup ";
        $SQL .= "       ON     lkusergroup.GroupID = `group`.groupID ";
        $SQL .= "WHERE  `group`.IsUserSpecific     = 1 ";
        $SQL .= sprintf("   AND lkusergroup.UserID = %d", $userID);

        if (!$result = $db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25005, __('Unable to get the UserGroup for this User.'));

            return false;
        }

        $row 		= $db->get_assoc_row($result);
        $userGroupID	= $row['GroupID'];

        if ($userGroupID == '')
        {
            // We should always have 1 display specific UserGroup for a display.
            // Do we a) Error here and give up?
            //		 b) Create one and link it up?
            // $this->SetError(25006, __('Unable to get the UserGroup for this Display'));

            if (!$userGroupID = $this->Add($userName, 1))
            {
                $this->SetError(25001, __('Could not add a user group for this user.'));

                return false;
            }

            // Link the Two together
            if (!$this->Link($userGroupID, $userID))
            {
                $this->SetError(25001, __('Could not link the new user with its group.'));

                return false;
            }
        }
        else
        {
            if (!$this->Edit($userGroupID, $userName)) return false;
        }
        
        Debug::LogEntry('audit', 'OUT', 'UserGroup', 'EditUserGroup');

        return true;
    }
}
?>