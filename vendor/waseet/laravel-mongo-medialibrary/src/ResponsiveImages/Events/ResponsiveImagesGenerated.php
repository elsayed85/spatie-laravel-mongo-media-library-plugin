<?php

namespace Waseet\MediaLibrary\ResponsiveImages\Events;

use Illuminate\Queue\SerializesModels;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;

class ResponsiveImagesGenerated
{
    use SerializesModels;

    public function __construct(public MongoMedia $media)
    {
    }
}
