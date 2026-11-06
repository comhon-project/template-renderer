<?php

namespace Comhon\TemplateRenderer\Dates;

use Comhon\TemplateRenderer\RenderContext;

/**
 * Common behavior of plain dates (wall-clock values placed in the render timezone).
 */
trait PlainTemporal
{
    /**
     * Create from a nullable value, null if the value is null.
     */
    public static function from(string|\DateTimeInterface|null $value, RenderContext $context): ?static
    {
        return $value === null ? null : new static($value, $context);
    }

    /**
     * Build the date from the wall-clock value of the given value, in the render timezone.
     * Any timezone carried by the value is ignored. Relative strings (e.g. "today") are interpreted in the render timezone.
     */
    private function init(string|\DateTimeInterface $value, RenderContext $context, string $format): void
    {
        $timezone = new \DateTimeZone($context->timezone());
        $parsed = $value instanceof \DateTimeInterface ? $value : self::parse($value, $timezone);

        $year = (int) $parsed->format('Y');
        if ($year < 1 || $year > 9999) {
            throw new \InvalidArgumentException("'{$parsed->format('Y-m-d')}' is not a supported date");
        }

        parent::__construct($parsed->format($format), $timezone);
    }

    private static function parse(string $value, \DateTimeZone $timezone): \DateTimeImmutable
    {
        $parsed = date_parse($value);
        // warnings are raised for dates or times that don't exist (e.g. "2026-02-30", "24:00")
        if (trim($value) === '' || $parsed['error_count'] > 0 || $parsed['warning_count'] > 0) {
            throw new \InvalidArgumentException("'$value' is not a valid date");
        }
        $isAbsolute = $parsed['year'] !== false && ! isset($parsed['relative']);

        // an absolute date is read in UTC, where no time is skipped (daylight saving time), so it is kept as written.
        // A relative one (e.g. "today") depends on the current time in the render timezone.
        return new \DateTimeImmutable($value, $isAbsolute ? new \DateTimeZone('UTC') : $timezone);
    }

    /**
     * Inherited factories would skip the normalization done by the constructor.
     */
    public static function createFromFormat(string $format, string $datetime, ?\DateTimeZone $timezone = null): static|false
    {
        throw self::unsupportedFactory(__FUNCTION__);
    }

    public static function createFromInterface(\DateTimeInterface $object): static
    {
        throw self::unsupportedFactory(__FUNCTION__);
    }

    public static function createFromMutable(\DateTime $object): static
    {
        throw self::unsupportedFactory(__FUNCTION__);
    }

    public static function createFromTimestamp(int|float $timestamp): static
    {
        throw self::unsupportedFactory(__FUNCTION__);
    }

    public static function __set_state(array $array): static
    {
        throw self::unsupportedFactory(__FUNCTION__);
    }

    private static function unsupportedFactory(string $method): \BadMethodCallException
    {
        return new \BadMethodCallException(
            static::class."::$method() is not supported, use new ".static::class.'() or '.static::class.'::from() instead'
        );
    }
}
