<?php

namespace Comhon\TemplateRenderer\Exceptions;

/**
 * Thrown by a driver when a template is invalid. The message is meant for the author of the template.
 */
class InvalidTemplateException extends \RuntimeException
{
    public function __construct(string $message, private ?int $templateLine = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The line of the template where the error is, when known.
     */
    public function getTemplateLine(): ?int
    {
        return $this->templateLine;
    }
}
