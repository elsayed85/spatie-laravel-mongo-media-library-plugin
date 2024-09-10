<?php

namespace Waseet\MediaLibrary\Support\UrlGenerator;

use Waseet\MediaLibrary\Conversions\ConversionCollection;
use Waseet\MediaLibrary\MediaCollections\Exceptions\InvalidUrlGenerator;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;
use Waseet\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

class UrlGeneratorFactory
{
    public static function createForMedia(MongoMedia $media, string $conversionName = ''): UrlGenerator
    {
        $urlGeneratorClass = config('mongo-media-library.url_generator');

        static::guardAgainstInvalidUrlGenerator($urlGeneratorClass);

        /** @var UrlGenerator $urlGenerator */
        $urlGenerator = app($urlGeneratorClass);

        $pathGenerator = PathGeneratorFactory::create($media);

        $urlGenerator
            ->setMedia($media)
            ->setPathGenerator($pathGenerator);

        if ($conversionName !== '') {
            $conversion = ConversionCollection::createForMedia($media)->getByName($conversionName);

            $urlGenerator->setConversion($conversion);
        }

        return $urlGenerator;
    }

    public static function guardAgainstInvalidUrlGenerator(string $urlGeneratorClass): void
    {
        if (! class_exists($urlGeneratorClass)) {
            throw InvalidUrlGenerator::doesntExist($urlGeneratorClass);
        }

        if (! is_subclass_of($urlGeneratorClass, UrlGenerator::class)) {
            throw InvalidUrlGenerator::doesNotImplementUrlGenerator($urlGeneratorClass);
        }
    }
}
