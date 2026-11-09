<?php

namespace Comhon\TemplateRenderer\Tests\Support;

/**
 * A security policy that fails for a reason unrelated to the template.
 */
class FailingPolicy extends AllowAllPolicy
{
    public function checkSecurity($tags, $filters, $functions, array $tests = []): void
    {
        throw new \RuntimeException('policy failure');
    }
}
