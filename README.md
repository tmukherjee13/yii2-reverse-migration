Yii2 Reverse Migration
======================
Generate working migration classes from existing Database

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist tmukherjee13/yii2-reverse-migration "*"
```

or add

```
"tmukherjee13/yii2-reverse-migration": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, Add the following in console.php:  :

```php
return [
    ...
    'components' => [
        ...
    ],
    'controllerMap' => [
        'migration' => [
            'class' => 'tmukherjee13\migration\console\controllers\MigrationController',
            'templateFile' => '@tmukherjee13/migration/views/template.php',
        ],
    ],
    ...
];

```

then you can use the migration command as follows:

to create table migration,
```
yii migration/table <tablename>
```
or
```
yii migration/table <tablename1>,<tablename2>

```

# yii2-reverse-migration
