<?php

namespace Filament\Infolists\Components;

use Closure;
use Filament\SpatieLaravelMongoMediaLibraryPlugin\Collections\AllMediaCollections;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Waseet\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;
use Throwable;

class SpatieMongoMediaLibraryImageEntry extends ImageEntry
{
    protected string | AllMediaCollections | Closure | null $collection = null;

    protected string | Closure | null $conversion = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultImageUrl(function (SpatieMongoMediaLibraryImageEntry $component, Model $record): ?string {
            if ($component->hasRelationship($record)) {
                $record = $component->getRelationshipResults($record);
            }

            $records = Arr::wrap($record);

            $collection = $component->getCollection();

            if (! is_string($collection)) {
                $collection = 'default';
            }

            foreach ($records as $record) {
                $url = $record->getFallbackMediaUrl($collection, $component->getConversion() ?? '');

                if (blank($url)) {
                    continue;
                }

                return $url;
            }

            return null;
        });
    }

    public function collection(string | AllMediaCollections | Closure | null $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function allCollections(): static
    {
        $this->collection(AllMediaCollections::make());

        return $this;
    }

    public function conversion(string | Closure | null $conversion): static
    {
        $this->conversion = $conversion;

        return $this;
    }

    public function getCollection(): string | AllMediaCollections | null
    {
        return $this->evaluate($this->collection);
    }

    public function getConversion(): ?string
    {
        return $this->evaluate($this->conversion);
    }

    public function getImageUrl(?string $state = null): ?string
    {
        $record = $this->getRecord();

        if (! $record) {
            return null;
        }

        if ($this->hasRelationship($record)) {
            $record = $this->getRelationshipResults($record);
        }

        $records = Arr::wrap($record);

        foreach ($records as $record) {
            /** @var Model $record */

            /** @var ?MongoMedia $media */
            $media = $record->getRelationValue('media')->first(fn (MongoMedia $media): bool => $media->uuid === $state);

            if (! $media) {
                continue;
            }

            $conversion = $this->getConversion();

            if ($this->getVisibility() === 'private') {
                try {
                    return $media->getTemporaryUrl(
                        now()->addMinutes(5),
                        $conversion ?? '',
                    );
                } catch (Throwable $exception) {
                    // This driver does not support creating temporary URLs.
                }
            }

            return $media->getAvailableUrl(Arr::wrap($conversion));
        }

        return null;
    }

    /**
     * @return array<string>
     */
    public function getState(): array
    {
        $record = $this->getRecord();

        if ($this->hasRelationship($record)) {
            $record = $this->getRelationshipResults($record);
        }

        $records = Arr::wrap($record);

        $state = [];

        $collection = $this->getCollection() ?? 'default';

        foreach ($records as $record) {
            /** @var Model $record */
            $state = [
                ...$state,
                ...$record->getRelationValue('media')
                    ->when(
                        ! $collection instanceof AllMediaCollections,
                        fn (MediaCollection $mediaCollection) => $mediaCollection->filter(fn (MongoMedia $media): bool => $media->getAttributeValue('collection_name') === $collection),
                    )
                    ->sortBy('order_column')
                    ->pluck('uuid')
                    ->all(),
            ];
        }

        return array_unique($state);
    }
}
