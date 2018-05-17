<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (LayoutHelperTrait.php)
 */


namespace Xibo\Tests\Helper;


use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboResolution;
use Xibo\OAuth2\Client\Exception\XiboApiException;

/**
 * Trait LayoutHelperTrait
 * @package Helper
 */
trait LayoutHelperTrait
{
    /**
     * @param int|null $status
     * @return XiboLayout
     */
    protected function createLayout($status = null)
    {
        // Create a Layout for us to work with.
        $layout = (new XiboLayout($this->getEntityProvider()))
            ->create(
                Random::generateString(),
                'PHPUnit Created Layout for Automated Integration Testing',
                '',
                $this->getResolutionId('landscape')
            );

        $this->getLogger()->debug('Layout created with name ' . $layout->layout);

        if ($status !== null) {
            // Set the initial status of this Layout to Built
            $this->setLayoutStatus($layout, $status);
        }

        return $layout;
    }

    /**
     * @param XiboLayout $layout
     * @param int $status
     * @return $this
     */
    protected function setLayoutStatus($layout, $status)
    {
        $layout->status = $status;
        $this->getStore()->update('UPDATE `layout` SET `status` = :status WHERE layoutId = :layoutId', ['layoutId' => $layout->layoutId, 'status' => $status]);
        $this->getStore()->commitIfNecessary();

        return $this;
    }

    /**
     * Build the Layout ready for XMDS
     * @param XiboLayout $layout
     * @return $this
     */
    protected function buildLayout($layout)
    {
        // Call the status route
        $this->getEntityProvider()->get('/layout/status/' . $layout->layoutId);

        return $this;
    }

    /**
     * @param XiboLayout $layout
     */
    protected function deleteLayout($layout)
    {
        $layout->delete();
    }

    /**
     * @param XiboLayout $layout
     * @param int $status
     * @return bool
     */
    protected function layoutStatusEquals($layout, $status)
    {
        // Requery the Display
        try {
            $check = (new XiboLayout($this->getEntityProvider()))->getById($layout->layoutId);

            $this->getLogger()->debug('Tested Layout ' . $layout->layout . '. Status returned is ' . $check->status);

            return $check->status === $status;

        } catch (XiboApiException $xiboApiException) {
            $this->getLogger()->error('API exception for ' . $layout->layoutId . ': ' . $xiboApiException->getMessage());
            return false;
        }

    }

    /**
     * @param $type
     * @return int
     */
    protected function getResolutionId($type)
    {
        if ($type === 'landscape') {
            $width = 1920;
            $height = 1080;
        } else if ($type === 'portrait') {
            $width = 1080;
            $height = 1920;
        } else {
            return -10;
        }

        //$this->getLogger()->debug('Querying for ' . $width . ', ' . $height);

        $resolutions = (new XiboResolution($this->getEntityProvider()))->get(['width' => $width, 'height' => $height]);

        if (count($resolutions) <= 0)
            return -10;

        return $resolutions[0]->resolutionId;
    }

    /**
     * @param XiboLayout $layout
     * @return XiboLayout
     */
    protected function checkout($layout)
    {
        $this->getLogger()->debug('Checkout ' . $layout->layoutId);

        $response = $this->getEntityProvider()->put('/layout/checkout/' . $layout->layoutId);

        // Swap the Layout object to use the one returned.
        /** @var XiboLayout $layout */
        $layout = $this->constructLayoutFromResponse($response);

        $this->getLogger()->debug('LayoutId is now: ' . $layout->layoutId);

        return $layout;
    }

    /**
     * @param XiboLayout $layout
     * @return XiboLayout
     */
    protected function publish($layout)
    {
        $this->getLogger()->debug('Publish ' . $layout->layoutId);

        $response = $this->getEntityProvider()->put('/layout/publish/' . $layout->layoutId);

        // Swap the Layout object to use the one returned.
        /** @var XiboLayout $layout */
        $layout = $this->constructLayoutFromResponse($response);

        $this->getLogger()->debug('LayoutId is now: ' . $layout->layoutId);

        return $layout;
    }

    /**
     * @param XiboLayout $layout
     * @return XiboLayout
     */
    protected function discard($layout)
    {
        $this->getLogger()->debug('Discard ' . $layout->layoutId);

        $response = $this->getEntityProvider()->put('/layout/discard/' . $layout->layoutId);

        // Swap the Layout object to use the one returned.
        /** @var XiboLayout $layout */
        $layout = $this->constructLayoutFromResponse($response);

        $this->getLogger()->debug('LayoutId is now: ' . $layout->layoutId);

        return $layout;
    }

    /**
     * @param $response
     * @return \Xibo\OAuth2\Client\Entity\XiboEntity|XiboLayout
     */
    private function constructLayoutFromResponse($response)
    {
        /** @var XiboLayout $layout */
        $layout = new XiboLayout($this->getEntityProvider());
        $layout = $layout->hydrate($response);

        if (isset($response['regions'])) {
            foreach ($response['regions'] as $item) {
                $region = new XiboRegion($this->getEntityProvider());
                $region->hydrate($item);

                // Add to parent object
                $layout->regions[] = $region;
            }
        } else {
            $this->getLogger()->debug('No regions returned with Layout object');
        }

        return $layout;
    }
}