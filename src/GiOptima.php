<?php

namespace gulch;

class GiOptima extends \Imagick
{
    /**
     * Resizes the image using smart defaults for high quality and low file size.
     *
     * This function is basically equivalent to:
     * `mogrify -path OUTPUT_PATH -filter Triangle -define filter:support=2.0 -thumbnail OUTPUT_WIDTH -unsharp 0.25x0.25+8+0.065 -dither None -posterize 136 -quality 82 -define jpeg:fancy-upsampling=off -define png:compression-filter=5 -define png:compression-level=9 -define png:compression-strategy=1 -define png:exclude-chunk=all -interlace none -colorspace sRGB -strip INPUT_PATH`
     *
     * @access   public
     *
     * @param    integer $columns The number of columns in the output image. 0 = maintain aspect ratio based on $rows.
     * @param    integer $rows The number of rows in the output image. 0 = maintain aspect ratio based on $columns.
     */
    public function smartResize($width, $height, $crop = true)
    {
        $this->setOption('filter:support', '2.0');

        if ($crop) {
            $original_ratio = $this->getImageHeight() / $this->getImageWidth();
            $need_ratio = $height / $width;
            if ($original_ratio > $need_ratio) {
                $this->cropImage($this->getImageWidth(), $this->getImageWidth() * $need_ratio, 0,
                    ($this->getImageHeight() - ($this->getImageWidth() * $need_ratio)) / 2);
            } elseif ($original_ratio < ($height / $width)) {
                $this->cropImage($this->getImageHeight() / $need_ratio, $this->getImageHeight(),
                    ($this->getImageWidth() - ($this->getImageHeight() / $need_ratio)) / 2, 0);
            }
        }
        
        // no upsizing
        if($width) {
            $width = min($width, $this->getImageWidth());
        }
        if($height) {
            $height = min($height, $this->getImageHeight());
        }
        
        $this->thumbnailImage($width, $height);

        $this->unsharpMaskImage(0, 0.5, 1, 0.05);
        $this->posterizeImage(136, false);
        $this->setOption('jpeg:fancy-upsampling', 'off');
        $this->setOption('png:compression-filter', '5');
        $this->setOption('png:compression-level', '9');
        $this->setOption('png:compression-strategy', '1');
        $this->setOption('png:exclude-chunk', 'all');
        $this->setInterlaceScheme(\Imagick::INTERLACE_NO);
        $this->setColorspace(\Imagick::COLORSPACE_SRGB);
        $this->stripImage();
    }

