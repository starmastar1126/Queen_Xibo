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


interface ModuleInterface
{
	// Some Default Add/Edit/Delete functionality each module should have
	public function EditForm();
	public function DeleteForm();
	public function EditMedia();
	public function DeleteMedia();

	// Return the name of the media as input by the user
	public function GetName();

	/**
	 * HTML Content to completely render this module.
	 */
    public function GetResource();

    /**
     * Is the Module Valid
     * @return int (0 = No, 1 = Yes, 2 = Player Dependent
     */
    public function IsValid();

    /**
     * Install or Upgrade this module
     * 	Expects $this->codeSchemaVersion to be set by the module.
     */
    public function InstallOrUpdate();
    public function InstallModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings);
    public function UpgradeModule($name, $description, $imageUri, $previewEnabled, $assignable, $settings);
    public function ModuleSettingsForm();
    public function ModuleSettings();
}
