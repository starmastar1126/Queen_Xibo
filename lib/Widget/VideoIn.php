<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class VideoIn
 * @package Xibo\Widget
 */
class VideoIn extends ModuleWidget
{
    /**
     * @inheritDoc
     */
    public function layoutDesignerJavaScript()
    {
        return 'videoin-designer-javascript';
    }

    /** @inheritdoc */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Video In';
            $module->type = 'videoin';
            $module->class = 'Xibo\Widget\VideoIn';
            $module->description = 'A module for displaying Video and Audio from an external source';
            $module->enabled = 1;
            $module->previewEnabled = 0;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'native';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 60;
            $module->settings = [];
            $module->installName = 'videoin';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * Edit
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?videoIn",
     *  operationId="WidgetVideoInEdit",
     *  tags={"widget"},
     *  summary="Edit a Video In Widget",
     *  description="Edit a Video In Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The WidgetId to Edit",
     *      type="integer",
     *      required=true
     *   ),
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
     *      description="Flag (0, 1) Select only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="sourceId",
     *      in="formData",
     *      description="Which device input should be shown? available options: HDMI, RGB, DVI, DP, OPS",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="showFullScreen",
     *      in="formData",
     *      description="Should the video expand over the top of existing content and show in full screen?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation"
     *  )
     * )
     *
     * @inheritDoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Set some options
        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setOption('sourceId', $sanitizedParams->getString('sourceId', ['default' => 'hdmi']));
        $this->setOption('showFullScreen', $sanitizedParams->getCheckbox('showFullScreen'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));

        $this->isValid();

        // Save the widget
        $this->saveWidget();

        return $response;
    }

    /** @inheritdoc */
    public function isValid()
    {
        if (!v::stringType()->notEmpty()->validate($this->getOption('sourceId'))) {
            throw new InvalidArgumentException(__('Please Select the sourceId'), 'sourceId');
        }

        if ($this->getUseDuration() == 1 && !v::intType()->min(1)->validate($this->getDuration())) {
            throw new InvalidArgumentException(__('You must enter a duration.'), 'duration');
        }

        // Client dependant
        return self::$STATUS_PLAYER;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        // Get resource isn't required for this module
        return null;
    }
}
