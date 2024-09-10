<?php

namespace Waseet\MediaLibrary\MediaCollections\Events;

use Illuminate\Queue\SerializesModels;
use Waseet\MediaLibrary\HasMedia;

class CollectionHasBeenCleared
{
    use SerializesModels;

    public function __construct(public HasMedia $model, public string $collectionName)
    {
    }
}
