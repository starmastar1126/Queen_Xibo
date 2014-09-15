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
define('BLACKLIST_ALL', "All");
define('BLACKLIST_SINGLE', "Single");

class XMDSSoap {
    
    private $licensed;
    private $includeSchedule;
    private $isAuditing;
    private $displayId;
    private $defaultLayoutId;
    private $version_instructions;
    private $clientType;
    private $clientVersion;
    private $clientCode;

    // Unfortunately we still need this for this data classes that require a DB
    private $db;
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
    public function RegisterDisplay($serverKey, $hardwareKey, $displayName, $clientType, $clientVersion, $clientCode, $macAddress, $version) {
    
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $displayName = Kit::ValidateParam($displayName, _STRING);
        $clientType = Kit::ValidateParam($clientType, _STRING);
        $clientVersion = Kit::ValidateParam($clientVersion, _STRING);
        $clientCode = Kit::ValidateParam($clientCode, _INT);
        $macAddress = Kit::ValidateParam($macAddress, _STRING);
        $version = Kit::ValidateParam($version, _STRING);
        $clientAddress = Kit::GetParam('REMOTE_ADDR', $_SERVER, _STRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Sender', 'Your client is not of the correct version for communication with this server.');

        Debug::LogEntry('audit', "[IN] serverKey [$serverKey], hardwareKey [$hardwareKey], displayName [$displayName]", 'xmds', 'RegisterDisplay');

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Check the Length of the hardwareKey
        if (strlen($hardwareKey) > 40)
            throw new SoapFault('Sender', 'The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).');

        // Check in the database for this hardwareKey
        try {
            $dbh = PDOConnect::init();
            $sth = $dbh->prepare('
                SELECT licensed, display, displayid, displayprofileid, client_type, version_instructions
                  FROM display 
                WHERE license = :hardwareKey');

            $sth->execute(array(
                   'hardwareKey' => $hardwareKey
                ));
            
            $result = $sth->fetchAll();
        }
        catch (Exception $e) {
         
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
            throw new SoapFault('Sender', 'Cannot check client key.');
        }

        // Use a display object to Add or Edit the display
        $displayObject = new Display();

        // Return an XML formatted string
        $return = new DOMDocument('1.0');
        $displayElement = $return->createElement('display');
        $return->appendChild($displayElement);

        // Is it there?
        if (count($result) == 0) {

            // Get the default layout id
            $defaultLayoutId = 4;

            // Add this display record
            if (!$displayid = $displayObject->Add($displayName, 0, $defaultLayoutId, $hardwareKey, 0, 0))
                throw new SoapFault('Sender', 'Error adding display');

            $displayElement->setAttribute('status', 1);
            $displayElement->setAttribute('code', 'ADDED');
            $displayElement->setAttribute('message', 'Display added and is awaiting licensing approval from an Administrator.');
        }
        else {
            // We have seen this display before, so check the licensed value
            $row = $result[0];

            $displayid = Kit::ValidateParam($row['displayid'], _INT);
            $display = Kit::ValidateParam($row['display'], _STRING);
            $clientType = Kit::ValidateParam($row['client_type'], _WORD);
            $versionInstructions = Kit::ValidateParam($row['version_instructions'], _HTMLSTRING);

            // Determine if we are licensed or not
            if ($row['licensed'] == 0) {
                // It is not licensed
                $displayElement->setAttribute('status', 2);
                $displayElement->setAttribute('code', 'WAITING');
                $displayElement->setAttribute('message', 'Display is awaiting licensing approval from an Administrator.');
            }
            else {
                // It is licensed
                $displayElement->setAttribute('status', 0);
                $displayElement->setAttribute('code', 'READY');
                $displayElement->setAttribute('message', 'Display is active and ready to start.');
                $displayElement->setAttribute('version_instructions', $versionInstructions);

                // Use the display profile and type to get this clients settings
                try {
                    $dbh = PDOConnect::init();

                    $displayProfileId = (empty($row['displayprofileid']) ? 0 : Kit::ValidateParam($row['displayprofileid'], _INT));
                    $params = array();

                    if ($displayProfileId == 0) {
                        $sth = $dbh->prepare('SELECT name, config FROM `displayprofile` WHERE type = :type AND isdefault = 1');
                        $params['type'] = $clientType;
                    }
                    else {
                        $sth = $dbh->prepare('SELECT name, config FROM `displayprofile` WHERE displayprofileid = :displayprofileid');
                        $params['displayprofileid'] = $displayProfileId;
                    }
                
                    $sth->execute($params);

                    if ($row = $sth->fetch()) {

                        // Load the config and inject the display name
                        $config = json_decode(Kit::ValidateParam($row['config'], _HTMLSTRING), true);

                        if ($clientType == 'windows') {
                            $config[] = array(
                                'name' => 'DisplayName',
                                'value' => $display,
                                'type' => 'string'
                            );
                        }
                        else {
                            $config[] = array(
                                'name' => 'displayName',
                                'value' => $display,
                                'type' => 'string'
                            );
                        }

                        // Create the XML nodes
                        foreach($config as $arrayItem) {
                            $node = $return->createElement($arrayItem['name'], $arrayItem['value']);
                            $node->setAttribute('type', $arrayItem['type']);
                            $displayElement->appendChild($node);
                        }
                    }
                }
                catch (Exception $e) {
                    Debug::LogEntry('error', $e->getMessage());
                    throw new SoapFault('Sender', 'Error after display found');
                }
            }
        }

        // Touch the display record
        $displayObject->Touch($displayid, array(
            'clientAddress' => $clientAddress,
            'macAddress' => $macAddress,
            'clientType' => $clientType,
            'clientVersion' => $clientVersion,
            'clientCode' => $clientCode
            ));

        // Log Bandwidth
        $returnXml = $return->saveXML();
        $this->LogBandwidth($displayid, Bandwidth::$REGISTER, strlen($returnXml));

        Debug::LogEntry('audit', $returnXml, get_class(), __FUNCTION__);

        return $return->saveXML();
    }

    /**
     * Returns a string containing the required files xml for the requesting display
     * @param string $hardwareKey Display Hardware Key
     * @return string $requiredXml Xml Formatted String
     */
    function RequiredFiles($serverKey, $hardwareKey, $version) {
        
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $version = Kit::ValidateParam($version, _STRING);
        $rfLookahead = Kit::ValidateParam(Config::GetSetting('REQUIRED_FILES_LOOKAHEAD'), _INT);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Sender', 'Your client is not of the correct version for communication with this server.');

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        $libraryLocation = Config::GetSetting("LIBRARY_LOCATION");

        // auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Sender', 'This display is not licensed.');

        if ($this->isAuditing == 1)
            Debug::LogEntry("audit", '[IN] with hardware key: ' . $hardwareKey, "xmds", "RequiredFiles");

        // Remove all Nonces for this display
        $nonce = new Nonce();
        $nonce->RemoveAllXmdsNonce($this->displayId);

        // Build a new RF
        $requiredFilesXml = new DOMDocument("1.0");
        $fileElements = $requiredFilesXml->createElement("files");
        $requiredFilesXml->appendChild($fileElements);

        // Hour to hour time bands for the query
        // Start at the current hour
        $fromFilter = time();
        // Move forwards an hour and the rf lookahead
        $rfLookahead = $fromFilter + 3600 + $rfLookahead;
        // Dial both items back to the top of the hour
        $fromFilter = $fromFilter - ($fromFilter % 3600);
        $toFilter = $rfLookahead - ($rfLookahead % 3600);

        if ($this->isAuditing == 1)
            Debug::LogEntry('audit', sprintf('FromDT = %s. ToDt = %s', date('Y-m-d h:i:s', $fromFilter), date('Y-m-d h:i:s', $toFilter)), 'xmds', 'RequiredFiles', '', $this->displayId);

        try {
            $dbh = PDOConnect::init();
        
            // Get a list of all layout ids in the schedule right now.
            $SQL  = " SELECT DISTINCT layout.layoutID ";
            $SQL .= " FROM `campaign` ";
            $SQL .= "   INNER JOIN schedule ON schedule.CampaignID = campaign.CampaignID ";
            $SQL .= "   INNER JOIN schedule_detail ON schedule_detail.eventID = schedule.eventID ";
            $SQL .= "   INNER JOIN `lkcampaignlayout` ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
            $SQL .= "   INNER JOIN `layout` ON lkcampaignlayout.LayoutID = layout.LayoutID ";
            $SQL .= "   INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL .= " WHERE lkdisplaydg.DisplayID = :displayId ";
            $SQL .= " AND schedule_detail.FromDT < :fromdt AND schedule_detail.ToDT > :todt ";
            $SQL .= "   AND layout.retired = 0  ";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'displayId' => $this->displayId,
                    'fromdt' => $toFilter,
                    'todt' => $fromFilter
                ));
    
            // Our layout list will always include the default layout
            $layouts = array();
            $layouts[] = $this->defaultLayoutId;
    
            // Build up the other layouts into an array
            foreach ($sth->fetchAll() as $row)
                $layouts[] = Kit::ValidateParam($row['layoutID'], _INT);  
        }
        catch (Exception $e) {            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
            return new SoapFault('Sender', 'Unable to get a list of layouts');
        }

        // Create a comma separated list to pass into the query which gets file nodes
        $layoutIdList = implode(',', $layouts);

        try {
            $dbh = PDOConnect::init();
        
            // Add file nodes to the $fileElements
            $SQL  = " SELECT 'layout' AS RecordType, layout.layoutID AS path, layout.layoutID AS id, MD5(layout.xml) AS `MD5`, NULL AS FileSize, layout.xml AS xml ";
            $SQL .= "   FROM layout ";
            $SQL .= sprintf(" WHERE layout.layoutid IN (%s)  ", $layoutIdList);
            $SQL .= " UNION ";
            $SQL .= " SELECT 'media' AS RecordType, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize, NULL AS xml ";
            $SQL .= "   FROM media ";
            $SQL .= "   INNER JOIN lklayoutmedia ";
            $SQL .= "   ON lklayoutmedia.MediaID = media.MediaID ";
            $SQL .= "   INNER JOIN layout ";
            $SQL .= "   ON layout.LayoutID = lklayoutmedia.LayoutID";
            $SQL .= sprintf(" WHERE layout.layoutid IN (%s)  ", $layoutIdList);
            $SQL .= "
                    UNION
                    SELECT 'media' AS RecordType, storedAs AS path, media.mediaID AS id, media.`MD5`, media.FileSize, NULL AS xml 
                       FROM `media`
                        INNER JOIN `lkmediadisplaygroup`
                        ON lkmediadisplaygroup.mediaid = media.MediaID
                        INNER JOIN lkdisplaydg 
                        ON lkdisplaydg.DisplayGroupID = lkmediadisplaygroup.DisplayGroupID
                    ";
            $SQL .= " WHERE lkdisplaydg.DisplayID = :displayId ";
            $SQL .= " ORDER BY RecordType DESC";
    
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'displayId' => $this->displayId
                ));

