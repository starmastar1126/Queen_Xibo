<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (NotificationView.php)
 */


namespace Xibo\Widget;

use Xibo\Factory\NotificationFactory;

/**
 * Class NotificationView
 * @package Xibo\Widget
 */
class NotificationView extends ModuleWidget
{
    /**
     * Install Files
     */
    public function InstallFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-text-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery.marquee.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-cycle-2.1.6.min.js')->save();
    }

    /**
     * @return string
     */
    public function layoutDesignerJavaScript()
    {
        return 'notificationview-designer-javascript';
    }

    /**
     * Adds an Notification Widget
     * @SWG\Post(
     *  path="/playlist/widget/notificationview/{playlistId}",
     *  operationId="WidgetNotificationAdd",
     *  tags={"widget"},
     *  summary="Add a Notification Widget",
     *  description="Add a new Notification Widget to the specified playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The playlist ID to add an Notification Widget",
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
     *      name="age",
     *      in="formData",
     *      description="The maximum notification age in minutes - 0 for all",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="noDataMessage",
     *      in="formData",
     *      description="Message to show when no notifications are available",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="effect",
     *      in="formData",
     *      description="Effect that will be used to transitions between items, available options: fade, fadeout, scrollVert, scollHorz, flipVert, flipHorz, shuffle, tileSlide, tileBlind ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="speed",
     *      in="formData",
     *      description="The transition speed of the selected effect in milliseconds (1000 = normal)",
     *      type="integer",
     *      required=false
     *   ),     *
     *  @SWG\Parameter(
     *      name="durationIsPerItem",
     *      in="formData",
     *      description="A flag (0, 1), The duration specified is per page/item, otherwise the widget duration is divided between the number of pages/items",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embedStyle",
     *      in="formData",
     *      description="Custom Style Sheets (CSS)",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new widget",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function add()
    {
        $this->setCommonOptions();
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit Media in the Database
     */
    public function edit()
    {
        $this->setCommonOptions();
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Set common options from Request Params
     */
    private function setCommonOptions()
    {

        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('age', $this->getSanitizer()->getInt('age'));
        $this->setOption('effect', $this->getSanitizer()->getString('effect'));
        $this->setOption('speed', $this->getSanitizer()->getInt('speed'));
        $this->setOption('durationIsPerItem', $this->getSanitizer()->getCheckbox('durationIsPerItem'));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 60));
        $this->setRawNode('noDataMessage', $this->getSanitizer()->getParam('noDataMessage', null));
        $this->setRawNode('template', $this->getSanitizer()->getParam('template', null));
        $this->setRawNode('embedStyle', $this->getSanitizer()->getParam('embedStyle', null));
    }

    private function validate()
    {

    }

    /** @inheritdoc */
    public function isValid()
    {
        // Can't be sure because the client does the rendering
        return 2;
    }

    /**
     * @return NotificationFactory
     */
    private function getNotificationFactory()
    {
        return $this->getApp()->container->get('notificationFactory');
    }

    /**
     * @param $isPreview
     * @param $displayId
     * @return array
     */
    private function getNotifications($isPreview, $displayId = null)
    {
        // Date format
        $dateFormat = $this->getOption('dateFormat', $this->getConfig()->GetSetting('DATE_FORMAT'));
        $age = $this->getOption('age', 0);

        // Parse the text template
        $template = $this->getRawNode('template', '');
        $matches = '';
        preg_match_all('/\[.*?\]/', $template, $matches);

        $items = [];

        if ($isPreview)
            $notifications = $this->getNotificationFactory()->query(['releaseDt DESC', 'createDt DESC', 'subject'], [
                'releaseDt' => ($age === 0) ? null : $this->getDate()->parse()->subMinutes($age)->format('U'),
                'userId' => $this->getUser()->userId
            ]);
        else
            $notifications = $this->getNotificationFactory()->query(['releaseDt DESC', 'createDt DESC', 'subject'], [
                'releaseDt' => ($age === 0) ? null : $this->getDate()->parse()->subMinutes($age)->format('U'),
                'displayId' => $displayId
            ]);

        $this->getLog()->debug('There are ' . count($notifications) . ' to render.');

        foreach ($notifications as $notification) {
            $rowString = $template;

            // Run through all [] substitutes in $matches
            foreach ($matches[0] as $sub) {
                $replace = '';

                // Use the pool of standard tags
                switch ($sub) {
                    case '[Name]':
                        $replace = $this->getOption('name');
                        break;

                    case '[Subject]':
                        $replace = $notification->subject;
                        break;

                    case '[Body]':
                        $replace = strip_tags($notification->body);
                        break;

                    case '[Date]':
                        $replace = $this->getDate()->getLocalDate($notification->releaseDt, $dateFormat);
                        break;
                }

                // Substitute the replacement we have found (it might be '')
                $rowString = str_replace($sub, $replace, $rowString);

            }

            $items[] = $rowString;
        }

        if (count($items) <= 0) {
            $items[] = $this->getRawNode('noDataMessage', null);
        }

        return $items;
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        // Behave exactly like the client.
        $data = [];
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Items
        $items = $this->getNotifications($isPreview, $displayId);

        // Include some vendor items
        $javaScriptContent  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-text-render.js') . '"></script>';// Need the marquee plugin?

        $effect = $this->getOption('effect');
        if (stripos($effect, 'marquee') !== false)
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery.marquee.min.js') . '"></script>';

        // Need the cycle plugin?
        if ($effect != 'none')
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js') . '"></script>';

        // Get the Style Sheet
        $styleSheetContent = $this->parseLibraryReferences($isPreview, $this->getRawNode('embedStyle', null));

        // Set some options
        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $effect,
            'duration' => $this->getDuration(),
            'durationIsPerItem' => false,
            'numItems' => 0,
            'takeItemsFrom' => 'start',
            'itemsPerPage' => 0,
            'speed' => $this->getOption('speed', 0),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
            'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0),
            'marqueeInlineSelector' => $this->getOption('marqueeInlineSelector', '.item, .item p')
        );

        // Add an options variable with some useful information for scaling
        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { $("body").xiboLayoutScaler(options); $("#content").xiboTextRender(options, items); });';
        $javaScriptContent .= '</script>';

        // Add our fonts.css file
        $headContent = '<link href="' . (($isPreview) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        $data['head'] = $headContent;

        // Replace the Style Sheet Content with our generated Style Sheet
        $data['styleSheet'] = $styleSheetContent;

        // Replace the Head Content with our generated java script
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data);
    }

    /** @inheritdoc */
    public function getModifiedTimestamp($displayId)
    {
        $widgetModifiedDt = null;
        $age = $this->getOption('age', 0);

        // Get the date/time of the last notification drawn by this Widget
        $notifications = $this->getNotificationFactory()->query(['releaseDt DESC', 'createDt DESC'], [
            'releaseDt' => ($age === 0) ? null : $this->getDate()->parse()->subMinutes($age)->format('U'),
            'displayId' => $displayId,
            'length' => 1
        ]);

        // Get the release date from the notification returned
        if (count($notifications) > 0) {
            $widgetModifiedDt = $notifications[0]->releaseDt;
        }

        return $widgetModifiedDt;
    }

    /** @inheritdoc */
    public function getCacheKey($displayId)
    {
        return $this->getWidgetId() . '_' . $displayId;
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // We have a long cache interval because we don't depend on any external data.
        // the modified timestamp expires us, unless we have an "age" parameter
        return $this->getOption('age', 1440 * 365) * 60;
    }
}