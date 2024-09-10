<?php

namespace Waseet\MediaLibrary\Support;

use Spatie\Image\Image;

class ImageFactory
{
    public static function load(string $path): Image
    {
        return Image::load($path)->useImageDriver(config('mongo-media-library.image_driver'))
            ->setTemporaryDirectory(config('mongo-media-library.temporary_directory_path') ?? storage_path('media-library/temp'));
    }
}
