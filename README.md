Yii2 Reverse Migration
======================
Generate working migration classes from existing Database

[![Latest Stable Version](https://poser.pugx.org/tmukherjee13/yii2-reverse-migration/v/stable)](https://packagist.org/packages/tmukherjee13/yii2-reverse-migration)
[![Packagist](https://img.shields.io/packagist/dt/doctrine/orm.svg?maxAge=2592000)](https://packagist.org/packages/tmukherjee13/yii2-reverse-migration)[![License](https://poser.pugx.org/tmukherjee13/yii2-reverse-migration/license)](https://packagist.org/packages/tmukherjee13/yii2-reverse-migration)


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

Once the extension is installed, Add the following in console.php:

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

for table migration,
```
yii migration/table <tablename>
```
or
```
yii migration/table <tablename1>,<tablename2>

```

for data migration,
```
yii migration/data <tablename>
```
or
```
yii migration/data <tablename1>,<tablename2>

```


to create migration of whole schema,
```
yii migration/schema <schemaname>
```

# yii2-reverse-migration
