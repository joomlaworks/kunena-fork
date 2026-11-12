<?php

/**
 * Kunena Component
 * @package         Kunena.Framework
 * @subpackage      Image
 *
 * @copyright       Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die;

use Joomla\Image\Image;

define('MIME_GIF', 'image/gif');
define('MIME_PNG', 'image/png');

/**
 * Helper class for image manipulation.
 * @since Kunena
 */
#[\AllowDynamicProperties]
class KunenaImage extends Image
{
    /**
     * Whether this server can read and write (resize) images of a type: GIF, JPEG and PNG through Joomla's image
     * library; WebP and AVIF here, when PHP's GD has them (AVIF: PHP 8.1 or newer, built with it). The same check on
     * every Joomla 3.x, Joomla 3.x UTD included (its Media Manager uses the same GD functions).
     *
     * @param   integer|string  $type  IMAGETYPE_* constant or a file extension
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public static function canProcess($type)
    {
        if (is_string($type)) {
            $type = strtolower(trim($type));
            $map  = array('gif' => IMAGETYPE_GIF, 'jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP);

            if ($type === 'avif') {
                return defined('IMAGETYPE_AVIF') && static::canProcess(IMAGETYPE_AVIF);
            }

            if (!isset($map[$type])) {
                return false;
            }

            $type = $map[$type];
        }

        switch ((int) $type) {
            case IMAGETYPE_GIF:
                return function_exists('imagecreatefromgif');

            case IMAGETYPE_JPEG:
                return function_exists('imagecreatefromjpeg');

            case IMAGETYPE_PNG:
                return function_exists('imagecreatefrompng');

            case IMAGETYPE_WEBP:
                return function_exists('imagecreatefromwebp') && function_exists('imagewebp');
        }

        return defined('IMAGETYPE_AVIF') && (int) $type === IMAGETYPE_AVIF
            && function_exists('imagecreatefromavif') && function_exists('imageavif');
    }

    /**
     * Image properties, also for AVIF files on PHP versions whose getimagesize() doesn't know AVIF yet.
     *
     * @param   string  $path  File
     *
     * @return  stdClass  width, height, type, attributes, bits, channels, mime, filesize, orientation
     *
     * @since 5.3.0
     */
    public static function getImageFileProperties($path)
    {
        try {
            return parent::getImageFileProperties($path);
        } catch (RuntimeException $e) {
            if (!static::isAvifFile($path) || !static::canProcess('avif')) {
                throw $e;
            }
        }

        $handle = @imagecreatefromavif($path);

        if (!$handle) {
            throw new RuntimeException('Unable to get properties for the image.');
        }

        $width  = imagesx($handle);
        $height = imagesy($handle);

        if (PHP_VERSION_ID < 80000) {
            imagedestroy($handle);
        }

        return (object) array(
            'width'       => $width,
            'height'      => $height,
            'type'        => IMAGETYPE_AVIF,
            'attributes'  => 'width="' . $width . '" height="' . $height . '"',
            'bits'        => null,
            'channels'    => null,
            'mime'        => 'image/avif',
            'filesize'    => filesize($path),
            'orientation' => $width > $height ? 'landscape' : ($width < $height ? 'portrait' : 'square'),
        );
    }

    /**
     * @param   string  $path  File
     *
     * @return  boolean  The file is an AVIF image (by its signature)
     *
     * @since 5.3.0
     */
    protected static function isAvifFile($path)
    {
        $head = is_file($path) ? (string) @file_get_contents($path, false, null, 0, 16) : '';

        return in_array(substr($head, 4, 8), array('ftypavif', 'ftypavis'), true);
    }

