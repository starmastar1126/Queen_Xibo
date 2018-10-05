<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (DisplayHelperTrait.php)
 */


namespace Xibo\Tests\Helper;


use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Exception\XiboApiException;

/**
 * Trait DisplayHelperTrait
 * @package Helper
 */
trait DisplayHelperTrait
{
    /**
     * @param int $status
     * @return XiboDisplay
     */
    protected function createDisplay($status = null)
    {
        // Generate names for display and xmr channel
        $hardwareId = Random::generateString(12, 'phpunit');
        $xmrChannel = Random::generateString(50);

        // This is a dummy pubKey and isn't used by anything important
        $xmrPubkey = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDmdnXL4gGg3yJfmqVkU1xsGSQI
3b6YaeAKtWuuknIF1XAHAHtl3vNhQN+SmqcNPOydhK38OOfrdb09gX7OxyDh4+JZ
inxW8YFkqU0zTqWaD+WcOM68wTQ9FCOEqIrbwWxLQzdjSS1euizKy+2GcFXRKoGM
pbBhRgkIdydXoZZdjQIDAQAB
-----END PUBLIC KEY-----';

        // Register our display
        $this->getXmdsWrapper()->RegisterDisplay($hardwareId,
            $hardwareId,
            'windows',
            null,
            null,
            null,
            '00:16:D9:C9:AL:69',
            $xmrChannel,
            $xmrPubkey
        );

        // Now find the Id of that Display
        $displays = (new XiboDisplay($this->getEntityProvider()))->get(['hardwareKey' => $hardwareId]);

        if (count($displays) != 1)
            $this->fail('Display was not added correctly');

        /** @var XiboDisplay $display */
        $display = $displays[0];

        // Set the initial status
        if ($status !== null)
            $this->displaySetStatus($display, $status);

        return $display;
    }

    /**
     * @param XiboDisplay $display
     * @param int $status
     */
    protected function displaySetStatus($display, $status)
    {
        $display->mediaInventoryStatus = $status;

        $this->getStore()->update('UPDATE `display` SET MediaInventoryStatus = :status, auditingUntil = :auditingUntil WHERE displayId = :displayId', [
            'displayId' => $display->displayId,
            'auditingUntil' => time() + 86400,
            'status' => $status
        ]);
        $this->getStore()->commitIfNecessary();
    }

    /**
     * @param XiboDisplay $display
     */
    protected function displaySetLicensed($display)
    {
        $this->getStore()->update('UPDATE `display` SET licensed = 1, auditingUntil = :auditingUntil WHERE displayId = :displayId', [
            'displayId' => $display->displayId,
            'auditingUntil' => (time() + 86400)
        ]);
        $this->getStore()->commitIfNecessary();
    }

    /**
     * @param XiboDisplay $display
     */
    protected function deleteDisplay($display)
    {
        $display->delete();
    }

    /**
     * @param XiboDisplay $display
     * @param int $status
     * @return bool
     */
    protected function displayStatusEquals($display, $status)
    {
        // Requery the Display
        try {
            $check = (new XiboDisplay($this->getEntityProvider()))->getById($display->displayGroupId);

            $this->getLogger()->debug('Tested Display ' . $display->display . '. Status returned is ' . $check->mediaInventoryStatus);

            return $check->mediaInventoryStatus === $status;

        } catch (XiboApiException $xiboApiException) {
            $this->getLogger()->error('API exception for ' . $display->displayId. ': ' . $xiboApiException->getMessage());
            return false;
        }

    }
}