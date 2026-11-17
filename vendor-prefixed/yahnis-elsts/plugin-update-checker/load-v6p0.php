<?php

namespace LDC\Deps\YahnisElsts\PluginUpdateChecker\v6p0;

use LDC\Deps\YahnisElsts\PluginUpdateChecker\v6\PucFactory as MajorFactory;
use LDC\Deps\YahnisElsts\PluginUpdateChecker\v6p0\PucFactory as MinorFactory;
require __DIR__ . '/Puc/v6p0/Autoloader.php';
new Autoloader();
require __DIR__ . '/Puc/v6p0/PucFactory.php';
require __DIR__ . '/Puc/v6/PucFactory.php';
//Register classes defined in this version with the factory.
foreach (array('LDC\Deps\Plugin\UpdateChecker' => Plugin\UpdateChecker::class, 'LDC\Deps\Theme\UpdateChecker' => Theme\UpdateChecker::class, 'LDC\Deps\Vcs\PluginUpdateChecker' => Vcs\PluginUpdateChecker::class, 'LDC\Deps\Vcs\ThemeUpdateChecker' => Vcs\ThemeUpdateChecker::class, 'GitHubApi' => Vcs\GitHubApi::class, 'BitBucketApi' => Vcs\BitBucketApi::class, 'GitLabApi' => Vcs\GitLabApi::class) as $pucGeneralClass => $pucVersionedClass) {
    MajorFactory::addVersion($pucGeneralClass, $pucVersionedClass, '6.0');
    //Also add it to the minor-version factory in case the major-version factory
    //was already defined by another, older version of the update checker.
    MinorFactory::addVersion($pucGeneralClass, $pucVersionedClass, '6.0');
}
