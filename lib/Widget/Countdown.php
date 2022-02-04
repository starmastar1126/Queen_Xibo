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
 *
 * 
 * Template strings to be translated, that will be used to replace tags in the ||tag|| format
 * __('Years')
 * __('Months')
 * __('Weeks')
 * __('Hours')
 * __('Minutes')
 * __('Seconds')
 * __('Total Hours')
 * __('Total Minutes')
 * __('Total Seconds')
 */
namespace Xibo\Widget;

use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Countdown
 * @package Xibo\Widget
 */
class Countdown extends ModuleWidget
{
    public $codeSchemaVersion = 1;


    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->resourceFolder = PROJECT_ROOT . '/modules/countdown';

        // Initialise extra validation rules
        v::with('Xibo\\Validation\\Rules\\');
    }
    
    /**
     * @inheritDoc
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Countdown';
            $module->type = 'countdown';
            $module->class = 'Xibo\Widget\Countdown';
            $module->description = 'Countdown Module';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 5;
            $module->settings = [];
            $module->installName = 'countdown';

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
        
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-countdown-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/moment.js')->save();
    }

    /**
     * @inheritDoc
     */
    public function layoutDesignerJavaScript()
    {
        return 'countdown-designer-javascript';
    }

    /**
     * Edit Countdown
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?countdown",
     *  operationId="widgetCountdownEdit",
     *  tags={"widget"},
     *  summary="Countdown Widget",
     *  description="Edit Countdown Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      name="themeId",
     *      in="formData",
     *      description="Flag (0 , 1) for Analogue countdown the light and dark theme",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="countdownType",
     *      in="formData",
     *      description="Type of a countdown widget 1-Use widget duration, 2- Custom duration",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="countdownDuration",
     *      in="formData",
     *      description="The duration in minutes, or a target date/time in the format Y-m-d H:i:s",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="countdownWarningDuration",
     *      in="formData",
     *      description="The warning duration in minutes, or a target date/time in the format Y-m-d H:i:s",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="overrideTemplate",
     *      in="formData",
     *      description="flag (0, 1) set to 0 and use templateId or set to 1 and provide whole template in the next parameters",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="widgetOriginalWidth",
     *      in="formData",
     *      description="This is the intended Width of the template and is used to scale the Widget within it's region when the template is applied, Pass only with overrideTemplate set to 1",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="widgetOriginalHeight",
     *      in="formData",
     *      description="This is the intended Height of the template and is used to scale the Widget within it's region when the template is applied, Pass only with overrideTemplate set to 1",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="mainTemplate",
     *      in="formData",
     *      description="Main template, Pass only with overrideTemplate set to 1 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="mainTemplate_advanced",
     *      in="formData",
     *      description="A flag (0, 1), Should text area by presented as a visual editor?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="styleSheet",
     *      in="formData",
     *      description="Optional StyleSheet Pass only with overrideTemplate set to 1 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="alignH",
     *      in="formData",
     *      description="Horizontal alignment - left, center, bottom",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="alignV",
     *      in="formData",
     *      description="Vertical alignment - top, middle, bottom",
     *      type="string",
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

        // You must also provide a duration (all media items must provide this field)
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));

        $this->setOption('countdownType', $sanitizedParams->getInt('countdownType', ['default' => 1]));
        $this->setOption('countdownDuration', $sanitizedParams->getString('countdownDuration', ['default' => 0]));
        $this->setOption('countdownWarningDuration', $sanitizedParams->getString('countdownWarningDuration', ['default' => 0]));
        $this->setOption('countdownDate', $sanitizedParams->getDate('countdownDate'));
        $this->setOption('countdownWarningDate', $sanitizedParams->getDate('countdownWarningDate'));
        $this->setOption('alignH', $sanitizedParams->getString('alignH', ['default' => 'center']));
        $this->setOption('alignV', $sanitizedParams->getString('alignV', ['default' => 'middle']));

        $this->setOption('templateId', $sanitizedParams->getString('templateId'));
        $this->setOption('overrideTemplate', $sanitizedParams->getCheckbox('overrideTemplate'));

        if ($this->getOption('overrideTemplate') == 1) {
            $this->setRawNode('mainTemplate', $request->getParam('mainTemplate', $request->getParam('mainTemplate', null)));
            $this->setOption('mainTemplate_advanced', $sanitizedParams->getCheckbox('mainTemplate_advanced'));

            $this->setRawNode('styleSheet', $request->getParam('styleSheet', $request->getParam('styleSheet', null)));

            $this->setOption('widgetOriginalWidth', $sanitizedParams->getInt('widgetOriginalWidth'));
            $this->setOption('widgetOriginalHeight', $sanitizedParams->getInt('widgetOriginalHeight'));
        }

        $this->isValid();

        // Save the widget
        $this->saveWidget();

        return $response;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        $data = [];

        // Set the null values for template variables.
        $mainTemplate = null;
        $styleSheet = null;
        $widgetOriginalWidth = null;
        $widgetOriginalHeight = null;

        // Replace the View Port Width?
        $data['viewPortWidth'] = $this->isPreview() ? $this->region->width : '[[ViewPortWidth]]';

        // Information from the Module        
        $duration = $this->getCalculatedDurationForGetResource();

        if($this->getOption('overrideTemplate') == 0) {
            
            $template = $this->getTemplateById($this->getOption('templateId'));

            if (isset($template)) {
                $mainTemplate = $template['template'];
                $styleSheet = $template['css'];
                $widgetOriginalWidth = $template['widgetOriginalWidth'];
                $widgetOriginalHeight = $template['widgetOriginalHeight'];
            }
            
        } else {
            $mainTemplate = $this->getRawNode('mainTemplate');
            $styleSheet = $this->getRawNode('styleSheet', '');
            $widgetOriginalWidth = intval($this->getOption('widgetOriginalWidth'));
            $widgetOriginalHeight = intval($this->getOption('widgetOriginalHeight'));
        }

        // Run through each item and substitute with the template
        $mainTemplate = $this->parseLibraryReferences($this->isPreview(), $mainTemplate);

        // Make substitutions
        $mainTemplate = $this->makeSubstitutions($mainTemplate);

        // Parse translations
        $mainTemplate = $this->parseTranslations($mainTemplate);
        
        $options = array(
            'type' => $this->getModuleType(),
            'duration' => $duration,
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'widgetDesignWidth' => $widgetOriginalWidth,
            'widgetDesignHeight'=> $widgetOriginalHeight,
            'countdownType' => $this->getOption('countdownType'),
            'countdownDuration' => $this->getOption('countdownDuration'),
            'countdownDate' => $this->getOption('countdownDate'),
            'countdownWarningDuration' => $this->getOption('countdownWarningDuration'),
            'countdownWarningDate' => $this->getOption('countdownWarningDate'),
            'alignmentH' => $this->getOption('alignH'),
            'alignmentV' => $this->getOption('alignV')
        );

        // Replace the head content
        $headContent = '';

        // Add our fonts.css file
        $headContent .= '<link href="' . ($this->isPreview() ? $this->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        
        // Add the CSS if it isn't empty, and replace the wallpaper
        if ($styleSheet != '') {
            $headContent .= '<style type="text/css">' . $this->parseLibraryReferences($this->isPreview(), $styleSheet) . '</style>';
        }
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        // Replace the Head Content with our generated javascript
        $data['head'] = $headContent;

        // Add some scripts to the JavaScript Content
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/moment.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-countdown-render.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-image-render.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript">var xiboICTargetId = ' . $this->getWidgetId() . ';</script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-interactive-control.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript">xiboIC.lockAllInteractions();</script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var body = ' . json_encode($mainTemplate) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '     $("body").xiboLayoutScaler(options); $("#content").find("img").xiboImageRender(options); ';
        
        // Run based only if the element is visible or not
        $javaScriptContent .= '     var runOnVisible = function() { $("#content").xiboCountdownRender(options, body); }; ';
        $javaScriptContent .= '     (xiboIC.checkVisible()) ? runOnVisible() : xiboIC.addToQueue(runOnVisible); ';
        
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data);
    }


    /**
     * Run through the data and substitute into the template
     * @param $source
     * @return mixed
     */
    private function makeSubstitutions($source)
    {
        // Replace all matches.
        $matches = '';
        preg_match_all('/\[.*?\]/', $source, $matches);
        
        // Substitute
        foreach ($matches[0] as $sub) {
            $replace = str_replace('[', '', str_replace(']', '', $sub));
            $replacement = 'NULL';
            
            // Replace tags
            switch ($replace) {
                case 'ss':
                    $replacement = '<span class="seconds"></span>';
                    break;
                case 'ssa':
                    $replacement = '<span class="secondsAll"></span>';
                    break;
                case 'mm':
                    $replacement = '<span class="minutes"></span>';
                    break;
                case 'mma':
                    $replacement = '<span class="minutesAll"></span>';
                    break;
                case 'hh':
                    $replacement = '<span class="hours"></span>';
                    break;
                case 'hha':
                    $replacement = '<span class="hoursAll"></span>';
                    break;
                case 'DD':
                    $replacement = '<span class="days"></span>';
                    break;
                case 'WW':
                    $replacement = '<span class="weeks"></span>';
                    break;
                case 'MM':
                    $replacement = '<span class="months"></span>';
                    break;
                case 'YY':
                    $replacement = '<span class="years"></span>';
                    break;
                default:
                    $replacement = 'NULL';
                    break;
            }
            
            // Replace the variable on the source string
            $source = str_replace($sub, $replacement, $source);
        }

        return $source;
    }


    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getOption('overrideTemplate') == 0 && ( $this->getOption('templateId') == '' || $this->getOption('templateId') == null)) {
            throw new InvalidArgumentException(__('Please choose a template'), 'templateId');
        }

        if ($this->getUseDuration() == 1 && !v::intType()->min(1)->validate($this->getDuration())) {
            throw new InvalidArgumentException(__('Please enter a duration.'), 'duration');
        }

        if($this->getOption('countdownType') == 1 && $this->getDuration() <= $this->getOption('countdownWarningDuration')) {
            throw new InvalidArgumentException(__('Warning duration needs to be lower than the widget duration.'), 'countdownWarningDuration');
        }

        if($this->getOption('countdownType') == 2 && ($this->getOption('countdownDuration') == '' || $this->getOption('countdownDuration') == null || $this->getOption('countdownDuration') <= 0)) {
            throw new InvalidArgumentException(__('Please enter a positive countdown duration.'), 'countdownDuration');
        }

        if($this->getOption('countdownType') == 2 && $this->getOption('countdownDuration') <= $this->getOption('countdownWarningDuration')) {
            throw new InvalidArgumentException(__('Warning duration needs to be lower than the countdown main duration.'), 'countdownWarningDuration');
        }

        if($this->getOption('countdownType') == 3 && ($this->getOption('countdownDate') == '' || $this->getOption('countdownDate') == null)) {
            throw new InvalidArgumentException(__('Please enter countdown date.'), 'countdownDate');
        }

        return self::$STATUS_VALID;
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // We have a long cache interval because we don't depend on any external data.
        return 86400 * 365;
    }

    /** @inheritDoc */
    public function hasTemplates()
    {
        return true;
    }

    /** @inheritDoc */
    public function hasHtmlEditor()
    {
        return true;
    }

    /** @inheritDoc */
    public function getHtmlWidgetOptions()
    {
        return ['mainTemplate'];
    }
}
