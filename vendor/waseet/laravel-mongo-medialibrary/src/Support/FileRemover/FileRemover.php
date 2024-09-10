<?php

namespace Waseet\MediaLibrary\Support\FileRemover;

use Illuminate\Contracts\Filesystem\Factory;
use Waseet\MediaLibrary\MediaCollections\Filesystem;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;

interface FileRemover
{
    public function __construct(Filesystem $mediaFileSystem, Factory $filesystem);

    /*
     * Remove all files relating to the media model.
     */
    public function removeAllFiles(MongoMedia $media): void;

    /*
     * Remove responsive files relating to the media model.
     */
    public function removeResponsiveImages(MongoMedia $media, string $conversionName): void;

    /*
     * Remove a file relating to the media model.
     */
    public function removeFile(string $path, string $disk): void;

}
