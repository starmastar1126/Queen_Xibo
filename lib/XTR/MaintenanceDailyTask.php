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
 */


namespace Xibo\XTR;
use Carbon\Carbon;
use Xibo\Controller\Module;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\DatabaseLogHandler;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\MediaServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class MaintenanceDailyTask
 * @package Xibo\XTR
 */
class MaintenanceDailyTask implements TaskInterface
{
    use TaskTrait;

    /** @var LayoutFactory */
    private $layoutFactory;

    /** @var UserFactory */
    private $userFactory;

    /** @var Module */
    private $moduleController;

    /** @var MediaServiceInterface */
    private $mediaService;

    /** @var DataSetFactory */
    private $dataSetFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->moduleController = $container->get('\Xibo\Controller\Module');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->userFactory = $container->get('userFactory');
        $this->dataSetFactory = $container->get('dataSetFactory');
        $this->mediaService = $container->get('mediaService');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Daily Maintenance') . PHP_EOL . PHP_EOL;

        // Long running task
        set_time_limit(0);

        // Import layouts
        $this->importLayouts();

        // Install module files
        $this->installModuleFiles();

        // Tidy logs
        $this->tidyLogs();

        // Tidy Cache
        $this->tidyCache();
    }

    /**
     * Tidy the DB logs
     */
    private function tidyLogs()
    {
        $this->runMessage .= '## ' . __('Tidy Logs') . PHP_EOL;

        $maxage = $this->config->getSetting('MAINTENANCE_LOG_MAXAGE');
        if ($maxage != 0) {
            // Run this in the log handler so that we share the same connection and don't deadlock.
            DatabaseLogHandler::tidyLogs(
                Carbon::now()
                    ->subDays(intval($maxage))
                    ->format(DateFormatHelper::getSystemFormat())
            );

            $this->runMessage .= ' - ' . __('Done') . PHP_EOL . PHP_EOL;
        } else {
            $this->runMessage .= ' - ' . __('Disabled') . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Tidy Cache
     */
    private function tidyCache()
    {
        $this->runMessage .= '## ' . __('Tidy Cache') . PHP_EOL;
        $this->pool->purge();
        $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
    }

    /**
     * Import Layouts
     * @throws GeneralException
     */
    private function importLayouts()
    {
        $this->runMessage .= '## ' . __('Import Layouts') . PHP_EOL;

        if ($this->config->getSetting('DEFAULTS_IMPORTED') == 0) {

            $folder = $this->config->uri('layouts', true);

            foreach (array_diff(scandir($folder), array('..', '.')) as $file) {
                if (stripos($file, '.zip')) {
                    $layout = $this->layoutFactory->createFromZip(
                        $folder . '/' . $file,
                        null,
                        $this->userFactory->getSystemUser()->getId(),
                        false,
                        false,
                        true,
                        false,
                        true,
                        $this->dataSetFactory,
                        null,
                        null,
                        $this->mediaService
                    );

                    $layout->save([
                        'audit' => false,
                        'import' => true
                    ]);

                    try {
                        $this->layoutFactory->getById($this->config->getSetting('DEFAULT_LAYOUT'));
                    } catch (NotFoundException $exception) {
                        $this->config->changeSetting('DEFAULT_LAYOUT', $layout->layoutId);
                    }
                }
            }

            $this->config->changeSetting('DEFAULTS_IMPORTED', 1);

            $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
        } else {
            $this->runMessage .= ' - ' . __('Not Required.') . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Install Module Files
     */
    private function installModuleFiles()
    {
        $this->moduleController->installAllModuleFiles();
    }
}