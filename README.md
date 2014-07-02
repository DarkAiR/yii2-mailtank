yii2-mailtank
=============

Yii2 Mailtank (mailtank.ru) extension

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
$ php composer.phar require darkair/yii2-mailtank "dev-master"
```

or add

```
"darkair/yii2-mailtank": "dev-master"
```

to the ```require``` section of your `composer.json` file.

## Usage

Add target class in your project config:

```
'components' => [
    'mailtank' => [
        'class' => 'mailtank\Mailtank',
        'host' => 'api.mailtank.ru',
        'token' => '',                  // API access key
    ],
```

Then use:

```
Yii::$app->mailtank
```
