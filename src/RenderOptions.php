<?php

namespace Comhon\TemplateRenderer;

/**
 * Options of a render. Missing values are resolved with the renderer defaults.
 * Can be extended to give options specific to a driver.
 */
class RenderOptions
{
    public function __construct(
        public readonly ?string $locale = null,
        public readonly ?string $timezone = null,
        public readonly ?string $preferredTimezone = null,
    ) {}
}
