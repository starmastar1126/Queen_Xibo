<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Daniel Garner
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
define('BLACKLIST_ALL', "All");
define('BLACKLIST_SINGLE', "Single");

class XMDSSoap
{
    private $db;

    private $licensed;
    private $includeSchedule;
    private $isAuditing;
    private $displayId;

    public function __construct()
    {
        global $db;
        $this->db =& $db;
    }

    /**
     * Registers a new display
     * @param <type> $serverKey
     * @param <type> $hardwareKey
     * @param <type> $displayName
     * @param <type> $version
     * @return <type>
     */
    public function RegisterDisplay($serverKey, $hardwareKey, $displayName, $version)
    {
	$db =& $this->db;
	define('SERVER_KEY', Config::GetSetting($db, 'SERVER_KEY'));

	// Sanitize
	$serverKey 	= Kit::ValidateParam($serverKey, _STRING);
	$hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
	$displayName 	= Kit::ValidateParam($displayName, _STRING);
	$version 	= Kit::ValidateParam($version, _STRING);

	// Make sure we are talking the same language
	if (!$this->CheckVersion($version))
            throw new SoapFault('Sender', 'Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk');

	Debug::LogEntry($db, "audit", "[IN]", "xmds", "RegisterDisplay");
	Debug::LogEntry($db, "audit", "serverKey [$serverKey], hardwareKey [$hardwareKey], displayName [$displayName]", "xmds", "RegisterDisplay");

	// Check the serverKey matches the one we have stored in this servers lic.txt file
	if ($serverKey != SERVER_KEY)
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

	// Check the Length of the hardwareKey
	if (strlen($hardwareKey) > 40)
            throw new SoapFault('Sender', 'The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).');

	// Check in the database for this hardwareKey
	$SQL = "SELECT licensed, display FROM display WHERE license = '$hardwareKey'";

	if (!$result = $db->query($SQL))
	{
            trigger_error("License key query failed:" . $db->error());
            throw new SoapFault('Sender', 'Cannot check client key.');
	}

	// Use a display object to Add or Edit the display
	$displayObject = new Display($db);

	// Is it there?
	if ($db->num_rows($result) == 0)
	{
            // Get the default layout id
            $defaultLayoutId = 4;

            // Add this display record
            if (!$displayid = $displayObject->Add($displayName, 0, $defaultLayoutId, $hardwareKey, 0, 0))
                throw new SoapFault('Sender', 'Error adding display');

            $active = 'Display added and is awaiting licensing approval from an Administrator';
	}
	else
	{
            // We have seen this display before, so check the licensed value
            $row = $db->get_row($result);

            if ($row[0] == 0)
            {
                // It is not licensed
                $active = 'Display is awaiting licensing approval from an Administrator.';
            }
            else
            {
                // It is licensed
                $active = 'Display is active and ready to start.';
            }
	}

	Debug::LogEntry($db, "audit", "$active", "xmds", "RegisterDisplay");
	Debug::LogEntry($db, "audit", "[OUT]", "xmds", "RegisterDisplay");

	return $active;
    }

