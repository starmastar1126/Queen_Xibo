<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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
namespace Xibo\Factory;

use Xibo\Entity\DisplayProfile;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class DisplayProfileFactory
 * @package Xibo\Factory
 */
class DisplayProfileFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /**
     * Construct a factory
     * @param ConfigServiceInterface $config
     * @param CommandFactory $commandFactory
     */
    public function __construct($config, $commandFactory)
    {
        $this->config = $config;
        $this->commandFactory = $commandFactory;
    }

    /**
     * @return DisplayProfile
     */
    public function createEmpty()
    {
        $displayProfile = new DisplayProfile(
            $this->getStore(),
            $this->getLog(),
            $this->config,
            $this->commandFactory,
            $this
        );
        $displayProfile->config = [];

        return $displayProfile;
    }

    /**
     * @param int $displayProfileId
     * @return DisplayProfile
     * @throws NotFoundException
     */
    public function getById($displayProfileId)
    {
        $profiles = $this->query(null, ['disableUserCheck' => 1, 'displayProfileId' => $displayProfileId]);

        if (count($profiles) <= 0) {
            throw new NotFoundException();
        }

        $profile = $profiles[0];
        /* @var DisplayProfile $profile */

        $profile->load([]);
        return $profile;
    }

    /**
     * @param string $type
     * @return DisplayProfile
     * @throws NotFoundException
     */
    public function getDefaultByType($type)
    {
        $profiles = $this->query(null, ['disableUserCheck' => 1, 'type' => $type, 'isDefault' => 1]);

        if (count($profiles) <= 0) {
            throw new NotFoundException();
        }

        $profile = $profiles[0];
        /* @var DisplayProfile $profile */
        $profile->load();
        return $profile;
    }

    /**
     * @param $clientType
     * @return DisplayProfile
     * @throws NotFoundException
     */
    public function getUnknownProfile($clientType)
    {
        $profile = $this->createEmpty();
        $profile->type = 'unknown';
        $profile->setClientType($clientType);
        $profile->isCustom = 0;
        $profile->load();
        return $profile;
    }

    /**
     * Get by Command Id
     * @param $commandId
     * @return DisplayProfile[]
     * @throws NotFoundException
     */
    public function getByCommandId($commandId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'commandId' => $commandId]);
    }

    public function getByOwnerId($ownerId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'userId' => $ownerId]);
    }

    public function createCustomProfile($options)
    {
        $params = $this->getSanitizer($options);
        $displayProfile = $this->createEmpty();
        $displayProfile->name = $params->getString('name');
        $displayProfile->type = $params->getString('type');
        $displayProfile->isDefault = $params->getCheckbox('isDefault');
        $displayProfile->userId = $params->getInt('userId');
        $displayProfile->isCustom = 1;

        return $displayProfile;
    }

    /**
     * Load the config from the file
     */
    public function loadForType($type)
    {
        $config = [
            'unknown' => [],
            'windows' => [
                ['name' => 'collectInterval', 'default' => 300, 'type' => 'int'],
                ['name' => 'downloadStartWindow', 'default' => '00:00', 'type' => 'string'],
                ['name' => 'downloadEndWindow', 'default' => '00:00', 'type' => 'string'],
                ['name' => 'dayPartId', 'default' => null],
                ['name' => 'xmrNetworkAddress', 'default' => '', 'type' => 'string'],
                ['name' => 'statsEnabled', 'default' => (int)$this->config->getSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0), 'type' => 'checkbox'],
                ['name' => 'aggregationLevel', 'default' => $this->config->getSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'), 'type' => 'string'],
                ['name' => 'powerpointEnabled', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'sizeX', 'default' => 0, 'type' => 'double'],
                ['name' => 'sizeY', 'default' => 0, 'type' => 'double'],
                ['name' => 'offsetX', 'default' => 0, 'type' => 'double'],
                ['name' => 'offsetY', 'default' => 0, 'type' => 'double'],
                ['name' => 'clientInfomationCtrlKey', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'clientInformationKeyCode', 'default' => 'I', 'type' => 'string'],
                ['name' => 'logLevel', 'default' => 'error', 'type' => 'string'],
                ['name' => 'logToDiskLocation', 'default' => '', 'type' => 'string'],
                ['name' => 'showInTaskbar', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'cursorStartPosition', 'default' => 'Unchanged', 'type' => 'string'],
                ['name' => 'doubleBuffering', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'emptyLayoutDuration', 'default' => 10, 'type' => 'int'],
                ['name' => 'enableMouse', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'enableShellCommands', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'expireModifiedLayouts', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'maxConcurrentDownloads', 'default' => 2, 'type' => 'int'],
                ['name' => 'shellCommandAllowList', 'default' => '', 'type' => 'string'],
                ['name' => 'sendCurrentLayoutAsStatusUpdate', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'screenShotRequestInterval', 'default' => 0, 'type' => 'int'],
                ['name' => 'screenShotSize', 'default' => (int)$this->config->getSetting('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', 200), 'type' => 'int'],
                ['name' => 'maxLogFileUploads', 'default' => 3, 'type' => 'int'],
                ['name' => 'embeddedServerPort', 'default' => 9696, 'type' => 'int'],
                ['name' => 'preventSleep', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'forceHttps', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'authServerWhitelist', 'default' => null, 'type' => 'string'],
                ['name' => 'edgeBrowserWhitelist', 'default' => null, 'type' => 'string'],
                ['name' => 'embeddedServerAllowWan', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'isRecordGeoLocationOnProofOfPlay', 'default' => 0, 'type' => 'checkbox']
            ],
            'android' => [
                ['name' => 'emailAddress', 'default' => ''],
                ['name' => 'settingsPassword', 'default' => ''],
                ['name' => 'collectInterval', 'default' => 300],
                ['name' => 'downloadStartWindow', 'default' => '00:00'],
                ['name' => 'downloadEndWindow', 'default' => '00:00'],
                ['name' => 'xmrNetworkAddress', 'default' => ''],
                ['name' => 'statsEnabled', 'default' => (int)$this->config->getSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0), 'type' => 'checkbox'],
                ['name' => 'aggregationLevel', 'default' => $this->config->getSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'), 'type' => 'string'],
                ['name' => 'orientation', 'default' => 0],
                ['name' => 'screenDimensions', 'default' => ''],
                ['name' => 'blacklistVideo', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'storeHtmlOnInternal', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'useSurfaceVideoView', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'logLevel', 'default' => 'error'],
                ['name' => 'versionMediaId', 'default' => null],
                ['name' => 'startOnBoot', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'actionBarMode', 'default' => 1],
                ['name' => 'actionBarDisplayDuration', 'default' => 30],
                ['name' => 'actionBarIntent', 'default' => ''],
                ['name' => 'autoRestart', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'startOnBootDelay', 'default' => 60],
                ['name' => 'sendCurrentLayoutAsStatusUpdate', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'screenShotRequestInterval', 'default' => 0],
                ['name' => 'expireModifiedLayouts', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'screenShotIntent', 'default' => ''],
                ['name' => 'screenShotSize', 'default' => (int)$this->config->getSetting('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', 200)],
                ['name' => 'updateStartWindow', 'default' => '00:00'],
                ['name' => 'updateEndWindow', 'default' => '00:00'],
                ['name' => 'dayPartId', 'default' => null],
                ['name' => 'webViewPluginState', 'default' => 'DEMAND'],
                ['name' => 'hardwareAccelerateWebViewMode', 'default' => '2'],
                ['name' => 'timeSyncFromCms', 'default' => 0],
                ['name' => 'webCacheEnabled', 'default' => 0],
                ['name' => 'serverPort', 'default' => 9696],
                ['name' => 'installWithLoadedLinkLibraries', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'forceHttps', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'isUseMultipleVideoDecoders', 'default' => 'default', 'type' => 'string'],
                ['name' => 'maxRegionCount', 'default' => 0],
                ['name' => 'embeddedServerAllowWan', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'isRecordGeoLocationOnProofOfPlay', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'videoEngine', 'default' => 'exoplayer', 'type' => 'string'],
                ['name' => 'isTouchEnabled', 'default' => 0, 'type' => 'checkbox']
            ],
            'linux' => [
                ['name' => 'collectInterval', 'default' => 300],
                ['name' => 'downloadStartWindow', 'default' => '00:00'],
                ['name' => 'downloadEndWindow', 'default' => '00:00'],
                ['name' => 'dayPartId', 'default' => null],
                ['name' => 'xmrNetworkAddress', 'default' => ''],
                ['name' => 'statsEnabled', 'default' => (int)$this->config->getSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0), 'type' => 'checkbox'],
                ['name' => 'aggregationLevel', 'default' => $this->config->getSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'), 'type' => 'string'],
                ['name' => 'sizeX', 'default' => 0],
                ['name' => 'sizeY', 'default' => 0],
                ['name' => 'offsetX', 'default' => 0],
                ['name' => 'offsetY', 'default' => 0],
                ['name' => 'logLevel', 'default' => 'error'],
                ['name' => 'enableShellCommands', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'expireModifiedLayouts', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'maxConcurrentDownloads', 'default' => 2],
                ['name' => 'shellCommandAllowList', 'default' => ''],
                ['name' => 'sendCurrentLayoutAsStatusUpdate', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'screenShotRequestInterval', 'default' => 0],
                ['name' => 'screenShotSize', 'default' => (int)$this->config->getSetting('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', 200)],
                ['name' => 'maxLogFileUploads', 'default' => 3],
                ['name' => 'embeddedServerPort', 'default' => 9696],
                ['name' => 'preventSleep', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'forceHttps', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'embeddedServerAllowWan', 'default' => 0, 'type' => 'checkbox']
            ],
            'lg' => [
                ['name' => 'emailAddress', 'default' => ''],
                ['name' => 'collectInterval', 'default' => 300],
                ['name' => 'downloadStartWindow', 'default' => '00:00'],
                ['name' => 'downloadEndWindow', 'default' => '00:00'],
                ['name' => 'dayPartId', 'default' => null],
                ['name' => 'xmrNetworkAddress', 'default' => ''],
                ['name' => 'statsEnabled', 'default' => (int)$this->config->getSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0), 'type' => 'checkbox'],
                ['name' => 'aggregationLevel', 'default' => $this->config->getSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'), 'type' => 'string'],
                ['name' => 'orientation', 'default' => 0],
                ['name' => 'logLevel', 'default' => 'error'],
                ['name' => 'versionMediaId', 'default' => null],
                ['name' => 'actionBarMode', 'default' => 1],
                ['name' => 'actionBarDisplayDuration', 'default' => 30],
                ['name' => 'sendCurrentLayoutAsStatusUpdate', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'mediaInventoryTimer', 'default' => 0],
                ['name' => 'screenShotRequestInterval', 'default' => 0, 'type' => 'int'],
                ['name' => 'screenShotSize', 'default' => 1],
                ['name' => 'timers', 'default' => '{}'],
                ['name' => 'pictureOptions', 'default' => '{}'],
                ['name' => 'lockOptions', 'default' => '{}'],
                ['name' => 'forceHttps', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'updateStartWindow', 'default' => '00:00'],
                ['name' => 'updateEndWindow', 'default' => '00:00'],
                ['name' => 'embeddedServerAllowWan', 'default' => 0, 'type' => 'checkbox']
            ],
            'sssp' => [
                ['name' => 'emailAddress', 'default' => ''],
                ['name' => 'collectInterval', 'default' => 300],
                ['name' => 'downloadStartWindow', 'default' => '00:00'],
                ['name' => 'downloadEndWindow', 'default' => '00:00'],
                ['name' => 'dayPartId', 'default' => null],
                ['name' => 'xmrNetworkAddress', 'default' => ''],
                ['name' => 'statsEnabled', 'default' => (int)$this->config->getSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0), 'type' => 'checkbox'],
                ['name' => 'aggregationLevel', 'default' => $this->config->getSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'), 'type' => 'string'],
                ['name' => 'orientation', 'default' => 0],
                ['name' => 'logLevel', 'default' => 'error'],
                ['name' => 'versionMediaId', 'default' => null],
                ['name' => 'actionBarMode', 'default' => 1],
                ['name' => 'actionBarDisplayDuration', 'default' => 30],
                ['name' => 'sendCurrentLayoutAsStatusUpdate', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'mediaInventoryTimer', 'default' => 0],
                ['name' => 'screenShotRequestInterval', 'default' => 0, 'type' => 'int'],
                ['name' => 'screenShotSize', 'default' => 1],
                ['name' => 'timers', 'default' => '{}'],
                ['name' => 'pictureOptions', 'default' => '{}'],
                ['name' => 'lockOptions', 'default' => '{}'],
                ['name' => 'forceHttps', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'updateStartWindow', 'default' => '00:00'],
                ['name' => 'updateEndWindow', 'default' => '00:00'],
                ['name' => 'embeddedServerAllowWan', 'default' => 0, 'type' => 'checkbox']
            ]
        ];

        if (!isset($config[$type])) {
            if ($this->config->getMiddleware() != null) {
                foreach ($this->config->middleware as $object) {
                    // Add any new routes from custom middleware
                    if (method_exists($object, 'registerCustomDisplayProfile')) {
                        $defaultConfig = $object->registerCustomDisplayProfile($type);
                        if (!empty($defaultConfig)) {
                            return $defaultConfig;
                        } else {
                            continue;
                        }
                    }
                }
                if (empty($defaultConfig)) {
                    $this->getLog()->error('Custom Display Profile registerCustomDisplayProfile function not found for ' . $type);
                    return [];
                }
            } else {
                return [];
            }
        }

        return $config[$type];
    }

    public function getCustomEditTemplate($type)
    {
        if ($this->config->getMiddleware() != null) {
            foreach ($this->config->middleware as $object) {
                // Add any new routes from custom middleware
                if (method_exists($object, 'getCustomEditTemplate')) {
                    $template = $object->getCustomEditTemplate($type);
                    if ($template != null) {
                        return $template;
                    } else {
                        continue;
                    }
                }
            }
            if (empty($template)) {
                $this->getLog()->error('Custom Display Profile Edit template not found for ' . $type);
                return null;
            }
        } else {
            $this->getLog()->error('Attempting to get Custom Display Profile Edit form, without any custom middleware');
            return null;
        }
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return DisplayProfile[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $profiles = [];
        $parsedFilter = $this->getSanitizer($filterBy);

        if ($sortOrder === null) {
            $sortOrder = ['name'];
        }

        try {
            $params = array();
            $select = 'SELECT displayProfileId, name, type, config, isDefault, userId, isCustom ';

            $body = ' FROM `displayprofile` WHERE 1 = 1 ';

            if ($parsedFilter->getInt('displayProfileId') !== null) {
                $body .= ' AND displayProfileId = :displayProfileId ';
                $params['displayProfileId'] = $parsedFilter->getInt('displayProfileId');
            }

            if ($parsedFilter->getInt('isDefault') !== null) {
                $body .= ' AND isDefault = :isDefault ';
                $params['isDefault'] = $parsedFilter->getInt('isDefault');
            }

            // Filter by DisplayProfile Name?
            if ($parsedFilter->getString('displayProfile') != null) {
                $terms = explode(',', $parsedFilter->getString('displayProfile'));
                $this->nameFilter('displayprofile', 'name', $terms, $body, $params, ($parsedFilter->getCheckbox('useRegexForName') == 1));
            }

            if ($parsedFilter->getString('type') != null) {
                $body .= ' AND type = :type ';
                $params['type'] = $parsedFilter->getString('type');
            }

            if ($parsedFilter->getInt('commandId') !== null) {
                $body .= '
                    AND `displayprofile`.displayProfileId IN (
                        SELECT `lkcommanddisplayprofile`.displayProfileId
                          FROM `lkcommanddisplayprofile`
                         WHERE `lkcommanddisplayprofile`.commandId = :commandId
                    )
                ';

                $params['commandId'] = $parsedFilter->getInt('commandId');
            }

            if ($parsedFilter->getInt('userId') !== null) {
                $body .= ' AND `displayprofile`.userId = :userId ';
                $params['userId'] = $parsedFilter->getInt('userId');
            }

            // Sorting?
            $order = '';
            if (is_array($sortOrder)) {
                $order .= 'ORDER BY ' . implode(',', $sortOrder);
            }

            $limit = '';
            // Paging
            if ($filterBy !== null && $parsedFilter->getInt('start') !== null && $parsedFilter->getInt('length') !== null) {
                $limit = ' LIMIT ' . intval($parsedFilter->getInt('start'), 0) . ', ' . $parsedFilter->getInt('length', ['default' => 10]);
            }

            $sql = $select . $body . $order . $limit;

            foreach ($this->getStore()->select($sql, $params) as $row) {
                $profile = $this->createEmpty()->hydrate($row, ['intProperties' => ['isDefault', 'isCustom']]);

                $profile->excludeProperty('configDefault');
                $profile->excludeProperty('configTabs');
                $profiles[] = $profile;
            }

            // Paging
            if ($limit != '' && count($profiles) > 0) {
                $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
                $this->_countLast = intval($results[0]['total']);
            }

            return $profiles;
        } catch (\Exception $e) {
            $this->getLog()->error($e);

            throw new NotFoundException();
        }
    }

    public function getAvailableTypes()
    {
        $types = $this->getStore()->select('SELECT DISTINCT type FROM `displayprofile` ORDER BY type', []);

        $entries = [];
        foreach ($types as $row) {
            $sanitizedRow = $this->getSanitizer($row);
            if ($sanitizedRow->getString('type') === 'sssp') {
                $typeName = 'Tizen';
            } elseif ($sanitizedRow->getString('type') === 'lg') {
                $typeName = 'webOS';
            } else {
                $typeName = ucfirst($sanitizedRow->getString('type'));
            }

            $entries[] = ['typeId' => $sanitizedRow->getString('type'), 'type' => $typeName];
        }

        return $entries;
    }

    public function isCustomType($type)
    {
        $results = $this->getStore()->select('SELECT displayProfileId FROM `displayprofile` WHERE isCustom = 1 AND type = :type', [
            'type' => $type
        ]);

        return (count($results) >= 1) ? 1 : 0;
    }
}
