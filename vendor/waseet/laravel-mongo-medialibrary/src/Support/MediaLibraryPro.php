<?php

namespace Waseet\MediaLibrary\Support;

use Waseet\MediaLibrary\MediaCollections\Exceptions\FunctionalityNotAvailable;
use Waseet\MediaLibraryPro\Models\TemporaryUpload;

class MediaLibraryPro
{
    public static function ensureInstalled(): void
    {
        if (! self::isInstalled()) {
            throw FunctionalityNotAvailable::mediaLibraryProRequired();
        }
    }

    public static function isInstalled(): bool
    {
        return class_exists(TemporaryUpload::class);
    }
}
