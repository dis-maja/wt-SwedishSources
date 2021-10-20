<?php

use Composer\Autoload\ClassLoader;

$ns_base = "DISMaja\\Webtrees\Module\\SwedishSources\\";

$loader = new ClassLoader();
$loader->addPsr4($ns_base, __DIR__);
$loader->addPsr4($ns_base . "Http\\", __DIR__ . "/Http");
$loader->addPsr4($ns_base . "Schema\\", __DIR__ . "/Schema");

$loader->register();
