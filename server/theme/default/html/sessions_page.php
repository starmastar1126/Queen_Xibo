<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
 * 	id = The GridID for rendering AJAX layout table return
 * 	filter_id = The Filter Form ID
 * 	form_meta = Extra form meta that needs to be sent to the CMS to return the report
 * 	pager = A paging control for this Xibo Grid
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="row">
	<div class="XiboGrid span12" id="<?php echo Theme::Get('id'); ?>">
		<div class="XiboFilter">
			<div class="FilterDiv" id="Filter">
				<form>
					<?php echo Theme::Get('form_meta'); ?>
					<table class="filterform" id="sessions_filterform">
						<tr>
							<td><label for="filter_type"><?php echo Theme::Translate('Type') ?></label></td>
							<td><?php echo Theme::SelectList('filter_type', Theme::Get('type_field_list'), 'typeid', 'type', Theme::Get('filter_typeid')); ?></td>
							<td><label for="filter_fromdt"><?php echo Theme::Translate('From DT') ?></label></td>
							<td><input class="date-pick" type="text" id="filter_fromdt" name="filter_fromdt" value="<?php echo Theme::Get('filter_fromdt'); ?>"></td>
							<td><label for="<?php echo Theme::Get('filter_id'); ?>"><?php echo Theme::Translate('Keep filter open') ?></label></td>
		                    <td><input type="checkbox" id="<?php echo Theme::Get('filter_id'); ?>" name="XiboFilterPinned" class="XiboFilterPinned" <?php echo Theme::Get('filter_pinned'); ?> /></td>
						</tr>
					</table>
				</form>
			</div>
		</div>
		<div class="XiboData"></div>
		<?php echo Theme::Get('pager'); ?>
	</div>
</div>
