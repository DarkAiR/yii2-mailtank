<?php

return [
    'id' => 'Mailtank tests',
    'basePath' => __DIR__ . '/../../../..',

    // application components
    'components' => [
        'mailtank' => [
            'class' => 'mailtank\Mailtank',
            'host' => $params['host'],
            'token' => $params['token'],
            'templatesPath' => $params['templatePath'],
            'templatePrefix' => $params['prefix'],
        ],
    ],
];