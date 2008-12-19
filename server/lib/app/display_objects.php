<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

DEFINE('HELP_BASE', "http://www.xibo.org.uk/manual/");

/**
 * 
 * @return 
 * @param $location Object
 * @param $return Object[optional]
 * @param $class Object[optional]
 * @param $text Object[optional]
 * @param $target Object[optional]
 */
function HelpButton($location, $return = false) 
{
	$link = HELP_BASE . "?p=$location";
	
	$button = <<<END
	<input type="button" onclick="window.open('$link')" value="Help" />
END;

	if ($return)
	{
		return $button;
	}
	else
	{
		echo $button;
		return true;
	}
}

/**
 * 
 * @return 
 * @param $title Object
 * @param $return Object[optional]
 * @param $image Object[optional]
 * @param $alt Object[optional]
 */
function HelpIcon($title, $return = false, $image = "img/forms/info_icon.gif", $alt = "Hover for more info")
{
	$button = <<<END
	<img src="$image" alt="$alt" title="$title">
END;
	
	if ($return)
	{
		return $button;
	}
	else
	{
		echo $button;
		return true;
	}
}

/**
 * Checks whether a Module is enabled, if so returns a button
 * @return 
 * @param $href Object
 * @param $title Object
 * @param $onclick Object
 * @param $image Object
 * @param $text Object
 */
function ModuleButton($module, $href, $title, $onclick, $image, $text)
{
	global $db;
	
	// Check that the module is enabled
	$SQL = "SELECT Enabled FROM module WHERE Module = '$module' AND Enabled = 1";
	if (!$result = $db->query($SQL))
	{
		trigger_error($db->error());
		return "";
	}
	// If there were no modules enabled with that name, then return nothing (i.e. we dont output a button)
	if ($db->num_rows($result) == 0 )
	{
		return "";
	}
	
	$button = <<<HTML
	<div class="regionicons">
		<a title="$title" href="$href" onclick="$onclick">
		<img class="dash_button" src="$image" />
		<span class="dash_text">$text</span></a>
	</div>
HTML;

	return $button;
}

?>