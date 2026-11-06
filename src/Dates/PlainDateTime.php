<?php

namespace Comhon\TemplateRenderer\Dates;

use Comhon\TemplateRenderer\RenderContext;

/**
 * A wall-clock date time (e.g. a meeting at 14:00), placed in the render timezone.
 * Any timezone carried by the given value is ignored.
 */
class PlainDateTime extends \DateTimeImmutable
{
    use PlainTemporal;

    public function __construct(string|\DateTimeInterface $datetime, RenderContext $context)
    {
        $this->init($datetime, $context, 'Y-m-d H:i:s.u');
    }
}
