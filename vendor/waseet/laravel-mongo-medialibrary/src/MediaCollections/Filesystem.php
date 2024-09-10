<?php

namespace Waseet\MediaLibrary\MediaCollections;

use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Waseet\MediaLibrary\Conversions\ConversionCollection;
use Waseet\MediaLibrary\Conversions\FileManipulator;
use Waseet\MediaLibrary\MediaCollections\Events\MediaHasBeenAdded;
use Waseet\MediaLibrary\MediaCollections\Exceptions\DiskCannotBeAccessed;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;
use Waseet\MediaLibrary\Support\File;
use Waseet\MediaLibrary\Support\FileRemover\FileRemoverFactory;
use Waseet\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;
use Waseet\MediaLibrary\Support\RemoteFile;

class Filesystem
{
    protected array $customRemoteHeaders = [];

    public function __construct(
        protected Factory $filesystem
    ) {
    }

    public function add(string $file, MongoMedia $media, ?string $targetFileName = null): bool
    {
        try {
            $this->copyToMediaLibrary($file, $media, null, $targetFileName);
        } catch(DiskCannotBeAccessed $exception) {
            return false;
        }

        event(new MediaHasBeenAdded($media));

        app(FileManipulator::class)->createDerivedFiles($media);

        return true;
    }

    public function addRemote(RemoteFile $file, MongoMedia $media, ?string $targetFileName = null): bool
    {
        try {
            $this->copyToMediaLibraryFromRemote($file, $media, null, $targetFileName);
        } catch(DiskCannotBeAccessed $exception) {
            return false;
        }

        event(new MediaHasBeenAdded($media));

        app(FileManipulator::class)->createDerivedFiles($media);

        return true;
    }

    public function prepareCopyFileOnDisk(RemoteFile $file, MongoMedia $media, string $destination): void
    {
        $this->copyFileOnDisk($file->getKey(), $destination, $media->disk);
    }

    public function copyToMediaLibraryFromRemote(RemoteFile $file, MongoMedia $media, ?string $type = null, ?string $targetFileName = null): void
    {
        $destinationFileName = $targetFileName ?: $file->getFilename();

        $destination = $this->getMediaDirectory($media, $type).$destinationFileName;

        $diskDriverName = (in_array($type, ['conversions', 'responsiveImages']))
            ? $media->getConversionsDiskDriverName()
            : $media->getDiskDriverName();

        if ($this->shouldCopyFileOnDisk($file, $media, $diskDriverName)) {
            $this->prepareCopyFileOnDisk($file, $media, $destination);

            return;
        }

        $storage = Storage::disk($file->getDisk());

        $headers = $diskDriverName === 'local'
            ? []
            : $this->getRemoteHeadersForFile(
                $file->getKey(),
                $media->getCustomHeaders(),
                $storage->mimeType($file->getKey())
            );

        $this->streamFileToDisk(
            $storage->getDriver()->readStream($file->getKey()),
            $destination,
            $media->disk,
            $headers
        );
    }

    protected function shouldCopyFileOnDisk(RemoteFile $file, MongoMedia $media, string $diskDriverName): bool
    {
        if ($file->getDisk() !== $media->disk) {
            return false;
        }

        if ($diskDriverName === 'local') {
            return true;
        }

        if (count($media->getCustomHeaders()) > 0) {
            return false;
        }

        if ((is_countable(config('mongo-media-library.remote.extra_headers')) ? count(config('mongo-media-library.remote.extra_headers')) : 0) > 0) {
            return false;
        }

        return true;
    }

    protected function copyFileOnDisk(string $file, string $destination, string $disk): void
    {
        $this->filesystem->disk($disk)
            ->copy($file, $destination);
    }

    protected function streamFileToDisk($stream, string $destination, string $disk, array $headers): void
    {
        $this->filesystem->disk($disk)
            ->getDriver()->writeStream(
                $destination,
                $stream,
                $headers
            );
    }

    public function copyToMediaLibrary(string $pathToFile, MongoMedia $media, ?string $type = null, ?string $targetFileName = null)
    {
        $destinationFileName = $targetFileName ?: pathinfo($pathToFile, PATHINFO_BASENAME);

        $destination = $this->getMediaDirectory($media, $type).$destinationFileName;

        $file = fopen($pathToFile, 'r');

        $diskName = (in_array($type, ['conversions', 'responsiveImages']))
            ? $media->conversions_disk
            : $media->disk;

        $diskDriverName = (in_array($type, ['conversions', 'responsiveImages']))
            ? $media->getConversionsDiskDriverName()
            : $media->getDiskDriverName();

        if ($diskDriverName === 'local') {
            $success = $this->filesystem
                ->disk($diskName)
                ->put($destination, $file);

            fclose($file);

            if (! $success) {
                throw DiskCannotBeAccessed::create($diskName);
            }

            return;
        }

        $success = $this->filesystem
            ->disk($diskName)
            ->put(
                $destination,
                $file,
                $this->getRemoteHeadersForFile($pathToFile, $media->getCustomHeaders()),
            );

        if (is_resource($file)) {
            fclose($file);
        }

        if (! $success) {
            throw DiskCannotBeAccessed::create($diskName);
        }
    }

