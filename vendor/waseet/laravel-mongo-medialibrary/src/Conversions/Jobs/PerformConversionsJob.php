<?php

namespace Waseet\MediaLibrary\Conversions\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Waseet\MediaLibrary\Conversions\ConversionCollection;
use Waseet\MediaLibrary\Conversions\FileManipulator;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;

class PerformConversionsJob implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;
    use Queueable;

    public $deleteWhenMissingModels = true;

    public function __construct(
        protected ConversionCollection $conversions,
        protected MongoMedia           $media,
        protected bool                 $onlyMissing = false,
    ) {
    }

    public function handle(FileManipulator $fileManipulator): bool
    {
        $fileManipulator->performConversions(
            $this->conversions,
            $this->media,
            $this->onlyMissing
        );

        return true;
    }
}
