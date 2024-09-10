<?php

namespace Waseet\MediaLibrary\Conversions\Actions;

use Waseet\MediaLibrary\Conversions\Conversion;
use Waseet\MediaLibrary\Conversions\Events\ConversionHasBeenCompleted;
use Waseet\MediaLibrary\Conversions\Events\ConversionWillStart;
use Waseet\MediaLibrary\Conversions\ImageGenerators\ImageGeneratorFactory;
use Waseet\MediaLibrary\MediaCollections\Filesystem;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;
use Waseet\MediaLibrary\ResponsiveImages\ResponsiveImageGenerator;

class PerformConversionAction
{
    public function execute(
        Conversion $conversion,
        MongoMedia $media,
        string     $copiedOriginalFile
    ) {
        $imageGenerator = ImageGeneratorFactory::forMedia($media);

        $copiedOriginalFile = $imageGenerator->convert($copiedOriginalFile, $conversion);

        if (! $copiedOriginalFile) {
            return;
        }

        event(new ConversionWillStart($media, $conversion, $copiedOriginalFile));

        $manipulationResult = (new PerformManipulationsAction())->execute($media, $conversion, $copiedOriginalFile);

        $newFileName = $conversion->getConversionFile($media);

        $renamedFile = $this->renameInLocalDirectory($manipulationResult, $newFileName);

        if ($conversion->shouldGenerateResponsiveImages()) {
            /** @var ResponsiveImageGenerator $responsiveImageGenerator */
            $responsiveImageGenerator = app(ResponsiveImageGenerator::class);

            $responsiveImageGenerator->generateResponsiveImagesForConversion(
                $media,
                $conversion,
                $renamedFile
            );
        }

        app(Filesystem::class)->copyToMediaLibrary($renamedFile, $media, 'conversions');

        $media->markAsConversionGenerated($conversion->getName());

        event(new ConversionHasBeenCompleted($media, $conversion));
    }

    protected function renameInLocalDirectory(
        string $fileNameWithDirectory,
        string $newFileNameWithoutDirectory
    ): string {
        $targetFile = pathinfo($fileNameWithDirectory, PATHINFO_DIRNAME).'/'.$newFileNameWithoutDirectory;

        rename($fileNameWithDirectory, $targetFile);

        return $targetFile;
    }
}
