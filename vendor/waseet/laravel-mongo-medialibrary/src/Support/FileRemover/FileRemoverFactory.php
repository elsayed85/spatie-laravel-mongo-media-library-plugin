<?php

namespace Waseet\MediaLibrary\Support\FileRemover;

use Waseet\MediaLibrary\MediaCollections\Exceptions\InvalidFileRemover;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;

class FileRemoverFactory
{
    public static function create(MongoMedia $media): FileRemover
    {
        $fileRemoverClass = config('mongo-media-library.file_remover_class');

        static::guardAgainstInvalidFileRemover($fileRemoverClass);

        return app($fileRemoverClass);
    }

    protected static function guardAgainstInvalidFileRemover(string $fileRemoverClass): void
    {
        if (! class_exists($fileRemoverClass)) {
            throw InvalidFileRemover::doesntExist($fileRemoverClass);
        }

        if (! is_subclass_of($fileRemoverClass, FileRemover::class)) {
            throw InvalidFileRemover::doesNotImplementFileRemover($fileRemoverClass);
        }
    }
}
