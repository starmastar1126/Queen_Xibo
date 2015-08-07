<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (index.php) is part of Xibo.
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

use Xibo\Helper\Config;

DEFINE('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/../..'));

error_reporting(E_ALL);
ini_set('display_errors', 1);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php'))
    die('Not configured');

Config::Load(PROJECT_ROOT . '/web/settings.php');

// Create a logger
$logger = new \Flynsarmy\SlimMonolog\Log\MonologWriter(array(
    'name' => 'MAINT',
    'handlers' => array(
        new \Xibo\Helper\DatabaseLogHandler()
    ),
    'processors' => array(
        new \Xibo\Helper\LogProcessor(),
        new \Monolog\Processor\UidProcessor(7)
    )
), false);

$app = new \Slim\Slim(array(
    'mode' => Config::GetSetting('SERVER_MODE'),
    'debug' => false,
    'log.writer' => $logger
));
$app->setName('maint');

\Xibo\Middleware\State::setState($app);

$app->add(new \Xibo\Middleware\Storage());

// Configure the Slim error handler
$app->error(function (\Exception $e) use ($app) {
    $controller = new \Xibo\Controller\Error();
    $controller->handler($e);
});

// All routes
$app->get('/', '\Xibo\Controller\Maintenance:run');

// Run app
$app->run();

