<?php

namespace Comhon\TemplateRenderer\Drivers\Twig;

use Twig\Sandbox\SecurityPolicyInterface;

/**
 * Policy of the sandbox that validates templates.
 *
 * Twig checks the tags, filters, functions and tests of a template against the policy when the
 * template starts rendering, before evaluating anything. This policy lets the real policy give its
 * verdict, then interrupts the render: a template is validated by Twig itself, without side effect.
 *
 * @internal
 */
final class ValidationPolicy implements SecurityPolicyInterface
{
    public function __construct(
        private SecurityPolicyInterface $policy,
    ) {}

    public function checkSecurity($tags, $filters, $functions, array $tests = []): void
    {
        $this->policy->checkSecurity($tags, $filters, $functions, $tests);

        // a template with the "use" tag checks that tag alone when instantiated, before the check
        // of everything it uses: that first check must not interrupt the render
        if ($tags === ['use'] && $filters === [] && $functions === [] && $tests === []) {
            return;
        }

        throw new TemplateChecked;
    }

    public function checkMethodAllowed($obj, $method): void
    {
        $this->policy->checkMethodAllowed($obj, $method);
    }

    public function checkPropertyAllowed($obj, $property): void
    {
        $this->policy->checkPropertyAllowed($obj, $property);
    }
}
