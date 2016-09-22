<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayTest.php)
 */
namespace Xibo\Tests\Integration;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;

class DisplayTest extends \Xibo\Tests\LocalWebTestCase
{
    protected $startDisplays;
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startDisplays = (new XiboDisplay($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // Tear down any displays that weren't there before
        $finalDisplays = (new XiboDisplay($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        
        # Loop over any remaining displays and nuke them
        foreach ($finalDisplays as $display) {
            /** @var XiboDisplay $display */
            $flag = true;
            foreach ($this->startDisplays as $startDisplay) {
               if ($startDisplay->displayId == $display->displayId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $display->delete();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $display->displayId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

    /**
     * Shows list of all displays Test
     */
    public function testListAll()
    {
        # Get all displays
        $this->client->get('/display');
        # Check if successful
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
     * Delete Display Test
     */
    public function testDelete()
    {
        # Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        # Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['hardwareKey' => $hardwareId]);
        if (count($displays) != 1)
            $this->fail('Display was not added correctly');
        /** @var XiboDisplay $display */
        $display = $displays[0];
        $this->client->delete('/display/' . $display->displayId);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    /**
     * Edit Display test
     */
    public function testEdit()
    {
        # Create a Display in the system
        $hardwareId = Random::generateString(12, 'phpunit');
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 'PHPUnit Test Display');
        # Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['hardwareKey' => $hardwareId]);
        if (count($displays) != 1)
            $this->fail('Display was not added correctly');
        /** @var XiboDisplay $display */
        $display = $displays[0];
        # Edit display and change its name
        $this->client->put('/display/' . $display->displayId, [
            'display' => 'API EDITED',
            'isAuditing' => $display->isAuditing,
            'defaultLayoutId' => $display->defaultLayoutId,
            'licensed' => $display->licensed,
            'license' => $display->license,
            'incSchedule' => $display->incSchedule,
            'emailAlert' => $display->emailAlert,
            'wakeOnLanEnabled' => $display->wakeOnLanEnabled,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        # Check if call was successful
        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        # Check if display has new edited name
        $this->assertSame('API EDITED', $object->data->display);
    }

    /**
     * Request screenshot Test
     */
    public function testScreenshot()
    {
        # Generate names for display and xmr channel
        $hardwareId = Random::generateString(12, 'phpunit');
        $xmrChannel = Random::generateString(50);
        # This is a dummy pubKey and isn't used by anything important
        $xmrPubkey = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDmdnXL4gGg3yJfmqVkU1xsGSQI
3b6YaeAKtWuuknIF1XAHAHtl3vNhQN+SmqcNPOydhK38OOfrdb09gX7OxyDh4+JZ
inxW8YFkqU0zTqWaD+WcOM68wTQ9FCOEqIrbwWxLQzdjSS1euizKy+2GcFXRKoGM
pbBhRgkIdydXoZZdjQIDAQAB
-----END PUBLIC KEY-----';
        # Register our display
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId,
            'PHPUnit Test Display',
            'windows',
            null,
            null,
            null,
            '00:16:D9:C9:AL:69',
            $xmrChannel,
            $xmrPubkey
        );

        # Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['hardwareKey' => $hardwareId]);
        if (count($displays) != 1)
            $this->fail('Display was not added correctly');
        /** @var XiboDisplay $display */
        $display = $displays[0];
        # Check if xmr channerl and pubkey were registered correctly
        $this->assertSame($xmrChannel, $display->xmrChannel, 'XMR Channel not set correctly by XMDS Register Display');
        $this->assertSame($xmrPubkey, $display->xmrPubKey, 'XMR PubKey not set correctly by XMDS Register Display');
        # Call request screenshot
        $this->client->put('/display/requestscreenshot/' . $display->displayId);
        # Check if successful
        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
    }

    /**
     * Wake On Lan Test
     */
    public function testWoL()
    {
        # Create dummy hardware key and mac address
        $hardwareId = Random::generateString(12, 'phpunit');
        $macAddress = '00-16-D9-C9-AE-69';
        # Register our display
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId, 
            'PHPUnit Test Display', 
            'windows', 
            null, 
            null, 
            null, 
            $macAddress,
            Random::generateString(50), 
            Random::generateString(50)
        );
        # Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['hardwareKey' => $hardwareId]);
        if (count($displays) != 1)
            $this->fail('Display was not added correctly');
        /** @var XiboDisplay $display */
        $display = $displays[0];
        # Check if mac address was added correctly
        $this->assertSame($macAddress, $display->macAddress, 'Mac Address not set correctly by XMDS Register Display');
        # Edit display and add broadcast channel
        $display->edit($display->display,
        $display->description, 
        $display->isAuditing, 
        $display->defaultLayoutId, 
        $display->licensed, 
        $display->license, 
        $display->incSchedule, 
        $display->emailAlert, 
        $display->wakeOnLanEnabled, 
        '127.0.0.1');
        # Call WOL
        $this->client->post('/display/wol/' . $display->displayId);
        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
    }
}
