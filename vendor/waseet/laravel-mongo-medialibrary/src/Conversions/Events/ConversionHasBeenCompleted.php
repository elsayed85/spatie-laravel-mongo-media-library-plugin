<?php

namespace Waseet\MediaLibrary\Conversions\Events;

use Illuminate\Queue\SerializesModels;
use Waseet\MediaLibrary\Conversions\Conversion;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;

class ConversionHasBeenCompleted
{
    use SerializesModels;

    public function __construct(public MongoMedia $media, public Conversion $conversion)
    {
    }
}
