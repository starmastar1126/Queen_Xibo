<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Theme;

class webpage extends Module
{
    /**
     * Install Files
     */
    public function InstallFiles()
    {
        $media = new Media();
        $media->addModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');;
        $media->addModuleFile('modules/preview/xibo-layout-scaler.js');;
        $media->addModuleFile('modules/preview/xibo-webpage-render.js');;
    }
    
    /**
     * Return the Add Form
     */
    public function AddForm()
    {
        $response = $this->getState();

        // Configure form
        $this->configureForm('AddMedia');

        $formFields = array();
         
        $formFields[] = FormManager::AddText('uri', __('Link'), NULL, 
            __('The Location (URL) of the webpage'), 'l', 'required');

        $formFields[] = FormManager::AddText('name', __('Name'), NULL, 
            __('An optional name for this media'), 'n');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this item should be displayed'), 'd', 'required');

        $formFields[] = FormManager::AddCombo(
            'modeid', 
            __('Options'), 
            NULL,
            array(
                    array('modeid' => '1', 'mode' => __('Open Natively')), 
                    array('modeid' => '2', 'mode' => __('Manual Position')),
                    array('modeid' => '3', 'mode' => __('Best Fit'))
                ),
            'modeid',
            'mode',
            __('How should this web page be embedded?'), 
            'm');

        $formFields[] = FormManager::AddNumber('pageWidth', __('Page Width'), NULL, 
            __('The width of the page. Leave empty to use the region width.'), 'w', NULL, 'webpage-widths');

        $formFields[] = FormManager::AddNumber('pageHeight', __('Page Height'), NULL, 
            __('The height of the page. Leave empty to use the region height'), 'h', NULL, 'webpage-widths');

        $formFields[] = FormManager::AddNumber('offsetTop', __('Offset Top'), NULL, 
            __('The starting point from the top in pixels'), 't', NULL, 'webpage-offsets');

        $formFields[] = FormManager::AddNumber('offsetLeft', __('Offset Left'), NULL, 
            __('The starting point from the left in pixels'), 'l', NULL, 'webpage-offsets');

        $formFields[] = FormManager::AddNumber('scaling', __('Scale Percentage'), NULL, 
            __('The Percentage to Scale this Webpage (0 - 100)'), 's', NULL, 'webpage-offsets');

        $formFields[] = FormManager::AddCheckbox('transparency', __('Background transparent?'), 
            NULL, __('Should the HTML be shown with a transparent background. Not currently available on the Windows Display Client.'), 
            't');

        // Field dependencies
        $modeFieldDepencies_1 = array(
                '.webpage-widths' => array('display' => 'none'),
                '.webpage-offsets' => array('display' => 'none'),
            );
        $modeFieldDepencies_2 = array(
                '.webpage-widths' => array('display' => 'block'),
                '.webpage-offsets' => array('display' => 'block'),
            );
        $modeFieldDepencies_3 = array(
                '.webpage-widths' => array('display' => 'block'),
                '.webpage-offsets' => array('display' => 'none'),
            );

        $response->AddFieldAction('modeid', 'init', 1, $modeFieldDepencies_1);
        $response->AddFieldAction('modeid', 'change', 1, $modeFieldDepencies_1);
        $response->AddFieldAction('modeid', 'init', 2, $modeFieldDepencies_2);
        $response->AddFieldAction('modeid', 'change', 2, $modeFieldDepencies_2);
        $response->AddFieldAction('modeid', 'init', 3, $modeFieldDepencies_3);
        $response->AddFieldAction('modeid', 'change', 3, $modeFieldDepencies_3);

        Theme::Set('form_fields', $formFields);

        $response->html = Theme::RenderReturn('form_render');
        $this->configureFormButtons($response);
        $response->dialogTitle = __('Add Webpage');

        return $response;
    }
    
