<?php

namespace Waseet\MediaLibrary\Conversions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Waseet\MediaLibrary\Conversions\Actions\PerformConversionAction;
use Waseet\MediaLibrary\Conversions\ImageGenerators\ImageGeneratorFactory;
use Waseet\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Waseet\MediaLibrary\MediaCollections\Filesystem;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;
use Waseet\MediaLibrary\ResponsiveImages\Jobs\GenerateResponsiveImagesJob;
use Waseet\MediaLibrary\Support\TemporaryDirectory;

class FileManipulator
{
    public function createDerivedFiles(
        MongoMedia $media,
        array      $onlyConversionNames = [],
        bool       $onlyMissing = false,
        bool       $withResponsiveImages = false
    ): void {
        if (! $this->canConvertMedia($media)) {
            return;
        }

        [$queuedConversions, $conversions] = ConversionCollection::createForMedia($media)
            ->filter(function (Conversion $conversion) use ($onlyConversionNames) {
                if (count($onlyConversionNames) === 0) {
                    return true;
                }

                return in_array($conversion->getName(), $onlyConversionNames);
            })
            ->filter(fn (Conversion $conversion) => $conversion->shouldBePerformedOn($media->collection_name))
            ->partition(fn (Conversion $conversion) => $conversion->shouldBeQueued());

        $this
            ->performConversions($conversions, $media, $onlyMissing)
            ->dispatchQueuedConversions($media, $queuedConversions, $onlyMissing)
            ->generateResponsiveImages($media, $withResponsiveImages);
    }

    public function performConversions(
        ConversionCollection $conversions,
        MongoMedia           $media,
        bool                 $onlyMissing = false
    ): self {
        if ($conversions->isEmpty()) {
            return $this;
        }

        $temporaryDirectory = TemporaryDirectory::create();

        $copiedOriginalFile = app(Filesystem::class)->copyFromMediaLibrary(
            $media,
            $temporaryDirectory->path(Str::random(32) . '.' . $media->extension)
        );

        $conversions
            ->reject(function (Conversion $conversion) use ($onlyMissing, $media) {
                $relativePath = $media->getPath($conversion->getName());

                if ($rootPath = config("filesystems.disks.{$media->disk}.root")) {
                    $relativePath = str_replace($rootPath, '', $relativePath);
                }

                return $onlyMissing && Storage::disk($media->disk)->exists($relativePath);
            })
            ->each(function (Conversion $conversion) use ($media, $copiedOriginalFile) {
                (new PerformConversionAction())->execute($conversion, $media, $copiedOriginalFile);
            });

        $temporaryDirectory->delete();

        return $this;
    }

    protected function dispatchQueuedConversions(
        MongoMedia           $media,
        ConversionCollection $conversions,
        bool                 $onlyMissing = false
    ): self {
        if ($conversions->isEmpty()) {
            return $this;
        }

        $performConversionsJobClass = config(
            'media-library.jobs.perform_conversions',
            PerformConversionsJob::class
        );

        /** @var PerformConversionsJob $job */
        $job = (new $performConversionsJobClass($conversions, $media, $onlyMissing))
            ->onConnection(config('mongo-media-library.queue_connection_name'))
            ->onQueue(config('mongo-media-library.queue_name'));

        dispatch($job);

        return $this;
    }

    protected function generateResponsiveImages(MongoMedia $media, bool $withResponsiveImages): self
    {
        if (! $withResponsiveImages) {
            return $this;
        }

        if (! count($media->responsive_images)) {
            return $this;
        }

        $generateResponsiveImagesJobClass = config(
            'media-library.jobs.generate_responsive_images',
            GenerateResponsiveImagesJob::class
        );

        /** @var GenerateResponsiveImagesJob $job */
        $job = (new $generateResponsiveImagesJobClass($media))
            ->onConnection(config('mongo-media-library.queue_connection_name'))
            ->onQueue(config('mongo-media-library.queue_name'));

        dispatch($job);

        return $this;
    }

    protected function canConvertMedia(MongoMedia $media): bool
    {
        $imageGenerator = ImageGeneratorFactory::forMedia($media);

        return $imageGenerator ? true : false;
    }
}