    /**
     * Loads an image: WebP and AVIF here, the other types through Joomla's image library.
     *
     * @param   string  $path  File
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function loadFile($path)
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException('The image file does not exist.');
        }

        $properties = static::getImageFileProperties($path);
        $readers    = array('image/webp' => 'imagecreatefromwebp', 'image/avif' => 'imagecreatefromavif');

        if (!isset($readers[$properties->mime])) {
            parent::loadFile($path);

            return;
        }

        if (!static::canProcess($properties->type)) {
            throw new RuntimeException('This server cannot process ' . $properties->mime . ' images.');
        }

        $this->destroy();

        $handle = @call_user_func($readers[$properties->mime], $path);

        if (!$handle) {
            throw new RuntimeException('Unable to process the ' . $properties->mime . ' image.');
        }

        // Keep the transparency
        imagealphablending($handle, false);
        imagesavealpha($handle, true);

        $this->handle = $handle;
        $this->path   = $path;
    }

    /**
     * Saves the image: WebP and AVIF here (with transparency), the other types through Joomla's image library.
     *
     * @param   string   $path     File
     * @param   integer  $type     IMAGETYPE_*
     * @param   array    $options  quality (1-100 for WebP and AVIF)
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public function toFile($path, $type = IMAGETYPE_JPEG, array $options = array())
    {
        $quality = isset($options['quality']) ? max(1, min(100, (int) $options['quality'])) : 85;

        if ((int) $type === IMAGETYPE_WEBP && static::canProcess(IMAGETYPE_WEBP)) {
            imagesavealpha($this->getHandle(), true);

            return imagewebp($this->getHandle(), $path, $quality);
        }

        if (defined('IMAGETYPE_AVIF') && (int) $type === IMAGETYPE_AVIF && static::canProcess(IMAGETYPE_AVIF)) {
            imagesavealpha($this->getHandle(), true);

            return imageavif($this->getHandle(), $path, $quality);
        }

        return parent::toFile($path, $type, $options);
    }

    /**
     * Copies a rectangular portion of an image to another image. Used when imagecopyresampled isn't available
     *
     * @param   mixed $dst_image dst image
     * @param   mixed $src_image src image
     * @param   mixed $dst_x     dst x
     * @param   mixed $dst_y     dst y
     * @param   mixed $src_x     src x
     * @param   mixed $src_y     src y
     * @param   mixed $dst_w     dst w
     * @param   mixed $dst_h     dst h
     * @param   mixed $src_w     src w
     * @param   mixed $src_h     src h
     *
     * @deprecated 5.1
     *
     * @return boolean
     * @since Kunena
     */
    public static function imageCopyResampledBicubic(&$dst_image, &$src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h)
    {
        // We should first cut the piece we are interested in from the source
        $src_img = ImageCreateTrueColor($src_w, $src_h);
        imagecopy($src_img, $src_image, 0, 0, $src_x, $src_y, $src_w, $src_h);

        // This one is used as temporary image
        $dst_img = ImageCreateTrueColor($dst_w, $dst_h);

        ImagePaletteCopy($dst_img, $src_img);
        $rX = $src_w / $dst_w;
        $rY = $src_h / $dst_h;
        $w  = 0;

        for ($y = 0; $y < $dst_h; $y++) {
            $ow = $w;
            $w  = round(($y + 1) * $rY);
            $t  = 0;

            for ($x = 0; $x < $dst_w; $x++) {
                $r  = $g = $b = 0;
                $a  = 0;
                $ot = $t;
                $t  = round(($x + 1) * $rX);

                for ($u = 0; $u < ($w - $ow); $u++) {
                    for ($p = 0; $p < ($t - $ot); $p++) {
                        $c = ImageColorsForIndex($src_img, ImageColorAt($src_img, $ot + $p, $ow + $u));
                        $r += $c['red'];
                        $g += $c['green'];
                        $b += $c['blue'];
                        $a++;
                    }
                }

                ImageSetPixel($dst_img, $x, $y, (int) ImageColorClosest($dst_img, $r / $a, $g / $a, $b / $a));
            }
        }

        // Apply the temp image over the returned image and use the destination x,y coordinates
        imagecopy($dst_image, $dst_img, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h);

        // We should return true since ImageCopyResampled/ImageCopyResized do it
        return true;
    }

    /**
     * Correct Image Orientation
     *
     * @since  K5.0
     *
     * @param   string $filename filename
     *
     * @return void
     */
    public static function correctImageOrientation($filename)
    {
        // TODO: need to check here if the file given is right an image ?

        if (function_exists('exif_read_data')) {
            $angle  = 0;

            try {
                $exif = @exif_read_data($filename);
            } catch (Exception $e) {
                $exif = false;
            }

            $flip   = 0;
            $img    = 0;

            if ($exif && isset($exif['Orientation'])) {
                $orientation = $exif['Orientation'];

                if ($orientation != 1) {
                    $img = new Image();
                    $img->loadFile($filename);

                    switch ($orientation) {
                        case 1: // Nothing
                            $angle  = 0;
                            $flip = 0;
                            break;

                        case 2: // Horizontal flip
                            $angle  = 0;
                            $flip = 1;
                            break;

                        case 3: // 180 rotate left
                            $angle  = 180;
                            $flip = 0;
                            break;

                        case 4: // Vertical flip
                            $angle  = 0;
                            $flip = 2;
                            break;

                        case 5: // Vertical flip + 90 rotate
                            $angle  = 90;
                            $flip = 2;
                            break;

                        case 6: // 270 rotate left
                            $angle  = 270;
                            $flip = 0;
                            break;

                        case 7: // Horizontal flip + 90 rotate
                            $angle  = 90;
                            $flip = 1;
                            break;

                        case 8: // 90 rotate left
                            $angle  = 90;
                            $flip = 0;
                            break;
                    }
                }
            }

            if ($angle > 0) {
                $img->rotate($angle, -1, false);
            }

            if ($flip != 0) {
                if ($flip == 1) {
                    $img->flip(IMG_FLIP_HORIZONTAL, false);
                } else {
                    $img->flip(IMG_FLIP_VERTICAL, false);
                }
            }
        }
    }

