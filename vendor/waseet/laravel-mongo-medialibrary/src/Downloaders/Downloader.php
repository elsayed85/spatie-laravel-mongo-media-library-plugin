<?php

namespace Waseet\MediaLibrary\Downloaders;

interface Downloader
{
    public function getTempFile(string $url): string;
}