    public function addCustomRemoteHeaders(array $customRemoteHeaders): void
    {
        $this->customRemoteHeaders = $customRemoteHeaders;
    }

    public function getRemoteHeadersForFile(
        string $file,
        array $mediaCustomHeaders = [],
        string $mimeType = null
    ): array {
        $mimeTypeHeader = ['ContentType' => $mimeType ?: File::getMimeType($file)];

        $extraHeaders = config('mongo-media-library.remote.extra_headers');

        return array_merge(
            $mimeTypeHeader,
            $extraHeaders,
            $this->customRemoteHeaders,
            $mediaCustomHeaders
        );
    }

    public function getStream(MongoMedia $media)
    {
        $sourceFile = $this->getMediaDirectory($media).'/'.$media->file_name;

        return $this->filesystem->disk($media->disk)->readStream($sourceFile);
    }

    public function copyFromMediaLibrary(MongoMedia $media, string $targetFile): string
    {
        file_put_contents($targetFile, $this->getStream($media));

        return $targetFile;
    }

    public function removeAllFiles(MongoMedia $media): void
    {
        $fileRemover = FileRemoverFactory::create($media);

        $fileRemover->removeAllFiles($media);
    }

    public function removeFile(MongoMedia $media, string $path): void
    {
        $fileRemover = FileRemoverFactory::create($media);

        $fileRemover->removeFile($path, $media->disk);
    }

    public function removeResponsiveImages(MongoMedia $media, string $conversionName = 'media_library_original'): void
    {
        $responsiveImagesDirectory = $this->getResponsiveImagesDirectory($media);

        $allFilePaths = $this->filesystem->disk($media->disk)->allFiles($responsiveImagesDirectory);

        $responsiveImagePaths = array_filter(
            $allFilePaths,
            fn (string $path) => Str::contains($path, $conversionName)
        );

        $this->filesystem->disk($media->disk)->delete($responsiveImagePaths);
    }

    public function syncFileNames(MongoMedia $media): void
    {
        $this->renameMediaFile($media);

        $this->renameConversionFiles($media);
    }

    public function syncMediaPath(MongoMedia $media): void
    {
        $factory = PathGeneratorFactory::create($media);

        $oldMedia = (clone $media)->fill($media->getOriginal());

        if ($factory->getPath($oldMedia) === $factory->getPath($media)) {
            return;
        }

        $this->filesystem->disk($media->disk)
            ->move($factory->getPath($oldMedia), $factory->getPath($media));
    }

    protected function renameMediaFile(MongoMedia $media): void
    {
        $newFileName = $media->file_name;
        $oldFileName = $media->getOriginal('file_name');

        $mediaDirectory = $this->getMediaDirectory($media);

        $oldFile = "{$mediaDirectory}/{$oldFileName}";
        $newFile = "{$mediaDirectory}/{$newFileName}";

        $this->filesystem->disk($media->disk)->move($oldFile, $newFile);
    }

    protected function renameConversionFiles(MongoMedia $media): void
    {
        $mediaWithOldFileName = config('mongo-media-library.media_model')::find($media->id);
        $mediaWithOldFileName->file_name = $mediaWithOldFileName->getOriginal('file_name');

        $conversionDirectory = $this->getConversionDirectory($media);

        $conversionCollection = ConversionCollection::createForMedia($media);

        foreach ($media->getMediaConversionNames() as $conversionName) {
            $conversion = $conversionCollection->getByName($conversionName);

            $oldFile = $conversionDirectory.$conversion->getConversionFile($mediaWithOldFileName);
            $newFile = $conversionDirectory.$conversion->getConversionFile($media);

            $disk = $this->filesystem->disk($media->conversions_disk);

            // A media conversion file might be missing, waiting to be generated, failed etc.
            if (! $disk->exists($oldFile)) {
                continue;
            }

            $disk->move($oldFile, $newFile);
        }
    }

    public function getMediaDirectory(MongoMedia $media, ?string $type = null): string
    {
        $directory = null;
        $pathGenerator = PathGeneratorFactory::create($media);

        if (! $type) {
            $directory = $pathGenerator->getPath($media);
        }

        if ($type === 'conversions') {
            $directory = $pathGenerator->getPathForConversions($media);
        }

        if ($type === 'responsiveImages') {
            $directory = $pathGenerator->getPathForResponsiveImages($media);
        }

        return $directory;
    }

    public function getConversionDirectory(MongoMedia $media): string
    {
        return $this->getMediaDirectory($media, 'conversions');
    }

    public function getResponsiveImagesDirectory(MongoMedia $media): string
    {
        return $this->getMediaDirectory($media, 'responsiveImages');
    }
}
