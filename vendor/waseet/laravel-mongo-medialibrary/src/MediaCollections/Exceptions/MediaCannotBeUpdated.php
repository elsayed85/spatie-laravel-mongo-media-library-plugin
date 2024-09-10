<?php

namespace Waseet\MediaLibrary\MediaCollections\Exceptions;

use Exception;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;

class MediaCannotBeUpdated extends Exception
{
    public static function doesNotBelongToCollection(string $collectionName, MongoMedia $media): self
    {
        return new static("Media id {$media->getKey()} is not part of collection `{$collectionName}`");
    }
}
