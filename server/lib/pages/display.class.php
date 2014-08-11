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
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class displayDAO extends baseDAO
{
    private $db;
    private $user;

    function __construct(database $db, user $user)
    {
        $this->db   =& $db;
        $this->user =& $user;

        Kit::ClassLoader('Display');

        $this->sub_page = Kit::GetParam('sp', _GET, _WORD, 'view');
        $this->ajax     = Kit::GetParam('ajax', _REQUEST, _WORD, 'false');
        $displayid      = Kit::GetParam('displayid', _REQUEST, _INT, 0);

        // validate displays so we get a realistic view of the table
        $this->validateDisplays();
    }

    /**
     * Include display page template page based on sub page selected
     * @return
     */
    function displayPage()
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="display"><input type="hidden" name="q" value="DisplayGrid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));

        // Default options
        if (Kit::IsFilterPinned('display', 'DisplayFilter')) {
            Theme::Set('filter_pinned', 'checked');
            Theme::Set('filter_displaygroup', Session::Get('display', 'filter_displaygroup'));
            Theme::Set('filter_display', Session::Get('display', 'filter_display'));
        }

        $displayGroups = $this->user->DisplayGroupList(0);
        array_unshift($displayGroups, array('displaygroupid' => '0', 'displaygroup' => 'All'));

        Theme::Set('displaygroup_field_list', $displayGroups);

        // Render the Theme and output
        Theme::Render('display_page');
    }

    /**
     * Modifies the selected display record
     * @return
     */
    function modify()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $response = new ResponseManager();

        $displayObject  = new Display();
        $displayObject->displayId = Kit::GetParam('displayid', _POST, _INT);

        $auth = $this->user->DisplayGroupAuth($this->GetDisplayGroupId($displayObject->displayId), true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display'), E_USER_ERROR);

        if (!$displayObject->Load())
            trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);

        // Update properties
        $displayObject->display = Kit::GetParam('display', _POST, _STRING);
        $displayObject->description = Kit::GetParam('description', _POST, _STRING);
        $displayObject->isAuditing = Kit::GetParam('auditing', _POST, _INT);
        $displayObject->defaultLayoutId = Kit::GetParam('defaultlayoutid', _POST, _INT);
        $displayObject->licensed = Kit::GetParam('licensed', _POST, _INT);
        $displayObject->incSchedule = Kit::GetParam('inc_schedule', _POST, _INT);
        $displayObject->emailAlert = Kit::GetParam('email_alert', _POST, _INT);
        $displayObject->alertTimeout = Kit::GetParam('alert_timeout', _POST, _INT);
        $displayObject->wakeOnLanEnabled = Kit::GetParam('wakeOnLanEnabled', _POST, _CHECKBOX);
        $displayObject->wakeOnLanTime = Kit::GetParam('wakeOnLanTime', _POST, _STRING);
        $displayObject->broadCastAddress = Kit::GetParam('broadCastAddress', _POST, _STRING);
        $displayObject->secureOn = Kit::GetParam('secureOn', _POST, _STRING);
        $displayObject->cidr = Kit::GetParam('cidr', _POST, _INT);
        $displayObject->latitude = Kit::GetParam('latitude', _POST, _DOUBLE);
        $displayObject->longitude = Kit::GetParam('longitude', _POST, _DOUBLE);
        $displayObject->displayProfileId = Kit::GetParam('displayprofileid', _POST, _INT);

        if (!$displayObject->Edit())
            trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Display Saved.'));
        $response->Respond();
    }

    /**
     * Modify Display form
     * @return
     */
    function displayForm()
    {
        $db             =& $this->db;
        $user           =& $this->user;
        $response       = new ResponseManager();

        // Get the display Id
        $displayObject = new Display();
        $displayObject->displayId = Kit::GetParam('displayid', _GET, _INT);

        $auth = $this->user->DisplayGroupAuth($this->GetDisplayGroupId($displayObject->displayId), true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display'), E_USER_ERROR);

        // Load this display
        if (!$displayObject->Load())
            trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'DisplayEditForm');
        Theme::Set('form_action', 'index.php?p=display&q=modify');
        Theme::Set('form_meta', '<input type="hidden" name="displayid" value="' . $displayObject->displayId . '" />');
        
        // Column 1
        $formFields = array();
        $formFields[] = FormManager::AddText('display', __('Display'), $displayObject->display, 
            __('The Name of the Display - (1 - 50 characters).'), 'd', 'required');

        $formFields[] = FormManager::AddText('hardwareKey', __('Display\'s Hardware Key'), $displayObject->license, 
            __('A unique identifier for this display.'), 'h', 'required', NULL, false);

        $formFields[] = FormManager::AddText('description', __('Description'), $displayObject->description, 
            __('A description - (1 - 254 characters).'), 'p', 'maxlength="50"');

        $formFields[] = FormManager::AddCombo(
                    'licensed', 
                    __('Licence Display?'), 
                    $displayObject->licensed,
                    array(array('licensedid' => '1', 'licensed' => 'Yes'), array('licensedid' => '0', 'licensed' => 'No')),
                    'licensedid',
                    'licensed',
                    __('Use one of the available licenses for this display?'), 
                    'l');

        $formFields[] = FormManager::AddCombo(
                    'defaultlayoutid', 
                    __('Default Layout'), 
                    $displayObject->defaultLayoutId,
                    $this->user->LayoutList(),
                    'layoutid',
                    'layout',
                    __('The Default Layout to Display where there is no other content.'), 
                    't');

        Theme::Set('form_fields_general', $formFields);

        // Maintenance
        $formFields = array();
        $formFields[] = FormManager::AddCombo(
                    'email_alert', 
                    __('Email Alerts'), 
                    $displayObject->emailAlert,
                    array(array('id' => '1', 'value' => 'Yes'), array('id' => '0', 'value' => 'No')),
                    'id',
                    'value',
                    __('Do you want to be notified by email if there is a problem with this display?'), 
                    'a');

        $formFields[] = FormManager::AddNumber('alert_timeout', __('Alert Timeout'), $displayObject->alertTimeout, 
            __('How long in minutes after the display last connected to the webservice should we send an alert. Set this value higher than the collection interval on the client. Set to 0 to use global default.'), 
            'o');

        Theme::Set('form_fields_maintenance', $formFields);
        
        // Location
        $formFields = array();

        $formFields[] = FormManager::AddNumber('latitude', __('Latitude'), $displayObject->latitude, 
            __('The Latitude of this display'), 'g');

        $formFields[] = FormManager::AddNumber('longitude', __('Longitude'), $displayObject->longitude, 
            __('The Longitude of this Display'), 'g');

        Theme::Set('form_fields_location', $formFields);

        // Wake on LAN
        $formFields = array();
        
        $formFields[] = FormManager::AddCheckbox('wakeOnLanEnabled', __('Enable Wake on LAN'), 
            $displayObject->wakeOnLanEnabled, __('Wake on Lan requires the correct network configuration to route the magic packet to the display PC'), 
            'w');

        $formFields[] = FormManager::AddText('broadCastAddress', __('BroadCast Address'), (($displayObject->broadCastAddress == '') ? $displayObject->clientAddress : $displayObject->broadCastAddress), 
            __('The IP address of the remote host\'s broadcast address (or gateway)'), 'b');

        $formFields[] = FormManager::AddText('secureOn', __('Wake on LAN SecureOn'), $displayObject->secureOn, 
            __('Enter a hexidecimal password of a SecureOn enabled Network Interface Card (NIC) of the remote host. Enter a value in this pattern: \'xx-xx-xx-xx-xx-xx\'. Leave the following field empty, if SecureOn is not used (for example, because the NIC of the remote host does not support SecureOn).'), 's');

        $formFields[] = FormManager::AddText('wakeOnLanTime', __('Wake on LAN Time'), $displayObject->wakeOnLanTime, 
            __('The time this display should receive the WOL command, using the 24hr clock - e.g. 19:00. Maintenance must be enabled.'), 't');

        $formFields[] = FormManager::AddText('cidr', __('Wake on LAN CIDR'), $displayObject->cidr, 
            __('Enter a number within the range of 0 to 32 in the following field. Leave the following field empty, if no subnet mask should be used (CIDR = 0). If the remote host\'s broadcast address is unkown: Enter the host name or IP address of the remote host in Broad Cast Address and enter the CIDR subnet mask of the remote host in this field.'), 'c');

        Theme::Set('form_fields_wol', $formFields);

        // Advanced
        $formFields = array();
        
        $displayProfileList = $this->user->DisplayProfileList(NULL, array('type' => $displayObject->clientType));
        array_unshift($displayProfileList, array('displayprofileid' => 0, 'name' => ''));

        $formFields[] = FormManager::AddCombo(
                    'displayprofileid', 
                    __('Settings Profile?'), 
                    $displayObject->displayProfileId,
                    $displayProfileList,
                    'displayprofileid',
                    'name',
                    __('What display profile should this display use?'), 
                    'p');

        $formFields[] = FormManager::AddCombo(
                    'inc_schedule', 
                    __('Interleave Default'), 
                    $displayObject->incSchedule,
                    array(array('id' => '1', 'value' => 'Yes'), array('id' => '0', 'value' => 'No')),
                    'id',
                    'value',
                    __('Whether to always put the default layout into the cycle.'), 
                    'i');

        $formFields[] = FormManager::AddCombo(
                    'auditing', 
                    __('Auditing'), 
                    $displayObject->isAuditing,
                    array(array('id' => '1', 'value' => 'Yes'), array('id' => '0', 'value' => 'No')),
                    'id',
                    'value',
                    __('Collect auditing from this client. Should only be used if there is a problem with the display.'), 
                    'a');

        Theme::Set('form_fields_advanced', $formFields);

        // Two tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('location', __('Location'));
        $tabs[] = FormManager::AddTab('maintenance', __('Maintenance'));
        $tabs[] = FormManager::AddTab('wol', __('Wake on LAN'));
        $tabs[] = FormManager::AddTab('advanced', __('Advanced'));

        Theme::Set('form_tabs', $tabs);

        $response->SetFormRequestResponse(NULL, __('Edit a Display'), '650px', '350px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Display', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DisplayEditForm").submit()');
        $response->Respond();
    }

    /**
     * Grid of Displays
     * @return
     */
    function DisplayGrid()
    {
        $db         =& $this->db;
        $user       =& $this->user;
        $response   = new ResponseManager();

        // Filter by Name
        $filter_display = Kit::GetParam('filter_display', _POST, _STRING);
        setSession('display', 'filter_display', $filter_display);
        
        // Display Group
        $filter_displaygroupid = Kit::GetParam('filter_displaygroup', _POST, _INT);
        setSession('display', 'filter_displaygroup', $filter_displaygroupid);

        // Pinned option?        
        setSession('display', 'DisplayFilter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));

        $displays = $user->DisplayList(array('displayid'), array('displaygroupid' => $filter_displaygroupid, 'display' => $filter_display));

        if (!is_array($displays))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get list of displays'), E_USER_ERROR);
        }

        // Do we want to make a VNC link out of the display name?
        $vncTemplate = Config::GetSetting('SHOW_DISPLAY_AS_VNCLINK');
        $linkTarget = Kit::ValidateParam(Config::GetSetting('SHOW_DISPLAY_AS_VNC_TGT'), _STRING);
        
        $rows = array();

        foreach($displays as $row)
        {
            // VNC Template as display name?
            if ($vncTemplate != '' && $row['clientaddress'] != '')
            {
                if ($linkTarget == '')
                    $linkTarget = '_top';

                $row['display'] = sprintf('<a href="' . $vncTemplate . '" title="VNC to ' . $row['display'] . '" target="' . $linkTarget . '">' . Theme::Prepare($row['display']) . '</a>', $row['clientaddress']);
            }

            // Format last accessed
            $row['lastaccessed'] = date("Y-m-d H:i:s", $row['lastaccessed']);

            // Create some login lights
            $row['licensed'] = ($row['licensed'] == 1) ? 'glyphicon glyphicon-ok' : 'glyphicon glyphicon-remove';
            $row['inc_schedule'] = ($row['inc_schedule'] == 1) ? 'glyphicon glyphicon-ok' : 'glyphicon glyphicon-remove';
            $row['email_alert'] = ($row['email_alert'] == 1) ? 'glyphicon glyphicon-ok' : 'glyphicon glyphicon-remove';
            $row['loggedin'] = ($row['loggedin'] == 1) ? 'glyphicon glyphicon-ok' : 'glyphicon glyphicon-remove';
            $row['mediainventorystatus'] = ($row['mediainventorystatus'] == 1) ? 'success' : (($row['mediainventorystatus'] == 2) ? 'danger' : 'warning');

            // Schedule Now
            if ($row['edit'] == 1 || Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes') {
                $row['buttons'][] = array(
                        'id' => 'display_button_schedulenow',
                        'url' => 'index.php?p=schedule&q=ScheduleNowForm&displayGroupId=' . $row['displaygroupid'],
                        'text' => __('Schedule Now')
                    );
            }

            // Media Inventory
            $row['buttons'][] = array(
                    'id' => 'display_button_mediainventory',
                    'url' => 'index.php?p=display&q=MediaInventory&DisplayId=' . $row['displayid'],
                    'text' => __('Media Inventory')
                );

            if ($row['edit'] == 1) {

                // Default Layout
                $row['buttons'][] = array(
                        'id' => 'display_button_defaultlayout',
                        'url' => 'index.php?p=display&q=DefaultLayoutForm&DisplayId=' . $row['displayid'],
                        'text' => __('Default Layout')
                    );

                // Edit
                $row['buttons'][] = array(
                        'id' => 'display_button_edit',
                        'url' => 'index.php?p=display&q=displayForm&displayid=' . $row['displayid'],
                        'text' => __('Edit')
                    );

                // Wake On LAN
                $row['buttons'][] = array(
                        'id' => 'display_button_wol',
                        'url' => 'index.php?p=display&q=WakeOnLanForm&DisplayId=' . $row['displayid'],
                        'text' => __('Wake on LAN')
                    );

                // File Associations
                $row['buttons'][] = array(
                        'id' => 'displaygroup_button_fileassociations',
                        'url' => 'index.php?p=displaygroup&q=FileAssociations&DisplayGroupID=' . $row['displaygroupid'],
                        'text' => __('Assign Files')
                    );

                // Logs
                $row['buttons'][] = array(
                        'id' => 'displaygroup_button_logs',
                        'url' => 'index.php?p=log&q=LastHundredForDisplay&displayid=' . $row['displayid'],
                        'text' => __('Last 100 Log Messages')
                    );
            }

            if ($row['del'] == 1) {

                // Delete
                $row['buttons'][] = array(
                        'id' => 'display_button_delete',
                        'url' => 'index.php?p=display&q=DeleteForm&displayid=' . $row['displayid'],
                        'text' => __('Delete')
                    );
            }

            if ($row['modifypermissions'] == 1) {

                // Display Groups
                $row['buttons'][] = array(
                        'id' => 'display_button_group_membership',
                        'url' => 'index.php?p=display&q=MemberOfForm&DisplayID=' . $row['displayid'],
                        'text' => __('Display Groups')
                    );

                // Permissions
                $row['buttons'][] = array(
                        'id' => 'display_button_group_membership',
                        'url' => 'index.php?p=displaygroup&q=PermissionsForm&DisplayGroupID=' . $row['displaygroupid'],
                        'text' => __('Permissions')
                    );

                // Version Information
                $row['buttons'][] = array(
                        'id' => 'display_button_version_instructions',
                        'url' => 'index.php?p=displaygroup&q=VersionInstructionsForm&displaygroupid=' . $row['displaygroupid'] . '&displayid=' . $row['displayid'],
                        'text' => __('Version Information')
                    );
            }

            // Assign this to the table row
            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        $output = Theme::RenderReturn('display_page_grid');

        $response->SetGridResponse($output);
        $response->Respond();
    }

    /**
     * Assess each Display to correctly set the logged in flag based on last accessed time
     * @return
     */
    function validateDisplays()
    {
        $db =& $this->db;

        // Get the global timeout (overrides the alert timeout on the display if 0
        $globalTimeout = Config::GetSetting('MAINTENANCE_ALERT_TOUT');

        // Get a list of all displays and there last accessed / alert timeout value
        $SQL  = "";
        $SQL .= "SELECT displayid, lastaccessed, alert_timeout FROM display ";

        if (!$result =$db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to access displays'), E_USER_ERROR);
        }

        // Look through each display
        while($row = $db->get_assoc_row($result))
        {
            $displayid    = Kit::ValidateParam($row['displayid'], _INT);
            $lastAccessed = Kit::ValidateParam($row['lastaccessed'], _INT);
            $alertTimeout = Kit::ValidateParam($row['alert_timeout'], _INT);

            // Do we need to update the logged in light?
            $timeoutToTestAgainst = ($alertTimeout == 0) ? $globalTimeout : $alertTimeout;

            // If the last time we accessed is less than now minus the timeout
            if ($lastAccessed < time() - ($timeoutToTestAgainst * 60))
            {
                // Update the display and set it as logged out
                $SQL = "UPDATE display SET loggedin = 0 WHERE displayid = " . $displayid;

                if ((!$db->query($SQL)))
                    trigger_error($db->error());

                Debug::LogEntry('audit', sprintf('LastAccessed = %d, Timeout = %d for displayId %d', $lastAccessed, $timeoutToTestAgainst, $displayid));
            }
        }
    }

    /**
     * Delete form
     */
    function DeleteForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $displayid = Kit::GetParam('displayid', _REQUEST, _INT);

        // Auth
        $auth = $this->user->DisplayGroupAuth($this->GetDisplayGroupId($displayid), true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to edit this display'), E_USER_ERROR);

        Theme::Set('form_id', 'DisplayDeleteForm');
        Theme::Set('form_action', 'index.php?p=display&q=Delete');
        Theme::Set('form_meta', '<input type="hidden" name="displayid" value="' . $displayid . '">');

        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to delete this display? This cannot be undone.'))));

        $response->SetFormRequestResponse(NULL, __('Delete this Display?'), '350px', '210');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Display', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#DisplayDeleteForm").submit()');
        $response->Respond();
    }

    /**
     * Delete a display
     */
    function Delete()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();
        $displayid = Kit::GetParam('displayid', _POST, _INT, 0);

        $auth = $this->user->DisplayGroupAuth($this->GetDisplayGroupId($displayid), true);
        if (!$auth->del)
            trigger_error(__('You do not have permission to edit this display'), E_USER_ERROR);

        if ($displayid == 0)
            trigger_error(__("No Display selected for Deletion."));

        $displayObject = new Display($db);

        if (!$displayObject->Delete($displayid))
            trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__("The Display has been Deleted"));
        $response->Respond();
    }

    /**
     * Form for editing the default layout of a display
     */
    public function DefaultLayoutForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        $displayId = Kit::GetParam('DisplayId', _GET, _INT);

        $auth = $this->user->DisplayGroupAuth($this->GetDisplayGroupId($displayId), true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display'), E_USER_ERROR);

        if (!$defaultLayoutId = $this->db->GetSingleValue(sprintf("SELECT defaultlayoutid FROM display WHERE displayid = %d", $displayId), 'defaultlayoutid', _INT))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get the default layout'), E_USER_ERROR);
        }

        Theme::Set('form_id', 'DefaultLayoutForm');
        Theme::Set('form_action', 'index.php?p=display&q=DefaultLayout');
        Theme::Set('form_meta', '<input type="hidden" name="DisplayId" value="' . $displayId . '">');

        $formFields = array();
        $formFields[] = FormManager::AddCombo(
                    'defaultlayoutid', 
                    __('Default Layout'), 
                    $defaultLayoutId,
                    $this->user->LayoutList(),
                    'layoutid',
                    'layout',
                    __('The Default Layout will be shown there are no other scheduled Layouts. It is usually a full screen logo or holding image.'), 
                    'd');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Edit Default Layout'), '300px', '150px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Display', 'DefaultLayout') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DefaultLayoutForm").submit()');
        $response->Respond();
    }

    /**
     * Edit the default layout for a display
     */
    public function DefaultLayout()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();
        $displayObject  = new Display($db);

        $displayId = Kit::GetParam('DisplayId', _POST, _INT);
        $defaultLayoutId = Kit::GetParam('defaultlayoutid', _POST, _INT);

        $auth = $this->user->DisplayGroupAuth($this->GetDisplayGroupId($displayId), true);
        if (!$auth->edit)
            trigger_error(__('You do not have permission to edit this display'), E_USER_ERROR);

        if (!$displayObject->EditDefaultLayout($displayId, $defaultLayoutId))
            trigger_error(__('Cannot Edit this Display'), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Display Saved.'));
        $response->Respond();
    }

    /**
     * Shows the inventory XML for the display
     */
    public function MediaInventory()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $displayId = Kit::GetParam('DisplayId', _GET, _INT);

        $auth = $this->user->DisplayGroupAuth($this->GetDisplayGroupId($displayId), true);
        if (!$auth->view)
            trigger_error(__('You do not have permission to view this display'), E_USER_ERROR);

        if ($displayId == 0)
            trigger_error(__('No DisplayId Given'));

        // Get the media inventory xml for this display
        $SQL = "SELECT IFNULL(MediaInventoryXml, '<xml></xml>') AS MediaInventoryXml FROM display WHERE DisplayId = %d";
        $SQL = sprintf($SQL, $displayId);

        if (!$mediaInventoryXml = $db->GetSingleValue($SQL, 'MediaInventoryXml', _HTMLSTRING))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get the Inventory for this Display'), E_USER_ERROR);
        }

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");

        if (!$document->loadXML($mediaInventoryXml))
            trigger_error(__('Invalid Media Inventory'), E_USER_ERROR);

        // Need to parse the XML and return a set of rows
        $xpath = new DOMXPath($document);
        $fileNodes = $xpath->query("//file");

        $rows = array();

        foreach ($fileNodes as $node)
        {
            $row = array();
            $row['type'] = $node->getAttribute('type');
            $row['id'] = $node->getAttribute('id');
            $row['complete'] = ($node->getAttribute('complete') == 0) ? __('No') : __('Yes');
            $row['last_checked'] = $node->getAttribute('lastChecked');
            $row['md5'] = $node->getAttribute('md5');

            $rows[] = $row;
        }

        // Store the table rows
        Theme::Set('table_rows', $rows);

        // Initialise the theme and capture the output
        $output = Theme::RenderReturn('display_form_mediainventory');

        $response->SetFormRequestResponse($output, __('Media Inventory'), '550px', '350px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Display', 'MediaInventory') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->Respond();
    }

    /**
     * Get DisplayGroupID
     * @param <type> $displayId
     */
    private function GetDisplayGroupId($displayId)
    {
        $sql = "SELECT displaygroup.DisplayGroupID ";
        $sql .= "  FROM `displaygroup` ";
        $sql .= "   INNER JOIN `lkdisplaydg` ON lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
        $sql .= " WHERE displaygroup.IsDisplaySpecific = 1 AND lkdisplaydg.DisplayID = %d";

        if (!$id = $this->db->GetSingleValue(sprintf($sql, $displayId), 'DisplayGroupID', _INT))
        {
            trigger_error($this->db->error());
            trigger_error(__('Unable to determine permissions'), E_USER_ERROR);
        }

        return $id;
    }

    /**
     * Member of Display Groups Form
     */
    public function MemberOfForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $displayID  = Kit::GetParam('DisplayID', _REQUEST, _INT);

        // Auth
        $auth = $this->user->DisplayGroupAuth($this->GetDisplayGroupId($displayID), true);
        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permission to change Display Groups on this display'), E_USER_ERROR);

        // There needs to be two lists here.
        //  - DisplayGroups this Display is already assigned to
        //  - DisplayGroups this Display could be assigned to

        // Set some information about the form
        Theme::Set('displaygroups_assigned_id', 'displaysIn');
        Theme::Set('displaygroups_available_id', 'displaysOut');
        Theme::Set('displaygroups_assigned_url', 'index.php?p=display&q=SetMemberOf&DisplayID=' . $displayID);

        // Display Groups Assigned
        $SQL  = "";
        $SQL .= "SELECT displaygroup.DisplayGroupID, ";
        $SQL .= "       displaygroup.DisplayGroup, ";
        $SQL .= "       CONCAT('DisplayGroupID_', displaygroup.DisplayGroupID) AS list_id ";
        $SQL .= "FROM   displaygroup ";
        $SQL .= "   INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
        $SQL .= sprintf("WHERE  lkdisplaydg.DisplayID   = %d ", $displayID);
        $SQL .= " AND displaygroup.IsDisplaySpecific = 0 ";
        $SQL .= " ORDER BY displaygroup.DisplayGroup ";

        $displaygroupsAssigned = $db->GetArray($SQL);

        if (!is_array($displaygroupsAssigned))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Display Groups'), E_USER_ERROR);
        }

        Theme::Set('displaygroups_assigned', $displaygroupsAssigned);

        // Display Groups not assigned
        $SQL  = "";
        $SQL .= "SELECT displaygroup.DisplayGroupID, ";
        $SQL .= "       displaygroup.DisplayGroup, ";
        $SQL .= "       CONCAT('DisplayGroupID_', displaygroup.DisplayGroupID) AS list_id ";
        $SQL .= "  FROM displaygroup ";
        $SQL .= " WHERE displaygroup.IsDisplaySpecific = 0 ";
        $SQL .= " AND displaygroup.DisplayGroupID NOT IN ";
        $SQL .= "       (SELECT lkdisplaydg.DisplayGroupID ";
        $SQL .= "          FROM lkdisplaydg ";
        $SQL .= sprintf(" WHERE  lkdisplaydg.DisplayID   = %d ", $displayID);
        $SQL .= "       )";
        $SQL .= " ORDER BY displaygroup.DisplayGroup ";

        Debug::LogEntry('audit', $SQL);

        $displaygroups_available = $db->GetArray($SQL);

        if (!is_array($displaygroups_available))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Display Groups'), E_USER_ERROR);
        }
        
        Theme::Set('displaygroups_available', $displaygroups_available);

        // Render the theme
        $form = Theme::RenderReturn('display_form_group_assign');

        $response->SetFormRequestResponse($form, __('Manage Membership'), '400', '375', 'DisplayGroupManageMembersCallBack');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DisplayGroup', 'Members') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), 'DisplayGroupMembersSubmit()');
        $response->Respond();
    }

    /**
     * Sets the Members of a group
     * @return
     */
    public function SetMemberOf()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        Kit::ClassLoader('displaygroup');
        $displayGroupObject = new DisplayGroup($db);

        $displayID = Kit::GetParam('DisplayID', _REQUEST, _INT);
        $displayGroups = Kit::GetParam('DisplayGroupID', _POST, _ARRAY, array());
        $members = array();

        // Get a list of current members
        $SQL  = "";
        $SQL .= "SELECT displaygroup.DisplayGroupID ";
        $SQL .= "FROM   displaygroup ";
        $SQL .= "   INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
        $SQL .= sprintf("WHERE  lkdisplaydg.DisplayID   = %d ", $displayID);
        $SQL .= " AND displaygroup.IsDisplaySpecific = 0 ";

        if(!$resultIn = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting Display Groups'), E_USER_ERROR);
        }

        while($row = $db->get_assoc_row($resultIn))
        {
            // Test whether this ID is in the array or not
            $displayGroupID = Kit::ValidateParam($row['DisplayGroupID'], _INT);

            if(!in_array($displayGroupID, $displayGroups))
            {
                // Its currently assigned but not in the $displays array
                //  so we unassign
                if (!$displayGroupObject->Unlink($displayGroupID, $displayID))
                {
                    trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
                }
            }
            else
            {
                $members[] = $displayGroupID;
            }
        }

        foreach($displayGroups as $displayGroupID)
        {
            // Add any that are missing
            if(!in_array($displayGroupID, $members))
            {
                if (!$displayGroupObject->Link($displayGroupID, $displayID))
                {
                    trigger_error($displayGroupObject->GetErrorMessage(), E_USER_ERROR);
                }
            }
        }

        $response->SetFormSubmitResponse(__('Group membership set'), false);
        $response->Respond();
    }

    /**
     * Form for wake on Lan
     */
    public function WakeOnLanForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();

        $displayId = Kit::GetParam('DisplayId', _GET, _INT);

        // Get the MAC Address
        $macAddress = $db->GetSingleValue(sprintf("SELECT MacAddress FROM `display` WHERE DisplayID = %d", $displayId), 'MacAddress', _STRING);

        if (!$macAddress || $macAddress == '')
            trigger_error(__('This display has no mac address recorded against it yet. Make sure the display is running.'), E_USER_ERROR);

        // Set some information about the form
        Theme::Set('form_id', 'WakeOnLanForm');
        Theme::Set('form_action', 'index.php?p=display&q=WakeOnLan');
        Theme::Set('form_meta', '<input type="hidden" name="DisplayId" value="' . $displayId . '"><input type="hidden" name="MacAddress" value="' . $macAddress . '">');

        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to send a Wake On Lan message to this display?'))));

        $response->SetFormRequestResponse(NULL, __('Wake On Lan'), '300px', '250px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Send'), '$("#WakeOnLanForm").submit()');
        $response->Respond();
    }

    /**
     * Wake on LAN
     */
    public function WakeOnLan()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $response = new ResponseManager();
        $displayObject  = new Display($db);

        $displayId = Kit::GetParam('DisplayId', _POST, _INT);

        if (!$displayObject->WakeOnLan($displayId))
            trigger_error($displayObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Wake on Lan command sent.'));
        $response->Respond();
    }
}
?>
