<?php

namespace Waseet\MediaLibrary\MediaCollections\Models\Observers;

use Waseet\MediaLibrary\Conversions\FileManipulator;
use Waseet\MediaLibrary\MediaCollections\Filesystem;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;

class MediaObserver
{
    public function creating(MongoMedia $media)
    {
        if ($media->shouldSortWhenCreating()) {
            if (is_null($media->order_column)) {
                $media->setHighestOrderNumber();
            }
        }
    }

    public function updating(MongoMedia $media)
    {
        /** @var Filesystem $filesystem */
        $filesystem = app(Filesystem::class);

        if (config('mongo-media-library.moves_media_on_update')) {
            $filesystem->syncMediaPath($media);
        }

        if ($media->file_name !== $media->getOriginal('file_name')) {
            $filesystem->syncFileNames($media);
        }
    }

    public function updated(MongoMedia $media)
    {
        if (is_null($media->getOriginal('model_id'))) {
            return;
        }

        $original = $media->getOriginal('manipulations');

        if ($media->manipulations !== $original) {
            $eventDispatcher = MongoMedia::getEventDispatcher();
            MongoMedia::unsetEventDispatcher();

            /** @var FileManipulator $fileManipulator */
            $fileManipulator = app(FileManipulator::class);

            $fileManipulator->createDerivedFiles($media);

            MongoMedia::setEventDispatcher($eventDispatcher);
        }
    }

    public function deleted(MongoMedia $media)
    {
        if (method_exists($media, 'isForceDeleting') && ! $media->isForceDeleting()) {
            return;
        }

        /** @var Filesystem $filesystem */
        $filesystem = app(Filesystem::class);

        $filesystem->removeAllFiles($media);
    }
}
