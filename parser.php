#!/usr/bin/env php
<?php
// application.php
require __DIR__ . '/Commands/ModSecParser.php';
require __DIR__ . '/Commands/WWWErrorParser.php';
require __DIR__ . '/Commands/Notifications.php';
require __DIR__.'/vendor/autoload.php';


use Symfony\Component\Console\Application;
use \Commands\ModSecParser;
use \Commands\WWWErrorParser;
use \Commands\Notifications;

$application = new Application();
$application->add(new ModSecParser());
$application->add(new WWWErrorParser());
$application->add(new Notifications());

$application->run();
