<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
 * Theme variables:
 *  buttons = An array containing the media buttons
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
    <?php echo Theme::Get('form_meta'); ?>
    <div class="control-group">
        <label class="control-label" for="dataset" accesskey="n" title="<?php echo Theme::Translate('The DataSet for this View'); ?>"><?php echo Theme::Translate('DataSet'); ?></label>
        <div class="controls">
            <?php echo Theme::SelectList('datasetid', Theme::Get('dataset_field_list'), 'datasetid', 'dataset'); ?>
        </div>
    </div>
    <div class="control-group">
        <label class="control-label" for="duration" accesskey="n" title="<?php echo Theme::Translate('The duration in seconds this data should be displayed'); ?>"><?php echo Theme::Translate('Duration'); ?></label>
        <div class="controls">
            <input class="required number" name="duration" type="text" id="duration" tabindex="1" value="<?php echo Theme::Get('duration'); ?>" <?php echo Theme::Get('is_duration_enabled'); ?> />
        </div>
    </div>
</form>