    /**
     * Changes the size of an image to the given dimensions and removes any associated profiles.
     *
     * `thumbnailImage` changes the size of an image to the given dimensions and
     * removes any associated profiles.  The goal is to produce small low cost
     * thumbnail images suited for display on the Web.
     *
     * With the original Imagick thumbnailImage implementation, there is no way to choose a
     * resampling filter. This class recreates Imagick’s C implementation and adds this
     * additional feature.
     *
     * Note: <https://github.com/mkoppanen/imagick/issues/90> has been filed for this issue.
     *
     * @access   public
     *
     * @param    integer $columns The number of columns in the output image. 0 = maintain aspect ratio based on $rows.
     * @param    integer $rows The number of rows in the output image. 0 = maintain aspect ratio based on $columns.
     * @param    bool $bestfit Treat $columns and $rows as a bounding box in which to fit the image.
     * @param    bool $fill Fill in the bounding box with the background colour.
     * @param    integer $filter The resampling filter to use. Refer to the list of filter constants at <http://php.net/manual/en/imagick.constants.php>.
     *
     * @return   bool    Indicates whether the operation was performed successfully.
     */
    public function thumbnailImage(
        $columns,
        $rows,
        $bestfit = false,
        $fill = false,
        $filter = \Imagick::FILTER_TRIANGLE
    ) {
        // sample factor; defined in original ImageMagick thumbnailImage function
        // the scale to which the image should be resized using the `sample` function
        $SampleFactor = 5;

        // filter whitelist
        $filters = array(
            \Imagick::FILTER_POINT,
            \Imagick::FILTER_BOX,
            \Imagick::FILTER_TRIANGLE,
            \Imagick::FILTER_HERMITE,
            \Imagick::FILTER_HANNING,
            \Imagick::FILTER_HAMMING,
            \Imagick::FILTER_BLACKMAN,
            \Imagick::FILTER_GAUSSIAN,
            \Imagick::FILTER_QUADRATIC,
            \Imagick::FILTER_CUBIC,
            \Imagick::FILTER_CATROM,
            \Imagick::FILTER_MITCHELL,
            \Imagick::FILTER_LANCZOS,
            \Imagick::FILTER_BESSEL,
            \Imagick::FILTER_SINC
        );

        // Parse parameters given to function
        $columns = (double)($columns);
        $rows = (double)($rows);
        $bestfit = (bool)$bestfit;
        $fill = (bool)$fill;

        // We can’t resize to (0,0)
        if ($rows < 1 && $columns < 1) {
            return false;
        }

        // Set a default filter if an acceptable one wasn’t passed
        if (!in_array($filter, $filters)) {
            $filter = \Imagick::FILTER_TRIANGLE;
        }

        // figure out the output width and height
        $width = (double)$this->getImageWidth();
        $height = (double)$this->getImageHeight();
        $new_width = $columns;
        $new_height = $rows;

        $x_factor = $columns / $width;
        $y_factor = $rows / $height;
        if ($rows < 1) {
            $new_height = round($x_factor * $height);
        } elseif ($columns < 1) {
            $new_width = round($y_factor * $width);
        }

        // if bestfit is true, the new_width/new_height of the image will be different than
        // the columns/rows parameters; those will define a bounding box in which the image will be fit
        if ($bestfit && $x_factor > $y_factor) {
            $x_factor = $y_factor;
            $new_width = round($y_factor * $width);
        } elseif ($bestfit && $y_factor > $x_factor) {
            $y_factor = $x_factor;
            $new_height = round($x_factor * $height);
        }
        if ($new_width < 1) {
            $new_width = 1;
        }
        if ($new_height < 1) {
            $new_height = 1;
        }

        $this->resizeImage($new_width, $new_height, $filter, 1);

        // if the alpha channel is not defined, make it opaque
        if ($this->getImageAlphaChannel() == \Imagick::ALPHACHANNEL_UNDEFINED) {
            $this->setImageAlphaChannel(\Imagick::ALPHACHANNEL_OPAQUE);
        }

        // set the image’s bit depth to 8 bits
        $this->setImageDepth(8);

        // turn off interlacing
        $this->setInterlaceScheme(\Imagick::INTERLACE_NO);

        // Strip all profiles except color profiles.
        foreach ($this->getImageProfiles('*', true) as $key => $value) {
            if ($key != 'icc' && $key != 'icm') {
                try {
                  $this->removeImageProfile($key);
                } catch(\ImagickException $e) {
                    //
                }
            }
        }

        if (method_exists($this, 'deleteImageProperty')) {
            $this->deleteImageProperty('comment');
            $this->deleteImageProperty('Thumb::URI');
            $this->deleteImageProperty('Thumb::MTime');
            $this->deleteImageProperty('Thumb::Size');
            $this->deleteImageProperty('Thumb::Mimetype');
            $this->deleteImageProperty('software');
            $this->deleteImageProperty('Thumb::Image::Width');
            $this->deleteImageProperty('Thumb::Image::Height');
            $this->deleteImageProperty('Thumb::Document::Pages');
        } else {
            $this->setImageProperty('comment', '');
            $this->setImageProperty('Thumb::URI', '');
            $this->setImageProperty('Thumb::MTime', '');
            $this->setImageProperty('Thumb::Size', '');
            $this->setImageProperty('Thumb::Mimetype', '');
            $this->setImageProperty('software', '');
            $this->setImageProperty('Thumb::Image::Width', '');
            $this->setImageProperty('Thumb::Image::Height', '');
            $this->setImageProperty('Thumb::Document::Pages', '');
        }

        // In case user wants to fill use extent for it rather than creating a new canvas
        // …fill out the bounding box
        if ($bestfit && $fill && ($new_width != $columns || $new_height != $rows)) {
            $extent_x = 0;
            $extent_y = 0;

            if ($columns > $new_width) {
                $extent_x = ($columns - $new_width) / 2;
            }
            if ($rows > $new_height) {
                $extent_y = ($rows - $new_height) / 2;
            }

            $this->extentImage($columns, $rows, 0 - $extent_x, $extent_y);
        }

        return true;
    }

    public function convertToWEBP()
    {
        try {
            $this->setImageFormat('webp');
            $this->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
            $this->setBackgroundColor(new \ImagickPixel('transparent'));
        } catch (\Exception $e) {
            echo 'Caught exception: ' . $e->getMessage() . '<br>';
        }
    }
}
