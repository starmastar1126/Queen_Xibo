<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Theme.php)
 */


namespace Xibo\Middleware;


use Slim\Middleware;

class Theme extends Middleware
{
    public function call()
    {
        // Configure the Theme
        \Xibo\Helper\Theme::getInstance();

        // Inject our Theme into the Twig View (if it exists)
        $app = $this->getApplication();

        // Provide the view path to Twig
        $twig = $app->view()->getInstance()->getLoader();
        /* @var \Twig_Loader_Filesystem $twig */

        // Append the module view paths
        $twig->setPaths(array_merge((new \Xibo\Factory\ModuleFactory($app))->getViewPaths(), [PROJECT_ROOT . '/views']));

        // Does this theme provide an alternative view path?
        if (\Xibo\Helper\Theme::getConfig('view_path') != '') {

            $twig->prependPath(\Xibo\Helper\Theme::getConfig('view_path'));
        }

        // Call Next
        $this->next->call();
    }
}