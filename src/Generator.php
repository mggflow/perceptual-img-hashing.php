<?php

namespace MGGFLOW\Images\Hashing\Perceptual;

/**
 * Генерация перцептивного хеша для изображений.
 */
class Generator
{
    public int $resizedWH = 32;
    public int $imageScalingMode = IMG_BICUBIC;
    public int $hashPixelsSize = 8;

    protected $oriniginalImage;
    protected $resizedImage;
    protected array $downsampledRows;
    protected array $downsampledPixels;
    protected array $hashDownsampledPixels;
    protected float $hashDownsampledPixelsMedian;
    protected int $decimalHash;


    /**
     * Сгенерировать перцептивный хеш для изображения.
     *
     * @param $imageResource resource
     * @return string
     */
    public function hash($imageResource): string
    {
        $this->setOriginalImage($imageResource);
        $this->createResizedImage();
        $this->downsampling();
        $this->createHashDownsampledPixels();
        $this->calcHashDownsampledPixelsMedian();
        $this->calcDecimalHash();

        return $this->calcHexadecimalHash();
    }

    protected function setOriginalImage($imageResource)
    {
        $this->oriniginalImage = $imageResource;
    }

    protected function createResizedImage()
    {
        $this->resizedImage = imagescale(
            $this->oriniginalImage,
            $this->resizedWH, $this->resizedWH,
            $this->imageScalingMode
        );
    }

    protected function downsampling()
    {
        $this->calcDownsampledRows();
        $this->flushResizedImage();
        $this->calcDownsampledCols();
    }

    protected function calcDownsampledRows()
    {
        $row = [];
        $this->downsampledRows = [];
        for ($y = 0; $y < $this->resizedWH; $y++) {
            for ($x = 0; $x < $this->resizedWH; $x++) {
                $rgb = imagecolorsforindex($this->resizedImage, imagecolorat($this->resizedImage, $x, $y));
                $row[$x] = $this->genLumaValueFromRGB($rgb["red"], $rgb["green"], $rgb["blue"]);
            }
            $this->downsampledRows[$y] = $this->simplifiedDiscreteCosineTransformation1D($row);
        }
    }

    protected function calcDownsampledCols()
    {
        $this->downsampledPixels = [];
        $col = [];
        for ($x = 0; $x < $this->resizedWH; $x++) {
            for ($y = 0; $y < $this->resizedWH; $y++) {
                $col[$y] = $this->downsampledRows[$y][$x];
            }
            $this->downsampledPixels[$x] = $this->simplifiedDiscreteCosineTransformation1D($col);
        }
    }

    protected function genLumaValueFromRGB($red, $green, $blue)
    {
        return floor(($red * 0.299) + ($green * 0.587) + ($blue * 0.114));
    }

    protected function simplifiedDiscreteCosineTransformation1D(&$vector): array
    {
        $transformed = [];
        $len = count($vector);
        $sumAddition = sqrt(2 / $len);

        for ($i = 0; $i < $len; $i++) {
            $sum = 0;
            for ($j = 0; $j < $len; $j++) {
                $sum += $vector[$j] * cos($i * pi() * ($j + 0.5) / ($len));
            }

            $transformed[$i] = $sum * $sumAddition;
        }

        return $transformed;
    }

    protected function flushResizedImage()
    {
        imagedestroy($this->resizedImage);
    }

    protected function createHashDownsampledPixels()
    {
        $this->hashDownsampledPixels = [];
        for ($y = 0; $y < $this->hashPixelsSize; $y++) {
            for ($x = 0; $x < $this->hashPixelsSize; $x++) {
                $this->hashDownsampledPixels[] = $this->downsampledPixels[$y][$x];
            }
        }
    }

    protected function calcHashDownsampledPixelsMedian()
    {
        $this->hashDownsampledPixelsMedian = $this->calcMedian($this->hashDownsampledPixels);
    }

    protected function calcMedian($values)
    {
        sort($values, SORT_NUMERIC);
        $middle = floor(count($values) / 2);

        if (count($values) % 2) {
            $median = $values[$middle];
        } else {
            $low = $values[$middle];
            $high = $values[$middle + 1];
            $median = ($low + $high) / 2;
        }

        return $median;
    }

    protected function calcDecimalHash()
    {
        $this->decimalHash = 0;
        $weight = 1;
        foreach ($this->hashDownsampledPixels as $pixel) {
            if ($pixel > $this->hashDownsampledPixelsMedian) {
                $this->decimalHash |= $weight;
            }
            $weight = $weight << 1;
        }
    }

    protected function calcHexadecimalHash(): string
    {
        return dechex($this->decimalHash);
    }
}