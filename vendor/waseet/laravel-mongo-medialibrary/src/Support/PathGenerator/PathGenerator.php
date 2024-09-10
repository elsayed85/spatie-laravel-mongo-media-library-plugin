<?php

namespace Waseet\MediaLibrary\Support\PathGenerator;

use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;

interface PathGenerator
{
    /*
     * Get the path for the given media, relative to the root storage path.
     */
    public function getPath(MongoMedia $media): string;

    /*
     * Get the path for conversions of the given media, relative to the root storage path.
     */
    public function getPathForConversions(MongoMedia $media): string;

    /*
     * Get the path for responsive images of the given media, relative to the root storage path.
     */
    public function getPathForResponsiveImages(MongoMedia $media): string;
}
