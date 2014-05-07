<?php

require 'vendor/autoload.php';

use Trunkat\LibphutilComposerConvertor\Convert;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new Convert);
$application->run();