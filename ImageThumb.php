<?php


namespace TonchikTm\Yii2Thumb;


use Exception;
use Imagine\Exception\RuntimeException;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ManipulatorInterface;
use Yii;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\httpclient\Client as HttpClient;
use yii\imagine\Image;

/**
 * Class ImageThumb
 * @package TonchikTm\Yii2Thumb
 */
class ImageThumb
{
    const THUMBNAIL_OUTBOUND = ManipulatorInterface::THUMBNAIL_OUTBOUND;
    const THUMBNAIL_INSET = ManipulatorInterface::THUMBNAIL_INSET;
    const THUMBNAIL_INSET_BOX = 'inset_box';
    const QUALITY = 60;
    const MKDIR_MODE = 0755;

    const CHECK_REM_MODE_NONE = 1;
    const CHECK_REM_MODE_CRC = 2;
    const CHECK_REM_MODE_HEADER = 3;

    /** @var string $cacheAlias path alias relative with @web where the cache files are kept */
    public static $cacheAlias = 'assets/thumbnails';

    /** @var int $cacheExpire */
    public static $cacheExpire = 0;

    /** @var yii\httpclient\Client */
    public static $httpClient;

    /**
     *
     * @param string $filename the image file path or path alias or URL
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param array $options
     * @return ImageInterface
     * @throws FileNotFoundException
     * @throws InvalidConfigException
     */
    public static function thumbnail($filename, $width, $height, $options=[])
    {
        return Image::getImagine()
            ->open(static::thumbFile($filename, $width, $height, $options));
    }

    /**
     *
     * @param string $filename the image file path or path alias or URL
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param array $options
     * @return string
     * @throws FileNotFoundException
     * @throws InvalidConfigException
     */
    public static function thumbFile($filename, $width, $height, $options=[])
    {
        $o = array_merge([
            'format'    => null, // format finally file
            'mode'      => self::THUMBNAIL_OUTBOUND, // mode of resizing original image to use in case both width and height specified
            'quality'   => null,
            'cacheMode' => self::CHECK_REM_MODE_NONE, // check file version on remote server
        ], $options);

        $fileContent = null;
        $fileNameIsUrl = false;

        if (\preg_match('/^https?:\/\//', $filename)) {
            $fileNameIsUrl = true;
            $commonCacheData = $filename . $width . $height . $o['mode'];
            switch ($o['cacheMode']) {
                case self::CHECK_REM_MODE_NONE:
                    $thumbnailFileName = \md5($commonCacheData);
                    break;
                case self::CHECK_REM_MODE_CRC:
                    $fileContent = static::fileFromUrlContent($filename);
                    $thumbnailFileName = \md5($commonCacheData . \crc32($fileContent));
                    break;
                case self::CHECK_REM_MODE_HEADER:
                    $fileContent = static::fileFromUrlContent($filename);
                    $thumbnailFileName = \md5($commonCacheData . static::fileFromUrlDate($filename));
                    break;
                default:
                    throw new InvalidConfigException('Unknown `cacheMode` param value.');
            }
        } else {
            $filename = FileHelper::normalizePath(Yii::getAlias($filename));
            if (!\is_file($filename)) {
                throw new FileNotFoundException("File {$filename} doesn't exist");
            }
            $thumbnailFileName = \md5($filename . $width . $height . $o['mode'] . \filemtime($filename));
        }

        $cachePath = Yii::getAlias('@webroot/' . static::$cacheAlias);

        $thumbFileExt = $o['format'] ? '.' . $o['format'] : \strrchr($filename, '.');
        $thumbFilePath = $cachePath . DIRECTORY_SEPARATOR . \substr($thumbnailFileName, 0, 2);
        $thumbFile = $thumbFilePath . DIRECTORY_SEPARATOR . $thumbnailFileName . $thumbFileExt;

        if (\file_exists($thumbFile)) {
            if (static::$cacheExpire !== 0 && (\time() - \filemtime($thumbFile)) > static::$cacheExpire) {
                \unlink($thumbFile);
            } else {
                return $thumbFile;
            }
        }

        if (!\is_dir($thumbFilePath)) {
            \mkdir($thumbFilePath, self::MKDIR_MODE, true);
        }

        if ($fileNameIsUrl) {
            $image = Image::getImagine()->load($fileContent ?: static::fileFromUrlContent($filename));
        } else {
            $image = Image::getImagine()->open($filename);
        }

        if ($o['mode'] === self::THUMBNAIL_INSET_BOX) {
            $image = $image->thumbnail(new Box($width, $height), ManipulatorInterface::THUMBNAIL_INSET);
        } else {
            $image = Image::thumbnail($image, $width, $height, $o['mode']);
        }

        $oSave = [
            'quality' => $o['quality'] === null ? self::QUALITY : $o['quality']
        ];

        $image->save($thumbFile, $oSave);

        return $thumbFile;
    }

