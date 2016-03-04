<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Soap5.php)
 */


namespace Xibo\Xmds;


use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Exception\NotFoundException;

class Soap5 extends Soap4
{
    /**
     * Registers a new display
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $displayName
     * @param string $clientType
     * @param string $clientVersion
     * @param int $clientCode
     * @param string $operatingSystem
     * @param string $macAddress
     * @param string $xmrChannel
     * @param string $xmrPubKey
     * @return string
     * @throws \SoapFault
     */
    public function RegisterDisplay($serverKey, $hardwareKey, $displayName, $clientType, $clientVersion, $clientCode, $operatingSystem, $macAddress, $xmrChannel, $xmrPubKey)
    {
        $this->logProcessor->setRoute('RegisterDisplay');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $displayName = $this->getSanitizer()->string($displayName);
        $clientType = $this->getSanitizer()->string($clientType);
        $clientVersion = $this->getSanitizer()->string($clientVersion);
        $clientCode = $this->getSanitizer()->int($clientCode);
        $macAddress = $this->getSanitizer()->string($macAddress);
        $clientAddress = $this->getSanitizer()->getString('REMOTE_ADDR');
        $xmrChannel = $this->getSanitizer()->string($xmrChannel);
        $xmrPubKey = trim($this->getSanitizer()->string($xmrPubKey));

        if ($xmrPubKey != '' && !str_contains($xmrPubKey, 'BEGIN PUBLIC KEY')) {
            $xmrPubKey = "-----BEGIN PUBLIC KEY-----\n" . $xmrPubKey . "\n-----END PUBLIC KEY-----\n";
        }

        // Audit in
        $this->getLog()->debug('serverKey: ' . $serverKey . ', hardwareKey: ' . $hardwareKey . ', displayName: ' . $displayName . ', macAddress: ' . $macAddress);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Check the Length of the hardwareKey
        if (strlen($hardwareKey) > 40)
            throw new \SoapFault('Sender', 'The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).');

        // Return an XML formatted string
        $return = new \DOMDocument('1.0');
        $displayElement = $return->createElement('display');
        $return->appendChild($displayElement);

        // Check in the database for this hardwareKey
        try {
            $display = $this->getFactoryService()->get('DisplayFactory')->getByLicence($hardwareKey);

            $this->logProcessor->setDisplay($display->displayId);

            // Now
            $dateNow = $this->getDate()->parse();

            // Append the time
            $displayElement->setAttribute('date', $this->getDate()->getLocalDate($dateNow));
            $displayElement->setAttribute('timezone', $this->getConfig()->GetSetting('defaultTimezone'));

            // Determine if we are licensed or not
            if ($display->licensed == 0) {
                // It is not licensed
                $displayElement->setAttribute('status', 2);
                $displayElement->setAttribute('code', 'WAITING');
                $displayElement->setAttribute('message', 'Display is awaiting licensing approval from an Administrator.');

            } else {
                // It is licensed
                $displayElement->setAttribute('status', 0);
                $displayElement->setAttribute('code', 'READY');
                $displayElement->setAttribute('message', 'Display is active and ready to start.');
                $displayElement->setAttribute('version_instructions', $display->versionInstructions);

                // Display Settings
                $settings = $display->getSettings();

                // Create the XML nodes
                foreach ($settings as $arrayItem) {

                    // Override the XMR address if empty
                    if (strtolower($arrayItem['name']) == 'xmrnetworkaddress' && $arrayItem['value'] == '') {
                        $arrayItem['value'] = $this->getConfig()->GetSetting('XMR_PUB_ADDRESS');
                    }

                    // Append Local Time to the root element
                    if (strtolower($arrayItem['name']) == 'displaytimezone' && $arrayItem['value'] != '') {
                        // Calculate local time
                        $dateNow->timezone($arrayItem['value']);

                        // Append Local Time
                        $displayElement->setAttribute('localDate', $this->getDate()->getLocalDate($dateNow));
                    }

                    $node = $return->createElement($arrayItem['name'], (isset($arrayItem['value']) ? $arrayItem['value'] : $arrayItem['default']));
                    $node->setAttribute('type', $arrayItem['type']);
                    $displayElement->appendChild($node);
                }

                // Add some special settings
                $nodeName = ($clientType == 'windows') ? 'DisplayName' : 'displayName';
                $node = $return->createElement($nodeName, $display->display);
                $node->setAttribute('type', 'string');
                $displayElement->appendChild($node);

                // Commands
                $commands = $display->getCommands();

                if (count($commands) > 0) {
                    // Append a command element
                    $commandElement = $return->createElement('commands');
                    $displayElement->appendChild($commandElement);

                    // Append each individual command
                    foreach ($display->getCommands() as $command) {
                        /* @var \Xibo\Entity\Command $command */
                        $node = $return->createElement($command->code);
                        $commandString = $return->createElement('commandString', $command->commandString);
                        $validationString = $return->createElement('validationString', $command->validationString);

                        $node->appendChild($commandString);
                        $node->appendChild($validationString);

                        $commandElement->appendChild($node);
                    }
                }

                // Check to see if the channel/pubKey are already entered
                if ($display->isAuditing == 1) {
                    $this->getLog()->debug('xmrChannel: [' . $xmrChannel . ']. xmrPublicKey: [' . $xmrPubKey . ']');
                }

                // Update the Channel
                $display->xmrChannel = $xmrChannel;
                // Update the PUB Key only if it has been cleared
                if ($display->xmrPubKey == '')
                    $display->xmrPubKey = $xmrPubKey;

                // Send Notification if required
                $this->AlertDisplayUp();
            }

        } catch (NotFoundException $e) {

            // Add a new display
            try {
                $display = new Display();
                $display->setContainer($this->getContainer());
                $display->display = $displayName;
                $display->isAuditing = 0;
                $display->defaultLayoutId = 4;
                $display->license = $hardwareKey;
                $display->licensed = 0;
                $display->incSchedule = 0;
                $display->clientAddress = $this->getIp();
                $display->xmrChannel = $xmrChannel;
                $display->xmrPubKey = $xmrPubKey;
            }
            catch (\InvalidArgumentException $e) {
                throw new \SoapFault('Sender', $e->getMessage());
            }

            $displayElement->setAttribute('status', 1);
            $displayElement->setAttribute('code', 'ADDED');
            $displayElement->setAttribute('message', 'Display added and is awaiting licensing approval from an Administrator.');
        }


        $display->lastAccessed = time();
        $display->loggedIn = 1;
        $display->clientAddress = $clientAddress;
        $display->macAddress = $macAddress;
        $display->clientType = $clientType;
        $display->clientVersion = $clientVersion;
        $display->clientCode = $clientCode;
        //$display->operatingSystem = $operatingSystem;
        $display->save(['validate' => false, 'audit' => false]);

        // Log Bandwidth
        $returnXml = $return->saveXML();
        $this->LogBandwidth($display->displayId, Bandwidth::$REGISTER, strlen($returnXml));

        // Audit our return
        if ($display->isAuditing == 1)
            $this->getLog()->debug($returnXml, $display->displayId);

        return $returnXml;
    }

    /**
     * Returns the schedule for the hardware key specified
     * @return string
     * @param string $serverKey
     * @param string $hardwareKey
     * @throws \SoapFault
     */
    function Schedule($serverKey, $hardwareKey)
    {
        return $this->doSchedule($serverKey, $hardwareKey, ['dependentsAsNodes' => true]);
    }
}