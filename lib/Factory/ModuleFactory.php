<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ModuleFactory.php) is part of Xibo.
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


namespace Xibo\Factory;


use Xibo\Entity\Media;
use Xibo\Entity\Module;
use Xibo\Entity\Widget;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;

class ModuleFactory
{
    /**
     * Create a Module
     * @param string $type
     * @return \Xibo\Widget\Module
     * @throws NotFoundException
     */
    public static function create($type)
    {
        $modules = ModuleFactory::query(null, array('type' => $type));

        if (count($modules) <= 0)
            throw new NotFoundException(sprintf(__('Unknown type %s'), $type));

        // Create a module
        $module = $modules[0];

        $type = 'Xibo\Widget\\' . $module->type;

        $type = new $type();
        /* @var \Xibo\Widget\Module $type */
        $type->setModule($module);

        return $type;
    }

    /**
     * Create a Module
     * @param string $class
     * @return \Xibo\Widget\Module
     * @throws NotFoundException
     */
    public static function createForInstall($class)
    {
        $type = new $class();
        /* @var \Xibo\Widget\Module $type */

        return $type;
    }

    /**
     * Create a Module
     * @param string $moduleId
     * @return \Xibo\Widget\Module
     * @throws NotFoundException
     */
    public static function createById($moduleId)
    {
        // Create a module
        $module = ModuleFactory::getById($moduleId);

        $moduleId = 'Xibo\Widget\\' . $module->type;

        $moduleId = new $moduleId();
        /* @var \Xibo\Widget\Module $moduleId */
        $moduleId->setModule($module);

        return $moduleId;
    }

    /**
     * Create a Module with a Media Record
     * @param Media $media
     * @return \Xibo\Widget\Module
     * @throws NotFoundException
     */
    public static function createWithMedia($media)
    {
        $modules = ModuleFactory::query(null, array('type' => $media->mediaType));

        if (count($modules) <= 0)
            throw new NotFoundException(sprintf(__('Unknown type %s'), $media->mediaType));

        // Create a widget
        $widget = new Widget();
        $widget->assignMedia($media->mediaId);

        // Create a module
        $module = $modules[0];

        // TODO: move this into the module itself (i.e. all modules have a namespace)
        $type = 'Xibo\Widget\\' . ucfirst($module->type);

        Log::debug('Creating module with type %s', $type);
        $type = new $type();

        /* @var \Xibo\Widget\Module $type */
        $type->setModule($module);
        $type->setWidget($widget);

        return $type;
    }

    /**
     * Create a Module for a Widget and optionally a playlist/region
     * @param string $type
     * @param int $widgetId
     * @param int $ownerId
     * @param int $playlistId
     * @param int $regionId
     * @return \Xibo\Widget\Module
     * @throws NotFoundException
     */
    public static function createForWidget($type, $widgetId = 0, $ownerId = 0, $playlistId = 0, $regionId = 0)
    {
        $module = ModuleFactory::create($type);

        // Do we have a regionId
        if ($regionId != 0) {
            // Load the region and set
            $region = RegionFactory::getById($regionId);
            $module->setRegion($region);
        }

        // Do we have a widgetId
        if ($widgetId == 0) {
            // If we don't have a widget we must have a playlist
            if ($playlistId == 0) {
                throw new \InvalidArgumentException(__('Neither Playlist or Widget provided'));
            }

            $playlist = PlaylistFactory::getById($playlistId);
            $playlist->load(['playlistIncludeRegionAssignments' => false]);

            // Create a new widget to use
            $widget = WidgetFactory::create($ownerId, $playlistId, $module->getModuleType(), 0);
            $module->setWidget($widget);

            $playlist->assignWidget($widget);
        }
        else {
            // Load the widget
            $module->setWidget(WidgetFactory::loadByWidgetId($widgetId));
        }

        return $module;
    }

    /**
     * Create a Module using a Widget
     * @param Widget $widget
     * @param Region[optional] $region
     * @return \Xibo\Widget\Module
     */
    public static function createWithWidget($widget, $region = null)
    {
        $module = ModuleFactory::create($widget->type);
        $module->setWidget($widget);

        if ($region != null)
            $module->setRegion($region);

        return $module;
    }

    public static function get($key = 'type')
    {
        $modules = ModuleFactory::query();

        if ($key != null && $key != '') {

            $keyed = array();
            foreach ($modules as $module) {
                /* @var Module $module */
                $keyed[$module->type] = $module;
            }

            return $keyed;
        }

        return $modules;
    }

    public static function getAssignableModules()
    {
        return ModuleFactory::query(null, array('assignable' => 1, 'enabled' => 1));
    }

    /**
     * Get module by Id
     * @param int $moduleId
     * @return Module
     * @throws NotFoundException
     */
    public static function getById($moduleId)
    {
        $modules = ModuleFactory::query(null, array('moduleId' => $moduleId));

        if (count($modules) <= 0)
            throw new NotFoundException();

        return $modules[0];
    }