    /**
     * Returns a string containing the required files xml for the requesting display
     * @param string $hardwareKey Display Hardware Key
     * @return string $requiredXml Xml Formatted String
     */
    function RequiredFiles($serverKey, $hardwareKey, $version)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey 	= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $version 	= Kit::ValidateParam($version, _STRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
        {
            throw new SoapFault('Sender', 'Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk');
        }

        $libraryLocation = Config::GetSetting($db, "LIBRARY_LOCATION");

        // auth this request...
        if (!$this->AuthDisplay($hardwareKey))
        {
            throw new SoapFault('Sender', 'This display is not licensed.');
        }

        if ($this->isAuditing == 1)
        {
            Debug::LogEntry($db, "audit", "[IN]", "xmds", "RequiredFiles");
            Debug::LogEntry($db, "audit", "$hardwareKey", "xmds", "RequiredFiles");
        }

        $requiredFilesXml = new DOMDocument("1.0");
        $fileElements 	= $requiredFilesXml->createElement("files");

        $requiredFilesXml->appendChild($fileElements);

        $currentdate 	= time();
        $infinityFromDT = mktime(0,0,0,1,1,2000);
        $infinityToDT	= mktime(0,0,0,12,31,2050);
        $plus4hours 	= $currentdate + 86400;

        //Add file nodes to the $fileElements
        //Firstly get all the scheduled layouts
        $SQL  = " SELECT DISTINCT layout.layoutID, layout.xml, layout.background ";
        $SQL .= " FROM layout ";
        $SQL .= " INNER JOIN schedule_detail ON schedule_detail.layoutID = layout.layoutID ";
        $SQL .= " INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = schedule_detail.DisplayGroupID ";
        $SQL .= " INNER JOIN display ON lkdisplaydg.DisplayID = display.displayID ";
        $SQL .= sprintf(" WHERE display.license = '%s'  ", $hardwareKey);

        $SQLBase = $SQL;

        // Do we include the default display
        if ($this->includeSchedule == 1)
        {
            $SQL .= sprintf(" AND ((schedule_detail.FromDT < %d AND schedule_detail.ToDT > %d )", $plus4hours, $currentdate);
            $SQL .= sprintf(" OR (schedule_detail.FromDT = %d AND schedule_detail.ToDT = %d ))", $infinityFromDT, $infinityToDT);
        }
        else
        {
            $SQL .= sprintf(" AND (schedule_detail.FromDT < %d AND schedule_detail.ToDT > %d )", $plus4hours, $currentdate);
        }

        if ($this->isAuditing == 1) Debug::LogEntry($db, "audit", "$SQL", "xmds", "RequiredFiles");

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            return new SoapFault('Sender', 'Unable to get a list of files');
        }

        // Was there anything?
        if ($db->num_rows($results) == 0)
        {
            // No rows, run the query for default layout
            $SQL  = $SQLBase;
            $SQL .= sprintf(" AND ((schedule_detail.FromDT < %d AND schedule_detail.ToDT > %d )", $plus4hours, $currentdate);
            $SQL .= sprintf(" OR (schedule_detail.FromDT = %d AND schedule_detail.ToDT = %d ))", $infinityFromDT, $infinityToDT);

            if ($this->isAuditing == 1) Debug::LogEntry($db, "audit", "$SQL", "xmds", "RequiredFiles");

            if (!$results = $db->query($SQL))
            {
                trigger_error($db->error());
                return new SoapFault('Sender', 'Unable to get A list of layouts for the schedule');
            }
        }

        while ($row = $db->get_row($results))
        {
            $layoutid = $row[0];
            $layoutXml = $row[1];
            $background = $row[2];

            // Add all the associated media first
            $SQL = "SELECT storedAs, media.mediaID, media.`MD5`, media.FileSize
                FROM media
                INNER JOIN lklayoutmedia ON lklayoutmedia.mediaID = media.mediaID
                WHERE storedAs IS NOT NULL
                AND lklayoutmedia.layoutID = $layoutid
                AND media.mediaID NOT IN (SELECT MediaID
                      FROM blacklist
                      WHERE DisplayID = " . $this->displayId . "
                      AND isIgnored = 0 )";

            if (!$mediaResults = $db->query($SQL))
            {
                trigger_error($db->error());
                return new SoapFault('Sender', "Unable to get a list of media for the layout [$layoutid]");
            }

            while ($row = $db->get_row($mediaResults))
            {
                $storedAs	= Kit::ValidateParam($row[0], _STRING);
                $mediaId	= Kit::ValidateParam($row[1], _INT);
                $md5		= Kit::ValidateParam($row[2], _STRING);
                $fileSize	= Kit::ValidateParam($row[3], _INT);

                // If they are empty calculate them and save them back to the media.
                if ($md5 == '' || $fileSize == 0)
                {
                    $md5 	= md5_file($libraryLocation.$row[0]);
                    $fileSize	= filesize($libraryLocation.$row[0]);

                    // Update the media record with this information
                    $SQL = sprintf("UPDATE media SET `MD5` = '%s', FileSize = %d WHERE MediaID = %d", $md5, $fileSize, $mediaId);

                    if (!$db->query($SQL))
                            trigger_error($db->error());
                }

                //Add the file node
                $file = $requiredFilesXml->createElement("file");

                $file->setAttribute("type", "media");
                $file->setAttribute("path", $storedAs);
                $file->setAttribute("id",	$mediaId);
                $file->setAttribute("size", $fileSize);
                $file->setAttribute("md5", $md5);

                $fileElements->appendChild($file);
            }

            //Also append another file node for the background image (if there is one)
            if ($background != "")
            {
                //firstly add this as a node
                $file = $requiredFilesXml->createElement("file");

                $file->setAttribute("type", "media");
                $file->setAttribute("path", $background);
                $file->setAttribute("md5", md5_file($libraryLocation.$background));
                $file->setAttribute("size", filesize($libraryLocation.$background));

                $fileElements->appendChild($file);
            }

            // Add this layout as node
            $file = $requiredFilesXml->createElement("file");

            $file->setAttribute("type", "layout");
            $file->setAttribute("path", $layoutid);
            $file->setAttribute("md5", md5($layoutXml . "\n"));

            $fileElements->appendChild($file);
        }

