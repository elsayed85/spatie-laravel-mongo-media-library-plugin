<?php

namespace Waseet\MediaLibrary\Support\FileNamer;

use Waseet\MediaLibrary\Conversions\Conversion;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;

abstract class FileNamer
{
    public function originalFileName(string $fileName): string
    {
        $extLength = strlen(pathinfo($fileName, PATHINFO_EXTENSION));

        $baseName = substr($fileName, 0, strlen($fileName) - ($extLength ? $extLength + 1 : 0));

        return $baseName;
    }

    abstract public function conversionFileName(string $fileName, Conversion $conversion): string;

    abstract public function responsiveFileName(string $fileName): string;

    public function temporaryFileName(MongoMedia $media, string $extension): string
    {
        return "{$this->responsiveFileName($media->file_name)}.{$extension}";
    }

    public function extensionFromBaseImage(string $baseImage): string
    {
        return pathinfo($baseImage, PATHINFO_EXTENSION);
    }
}
