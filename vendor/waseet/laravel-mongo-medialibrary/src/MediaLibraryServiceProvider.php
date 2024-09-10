<?php

namespace Waseet\MediaLibrary;

use Illuminate\Support\ServiceProvider;
use Waseet\MediaLibrary\Conversions\Commands\RegenerateCommand;
use Waseet\MediaLibrary\MediaCollections\Commands\CleanCommand;
use Waseet\MediaLibrary\MediaCollections\Commands\ClearCommand;
use Waseet\MediaLibrary\MediaCollections\Filesystem;
use Waseet\MediaLibrary\MediaCollections\MediaRepository;
use Waseet\MediaLibrary\MediaCollections\Models\MongoMedia;
use Waseet\MediaLibrary\MediaCollections\Models\Observers\MediaObserver;
use Waseet\MediaLibrary\ResponsiveImages\TinyPlaceholderGenerator\TinyPlaceholderGenerator;
use Waseet\MediaLibrary\ResponsiveImages\WidthCalculator\WidthCalculator;

class MediaLibraryServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerPublishables();

        $mediaClass = config('mongo-media-library.media_model', MongoMedia::class);

        $mediaClass::observe(new MediaObserver());
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mongo-media-library.php', 'mongo-media-library');

        $this->app->scoped(MediaRepository::class, function () {
            $mediaClass = config('mongo-media-library.media_model');

            return new MediaRepository(new $mediaClass());
        });

        $this->registerCommands();
    }

    protected function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/mongo-media-library.php' => config_path('mongo-media-library.php'),
        ], 'config');
    }

    protected function registerCommands(): void
    {
        $this->app->bind(Filesystem::class, Filesystem::class);
        $this->app->bind(WidthCalculator::class, config('mongo-media-library.responsive_images.width_calculator'));
        $this->app->bind(TinyPlaceholderGenerator::class, config('mongo-media-library.responsive_images.tiny_placeholder_generator'));

        $this->app->bind('command.mongo-media-library:regenerate', RegenerateCommand::class);
        $this->app->bind('command.mongo-media-library:clear', ClearCommand::class);
        $this->app->bind('command.mongo-media-library:clean', CleanCommand::class);

        $this->commands([
            'command.mongo-media-library:regenerate',
            'command.mongo-media-library:clear',
            'command.mongo-media-library:clean',
        ]);
    }
}
