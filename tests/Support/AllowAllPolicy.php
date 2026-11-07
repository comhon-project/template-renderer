<?php

namespace Comhon\TemplateRenderer\Tests\Support;

use Twig\Sandbox\SecurityPolicyInterface;

/**
 * A security policy that allows everything, to check that a custom policy is used.
 */
class AllowAllPolicy implements SecurityPolicyInterface
{
    public function checkSecurity($tags, $filters, $functions, array $tests = []): void {}

    public function checkMethodAllowed($obj, $method): void {}

    public function checkPropertyAllowed($obj, $property): void {}
}
