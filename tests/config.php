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
            'templatesPath' => __DIR__ . '/fixtures/mailTemplates',
            'templatePrefix' => 'blablanator_test',
            'excludeTemplates' => [
                'base',
                'subfolder/excludeMail',
            ],
        ],
    ],
];