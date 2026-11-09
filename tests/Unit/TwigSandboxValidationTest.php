<?php

namespace Comhon\TemplateRenderer\Tests\Unit;

use Comhon\TemplateRenderer\Drivers\TwigDriver;
use Comhon\TemplateRenderer\Exceptions\InvalidTemplateException;
use Comhon\TemplateRenderer\RenderContext;
use Comhon\TemplateRenderer\Tests\Support\FailingPolicy;
use Comhon\TemplateRenderer\Tests\TestCase;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedTestError;

/**
 * With the sandbox, a template is validated by Twig itself: its render is started and interrupted
 * right after the security check, which runs before anything is evaluated.
 */
class TwigSandboxValidationTest extends TestCase
{
    private function driver(array $sandbox): TwigDriver
    {
        return new TwigDriver(['sandbox' => ['enabled' => true, 'allowed_filters' => ['escape', 'upper'], ...$sandbox]]);
    }

    public function test_validation_evaluates_nothing()
    {
        // the "deprecated" tag triggers a deprecation when the template is executed: a probe
        $probes = 0;
        set_error_handler(function (int $errno, string $message) use (&$probes) {
            if ($errno === E_USER_DEPRECATED && str_contains($message, 'probe')) {
                $probes++;

                return true;
            }

            return false;
        });
        try {
            $driver = $this->driver(['allowed_tags' => ['deprecated']]);
            $template = "{% deprecated 'probe' %}{{ x|upper }}";

            $driver->validate($template);
            $this->assertEquals(0, $probes);

            $this->assertEquals('A', $driver->render($template, ['x' => 'a'], new RenderContext));
            $this->assertEquals(1, $probes);

            // the compiled template is reused after a render: still checked, still not executed
            $driver->validate($template);
            $this->assertEquals(1, $probes);

            // a rejected template leaves the renderer ready
            try {
                $driver->validate("{% deprecated 'probe' %}{{ x|lower }}");
                $this->fail('filter should be rejected');
            } catch (InvalidTemplateException $e) {
                $this->assertInstanceOf(SecurityNotAllowedFilterError::class, $e->getPrevious());
            }
            $this->assertEquals('A', $driver->render($template, ['x' => 'a'], new RenderContext));
            $this->assertEquals(2, $probes);
        } finally {
            restore_error_handler();
        }
    }

    public function test_validation_rejects_what_the_render_rejects()
    {
        $driver = $this->driver(['allowed_tags' => ['if', 'for', 'block']]);
        $extending = $this->driver(['allowed_tags' => ['if', 'for', 'block', 'extends', 'use']]);
        foreach ([$driver, $extending] as $one) {
            /** @var ArrayLoader $loader */
            $loader = (new \ReflectionProperty(TwigDriver::class, 'loader'))->getValue($one);
            $loader->setTemplate('other', '{% block b %}other{% endblock %}');
        }

        $cases = [
            ["{# line 1 #}\n{% extends 'other' %}", SecurityNotAllowedTagError::class, 'Tag "extends" is not allowed', $driver],
            // the "use" tag is checked when the template is instantiated
            ["text\n{% use 'other' %}", SecurityNotAllowedTagError::class, 'Tag "use" is not allowed', $driver],
            ["text\n{% include 'other' %}", SecurityNotAllowedTagError::class, 'Tag "include" is not allowed', $driver],
            ["text\n{% embed 'other' %}{% endembed %}", SecurityNotAllowedTagError::class, 'Tag "embed" is not allowed', $driver],
            ["{% extends 'other' %}\n{% block b %}{{ parent() }}{% endblock %}", SecurityNotAllowedFunctionError::class, 'Function "parent" is not allowed', $extending],
            // the "use" tag is checked alone first, the rest of the template must still be checked
            ["{% use 'other' %}\n{{ x|lower }}", SecurityNotAllowedFilterError::class, 'Filter "lower" is not allowed', $extending],
            ["text\n{% for i in 1..2 %}{% endfor %}", SecurityNotAllowedFunctionError::class, 'Function "range" is not allowed', $driver],
            ["text\n{{ x|lower }}", SecurityNotAllowedFilterError::class, 'Filter "lower" is not allowed', $driver],
            ["text\n{{ 8 is constant('PHP_INT_SIZE') }}", SecurityNotAllowedTestError::class, 'Test "constant" is not allowed', $driver],
        ];
        foreach ($cases as [$template, $class, $message, $driver]) {
            try {
                $driver->validate($template);
                $this->fail("validate should reject: $template");
            } catch (InvalidTemplateException $e) {
                $this->assertInstanceOf($class, $e->getPrevious(), $template);
                $this->assertStringContainsString($message, $e->getMessage(), $template);
                $this->assertEquals(2, $e->getTemplateLine(), $template);
                $validated = $e->getPrevious()->getMessage();
            }
            try {
                $driver->render($template, ['x' => 'a'], new RenderContext);
                $this->fail("render should reject: $template");
            } catch (SecurityError $e) {
                $this->assertInstanceOf($class, $e, $template);
                $this->assertEquals($validated, $e->getMessage(), $template);
            }
        }

        // allowed, valid
        $driver->validate('{% if x %}{% for i in [1] %}{{ i|upper }}{% endfor %}{% endif %}');
        $extending->validate("{% extends 'other' %}{% block b %}{{ x|upper }}{% endblock %}");
        $extending->validate("{% use 'other' %}{{ x|upper }}");
        $extending->validate("{% use 'other' %}text only");
    }

    public function test_validation_reports_only_errors_about_the_template()
    {
        // a failure unrelated to the template is not an invalid template
        $driver = new TwigDriver(['sandbox' => ['enabled' => true, 'policy' => FailingPolicy::class]]);

        try {
            $driver->validate('text');
            $this->fail('the failure should be thrown');
        } catch (RuntimeError $e) {
            // Twig wraps the failure, it is not an invalid template
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            $this->assertEquals('policy failure', $e->getPrevious()->getMessage());
        }
    }

    public function test_validation_covers_the_template_itself_only()
    {
        // the body of an embed is another template, checked when rendered: a known limit
        $driver = $this->driver(['allowed_tags' => ['embed', 'extends', 'block']]);
        $loader = (new \ReflectionProperty(TwigDriver::class, 'loader'))->getValue($driver);
        $loader->setTemplate('other', '{% block b %}other{% endblock %}');
        $template = "{% embed 'other' %}{% block b %}{{ x|lower }}{% endblock %}{% endembed %}";

        $driver->validate($template);
        $this->expectException(SecurityNotAllowedFilterError::class);
        $driver->render($template, ['x' => 'a'], new RenderContext);
    }

    public function test_validation_reports_compile_errors()
    {
        // a missing required argument is a syntax error raised when the template is compiled,
        // which the syntactic validation of a renderer without sandbox doesn't reach
        (new TwigDriver([]))->validate('{{ x|batch }}');

        $this->expectException(InvalidTemplateException::class);
        $this->expectExceptionMessage('Value for argument "size" is required for filter "batch"');
        $this->driver(['allowed_filters' => ['escape', 'batch']])->validate('{{ x|batch }}');
    }
}
