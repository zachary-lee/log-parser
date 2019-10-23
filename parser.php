#!/usr/bin/env php
<?php
// application.php
require __DIR__ . '/Commands/ModSecParser.php';
require __DIR__.'/vendor/autoload.php';


use Symfony\Component\Console\Application;
use \Commands\ModSecParser;

$application = new Application();
$application->add(new ModSecParser());

$application->run();
