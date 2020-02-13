# Modern Thumbnail Image Helper for Yii2

[![Latest Stable Version](https://poser.pugx.org/tonchik-tm/yii2-modern-thumbnailer/v/stable?format=flat-square)](https://packagist.org/packages/tonchik-tm/yii2-modern-thumbnailer)
[![Total Downloads](https://poser.pugx.org/tonchik-tm/yii2-modern-thumbnailer/downloads?format=flat-square)](https://packagist.org/packages/tonchik-tm/yii2-modern-thumbnailer)
[![Latest Unstable Version](https://poser.pugx.org/tonchik-tm/yii2-modern-thumbnailer/v/unstable?format=flat-square)](https://packagist.org/packages/tonchik-tm/yii2-modern-thumbnailer)
[![License](https://poser.pugx.org/tonchik-tm/yii2-modern-thumbnailer/license?format=flat-square)](https://packagist.org/packages/tonchik-tm/yii2-modern-thumbnailer)

Yii2 helper for creating and caching webp thumbnails on real time.

Installation
------------
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

* Either run

```
php composer.phar require "tonchik-tm/yii2-modern-thumbnailer" "*"
```
or add

```json
"tonchik-tm/yii2-modern-thumbnailer": "*"
```

to the require section of your application's `composer.json` file.

Usage
-----
For example:

```php
use TonchikTm\Yii2Thumb\ImageThumb;

$mode    = Imagine\Image\ImageInterface::THUMBNAIL_INSET;
$options = [
    'source' => [
        'format' => 'webp',
        'mode' => $mode
    ],
    'img' => [
        'mode' => $mode
    ]
];

echo ImageThumb::thumbPicture(
    Yii::getAlias('@webroot/assets/example.png'),
    300,
    300,
    $options
);
```

In the output we will get something like this code:

```html
<picture data-cache="hit">
    <source srcset="/assets/thumbnails/example.png.webp" type="image/webp" />
    <img src="/assets/thumbnails/example.png" />
</picture>
```

For other functions please see the source code.

The package is in the process of developing