    /**
     * Return the Edit Form as HTML
     * @return 
     */
    public function EditForm()
    {
        $response = $this->getState();

        // Edit calls are the same as add calls, except you will to check the user has permissions to do the edit
        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Configure the form
        $this->configureForm('EditMedia');

        $formFields = array();
        
        $formFields[] = FormManager::AddText('uri', __('Link'), urldecode($this->GetOption('uri')), 
            __('The Location (URL) of the webpage'), 'l', 'required');

        $formFields[] = FormManager::AddText('name', __('Name'), $this->GetOption('name'), 
            __('An optional name for this media'), 'n');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->getDuration(),
            __('The duration in seconds this item should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        $formFields[] = FormManager::AddCombo(
            'modeid', 
            __('Options'), 
            $this->GetOption('modeid'),
            array(
                    array('modeid' => '1', 'mode' => __('Open Natively')), 
                    array('modeid' => '2', 'mode' => __('Manual Position')),
                    array('modeid' => '3', 'mode' => __('Best Fit'))
                ),
            'modeid',
            'mode',
            __('How should this web page be embedded?'), 
            'm');

        $formFields[] = FormManager::AddNumber('pageWidth', __('Page Width'), $this->GetOption('pageWidth'), 
            __('The width of the page. Leave empty to use the region width.'), 'w', NULL, 'webpage-widths');

        $formFields[] = FormManager::AddNumber('pageHeight', __('Page Height'), $this->GetOption('pageHeight'), 
            __('The height of the page. Leave empty to use the region height'), 'h', NULL, 'webpage-widths');

        $formFields[] = FormManager::AddNumber('offsetTop', __('Offset Top'), $this->GetOption('offsetTop'), 
            __('The starting point from the top in pixels'), 't', NULL, 'webpage-offsets');

        $formFields[] = FormManager::AddNumber('offsetLeft', __('Offset Left'), $this->GetOption('offsetLeft'), 
            __('The starting point from the left in pixels'), 'l', NULL, 'webpage-offsets');

        $formFields[] = FormManager::AddNumber('scaling', __('Scale Percentage'), $this->GetOption('scaling'), 
            __('The Percentage to Scale this Webpage (0 - 100)'), 's', NULL, 'webpage-offsets');
           
        $formFields[] = FormManager::AddCheckbox('transparency', __('Background transparent?'), 
            $this->GetOption('transparency'), __('Should the HTML be shown with a transparent background. Not currently available on the Windows Display Client.'), 
            't');

        // Field dependencies
        $modeFieldDepencies_1 = array(
                '.webpage-widths' => array('display' => 'none'),
                '.webpage-offsets' => array('display' => 'none'),
            );
        $modeFieldDepencies_2 = array(
                '.webpage-widths' => array('display' => 'block'),
                '.webpage-offsets' => array('display' => 'block'),
            );
        $modeFieldDepencies_3 = array(
                '.webpage-widths' => array('display' => 'block'),
                '.webpage-offsets' => array('display' => 'none'),
            );

        $response->AddFieldAction('modeid', 'init', 1, $modeFieldDepencies_1);
        $response->AddFieldAction('modeid', 'change', 1, $modeFieldDepencies_1);
        $response->AddFieldAction('modeid', 'init', 2, $modeFieldDepencies_2);
        $response->AddFieldAction('modeid', 'change', 2, $modeFieldDepencies_2);
        $response->AddFieldAction('modeid', 'init', 3, $modeFieldDepencies_3);
        $response->AddFieldAction('modeid', 'change', 3, $modeFieldDepencies_3);

        Theme::Set('form_fields', $formFields);

        $response->html = Theme::RenderReturn('form_render');
        $this->configureFormButtons($response);
        $response->dialogTitle = __('Edit Webpage');

        return $response;
        $this->response->AddButton(__('Apply'), 'XiboDialogApply("#ModuleForm")');
    }
    