    /**
     * Get module by extension
     * @param string $extension
     * @return Module
     * @throws NotFoundException
     */
    public static function getByExtension($extension)
    {
        $modules = ModuleFactory::query(null, array('extension' => $extension));

        if (count($modules) <= 0)
            throw new NotFoundException(sprintf(__('Extension %s does not match any enabled Module'), $extension));

        return $modules[0];
    }

    /**
     * Get Valid Extensions
     * @param array[Optional] $filterBy
     * @return array[string]
     */
    public static function getValidExtensions($filterBy = [])
    {
        $modules = ModuleFactory::query(null, $filterBy);
        $extensions = array();

        foreach($modules as $module) {
            /* @var Module $module */
            if ($module->validExtensions != '') {
                foreach (explode(',', $module->validExtensions) as $extension) {
                    $extensions[] = $extension;
                }
            }
        }

        return $extensions;
    }

    /**
     * Get View Paths
     * @return array[string]
     */
    public static function getViewPaths()
    {
        $modules = ModuleFactory::query();
        $paths = array_map(function ($module) {
            /* @var Module $module */
            return $module->viewPath;
        }, $modules);

        $paths = array_unique($paths);

        return $paths;
    }

    public static function query($sortOrder = null, $filterBy = array())
    {
        if ($sortOrder == null)
            $sortOrder = array('Module');

        $entries = array();

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $params = array();

            $SQL = '
                SELECT ModuleID,
                   Module,
                   Name,
                   Enabled,
                   Description,
                   render_as,
                   settings,
                   RegionSpecific,
                   ValidExtensions,
                   ImageUri,
                   PreviewEnabled,
                   assignable,
                   SchemaVersion,
                   viewPath
                  FROM `module`
                 WHERE 1 = 1
            ';

            if (Sanitize::getInt('moduleId', $filterBy) != null) {
                $params['moduleId'] = Sanitize::getInt('moduleId', $filterBy);
                $SQL .= ' AND ModuleID = :moduleId ';
            }

            if (Sanitize::getString('name', $filterBy) != '') {
                $params['name'] = Sanitize::getString('name', $filterBy);
                $SQL .= ' AND name = :name ';
            }

            if (Sanitize::getString('type', $filterBy) != '') {
                $params['type'] = Sanitize::getString('type', $filterBy);
                $SQL .= ' AND module = :type ';
            }

            if (Sanitize::getString('extension', $filterBy) != '') {
                $params['extension'] = '%' . Sanitize::getString('extension', $filterBy) . '%';
                $SQL .= ' AND ValidExtensions LIKE :extension ';
            }

            if (Sanitize::getInt('assignable', -1, $filterBy) != -1) {
                $SQL .= " AND assignable = :assignable ";
                $params['assignable'] = Sanitize::getInt('assignable', $filterBy);
            }

            if (Sanitize::getInt('enabled', -1, $filterBy) != -1) {
                $SQL .= " AND enabled = :enabled ";
                $params['enabled'] = Sanitize::getInt('enabled', $filterBy);
            }

            if (Sanitize::getInt('regionSpecific', -1, $filterBy) != -1) {
                $SQL .= " AND regionSpecific = :regionSpecific ";
                $params['regionSpecific'] = Sanitize::getInt('regionSpecific', $filterBy);
            }

            // Sorting?
            if (is_array($sortOrder))
                $SQL .= 'ORDER BY ' . implode(',', $sortOrder);

            // Paging
            if (Sanitize::getInt('start') !== null && Sanitize::getInt('length') !== null) {
                $SQL .= ' LIMIT ' . Sanitize::getInt('start') . ', ' . Sanitize::getInt('length', 10);
            }

            Log::sql($SQL, $params);

            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            foreach ($sth->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $module = new Module();
                $module->moduleId = Sanitize::int($row['ModuleID']);
                $module->name = Sanitize::string($row['Name']);
                $module->description = Sanitize::string($row['Description']);
                $module->validExtensions = Sanitize::string($row['ValidExtensions']);
                $module->imageUri = Sanitize::string($row['ImageUri']);
                $module->renderAs = Sanitize::string($row['render_as']);
                $module->type = strtolower(Sanitize::string($row['Module']));
                $module->enabled = Sanitize::int($row['Enabled']);
                $module->regionSpecific = Sanitize::int($row['RegionSpecific']);
                $module->previewEnabled = Sanitize::int($row['PreviewEnabled']);
                $module->assignable = Sanitize::int($row['assignable']);
                $module->schemaVersion = Sanitize::int($row['SchemaVersion']);
                $module->viewPath = Sanitize::string($row['viewPath']);

                $settings = $row['settings'];
                $module->settings = ($settings == '') ? array() : json_decode($settings, true);

                $entries[] = $module;
            }

            return $entries;
        }
        catch (\Exception $e) {

            Log::error($e->getMessage());

            return array();
        }
    }
}