            // Prepare a SQL statement in case we need to update the MD5 and FileSize on media nodes.
            $mediaSth = $dbh->prepare('UPDATE media SET `MD5` = :md5, FileSize = :size WHERE MediaID = :mediaid');

            // What is the send file mode?
            $sendFileMode = Config::GetSetting('SENDFILE_MODE');

            foreach ($sth->fetchAll() as $row) {
                $recordType = Kit::ValidateParam($row['RecordType'], _WORD);
                $path   = Kit::ValidateParam($row['path'], _STRING);
                $id     = Kit::ValidateParam($row['id'], _STRING);
                $md5    = Kit::ValidateParam($row['MD5'], _HTMLSTRING);
                $fileSize   = Kit::ValidateParam($row['FileSize'], _INT);
                $xml = Kit::ValidateParam($row['xml'], _HTMLSTRING);
                $mediaNonce = '';

                if ($recordType == 'layout') {
                    // For layouts the MD5 column is the layout xml
                    $fileSize = strlen($xml);
                    
                    if ($this->isAuditing == 1) 
                        Debug::LogEntry("audit", 'MD5 for layoutid ' . $id . ' is: [' . $md5 . ']', "xmds", "RequiredFiles");

                    // Add nonce
                    $nonce->AddXmdsNonce('layout', $this->displayId, NULL, $fileSize, NULL, $id);
                }
                else if ($recordType == 'media') {
                    // If they are empty calculate them and save them back to the media.
                    if ($md5 == '' || $fileSize == 0) {

                        $md5 = md5_file($libraryLocation.$path);
                        $fileSize = filesize($libraryLocation.$path);
                        
                        // Update the media record with this information
                        $mediaSth->execute(array('md5' => $md5, 'size' => $fileSize, 'mediaid' => $id));
                    }

                    // Add nonce
                    $mediaNonce = $nonce->AddXmdsNonce('file', $this->displayId, $id, $fileSize, $path);
                }
                else {
                    continue;
                }

                // Add the file node
                $file = $requiredFilesXml->createElement("file");
                $file->setAttribute("type", $recordType);
                $file->setAttribute("id", $id);
                $file->setAttribute("size", $fileSize);
                $file->setAttribute("md5", $md5);

                if ($recordType == 'media' && $sendFileMode != 'Off') {
                    // Serve a link instead (standard HTTP link)
                    $file->setAttribute("path", Kit::GetXiboRoot() . '?file=' . $mediaNonce);
                    $file->setAttribute("saveAs", $path);
                    $file->setAttribute("download", 'http');
                }
                else {
                    $file->setAttribute("download", 'xmds');
                    $file->setAttribute("path", $path);
                }
                
                $fileElements->appendChild($file);
            }
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return new SoapFault('Sender', 'Unable to get a list of files');
        }

        Kit::ClassLoader('layout');

        // Go through each layout and see if we need to supply any resource nodes.
        foreach ($layouts as $layoutId) {
            // Load the layout XML and work out if we have any ticker / text / dataset media items
            $layout = new Layout($this->db);

            $layoutInformation = $layout->LayoutInformation($layoutId);

            foreach($layoutInformation['regions'] as $region) {
                foreach($region['media'] as $media) {
                    if ($media['render'] == 'html' || $media['mediatype'] == 'ticker' || $media['mediatype'] == 'text' || $media['mediatype'] == 'datasetview' || $media['mediatype'] == 'webpage') {
                        // Append this item to required files
                        $file = $requiredFilesXml->createElement("file");
                        $file->setAttribute('type', 'resource');
                        $file->setAttribute('id', rand());
                        $file->setAttribute('layoutid', $layoutId);
                        $file->setAttribute('regionid', $region['regionid']);
                        $file->setAttribute('mediaid', $media['mediaid']);
                        $file->setAttribute('updated', (isset($media['updated']) ? $media['updated'] : 0));
                        
                        $fileElements->appendChild($file);

                        $nonce->AddXmdsNonce('resource', $this->displayId, NULL, NULL, NULL, $layoutId, $region['regionid'], $media['mediaid']);
                    }
                }
            }
        }

        // Add a blacklist node
        $blackList = $requiredFilesXml->createElement("file");
        $blackList->setAttribute("type", "blacklist");

        $fileElements->appendChild($blackList);

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT MediaID FROM blacklist WHERE DisplayID = :displayid AND isIgnored = 0');
            $sth->execute(array(
                    'displayid' => $this->displayId
                ));
        
            // Add a black list element for each file
            foreach ($sth->fetchAll() as $row) {
                $file = $requiredFilesXml->createElement("file");
                $file->setAttribute("id", $row['MediaID']);
    
                $blackList->appendChild($file);
            }  
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return new SoapFault('Sender', 'Unable to get a list of blacklisted files');
        }

        // Phone Home?
        $this->PhoneHome();

        if ($this->isAuditing == 1)
            Debug::LogEntry("audit", "[OUT]" . $requiredFilesXml->saveXML(), "xmds", "RequiredFiles");

        // Return the results of requiredFiles()
        $requiredFilesXml->formatOutput = true;
        $output = $requiredFilesXml->saveXML();

        // Log Bandwidth
        $this->LogBandwidth($this->displayId, Bandwidth::$RF, strlen($output));

        return $output;
    }

    /**
     * Get File
     * @param string $serverKey   The ServerKey for this CMS
     * @param string $hardwareKey The HardwareKey for this Display
     * @param int $mediaId     The ID
     * @param string $fileType    The File Type
     * @param int $chunkOffset The Offset of the Chunk Requested
     * @param string $chunkSize   The Size of the Chunk Requested
     * @param string $version     The XMDS Version
     */
    function GetFile($serverKey, $hardwareKey, $fileId, $fileType, $chunkOffset, $chunkSize, $version) {
        
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $fileId = Kit::ValidateParam($fileId, _INT);
        $fileType = Kit::ValidateParam($fileType, _WORD);
        $chunkOffset = Kit::ValidateParam($chunkOffset, _INT);
        $chunkSize = Kit::ValidateParam($chunkSize, _INT);
        $version = Kit::ValidateParam($version, _STRING);

        $libraryLocation = Config::GetSetting("LIBRARY_LOCATION");

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server.");

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Authenticate this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', "This display client is not licensed");

        if ($this->isAuditing == 1)
            Debug::LogEntry("audit", "[IN] Params: [$hardwareKey] [$fileId] [$fileType] [$chunkOffset] [$chunkSize]", "xmds", "GetFile");
        
        $nonce = new Nonce();

        if ($fileType == "layout") {
            $fileId = Kit::ValidateParam($fileId, _INT);

            // Validate the nonce
            if (!$nonce->AllowedFile('layout', $this->displayId, NULL, $fileId))
                throw new SoapFault('Receiver', 'Requested an invalid file.');

            try {
                $dbh = PDOConnect::init();
            
                $sth = $dbh->prepare('SELECT xml FROM layout WHERE layoutid = :layoutid');
                $sth->execute(array('layoutid' => $fileId));
            
                if (!$row = $sth->fetch())
                    throw new Exception('No file found with that ID');

                $file = $row['xml'];
                
                // Store file size for bandwidth log
                $chunkSize = strlen($file);
            }
            catch (Exception $e) {
                Debug::LogEntry('error', $e->getMessage());
                return new SoapFault('Receiver', 'Unable the find layout.');
            }
        }
        else if ($fileType == "media")
        {
            // Validate the nonce
            if (!$nonce->AllowedFile('file', $this->displayId, $fileId))
                throw new SoapFault('Receiver', 'Requested an invalid file.');

            try {
                $dbh = PDOConnect::init();
            
                $sth = $dbh->prepare('SELECT storedAs FROM `media` WHERE mediaid = :mediaid');
                $sth->execute(array('mediaid' => $fileId));
            
                if (!$row = $sth->fetch())
                    throw new Exception('No file found with that ID');

                // Return the Chunk size specified
                $f = fopen($libraryLocation . $row['storedAs'], 'r');

                fseek($f, $chunkOffset);

                $file = fread($f, $chunkSize);
                
                // Store file size for bandwidth log
                $chunkSize = strlen($file);
            }
            catch (Exception $e) {
                Debug::LogEntry('error', $e->getMessage());
                return new SoapFault('Receiver', 'Unable the find media.');
            }
        }
        else {
            throw new SoapFault('Receiver', 'Unknown FileType Requested.');
        }

        // Log Bandwidth
        $this->LogBandwidth($this->displayId, Bandwidth::$GETFILE, $chunkSize);
        
        return $file;
    }

    /**
     * Returns the schedule for the hardware key specified
     * @return
     * @param $hardwareKey Object
     */
    function Schedule($serverKey, $hardwareKey, $version) {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $version = Kit::ValidateParam($version, _STRING);
        $rfLookahead = Kit::ValidateParam(Config::GetSetting('REQUIRED_FILES_LOOKAHEAD'), _INT);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Sender', "Your client is not of the correct version for communication with this server.");

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        //auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Sender', "This display client is not licensed");

        $scheduleXml = new DOMDocument("1.0");
        $layoutElements = $scheduleXml->createElement("schedule");

        $scheduleXml->appendChild($layoutElements);

        // Hour to hour time bands for the query
        // Start at the current hour
        $fromFilter = time();
        // Move forwards an hour and the rf lookahead
        $rfLookahead = $fromFilter + 3600 + $rfLookahead;
        // Dial both items back to the top of the hour
        $fromFilter = $fromFilter - ($fromFilter % 3600);

        if (Config::GetSetting('SCHEDULE_LOOKAHEAD') == 'On')
            $toFilter = $rfLookahead - ($rfLookahead % 3600);
        else 
            $toFilter = ($fromFilter + 3600) - (($fromFilter + 3600) % 3600);

        if ($this->isAuditing == 1)
            Debug::LogEntry('audit', sprintf('FromDT = %s. ToDt = %s', date('Y-m-d h:i:s', $fromFilter), date('Y-m-d h:i:s', $toFilter)), 'xmds', 'Schedule', '', $this->displayId);
        
        try {
            $dbh = PDOConnect::init();
        
            // Add file nodes to the $fileElements
            // Firstly get all the scheduled layouts
            $SQL  = " SELECT layout.layoutID, schedule_detail.FromDT, schedule_detail.ToDT, schedule.eventID, schedule.is_priority, ";
            $SQL .= "  (SELECT GROUP_CONCAT(DISTINCT StoredAs) FROM media INNER JOIN lklayoutmedia ON lklayoutmedia.MediaID = media.MediaID WHERE lklayoutmedia.LayoutID = layout.LayoutID GROUP BY lklayoutmedia.LayoutID) AS Dependents";
            $SQL .= " FROM `campaign` ";
            $SQL .= " INNER JOIN schedule ON schedule.CampaignID = campaign.CampaignID ";
            $SQL .= " INNER JOIN schedule_detail ON schedule_detail.eventID = schedule.eventID ";
            $SQL .= " INNER JOIN `lkcampaignlayout` ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
            $SQL .= " INNER JOIN `layout` ON lkcampaignlayout.LayoutID = layout.LayoutID ";
            $SQL .= " INNER JOIN lkdisplaydg ON lkdisplaydg.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL .= " WHERE lkdisplaydg.DisplayID = :displayId ";
            $SQL .= " AND (schedule_detail.FromDT < :fromdt AND schedule_detail.ToDT > :todt )";
            $SQL .= "   AND layout.retired = 0  ";
            $SQL .= " ORDER BY schedule.DisplayOrder, lkcampaignlayout.DisplayOrder, schedule_detail.eventID ";
    
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'displayId' => $this->displayId,
                    'fromdt' => $toFilter,
                    'todt' => $fromFilter
                ));
    
            // We must have some results in here by this point
            foreach ($sth->fetchAll() as $row) {
                $layoutid = $row[0];
                $fromdt = date('Y-m-d H:i:s', $row[1]);
                $todt = date('Y-m-d H:i:s', $row[2]);
                $scheduleid = $row[3];
                $is_priority = Kit::ValidateParam($row[4], _INT);
                $dependents = Kit::ValidateParam($row[5], _STRING);
    
                // Add a layout node to the schedule
                $layout = $scheduleXml->createElement("layout");
    
                $layout->setAttribute("file", $layoutid);
                $layout->setAttribute("fromdt", $fromdt);
                $layout->setAttribute("todt", $todt);
                $layout->setAttribute("scheduleid", $scheduleid);
                $layout->setAttribute("priority", $is_priority);
                $layout->setAttribute("dependents", $dependents);
    
                $layoutElements->appendChild($layout);
            }
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return new SoapFault('Unable to get A list of layouts for the schedule');
        }

        // Are we interleaving the default?
        if ($this->includeSchedule == 1) {
            // Add as a node at the end of the schedule.
            $layout = $scheduleXml->createElement("layout");

            $layout->setAttribute("file", $this->defaultLayoutId);
            $layout->setAttribute("fromdt", '2000-01-01 00:00:00');
            $layout->setAttribute("todt", '2030-01-19 00:00:00');
            $layout->setAttribute("scheduleid", 0);
            $layout->setAttribute("priority", 0);

            $layoutElements->appendChild($layout);
        }

        // Add on the default layout node
        $default = $scheduleXml->createElement("default");
        $default->setAttribute("file", $this->defaultLayoutId);
        $layoutElements->appendChild($default);

        // Format the output
        $scheduleXml->formatOutput = true;
        
        if ($this->isAuditing == 1)
            Debug::LogEntry("audit", $scheduleXml->saveXML(), "xmds", "Schedule");

        $output = $scheduleXml->saveXML();

        // Log Bandwidth
        $this->LogBandwidth($this->displayId, Bandwidth::$SCHEDULE, strlen($output));

        return $output;
    }

    /**
     *
     * @return
     * @param $hardwareKey Object
     * @param $mediaId Object
     * @param $type Object
     */
    function BlackList($serverKey, $hardwareKey, $mediaId, $type, $reason, $version) {
        
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $mediaId = Kit::ValidateParam($mediaId, _STRING);
        $type = Kit::ValidateParam($type, _STRING);
        $reason = Kit::ValidateParam($reason, _STRING);
        $version = Kit::ValidateParam($version, _STRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server.");

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Authenticate this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', "This display client is not licensed", $hardwareKey);

        if ($this->isAuditing == 1) 
            Debug::LogEntry( "audit", "[IN] $xml", "xmds", "BlackList", "", $this->displayId);

        try {
            $dbh = PDOConnect::init();
        
            // Check to see if this media / display is already blacklisted (and not ignored)
            $sth = $dbh->prepare('SELECT BlackListID FROM blacklist WHERE MediaID = :mediaid AND isIgnored = 0 AND DisplayID = :displayid');
            $sth->execute(array(
                    'mediaid' => $mediaId,
                    'displayid' => $this->displayId
                ));

            $results = $sth->fetchAll();
            
            if (count($results) == 0) {

                $insertSth = $dbh->prepare('
                        INSERT INTO blacklist (MediaID, DisplayID, ReportingDisplayID, Reason)
                            VALUES (:mediaid, :displayid, :reportingdisplayid, :reason)
                    ');

                // Insert the black list record
                if ($type == BLACKLIST_SINGLE) {
                    $insertSth->execute(array(
                            'mediaid' => $mediaId, 
                            'displayid' => $displayId, 
                            'reportingdisplayid' => $this->displayId, 
                            'reason' => $reason
                        ));
                }
                else {
                    $displaySth = $dbh->prepare('SELECT displayID FROM `display`');
                    $displaySth->execute();

                    foreach ($displaySth->fetchAll() as $row) {

                        $insertSth->execute(array(
                            'mediaid' => $mediaId, 
                            'displayid' => $row['displayID'], 
                            'reportingdisplayid' => $this->displayId, 
                            'reason' => $reason
                        ));
                    }
                }
            }
            else {
                if ($this->isAuditing == 1) 
                    Debug::LogEntry( "audit", "Media Already BlackListed [$mediaId]", "xmds", "BlackList", "", $this->displayId);
            }
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return new SoapFault("Unable to query for BlackList records.");
        }

        $this->LogBandwidth($this->displayId, Bandwidth::$BLACKLIST, strlen($reason));

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
    function SubmitLog($version, $serverKey, $hardwareKey, $logXml) {
        
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $version = Kit::ValidateParam($version, _STRING);
        $logXml = Kit::ValidateParam($logXml, _HTMLSTRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Sender', "Your client is not of the correct version for communication with this server.");

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');
        
        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Sender', 'This display client is not licensed.');
        
        if ($this->isAuditing == 1) 
            Debug::LogEntry( "audit", 'IN. XML [' . $logXml . ']', "xmds", "SubmitLog", "", $this->displayId);

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");

        if (!$document->loadXML($logXml))
            throw new SoapFault('Receiver', "XML Cannot be loaded into DOM Document.");

        foreach ($document->documentElement->childNodes as $node) {
            
            // Make sure we dont consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE) 
                continue;

            // Zero out the common vars
            $date = "";
            $message = "";
            $scheduleID = "";
            $layoutID = "";
            $mediaID = "";
            $cat = '';
            $method = '';
            $thread = '';

            // This will be a bunch of trace nodes
            $message = $node->textContent;

            if ($this->isAuditing == 1) 
                Debug::LogEntry("audit", 'Trace Message: [' . $message . ']', "xmds", "SubmitLog", "", $this->displayId);

            // Each element should have a category and a date
            $date = $node->getAttribute('date');
            $cat = strtolower($node->getAttribute('category'));

            if ($date == '' || $cat == '') {
                trigger_error('Log submitted without a date or category attribute');
                continue;
            }

            // Get the date and the message (all log types have these)
            foreach ($node->childNodes as $nodeElements) {

                if ($nodeElements->nodeName == "scheduleID") {
                    $scheduleID = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "layoutID") {
                    $layoutID = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "mediaID") {
                    $mediaID = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "type") {
                    $type = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "method") {
                    $method = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "message") {
                    $message = $nodeElements->textContent;
                }
                else if ($nodeElements->nodeName == "thread") {
                    if ($nodeElements->textContent != '')
                        $thread = '[' . $nodeElements->textContent . '] ';
                }
            }

            // If the message is still empty, take the entire node content
            if ($message == '')
                $message = $node->textContent;

            // We should have enough information to log this now.
            $logType = ($cat == 'error') ? 'error' : 'audit';
            
            Debug::LogEntry($logType, $message, 'Client', $thread . $method, $date, $this->displayId, $scheduleID, $layoutID, $mediaID);
        }

        $this->LogBandwidth($this->displayId, Bandwidth::$SUBMITLOG, strlen($logXml));

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
    function SubmitStats($version, $serverKey, $hardwareKey, $statXml) {
        
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $version = Kit::ValidateParam($version, _STRING);
        $statXml = Kit::ValidateParam($statXml, _HTMLSTRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server.");

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');
        
        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', "This display client is not licensed");
        
        if ($this->isAuditing == 1) 
            Debug::LogEntry( "audit", "IN. StatXml: [" . $statXml . "]", "xmds", "SubmitStats", "", $this->displayId);

        if ($statXml == "")
            throw new SoapFault('Receiver', "Stat XML is empty.");
        
        // Log
        $statObject = new Stat($this->db);

        // Log
        if ($this->isAuditing == 1) 
            Debug::LogEntry( "audit", "About to Create DOMDocument.", "xmds", "SubmitStats", "", $this->displayId);

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");
        $document->loadXML($statXml);

        foreach ($document->documentElement->childNodes as $node) {
            // Make sure we dont consider any text nodes
            if ($node->nodeType == XML_TEXT_NODE) 
                continue;

            // Zero out the common vars
            $fromdt = '';
            $todt = '';
            $type = '';

            $scheduleID = 0;
            $layoutID = 0;
            $mediaID = '';
            $tag = '';

            // Each element should have these attributes
            $fromdt = $node->getAttribute('fromdt');
            $todt = $node->getAttribute('todt');
            $type = $node->getAttribute('type');

            if ($fromdt == '' || $todt == '' || $type == '') {
                trigger_error('Stat submitted without the fromdt, todt or type attributes.');
                continue;
            }

            $scheduleID = $node->getAttribute('scheduleid');
            $layoutID = $node->getAttribute('layoutid');
            $mediaID = $node->getAttribute('mediaid');
            $tag = $node->getAttribute('tag');

            // Write the stat record with the information we have available to us.
            if (!$statObject->Add($type, $fromdt, $todt, $scheduleID, $this->displayId, $layoutID, $mediaID, $tag)) {
                trigger_error(sprintf('Stat Add failed with error: %s', $statObject->GetErrorMessage()));
                continue;
            }
        }

        $this->LogBandwidth($this->displayId, Bandwidth::$SUBMITSTATS, strlen($statXml));

        return true;
    }

    /**
     * Store the media inventory for a client
     * @param <type> $hardwareKey
     * @param <type> $inventory
     */
    public function MediaInventory($version, $serverKey, $hardwareKey, $inventory) {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $version = Kit::ValidateParam($version, _STRING);
        $inventory = Kit::ValidateParam($inventory, _HTMLSTRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server.");

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', 'This display client is not licensed');

        if ($this->isAuditing == 1) 
            Debug::LogEntry( 'audit', $inventory, 'xmds', 'MediaInventory', '', $this->displayId);

        // Check that the $inventory contains something
        if ($inventory == '')
            throw new SoapFault('Receiver', 'Inventory Cannot be Empty');

        // Load the XML into a DOMDocument
        $document = new DOMDocument("1.0");
        $document->loadXML($inventory);

        // Assume we are complete (but we are getting some)
        $mediaInventoryComplete = 1;

        $xpath = new DOMXPath($document);
        $fileNodes = $xpath->query("//file");

        foreach ($fileNodes as $node) {
            $mediaId = $node->getAttribute('id');
            $complete = $node->getAttribute('complete');
            $md5 = $node->getAttribute('md5');
            $lastChecked = $node->getAttribute('lastChecked');

            // TODO: Check the MD5?

            // If this item is a 0 then set not complete
            if ($complete == 0)
                $mediaInventoryComplete = 2;
        }

        // Touch the display record
        $displayObject = new Display();
        $displayObject->Touch($this->displayId, array('mediaInventoryStatus' => $mediaInventoryComplete, 'mediaInventoryXml' => $inventory));

        $this->LogBandwidth($this->displayId, Bandwidth::$MEDIAINVENTORY, strlen($inventory));

        return true;
    }

    /**
     * Gets additional resources for assigned media
     * @param <type> $serverKey
     * @param <type> $hardwareKey
     * @param <type> $layoutId
     * @param <type> $regionId
     * @param <type> $mediaId
     * @param <type> $version
     */
    function GetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId, $version) {
        
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $layoutId = Kit::ValidateParam($layoutId, _INT);
        $regionId = Kit::ValidateParam($regionId, _STRING);
        $mediaId = Kit::ValidateParam($mediaId, _STRING);
        $version = Kit::ValidateParam($version, _STRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server.");

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', "This display client is not licensed");

        // Validate the nonce
        $nonce = new Nonce();
        if (!$nonce->AllowedFile('resource', $this->displayId, NULL, $layoutId, $regionId, $mediaId))
            throw new SoapFault('Receiver', 'Requested an invalid file.');

        // What type of module is this?
        Kit::ClassLoader('region');
        $region = new region($this->db);
        $type = $region->GetMediaNodeType($layoutId, $regionId, $mediaId);

        if ($type == '')
            throw new SoapFault('Receiver', 'Unable to get the media node type');

        // Dummy User Object
        $user = new User($this->db);
        $user->userid = 0;
        $user->usertypeid = 1;

        // Get the resource from the module
        require_once('modules/' . $type . '.module.php');
        
        if (!$module = new $type($this->db, $user, $mediaId, $layoutId, $regionId))
            throw new SoapFault('Receiver', 'Cannot create module. Check CMS Log');

        $resource = $module->GetResource($this->displayId);

        if (!$resource || $resource == '')
            throw new SoapFault('Receiver', 'Unable to get the media resource');

        // Log Bandwidth
        $this->LogBandwidth($this->displayId, Bandwidth::$GETRESOURCE, strlen($resource));

        return $resource;
    }

    public function NotifyStatus($version, $serverKey, $hardwareKey, $status) {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $version = Kit::ValidateParam($version, _STRING);
        $status = Kit::ValidateParam($status, _HTMLSTRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server.");

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', 'This display client is not licensed');

        if ($this->isAuditing == 1) 
            Debug::LogEntry('audit', $status, 'xmds', 'Status', '', $this->displayId);

        // Touch the display record
        $displayObject = new Display();
        $displayObject->Touch($this->displayId, json_decode($status, true));

        $this->LogBandwidth($this->displayId, Bandwidth::$NOTIFYSTATUS, strlen($status));

        return true;
    }

    public function SubmitScreenShot($version, $serverKey, $hardwareKey, $screenShot) {
        // Sanitize
        $serverKey = Kit::ValidateParam($serverKey, _STRING);
        $hardwareKey = Kit::ValidateParam($hardwareKey, _STRING);
        $version = Kit::ValidateParam($version, _STRING);
        $screenShot = Kit::ValidateParam($screenShot, _HTMLSTRING);

        // Make sure we are talking the same language
        if (!$this->CheckVersion($version))
            throw new SoapFault('Receiver', "Your client is not of the correct version for communication with this server.");

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new SoapFault('Receiver', 'This display client is not licensed');

        if ($this->isAuditing == 1) 
            Debug::LogEntry('audit', $screenShot, 'xmds', 'SubmitScreenShot', '', $this->displayId);

        // Open this displays screen shot file and save this.
        File::EnsureLibraryExists();
        $location = Config::GetSetting('LIBRARY_LOCATION') . 'screenshots/' . $this->displayId . '_screenshot.jpg';
        $fp = fopen($location, 'wb');
        fwrite($fp, $screenShot);
        fclose($fp);

        $this->LogBandwidth($this->displayId, Bandwidth::$SCREENSHOT, filesize($location));

        return true;
    }

    /**
     * PHONE_HOME if required
     */
    private function PhoneHome() {
        
        if (Config::GetSetting('PHONE_HOME') == 'On') {
            // Find out when we last PHONED_HOME :D
            // If it's been > 28 days since last PHONE_HOME then
            if (Config::GetSetting('PHONE_HOME_DATE') < (time() - (60 * 60 * 24 * 28))) {
                
                try {
                    $dbh = PDOConnect::init();
                
                    // Retrieve number of displays
                    $sth = $dbh->prepare('SELECT COUNT(*) AS Cnt FROM `display` WHERE `licensed` = 1');
                    $sth->execute();

                    $PHONE_HOME_CLIENTS = $sth->fetchColumn();
                
                    // Retrieve version number
                    $PHONE_HOME_VERSION = Config::Version('app_ver');
                
                    $PHONE_HOME_URL = Config::GetSetting('PHONE_HOME_URL') . "?id=" . urlencode(Config::GetSetting('PHONE_HOME_KEY')) . "&version=" . urlencode($PHONE_HOME_VERSION) . "&numClients=" . urlencode($PHONE_HOME_CLIENTS);
                
                    if ($this->isAuditing == 1)
                        Debug::LogEntry("audit", "PHONE_HOME_URL " . $PHONE_HOME_URL , "xmds", "RequiredFiles");
                    
                    // Set PHONE_HOME_TIME to NOW.
                    $sth = $dbh->prepare('UPDATE `setting` SET `value` = :time WHERE `setting`.`setting` = :setting LIMIT 1');
                    $sth->execute(array(
                            'time' => time(),
                            'setting' => 'PHONE_HOME_DATE'
                        ));
                                
                    @file_get_contents($PHONE_HOME_URL);
                
                    if ($this->isAuditing == 1)
                        Debug::LogEntry("audit", "PHONE_HOME [OUT]", "xmds", "RequiredFiles");
                }
                catch (Exception $e) {
                    
                    Debug::LogEntry('error', $e->getMessage());

                    return false;
                }
            }
        }
    }

    /**
     * Authenticates the display
     * @param <type> $hardwareKey
     * @return <type>
     */
    private function AuthDisplay($hardwareKey, $status = NULL) {
    
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('
                SELECT licensed, inc_schedule, isAuditing, displayID, defaultlayoutid, loggedin, 
                    email_alert, display, version_instructions, client_type, client_code, client_version
                  FROM display 
                 WHERE license = :hardwareKey
                ');

            $sth->execute(array(
                    'hardwareKey' => $hardwareKey
                ));

            $result = $sth->fetchAll();
        
            // Is it there?
            if (count($result) == 0)
                return false;
            
            // We have seen this display before, so check the licensed value
            $row = $result[0];

            if ($row['licensed'] == 0)
                return false;
        
            // Pull the client IP address
            $clientAddress = Kit::GetParam('REMOTE_ADDR', $_SERVER, _STRING);
        
            // See if the client was offline and if appropriate send an alert
            // to say that it has come back online
            if ($row['loggedin'] == 0 
                    && $row['email_alert'] == 1 
                    && (Config::GetSetting('MAINTENANCE_ENABLED') == 'On' || Config::GetSetting('MAINTENANCE_ENABLED') == 'Protected') 
                    && Config::GetSetting('MAINTENANCE_EMAIL_ALERTS') == 'On') {

                $msgTo    = Kit::ValidateParam(Config::GetSetting("mail_to"),_PASSWORD);
                $msgFrom  = Kit::ValidateParam(Config::GetSetting("mail_from"),_PASSWORD);

                $subject  = sprintf(__("Recovery for Display %s"),$row[7]);
                $body     = sprintf(__("Display %s with ID %d is now back online."), $row[7], $row[3]);

                Kit::SendEmail($msgTo, $msgFrom, $subject, $body);
            }
        
            // It is licensed?
            $this->licensed = true;
            $this->includeSchedule = $row['inc_schedule'];
            $this->isAuditing = $row['isAuditing'];
            $this->displayId = $row['displayID'];
            $this->defaultLayoutId = $row['defaultlayoutid'];
            $this->version_instructions = $row['version_instructions'];
            $this->clientType = $row['client_type'];
            $this->clientVersion = $row['client_version'];
            $this->clientCode = $row['client_code'];
            
            // Last accessed date on the display
            $displayObject = new Display();
            $displayObject->Touch($this->displayId, array('clientAddress' => $clientAddress));
                
            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return false;
        }
    }

    /**
     * Checks that the calling service is talking the correct version
     * @return
     * @param $version Object
     */
    private function CheckVersion($version) {
        
        // Look up the Service XMDS version from the Version table
        $serverVersion = Config::Version('XmdsVersion');

        if ($version != $serverVersion) {
            Debug::LogEntry('audit', sprintf('A Client with an incorrect version connected. Client Version: [%s] Server Version [%s]', $version, $serverVersion));
            return false;
        }

        return true;
    }

    /**
     * Check we havent exceeded the bandwidth limits
     */
    private function CheckBandwidth() {
        $xmdsLimit = Config::GetSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB');

        if ($xmdsLimit <= 0)
            return true;

        try {
            $dbh = PDOConnect::init();
        
            // Test bandwidth for the current month
            $sth = $dbh->prepare('SELECT IFNULL(SUM(Size), 0) AS BandwidthUsage FROM `bandwidth` WHERE Month = :month');
            $sth->execute(array(
                    'month' => strtotime(date('m').'/02/'.date('Y').' 00:00:00')
                ));

            $bandwidthUsage = $sth->fetchColumn(0);
    
            return ($bandwidthUsage >= ($xmdsLimit * 1024)) ? false : true;  
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return false;
        }
    }

    /**
     * Log Bandwidth Usage
     * @param <type> $displayId
     * @param <type> $type
     * @param <type> $sizeInBytes
     */
    private function LogBandwidth($displayId, $type, $sizeInBytes) {
        
        $bandwidth = new Bandwidth();
        $bandwidth->Log($displayId, $type, $sizeInBytes);
    }
}
?>
