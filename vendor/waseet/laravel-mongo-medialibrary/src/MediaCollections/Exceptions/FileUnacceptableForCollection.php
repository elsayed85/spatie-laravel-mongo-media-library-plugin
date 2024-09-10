<?php

namespace Waseet\MediaLibrary\MediaCollections\Exceptions;

use Waseet\MediaLibrary\HasMedia;
use Waseet\MediaLibrary\MediaCollections\File;
use Waseet\MediaLibrary\MediaCollections\MediaCollection;

class FileUnacceptableForCollection extends FileCannotBeAdded
{
    public static function create(File $file, MediaCollection $mediaCollection, HasMedia $hasMedia): self
    {
        $modelType = $hasMedia::class;

        return new static("The file with properties `{$file}` was not accepted into the collection named `{$mediaCollection->name}` of model `{$modelType}` with id `{$hasMedia->getKey()}`");
    }
}
