<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
namespace Xibo\Widget;

use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class GoogleTraffic
 * @package Xibo\Widget
 */
class GoogleTraffic extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    /** @inheritdoc */
    public function init()
    {
        // Initialise extra validation rules
        v::with('Xibo\\Validation\\Rules\\');
    }

    /**
     * @inheritDoc
     */
    public function layoutDesignerJavaScript()
    {
        return 'googletraffic-designer-javascript';
    }

    /**
     * @inheritDoc
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Google Traffic';
            $module->type = 'googletraffic';
            $module->class = 'Xibo\Widget\GoogleTraffic';
            $module->description = 'Google Traffic Map';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 600;
            $module->settings = [];
            $module->installName = 'googletraffic';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * @inheritDoc
     */
    public function installFiles()
    {
        // Extends parent's method
        parent::installFiles();
    }

    /**
     * @inheritDoc
     */
    public function settingsForm()
    {
        return 'googletraffic-form-settings';
    }

    /**
     * @inheritDoc
     */
    public function settings(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Process any module settings you asked for.
        $apiKey = $sanitizedParams->getString('apiKey');

        if ($this->module->enabled != 0) {
            if ($apiKey == '') {
                throw new InvalidArgumentException(__('Missing API Key'), 'apiKey');
            }
        }

        $this->module->settings['apiKey'] = $apiKey;

        // Minimum duration
        $this->module->settings['minDuration'] = $sanitizedParams->getInt('minDuration');

        // Validate that the default duration isn't lower that the min duration
        if ($this->module->settings['minDuration'] > $this->module->defaultDuration)
            throw new InvalidArgumentException(__('Please set your default duration higher than your minimum'), 'defaultDuration');

        // Should we reset all widgets?
        if ($sanitizedParams->getCheckbox('resetAllWidgets') == 1) {
            $this->getStore()->update('UPDATE `widget` SET duration = :duration WHERE type = :type AND useDuration = 1', [
                'type' => 'googletraffic',
                'duration' => $this->module->settings['minDuration']
            ]);

            // Dump the cache to force a re-cache of all the API keys
            $this->dumpCacheForModule();
        }

        return $response;
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?traffic",
     *  operationId="widgetGoogleTrafficEdit",
     *  tags={"widget"},
     *  summary="Edit a Google Traffic Widget",
     *  description="Edit a Google traffic Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The WidgetId to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Optional Widget Name",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The Widget Duration",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="(0, 1) Select 1 only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="zoom",
     *      in="formData",
     *      description="How far should the map be zoomed in? The higher the number the closer the zoom, 1 represents entire globe",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="useDisplayLocation",
     *      in="formData",
     *      description="Flag (0, 1) Use the location configured on display",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="longitude",
     *      in="formData",
     *      description="The longitude for this Google Traffic widget, only pass if useDisplayLocation set to 0",
     *      type="number",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="latitude",
     *      in="formData",
     *      description="The latitude for this Google Traffic widget, only pass if useDisplayLocation set to 0",
     *      type="number",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @inheritDoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));
        $this->setOption('useDisplayLocation', $sanitizedParams->getCheckbox('useDisplayLocation'));
        $this->setOption('longitude', $sanitizedParams->getDouble('longitude'));
        $this->setOption('latitude', $sanitizedParams->getDouble('latitude'));
        $this->setOption('zoom', $sanitizedParams->getInt('zoom'));

        $this->isValid();

        // Save the widget
        $this->saveWidget();

        return $response;
    }

    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0) {
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');
        }

        if ($this->getOption('zoom') == '') {
            throw new InvalidArgumentException(__('Please enter a zoom level'), 'zoom');
        }

        if ($this->getOption('useDisplayLocation') == 0) {
            // Validate lat/long
            if (!v::latitude()->validate($this->getOption('latitude'))) {
                throw new InvalidArgumentException(__('The latitude entered is not valid.'), 'latitude');
            }

            if (!v::longitude()->validate($this->getOption('longitude'))) {
                throw new InvalidArgumentException(__('The longitude entered is not valid.'), 'longitude');
            }
        }

        // Check the duration against the minDuration setting
        $minDuration = $this->getSetting('minDuration', 600);
        if ($this->getUseDuration() == 1 && $this->getDuration() < $minDuration)
            throw new InvalidArgumentException(__('The minimum duration for this Widget is %d.', $minDuration), 'duration');

        return self::$STATUS_PLAYER;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        if ($this->getSetting('apiKey') == '') {
            throw new ConfigurationException(__('Module not configured. Missing API Key.'));
        }

        // Get the lat/long
        $defaultLat = $this->getConfig()->getSetting('DEFAULT_LAT');
        $defaultLong = $this->getConfig()->getSetting('DEFAULT_LONG');

        if ($this->getOption('useDisplayLocation') == 1) {
            // Use the display ID or the default.
            if ($displayId != 0) {

                $display = $this->displayFactory->getById($displayId);

                if ($display->latitude != '' && $display->longitude != ''
                    && v::latitude()->validate($display->latitude) && v::longitude()->validate($display->longitude)
                ) {
                    $defaultLat = $display->latitude;
                    $defaultLong = $display->longitude;
                } else {
                    $this->getLog()->info('Warning, display %s does not have a lat/long and yet a forecast widget is set to use display location.', $display->display);
                }
            }
        } else {
            $defaultLat = $this->getOption('latitude', $defaultLat);
            $defaultLong = $this->getOption('longitude', $defaultLong);
        }

        if (!v::longitude()->validate($defaultLong) || !v::latitude()->validate($defaultLat)) {
            $this->getLog()->error('Traffic widget configured with incorrect lat/long. WidgetId is ' . $this->getWidgetId()
                . ', Lat is ' . $defaultLat . ', Lng is ' . $defaultLong);
            return false;
        }

        // Include some vendor items
        $javaScriptContent  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript">var xiboICTargetId = ' . $this->getWidgetId() . ';</script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-interactive-control.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript">xiboIC.lockAllInteractions();</script>';

        return $this->renderTemplate([
            'viewPortWidth' => ($this->isPreview()) ? $this->region->width : '[[ViewPortWidth]]',
            'apiKey' => $this->getSetting('apiKey'),
            'javaScript' => $javaScriptContent,
            'lat' => $defaultLat,
            'long' => $defaultLong,
            'zoom' => $this->getOption('zoom', 12)
        ], 'google-traffic-get-resource');
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // We have a long cache interval because we don't depend on any external data.
        return 86400 * 365;
    }

    /** @inheritdoc */
    public function getCacheKey($displayId)
    {
        return $this->getWidgetId() . (($this->getOption('useDisplayLocation') == 1 || $displayId === 0) ? '_' . $displayId : '');
    }

    /** @inheritdoc */
    public function isCacheDisplaySpecific()
    {
        return ($this->getOption('useDisplayLocation') == 1);
    }
}