<?php

require 'vendor/autoload.php';

use Trunkat\LibutilComposerConvertor\Convert;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new Convert);
$application->run();