Deepl in PHP
============

Translate texts. Simply.

A simple library without dependencies for automatic translation of your texts.

📦 Installation
---------------

It's best to use [Composer](https://getcomposer.org) for installation, and you can also find the package on
[Packagist](https://packagist.org/packages/baraja-core/deepl) and
[GitHub](https://github.com/baraja-core/deepl).

To install, simply use the command:

```
$ composer require baraja-core/deepl
```

You can use the package manually by creating an instance of the internal classes, or register a DIC extension to link the services directly to the Nette Framework.

How to use
----------

The library requires no dependencies. Simply create an instance, pass the API key and start translating:

```php
$apiKey = '...';
$isFreeApiKey = true;
$deepl = new \Baraja\Deepl\Deepl($apiKey, $isFreeApiKey);

// Translate "Hello!" to DE
echo $deepl->translate('Hello!', DeeplLocale::DE);
```

The library is designed for maximum ease of use. At the same time, you can easily configure all the modules.

Supported languages
-------------------

You can always get the list of supported languages from the `DeeplLocale` enum:

```php
echo 'Supported languages: ';
$enumValues = array_map(static fn(\UnitEnum $case): string => htmlspecialchars($case->value ?? $case->name), DeeplLocale::cases());
echo implode(', ', $enumValues);
```

For normal translation work, we recommend using the built-in enum cases directly:

```php
$deepl->translate('Text', DeeplLocale::CS, DeeplLocale::EN);
```

The first language parameter specifies the target language of the translation, the second parameter specifies the source language. If you don't specify a language, it will be detected automatically.

Translation cache
-----------------

We know that translating the same requests over and over again can be very expensive for you, because the API is charged by real requests. Therefore, the library directly implements a native cache that stores translation results in a temporary storage.

The default cache implementation is the `FileResultCache` service, in which you can configure a custom disk path. The default storage for the cache is `sys_get_temp_dir() . '/deepl'`.

```php
$cache = new \Depl\FileResultCache(__DIR__ . '/cache');
$apiKey = '...';
$deepl = new \Baraja\Deepl\Deepl($apiKey, false, $cache);
```

📄 License
----------

`baraja-core/deepl` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/deepl/blob/master/LICENSE) file for more details.
