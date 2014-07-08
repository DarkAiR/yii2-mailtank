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
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host='.$params['dbHost'].';dbname='.$params['dbName'],
            'username' => $params['dbUser'],
            'password' => $params['dbPass'],
            'charset' => 'utf8',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'sentry\SentryTarget',
                    'levels' => ['error', 'warning'],
                    'dsn' => $params['sentryDsn'],
                ],
            ],
        ],
    ],
];