    /**
     * Add Media to the Database
     */
    public function AddMedia()
    {
        $response = $this->getState();

        // Other properties
        $uri = \Kit::GetParam('uri', _POST, _URI);
        $duration = \Kit::GetParam('duration', _POST, _INT, 0);
        $scaling = \Kit::GetParam('scaling', _POST, _INT, 100);
        $transparency = \Kit::GetParam('transparency', _POST, _CHECKBOX, 'off');
        $offsetLeft = \Xibo\Helper\Sanitize::getInt('offsetLeft');
        $offsetTop = \Xibo\Helper\Sanitize::getInt('offsetTop');
	$name = \Xibo\Helper\Sanitize::getString('name');
        
        // Validate the URL?
        if ($uri == "" || $uri == "http://")
            throw new InvalidArgumentException(__('Please enter a Link'));
        
        if ($duration == 0)
            throw new InvalidArgumentException(__('You must enter a duration.'));
        
        // Any Options
        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration()));
        $this->SetOption('xmds', true);
        $this->SetOption('uri', $uri);
        $this->SetOption('scaling', $scaling);
        $this->SetOption('transparency', $transparency);
        $this->SetOption('offsetLeft', $offsetLeft);
        $this->SetOption('offsetTop', $offsetTop);
        $this->SetOption('pageWidth', \Kit::GetParam('pageWidth', _POST, _INT));
        $this->SetOption('pageHeight', \Kit::GetParam('pageHeight', _POST, _INT));
        $this->SetOption('modeid', \Kit::GetParam('modeid', _POST, _INT));
	$this->SetOption('name', $name);

        // Save the widget
        $this->saveWidget();

        // Load form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        return $response;
    }
    
    /**
     * Edit Media in the Database
     * @return 
     */
    public function EditMedia()
    {
        $response = $this->getState();

        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Other properties
        $uri = \Kit::GetParam('uri', _POST, _URI);
        $scaling = \Kit::GetParam('scaling', _POST, _INT, 100);
        $transparency = \Kit::GetParam('transparency', _POST, _CHECKBOX, 'off');
        $offsetLeft = \Xibo\Helper\Sanitize::getInt('offsetLeft');
        $offsetTop = \Xibo\Helper\Sanitize::getInt('offsetTop');
	$name = \Xibo\Helper\Sanitize::getString('name');

        // Validate the URL?
        if ($uri == "" || $uri == "http://")
            throw new InvalidArgumentException(__('Please enter a Link'));

        // Any Options
        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration()));
        $this->SetOption('xmds', true);
        $this->SetOption('uri', $uri);
        $this->SetOption('scaling', $scaling);
        $this->SetOption('transparency', $transparency);
        $this->SetOption('offsetLeft', $offsetLeft);
        $this->SetOption('offsetTop', $offsetTop);
        $this->SetOption('pageWidth', \Kit::GetParam('pageWidth', _POST, _INT));
        $this->SetOption('pageHeight', \Kit::GetParam('pageHeight', _POST, _INT));
        $this->SetOption('modeid', \Kit::GetParam('modeid', _POST, _INT));
	$this->SetOption('name', $name);

        // Save the widget
        $this->saveWidget();

        // Load an edit form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();
            $this->response->callBack = 'refreshPreview("' . $this->regionid . '")';

        return $response;
    }

    /**
     * Preview code for a module
     * @param int $width
     * @param int $height
     * @param int $scaleOverride The Scale Override
     * @return string The Rendered Content
     */
    public function Preview($width, $height, $scaleOverride = 0)
    {
        // If we are opening the web page natively on the device, then we cannot offer a preview
        if ($this->GetOption('modeid') == 1)
            return '<div style="text-align:center;"><img alt="' . $this->type . ' thumbnail" src="theme/default/img/forms/' . $this->type . '.gif" /></div>';

        return $this->PreviewAsClient($width, $height, $scaleOverride);
    }

    /**
     * GetResource for Web page Media
     * @param int $displayId
     * @return mixed|string
     */
    public function GetResource($displayId = 0)
    {
        // Load in the template
        $template = file_get_contents('modules/preview/HtmlTemplate.html');
        
        // Replace the View Port Width?
        if (isset($_GET['preview']))
            $template = str_replace('[[ViewPortWidth]]', $this->region->width, $template);

        // Get some parameters
        $width = \Kit::GetParam('width', _REQUEST, _DOUBLE);
        $height = \Kit::GetParam('height', _REQUEST, _DOUBLE);

        // Work out the url
        $url = urldecode($this->GetOption('uri'));
        $url = (preg_match('/^' . preg_quote('http') . "/", $url)) ? $url : 'http://' . $url;

        // Set the iFrame dimensions
        $iframeWidth = $this->GetOption('pageWidth');
        $iframeHeight = $this->GetOption('pageHeight');

        $options = array(
                'modeId' => $this->GetOption('modeid'),
                'originalWidth' => intval($this->region->width),
                'originalHeight' => intval($this->region->height),
                'iframeWidth' => intval(($iframeWidth == '' || $iframeWidth == 0) ? $this->region->width : $iframeWidth),
                'iframeHeight' => intval(($iframeHeight == '' || $iframeHeight == 0) ? $this->region->height : $iframeHeight),
                'previewWidth' => intval($width),
                'previewHeight' => intval($height),
                'offsetTop' => intval($this->GetOption('offsetTop', 0)),
                'offsetLeft' => intval($this->GetOption('offsetLeft', 0)),
                'scale' => ($this->GetOption('scaling', 100) / 100),
                'scaleOverride' => \Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
            );

        // Head Content
        $headContent = '<style>#iframe { border:0; }</style>';
        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);

        // Body content
        $output = '<iframe id="iframe" scrolling="no" frameborder="0" src="' . $url . '"></iframe>';
        
        // Replace the Body Content with our generated text
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', $output, $template);

        // After body content
        $isPreview = (\Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');
        $after_body  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';
        $after_body .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';
        $after_body .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-webpage-render.js"></script>';
        $after_body .= '<script>
            var options = ' . json_encode($options) . '
            $(document).ready(function() {
                $("#content").xiboLayoutScaler(options)
                $("#iframe").xiboIframeScaler(options);
            });
            </script>';

        // Replace the After body Content
        $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $after_body, $template);

        return $template;
    }

    public function GetName() {
        return $this->GetOption('name');
    }

    public function IsValid()
    {
        // Can't be sure because the client does the rendering
        return 2;
    }
}
?>
