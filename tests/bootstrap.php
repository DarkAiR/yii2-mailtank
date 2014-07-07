<?php

require(__DIR__ . '/../../../../vendor/autoload.php');
require(__DIR__ . '/../../../../vendor/yiisoft/yii2/Yii.php');

$params = include __DIR__ . '/params.php';
$config = include __DIR__ . '/config.php';

$application = new yii\console\Application($config);