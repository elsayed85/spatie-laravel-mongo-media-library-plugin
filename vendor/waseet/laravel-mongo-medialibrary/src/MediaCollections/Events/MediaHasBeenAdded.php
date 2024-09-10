<?php

namespace Waseet\MediaLibrary\MediaCollections\Events;

use Illuminate\Queue\SerializesModels;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;

class MediaHasBeenAdded
{
    use SerializesModels;

    public function __construct(public MongoMedia $media)
    {
    }
}
