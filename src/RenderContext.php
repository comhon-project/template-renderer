<?php

namespace Comhon\TemplateRenderer;

use Comhon\TemplateRenderer\Dates\PlainDate;
use Comhon\TemplateRenderer\Dates\PlainDateTime;

/**
 * The context of a render: the options given to the render, and the values resolved from them.
 */
final class RenderContext
{
    /**
     * @param  RenderOptions  $options  the options given to the render
     * @param  RenderOptions|null  $defaults  the values used when an option is not given
     */
    public function __construct(
        private RenderOptions $options = new RenderOptions,
        private ?RenderOptions $defaults = null,
    ) {}

    /**
     * The options given to the render, as is.
     */
    public function options(): RenderOptions
    {
        return $this->options;
    }

    /**
     * Resolved locale: the locale option, otherwise the default one (null if none).
     */
    public function locale(): ?string
    {
        return $this->options->locale ?? $this->defaults?->locale;
    }

    /**
     * Resolved timezone: the timezone option, otherwise the default one, otherwise the PHP default timezone.
     */
    public function timezone(): string
    {
        return $this->options->timezone ?? $this->defaults?->timezone ?? date_default_timezone_get();
    }

    /**
     * Resolved preferred timezone: the preferred timezone option, otherwise the resolved timezone.
     */
    public function preferredTimezone(): string
    {
        return $this->options->preferredTimezone ?? $this->timezone();
    }

    /**
     * Create a wall-clock date time in the render timezone, null if the value is null.
     */
    public function dateTime(string|\DateTimeInterface|null $value): ?PlainDateTime
    {
        return PlainDateTime::from($value, $this);
    }

    /**
     * Create a day in the render timezone, null if the value is null.
     */
    public function date(string|\DateTimeInterface|null $value): ?PlainDate
    {
        return PlainDate::from($value, $this);
    }
}