    /**
     *
     * @param string $filename the image file path or path alias or URL
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param array $options
     * @return string
     * @throws FileNotFoundException
     * @throws InvalidConfigException
     */
    public static function thumbFileUrl($filename, $width, $height, $options=[])
    {
        $cacheUrl = Yii::getAlias('@web/' . static::$cacheAlias);
        $thumbFilePath = static::thumbFile($filename, $width, $height, $options);

        \preg_match('#[^\\' . DIRECTORY_SEPARATOR . ']+$#', $thumbFilePath, $matches);
        $fileName = $matches[0];

        return $cacheUrl . '/' . \substr($fileName, 0, 2) . '/' . $fileName;
    }

    /**
     *
     * @param string $filename the image file path or path alias or URL
     * @param integer $width the width in pixels to create the thumbnail
     * @param integer $height the height in pixels to create the thumbnail
     * @param array $options
     * @return string
     */
    public static function thumbImg($filename, $width, $height, $options=[])
    {
        try {
            $thumbFileUrl = static::thumbFileUrl($filename, $width, $height, $options);
        } catch (Exception $e) {
            return static::errorHandler($e, $filename);
        }

        $imgOptions = !empty($options['attributes']) ? $options['attributes'] : [];

        return Html::img($thumbFileUrl, $imgOptions);
    }

    /**
     *
     * @param string $filename
     * @param integer $width
     * @param integer $height
     * @param array $options
     * @return string
     */
    public static function thumbSource($filename, $width, $height, $options=[])
    {
        try {
            $thumbFileUrl = static::thumbFileUrl($filename, $width, $height, $options);
        } catch (Exception $e) {
            return static::errorHandler($e, $filename);
        }

        $sourceOptions = [
            'srcset' => $thumbFileUrl,
            'type' => self::getMimeType($options['format'] ?: 'webp'),
        ];

        return Html::tag('source', null, $sourceOptions);
    }

    /**
     *
     * @param string $filename
     * @param integer $width
     * @param integer $height
     * @param array $options
     * @return string
     */
    public static function thumbPicture($filename, $width, $height, $options = [])
    {
        $oSource = !empty($options['source']) ? $options['source'] : [];
        $oImg = !empty($options['img']) ? $options['img'] : [];

        $pictureOptions = [ 'data-cache' => 'hit' ];
        $sourceOptions = array_merge([
            'format' => 'webp',
            'mode' => self::THUMBNAIL_OUTBOUND,
            'quality' => null,
            'cacheMode' => self::CHECK_REM_MODE_NONE,
        ], $oSource);

        return
            Html::beginTag('picture', $pictureOptions) . "\n\t" .
                self::thumbSource($filename, $width, $height, $sourceOptions) . "\n\t" .
                self::thumbImg($filename, $width, $height, $oImg) . "\n" .
            Html::endTag('picture');
    }

    /**
     * Clear cache directory.
     *
     * @return bool
     * @throws ErrorException
     */
    public static function clearCache()
    {
        $cacheDir = Yii::getAlias('@webroot/' . static::$cacheAlias);
        FileHelper::removeDirectory($cacheDir);
        return @\mkdir($cacheDir, self::MKDIR_MODE, true);
    }

    /**
     *
     * @param Exception $error
     * @param string $filename
     * @return string
     */
    protected static function errorHandler($error, $filename)
    {
        if ($error instanceof FileNotFoundException) {
            return $error->getMessage();
        }

        Yii::warning("{$error->getCode()}\n{$error->getMessage()}\n{$error->getFile()}");
        return 'Error ' . $error->getCode();
    }

    /**
     *
     * @param string $url
     * @return string
     * @throws FileNotFoundException
     */
    protected static function fileFromUrlDate($url)
    {
        $response = self::getHttpClient()
            ->head($url)
            ->send();
        if (!$response->isOk) {
            throw new FileNotFoundException("URL {$url} doesn't exist");
        }

        return $response->headers['Last-Modified'];
    }

    /**
     *
     * @param string $url
     * @return string
     * @throws FileNotFoundException
     */
    protected static function fileFromUrlContent($url)
    {
        $response = self::getHttpClient()
            ->createRequest()
            ->setMethod('GET')
            ->setUrl($url)
            ->send();
        if (!$response->isOk) {
            throw new FileNotFoundException("URL {$url} doesn't exist");
        }

        return $response->content;
    }

    /**
     * @return HttpClient
     */
    protected static function getHttpClient()
    {
        if (self::$httpClient === null || !(self::$httpClient instanceof HttpClient)) {
            self::$httpClient = new HttpClient();
        }

        return self::$httpClient;
    }

    /**
     * Get the mime type based on format.
     *
     * @param string $format
     * @throws RuntimeException
     * @return string mime-type
     */
    private static function getMimeType($format)
    {
        static $mimeTypes = array(
            'jpeg' => 'image/jpeg',
            'jpg'  => 'image/jpeg',
            'gif'  => 'image/gif',
            'png'  => 'image/png',
            'wbmp' => 'image/vnd.wap.wbmp',
            'xbm'  => 'image/xbm',
            'webp' => 'image/webp',
            'bmp'  => 'image/bmp',
        );

        if (!isset($mimeTypes[$format])) {
            throw new RuntimeException(sprintf('Unsupported format given. Only %s are supported, %s given', implode(', ', array_keys($mimeTypes)), $format));
        }

        return $mimeTypes[$format];
    }
}