    /**
     * Method to resize the current image.
     *
     * @param   mixed   $width         The width of the resized image in pixels or a percentage.
     * @param   mixed   $height        The height of the resized image in pixels or a percentage.
     * @param   boolean $createNew     If true the current image will be cloned, resized and returned; else
     *                                 the current image will be resized and returned.
     * @param   integer $scaleMethod   Which method to use for scaling
     *
     * @return  KunenaImage
     *
     *
     * @throws Exception
     * @since   4.0.0
     */
    public function resize($width, $height, $createNew = true, $scaleMethod = self::SCALE_INSIDE)
    {
        $config = KunenaFactory::getConfig();

        switch ($config->avatarresizemethod) {
            case '0':
                $resizemethod = 'imagecopyresized';
                break;
            case '1':
                $resizemethod = 'imagecopyresampled';
                break;
            default:
                $resizemethod = 'self::imageCopyResampledBicubic';
                break;
        }

        // Make sure the resource handle is valid.
        if (!$this->isLoaded()) {
            throw new LogicException('No valid image was loaded.');
        }

        // Sanitize width.
        $width = $this->sanitizeWidth($width, $height);

        // Sanitize height.
        $height = $this->sanitizeHeight($height, $width);

        // Prepare the dimensions for the resize operation.
        $dimensions = $this->prepareDimensions($width, $height, $scaleMethod);

        // Instantiate offset.
        $offset    = new stdClass();
        $offset->x = $offset->y = 0;

        // Get true color handle
        $handle = imagecreatetruecolor($dimensions->width, $dimensions->height);

        // Center image if needed and create the new true color image handle.
        if ($scaleMethod == self::SCALE_FIT) {
            // Get the offsets
            $offset->x = round(($width - $dimensions->width) / 2);
            $offset->y = round(($height - $dimensions->height) / 2);

            // Make image transparent, otherwise canvas outside initial image would default to black
            if (!$this->isTransparent()) {
                $transparency = imagecolorAllocateAlpha($this->handle, 0, 0, 0, 127);
                imagecolorTransparent($this->handle, $transparency);
            }
        }

        $imgProperties = self::getImageFileProperties($this->getPath());

        if ($imgProperties->mime == MIME_GIF) {
            $trnprt_indx = imagecolortransparent($this->handle);

            if ($trnprt_indx >= 0 && $trnprt_indx < imagecolorstotal($this->handle)) {
                $trnprt_color = imagecolorsforindex($this->handle, $trnprt_indx);
                $trnprt_indx  = imagecolorallocate($handle, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
                imagefill($handle, 0, 0, $trnprt_indx);
                imagecolortransparent($handle, $trnprt_indx);
            }
        } elseif ($imgProperties->mime == MIME_PNG) {
            imagealphablending($handle, false);
            imagesavealpha($handle, true);

            if ($this->isTransparent()) {
                $transparent = imagecolorallocatealpha($this->handle, 255, 255, 255, 127);
                imagefilledrectangle($this->handle, 0, 0, $width, $height, $transparent);
            }
        }

        if ($this->isTransparent()) {
            $trnprt_indx = imagecolortransparent($this->handle);

            if ($trnprt_indx >= 0 && $trnprt_indx < imagecolorstotal($this->handle)) {
                // Get the transparent color values for the current image.
                $rgba  = imageColorsForIndex($this->handle, imagecolortransparent($this->handle));
                $color = imageColorAllocateAlpha($handle, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']);
            } else {
                $color = imageColorAllocateAlpha($handle, 255, 255, 255, 127);
            }

            // Set the transparent color values for the new image.
            imagecolortransparent($handle, $color);
            imagefill($handle, 0, 0, $color);

            imagecopyresized(
                $handle,
                $this->handle,
                $offset->x,
                $offset->y,
                0,
                0,
                $dimensions->width,
                $dimensions->height,
                $this->getWidth(),
                $this->getHeight()
            );
        } else {
            call_user_func_array(
                $resizemethod,
                array(&$handle, &$this->handle, $offset->x, $offset->y, 0, 0,
                    $dimensions->width, $dimensions->height, $this->getWidth(), $this->getHeight(), )
            );
        }

        // If we are resizing to a new image, create a new KunenaImage object.
        if ($createNew) {
            // @codeCoverageIgnoreStart
            $new = new KunenaImage($handle);

            return $new;

            // @codeCoverageIgnoreEnd
        }
        // Swap out the current handle for the new image handle.
        else {
            // Free the memory from the current handle
            $this->destroy();

            $this->handle = $handle;

            return $this;
        }
    }
}