        // Add a blacklist node
        $blackList = $requiredFilesXml->createElement("file");
        $blackList->setAttribute("type", "blacklist");

        $fileElements->appendChild($blackList);

        // Populate
        $SQL = "SELECT MediaID
                FROM blacklist
                WHERE DisplayID = " . $this->displayId . "
                AND isIgnored = 0";

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            return new SoapFault('Sender', 'Unable to get a list of blacklisted files');
        }

        // Add a black list element for each file
        while ($row = $db->get_row($results))
        {
            $file = $requiredFilesXml->createElement("file");
            $file->setAttribute("id", $row[0]);

            $blackList->appendChild($file);
        }

        // PHONE_HOME if required.
        if (Config::GetSetting($db,'PHONE_HOME') == 'On')
        {
            // Find out when we last PHONED_HOME :D
            // If it's been > 28 days since last PHONE_HOME then
            if (Config::GetSetting($db,'PHONE_HOME_DATE') < (time() - (60 * 60 * 24 * 28)))
            {
                if ($this->isAuditing == 1)
                {
                    Debug::LogEntry($db, "audit", "PHONE_HOME [IN]", "xmds", "RequiredFiles");
                }

                // Retrieve number of displays
                $SQL = "SELECT COUNT(*)
                        FROM `display`
                        WHERE `licensed` = '1'";

                if (!$results = $db->query($SQL))
                {
                    trigger_error($db->error());
                }
                while ($row = $db->get_row($results))
                {
                    $PHONE_HOME_CLIENTS = Kit::ValidateParam($row[0],_INT);
                }

                // Retrieve version number
                $PHONE_HOME_VERSION = Config::Version($db, 'app_ver');

                $PHONE_HOME_URL = Config::GetSetting($db,'PHONE_HOME_URL') . "?id=" . urlencode(Config::GetSetting($db,'PHONE_HOME_KEY')) . "&version=" . urlencode($PHONE_HOME_VERSION) . "&numClients=" . urlencode($PHONE_HOME_CLIENTS);

                if ($this->isAuditing == 1)
                {
                    Debug::LogEntry($db, "audit", "PHONE_HOME_URL " . $PHONE_HOME_URL , "xmds", "RequiredFiles");
                }

                // Set PHONE_HOME_TIME to NOW.
                $SQL = "UPDATE `setting`
                        SET `value` = '" . time() . "'
                        WHERE `setting`.`setting` = 'PHONE_HOME_DATE' LIMIT 1";

                if (!$results = $db->query($SQL))
                {
                    trigger_error($db->error());
                }

                @file_get_contents($PHONE_HOME_URL);

                if ($this->isAuditing == 1)
                {
                    Debug::LogEntry($db, "audit", "PHONE_HOME [OUT]", "xmds", "RequiredFiles");
                }
            //endif
            }
        }
        // END OF PHONE_HOME CODE

        if ($this->isAuditing == 1)
        {
            Debug::LogEntry($db, "audit", $requiredFilesXml->saveXML(), "xmds", "RequiredFiles");
            Debug::LogEntry($db, "audit", "[OUT]", "xmds", "RequiredFiles");
        }

        // Return the results of requiredFiles()
        $requiredFilesXml->formatOutput = true;

        return $requiredFilesXml->saveXML();
    }

    /**
     * Gets the specified file
     * @return
     * @param $hardwareKey Object
     * @param $filePath Object
     * @param $fileType Object
     */
    function GetFile($serverKey, $hardwareKey, $filePath, $fileType, $chunkOffset, $chunkSize, $version)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey 	= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $fileType 	= Kit::ValidateParam($fileType, _WORD);
        $chunkOffset 	= Kit::ValidateParam($chunkOffset, _INT);
        $chunkSize 	= Kit::ValidateParam($chunkSize, _INT);
        $version 	= Kit::ValidateParam($version, _STRING);

        $libraryLocation = Config::GetSetting($db, "LIBRARY_LOCATION");

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
        {
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk");
        }

        //auth this request...
        if (!$this->AuthDisplay($hardwareKey))
        {
            throw new SoapFault('Receiver', "This display client is not licensed");
        }

        if ($this->isAuditing == 1)
        {
            Debug::LogEntry($db, "audit", "[IN]", "xmds", "GetFile");
            Debug::LogEntry($db, "audit", "Params: [$hardwareKey] [$filePath] [$fileType] [$chunkOffset] [$chunkSize]", "xmds", "GetFile");
        }

        if ($fileType == "layout")
        {
            $filePath = Kit::ValidateParam($filePath, _INT);

            $SQL = sprintf("SELECT xml FROM layout WHERE layoutid = %d", $filePath);
            if (!$results = $db->query($SQL))
            {
                trigger_error($db->error());
                return new SoapFault('Receiver', 'Unable the find layout.');
            }

            $row = $db->get_row($results);
            $file = $row[0];
        }
        elseif ($fileType == "media")
        {
            $filePath = Kit::ValidateParam($filePath, _STRING);

            if (strstr($filePath, '/') || strstr($filePath, '\\'))
            {
                throw new SoapFault('Receiver', "Invalid file path.");
            }

            // Return the Chunk size specified
            $f = fopen($libraryLocation.$filePath,"r");

            fseek($f, $chunkOffset);

            $file = fread($f, $chunkSize);
        }
        else
        {
            throw new SoapFault('Receiver', 'Unknown FileType Requested.');
        }

        if ($this->isAuditing == 1) Debug::LogEntry($db, "audit", "[OUT]", "xmds", "GetFile");

        return base64_encode($file);
    }

    /**
     * Returns the schedule for the hardware key specified
     * @return
     * @param $hardwareKey Object
     */
    function Schedule($serverKey, $hardwareKey, $version)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey 		= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $version 		= Kit::ValidateParam($version, _STRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
        {
            throw new SoapFault('Sender', "Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk");
        }

        //auth this request...
        if (!$this->AuthDisplay($hardwareKey))
        {
            throw new SoapFault('Sender', "This display client is not licensed");
        }

        if ($this->isAuditing == 1) Debug::LogEntry($db, "audit", "[IN] $hardwareKey", "xmds", "Schedule");

        $scheduleXml = new DOMDocument("1.0");
        $layoutElements = $scheduleXml->createElement("schedule");

        $scheduleXml->appendChild($layoutElements);

        $currentdate 	= time();
        $infinityFromDT = mktime(0,0,0,1,1,2000);
        $infinityToDT	= mktime(0,0,0,12,31,2050);
        $plus4hours 	= $currentdate + 86400;

        //Add file nodes to the $fileElements
        //Firstly get all the scheduled layouts
        $SQL  = " SELECT layout.layoutID, schedule_detail.FromDT, schedule_detail.ToDT, schedule_detail.eventID ";
        $SQL .= " FROM layout ";
        $SQL .= " INNER JOIN schedule_detail ON schedule_detail.layoutID = layout.layoutID ";
        $SQL .= " INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = schedule_detail.DisplayGroupID ";
        $SQL .= " INNER JOIN display ON lkdisplaydg.DisplayID = display.displayID ";
        $SQL .= sprintf(" WHERE display.license = '%s'  ", $hardwareKey);
        $SQL .= "   AND layout.retired = 0  ";

        // Store the Base SQL for this display
        $SQLBase = $SQL;

        // Do we include the default display
        if ($this->includeSchedule == 1)
        {
            $SQL .= " AND ((schedule_detail.FromDT < $currentdate AND schedule_detail.ToDT > $currentdate )";
            $SQL .= sprintf(" OR (schedule_detail.FromDT = %d AND schedule_detail.ToDT = %d ))", $infinityFromDT, $infinityToDT);
        }
        else
        {
            $SQL .= sprintf(" AND (schedule_detail.FromDT < %d AND schedule_detail.ToDT > %d )", $currentdate, $currentdate);
        }

        // Before we run the main query we should check to see if there are any priority layouts to deal with
        $SQLp = " AND schedule_detail.is_priority = 1 ";

        if ($this->isAuditing == 1) Debug::LogEntry($db, "audit", "$SQL", "xmds", "Schedule");

        // Run the query
        if (!$results = $db->query($SQL . $SQLp))
        {
            trigger_error($db->error());
            return new SoapFault('Unable to get A list of layouts for the priority schedule');
        }

        // If there were no results then continue to get the full schedule
        if ($db->num_rows($results) == 0)
        {
            // Run the query
            if (!$results = $db->query($SQL))
            {
                trigger_error($db->error());
                return new SoapFault('Unable to get A list of layouts for the schedule');
            }

            // Was there anything?
            if ($db->num_rows($results) == 0)
            {
                // No rows, run the query for default layout
                $SQL  = $SQLBase;
                $SQL .= " AND ((schedule_detail.FromDT < $currentdate AND schedule_detail.ToDT > $currentdate )";
                $SQL .= sprintf(" OR (schedule_detail.FromDT = %d AND schedule_detail.ToDT = %d ))", $infinityFromDT, $infinityToDT);

                if ($this->isAuditing == 1) Debug::LogEntry($db, "audit", "TTTT: $SQL", "xmds", "Schedule");

                if (!$results = $db->query($SQL))
                {
                    trigger_error($db->error());
                    return new SoapFault("Unable to get A list of layouts for the last chance schedule");
                }
            }
        }

        // We must have some results in here by this point
        while ($row = $db->get_row($results))
        {
            $layoutid 	= $row[0];
            $fromdt 	= date('Y-m-d h:i:s', $row[1]);
            $todt	= date('Y-m-d h:i:s', $row[2]);
            $scheduleid = $row[3];

            //firstly add this as a node
            $layout = $scheduleXml->createElement("layout");

            $layout->setAttribute("file", $layoutid);
            $layout->setAttribute("fromdt", $fromdt);
            $layout->setAttribute("todt", $todt);
            $layout->setAttribute("scheduleid", $scheduleid);

            $layoutElements->appendChild($layout);
        }

        if ($this->isAuditing == 1) Debug::LogEntry($db, "audit", $scheduleXml->saveXML(), "xmds", "Schedule");
        if ($this->isAuditing == 1) Debug::LogEntry($db, "audit", "[OUT]", "xmds", "Schedule");

        $scheduleXml->formatOutput = true;
        return $scheduleXml->saveXML();
    }

    /**
     *
     * @return
     * @param $hardwareKey Object
     * @param $mediaId Object
     * @param $type Object
     */
    function BlackList($serverKey, $hardwareKey, $mediaId, $type, $reason, $version)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey 		= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $mediaId	 	= Kit::ValidateParam($mediaId, _STRING);
        $type		 	= Kit::ValidateParam($type, _STRING);
        $reason		 	= Kit::ValidateParam($reason, _STRING);
        $version 		= Kit::ValidateParam($version, _STRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
        {
                throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk", $serverKey);
        }

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
        {
                throw new SoapFault('Receiver', "This display client is not licensed", $hardwareKey);
        }

        if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", "[IN]", "xmds", "BlackList", "", $this->displayId);
        if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", "$xml", "xmds", "BlackList", "", $this->displayId);

        // Check to see if this media/display is already blacklisted (and not ignored)
        $SQL = "SELECT BlackListID FROM blacklist WHERE MediaID = $mediaId AND isIgnored = 0 AND DisplayID = " . $this->displayId;

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            return new SoapFault("Unable to query for BlackList records.");
        }

        if ($db->num_rows($results) == 0)
        {
            // Insert the black list record
            $SQL = "SELECT displayID FROM display";

            if ($type == BLACKLIST_SINGLE)
                $SQL .= " WHERE displayID = " . $this->displayId;

            if (!$displays = $db->query($SQL))
            {
                trigger_error($db->error());
                return new SoapFault("Unable to query for BlackList Displays.");
            }

            while ($row = $db->get_row($displays))
            {
                $displayId = $row[0];

                $SQL = "INSERT INTO blacklist (MediaID, DisplayID, ReportingDisplayID, Reason)
                            VALUES ($mediaId, $displayId, " . $this->displayId . ", '$reason') ";

                if (!$db->query($SQL))
                {
                    trigger_error($db->error());
                    return new SoapFault("Unable to insert BlackList records.");
                }
            }
        }
        else
        {
            if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", "Media Already BlackListed [$mediaId]", "xmds", "BlackList", "", $this->displayId);
        }

        if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", "[OUT]", "xmds", "BlackList", "", $this->displayId);

        return true;
    }

    /**
     * Submit client logging
     * @return
     * @param $version Object
     * @param $serverKey Object
     * @param $hardwareKey Object
     * @param $logXml Object
     */
    function SubmitLog($version, $serverKey, $hardwareKey, $logXml)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey 	= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $version 	= Kit::ValidateParam($version, _STRING);
        $logXml 	= Kit::ValidateParam($logXml, _HTMLSTRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
        {
            throw new SoapFault('Sender', "Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk");
        }

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
        {
            throw new SoapFault('Sender', 'This display client is not licensed.');
        }

        if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", "IN", "xmds", "SubmitLog", "", $this->displayId);
        if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", 'XML [' . $logXml . ']', "xmds", "SubmitLog", "", $this->displayId);

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");

        if (!$document->loadXML($logXml))
        {
            throw new SoapFault('Receiver', "XML Cannot be loaded into DOM Document.");
        }

        foreach ($document->documentElement->childNodes as $node)
        {
            // Make sure we dont consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE) continue;

            //Zero out the common vars
            $date 		= "";
            $message 	= "";
            $scheduleID = "";
            $layoutID 	= "";
            $mediaID 	= "";
            $cat		= '';
            $method		= '';

            // This will be a bunch of trace nodes
            $message = $node->textContent;

            if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", 'Trace Message: [' . $message . ']', "xmds", "SubmitLog", "", $this->displayId);

            // Each element should have a category and a date

            $date	= $node->getAttribute('date');
            $cat	= $node->getAttribute('category');

            if ($date == '' || $cat == '')
            {
                trigger_error('Log submitted without a date or category attribute');
                continue;
            }

            // Get the date and the message (all log types have these)
            foreach ($node->childNodes as $nodeElements)
            {
                if ($nodeElements->nodeName == "scheduleID")
                {
                    $scheduleID = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "layoutID")
                {
                    $layoutID = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "mediaID")
                {
                    $mediaID = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "type")
                {
                    $type = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "method")
                {
                    $method	= $nodeElements->textContent;
                }
            }

            // We should have enough information to log this now.
            if ($cat == 'error' || $cat == 'Error')
            {
                Debug::LogEntry($db, $cat, $message, 'Client', $method, $date, $this->displayId, $scheduleID, $layoutID, $mediaID);
            }
            else
            {
                Debug::LogEntry($db, 'audit', $message, 'Client', $method, $date, $this->displayId, $scheduleID, $layoutID, $mediaID);
            }
        }

        if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", "OUT", "xmds", "SubmitLog", "", $this->displayId);

        return true;
    }

    /**
     * Submit display statistics to the server
     * @return
     * @param $version Object
     * @param $serverKey Object
     * @param $hardwareKey Object
     * @param $statXml Object
     */
    function SubmitStats($version, $serverKey, $hardwareKey, $statXml)
    {
        $db =& $this->db;

        // Sanitize
        $serverKey 		= Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey 	= Kit::ValidateParam($hardwareKey, _STRING);
        $version 		= Kit::ValidateParam($version, _STRING);
        $statXml 		= Kit::ValidateParam($statXml, _HTMLSTRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
        {
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server. You can get the latest from http://www.xibo.org.uk");
        }

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
        {
            throw new SoapFault('Receiver', "This display client is not licensed");
        }

        if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", "IN", "xmds", "SubmitStats", "", $this->displayId);
        if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", "StatXml: [" . $statXml . "]", "xmds", "SubmitStats", "", $this->displayId);

        if ($statXml == "")
        {
            throw new SoapFault('Receiver', "Stat XML is empty.");
        }

        // Log
        if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", "About to create Stat Object.", "xmds", "SubmitStats", "", $this->displayId);

        $statObject = new Stat($db);

        // Log
        if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", "About to Create DOMDocument.", "xmds", "SubmitStats", "", $this->displayId);

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");
        $document->loadXML($statXml);

        foreach ($document->documentElement->childNodes as $node)
        {
            // Make sure we dont consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE) continue;

            //Zero out the common vars
            $fromdt	= '';
            $todt	= '';
            $type	= '';

            $scheduleID = 0;
            $layoutID 	= 0;
            $mediaID 	= '';
            $tag	= '';

            // Each element should have these attributes
            $fromdt	= $node->getAttribute('fromdt');
            $todt	= $node->getAttribute('todt');
            $type	= $node->getAttribute('type');

            if ($fromdt == '' || $todt == '' || $type == '')
            {
                trigger_error('Stat submitted without the fromdt, todt or type attributes.');
                continue;
            }

            $scheduleID	= $node->getAttribute('scheduleid');
            $layoutID	= $node->getAttribute('layoutid');
            $mediaID	= $node->getAttribute('mediaid');
            $tag	= $node->getAttribute('tag');

            // Write the stat record with the information we have available to us.
            if (!$statObject->Add($type, $fromdt, $todt, $scheduleID, $this->displayId, $layoutID, $mediaID, $tag))
            {
                trigger_error(sprintf('Stat Add failed with error: %s', $statObject->GetErrorMessage()));
                continue;
            }
        }

        if ($this->isAuditing == 1) Debug::LogEntry ($db, "audit", "OUT", "xmds", "SubmitStats", "", $this->displayId);

        return true;
    }

    /**
     * Authenticates the display
     * @param <type> $hardwareKey
     * @return <type>
     */
    private function AuthDisplay($hardwareKey)
    {
	$db =& $this->db;

	// check in the database for this hardwareKey
	$SQL = "SELECT licensed, inc_schedule, isAuditing, displayID FROM display WHERE license = '$hardwareKey'";

        if (!$result = $db->query($SQL))
	{
            trigger_error("License key query failed:" .$db->error());
            return false;
	}

	//Is it there?
	if ($db->num_rows($result) == 0)
            return false;
	
        //we have seen this display before, so check the licensed value
        $row = $db->get_row($result);

        if ($row[0] == 0)
            return false;

        // Last accessed date on the display
        $displayObject = new Display($db);
        $displayObject->Touch($hardwareKey);

        // It is licensed?
        $this->licensed = true;
        $this->includeSchedule = $row[1];
        $this->isAuditing = $row[2];
        $this->displayId = $row[3];
        
        return true;
    }

    /**
     * Checks that the calling service is talking the correct version
     * @return
     * @param $version Object
     */
    private function CheckVersion($version)
    {
        $db =& $this->db;

        // Look up the Service XMDS version from the Version table
        $serverVersion = Config::Version($db, 'XmdsVersion');

        if ($version != $serverVersion)
        {
            Debug::LogEntry($db, 'audit', sprintf('A Client with an incorrect version connected. Client Version: [%s] Server Version [%s]', $version, $serverVersion));
            return false;
        }

        return true;
    }
}
?>
