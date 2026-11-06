<?php

namespace Comhon\TemplateRenderer\Dates;

use Comhon\TemplateRenderer\RenderContext;

/**
 * A day without time (e.g. a birthday), never shifted to another day.
 * Any timezone carried by the given value is ignored.
 */
class PlainDate extends \DateTimeImmutable
{
    use PlainTemporal;

    public function __construct(string|\DateTimeInterface $date, RenderContext $context)
    {
        $this->init($date, $context, 'Y-m-d');
    }

    /**
     * Keep the same day in the given timezone, the result is still a PlainDate.
     */
    public function setTimezone(\DateTimeZone $timezone): \DateTimeImmutable
    {
        return parent::createFromFormat('!Y-m-d', $this->format('Y-m-d'), $timezone);
    }
}
