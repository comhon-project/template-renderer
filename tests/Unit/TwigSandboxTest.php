<?php

namespace Comhon\TemplateRenderer\Tests\Unit;

use Carbon\Carbon;
use Comhon\TemplateRenderer\Dates\PlainDateTime;
use Comhon\TemplateRenderer\Drivers\TwigDriver;
use Comhon\TemplateRenderer\Exceptions\InvalidTemplateException;
use Comhon\TemplateRenderer\Facades\Template;
use Comhon\TemplateRenderer\RenderContext;
use Comhon\TemplateRenderer\Renderer;
use Comhon\TemplateRenderer\RenderOptions;
use Comhon\TemplateRenderer\Rules\Template as RulesTemplate;
use Comhon\TemplateRenderer\Tests\Support\AllowAllPolicy;
use Comhon\TemplateRenderer\Tests\TestCase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Twig\Extension\CoreExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedPropertyError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedTestError;

class TwigSandboxTest extends TestCase
{
    private const LISTS = [
        'allowed_tags' => ['if', 'for', 'set'],
        'allowed_filters' => ['escape', 'e', 'upper', 'date'],
        'allowed_functions' => ['date'],
    ];

    private function config(array $sandbox = []): array
    {
        return ['driver' => 'twig', 'sandbox' => ['enabled' => true, ...self::LISTS, ...$sandbox]];
    }

    private function driver(array $sandbox = []): TwigDriver
    {
        return new TwigDriver($this->config($sandbox));
    }

    private function render(TwigDriver $driver, string $template, array $replacements = []): string
    {
        return $driver->render($template, $replacements, new RenderContext);
    }

    private function user(): object
    {
        return new class
        {
            public string $name = 'doe';

            public function greet(): string
            {
                return 'hello';
            }

            public function __toString(): string
            {
                return 'user';
            }
        };
    }

    public function test_sandbox_is_disabled_by_default()
    {
        $template = '{{ user.name|upper }} {{ user.greet() }} {{ user }}';
        foreach ([[], ['sandbox' => []], ['sandbox' => ['enabled' => false, ...self::LISTS]]] as $config) {
            $driver = new TwigDriver(['driver' => 'twig', ...$config]);
            $this->assertFalse($driver->isSandboxed());
            $this->assertEquals('DOE hello user', $this->render($driver, $template, ['user' => $this->user()]));
        }

        // the configuration shipped with the package has the sandbox disabled
        $this->assertFalse(config('template-renderer.renderers.twig.sandbox.enabled'));
        $this->assertFalse(Template::isSandboxed());
        $this->assertEquals('DOE hello user', Template::render($template, ['user' => $this->user()]));
    }

    public function test_sandbox_allows_what_is_listed()
    {
        $driver = $this->driver(['allowed_functions' => ['range']]);
        $this->assertTrue($driver->isSandboxed());

        // the ".." operator calls the range function
        $template = "{% set greeting = 'hi' %}{% if name %}{{ greeting }} {{ name|upper }}{% endif %}{% for i in 1..2 %} {{ i }}{% endfor %}";
        $this->assertEquals('hi DOE 1 2', $this->render($driver, $template, ['name' => 'doe']));
    }

    /**
     * Templates rejected with the lists of this test, with the expected error and message (the item is used on line 2).
     */
    private function rejectedCases(): array
    {
        return [
            ["line 1\n{% apply upper %}x{% endapply %}", SecurityNotAllowedTagError::class, 'Tag "apply" is not allowed'],
            ["line 1\n{{ name|lower }}", SecurityNotAllowedFilterError::class, 'Filter "lower" is not allowed'],
            ["line 1\n{{ max(1, 2) }}", SecurityNotAllowedFunctionError::class, 'Function "max" is not allowed'],
            ["line 1\n{% for i in 1..2 %}{% endfor %}", SecurityNotAllowedFunctionError::class, 'Function "range" is not allowed'],
            ["line 1\n{{ 8 is constant('PHP_INT_SIZE') }}", SecurityNotAllowedTestError::class, 'Test "constant" is not allowed'],
        ];
    }

    public function test_sandbox_rejects_what_is_not_listed()
    {
        $driver = $this->driver();
        foreach ($this->rejectedCases() as [$template, $class, $message]) {
            try {
                $this->render($driver, $template, ['name' => 'doe']);
                $this->fail("$class expected for: $template");
            } catch (SecurityError $e) {
                $this->assertInstanceOf($class, $e);
                $this->assertStringContainsString($message, $e->getMessage());
                $this->assertEquals(2, $e->getTemplateLine());
            }
        }
    }

    public function test_sandbox_missing_lists_are_empty()
    {
        $driver = new TwigDriver(['sandbox' => ['enabled' => true]]);
        $this->assertTrue($driver->isSandboxed());
        $this->assertEquals('text', $this->render($driver, 'text'));

        try {
            $this->render($driver, '{% if name %}x{% endif %}', ['name' => 'doe']);
            $this->fail('tag should be rejected');
        } catch (SecurityNotAllowedTagError $e) {
            $this->assertStringContainsString('Tag "if" is not allowed', $e->getMessage());
        }

        // auto escaping applies the escape filter to every printed expression
        try {
            $this->render($driver, '{{ name }}', ['name' => 'doe']);
            $this->fail('escape filter should be rejected');
        } catch (SecurityNotAllowedFilterError $e) {
            $this->assertStringContainsString('Filter "escape" is not allowed', $e->getMessage());
        }
    }

    public function test_sandbox_tests()
    {
        // safe built-in tests are always allowed
        $driver = $this->driver();
        $this->assertEquals('set', $this->render($driver, "{{ name is empty ? 'empty' : 'set' }}", ['name' => 'doe']));
        $this->assertEquals('ko', $this->render($driver, "{{ other is defined ? 'ok' : 'ko' }}"));

        // the constant test must be listed
        $driver = $this->driver(['allowed_tests' => ['constant']]);
        $this->assertEquals('ok', $this->render($driver, "{{ 8 is constant('PHP_INT_SIZE') ? 'ok' : 'ko' }}"));
    }

    public function test_sandbox_checks_object_methods_and_properties()
    {
        $user = $this->user();
        $driver = $this->driver();

        try {
            $this->render($driver, "line 1\n{{ user.greet() }}", ['user' => $user]);
            $this->fail('method should be rejected');
        } catch (SecurityNotAllowedMethodError $e) {
            $this->assertStringContainsString('Calling "greet" method on a', $e->getMessage());
            $this->assertEquals(2, $e->getTemplateLine());
        }
        try {
            $this->render($driver, "line 1\n{{ user.name }}", ['user' => $user]);
            $this->fail('property should be rejected');
        } catch (SecurityNotAllowedPropertyError $e) {
            $this->assertStringContainsString('Calling "name" property on a', $e->getMessage());
            $this->assertEquals(2, $e->getTemplateLine());
        }

        // arrays are not checked
        $this->assertEquals('doe', $this->render($driver, '{{ user.name }}', ['user' => ['name' => 'doe']]));

        $driver = $this->driver([
            'allowed_methods' => [$user::class => ['greet']],
            'allowed_properties' => [$user::class => ['name']],
        ]);
        $this->assertEquals('hello doe', $this->render($driver, '{{ user.greet() }} {{ user.name }}', ['user' => $user]));
    }

    public function test_sandbox_allows_methods_by_interface()
    {
        $context = new RenderContext(new RenderOptions(timezone: 'Europe/Paris'));
        $replacements = [
            'carbon' => Carbon::parse('2026-12-12T12:00:00Z'),
            'meeting' => new PlainDateTime('2026-12-12 14:00', $context),
        ];

        // a date given to a filter needs nothing, unless it is Stringable (Carbon): its __toString must then be allowed
        $this->assertEquals('14:00', $this->driver()->render("{{ meeting|date('H:i') }}", $replacements, $context));
        try {
            $this->driver()->render("{{ carbon|date('H:i') }}", $replacements, $context);
            $this->fail('__toString should be rejected');
        } catch (SecurityNotAllowedMethodError $e) {
            $this->assertStringContainsString('Calling "__tostring" method on a "Carbon\\Carbon" object', $e->getMessage());
        }

        // an interface entry covers every implementation
        $driver = $this->driver(['allowed_methods' => [\DateTimeInterface::class => ['__toString', 'format', 'getTimestamp']]]);
        $template = "{{ carbon|date('H:i') }} {{ carbon.format('H:i') }} {{ meeting.format('H:i') }} {{ meeting.timestamp > carbon.getTimestamp() ? 'after' : 'before' }}";
        $this->assertEquals('13:00 12:00 14:00 after', $driver->render($template, $replacements, $context));

        // "timestamp" is a magic property of Carbon (__get), not the getTimestamp() method
        try {
            $driver->render('{{ carbon.timestamp }}', $replacements, $context);
            $this->fail('magic property should be rejected');
        } catch (SecurityNotAllowedPropertyError $e) {
            $this->assertStringContainsString('Calling "timestamp" property on a "Carbon\\Carbon" object', $e->getMessage());
        }

        $this->expectException(SecurityNotAllowedMethodError::class);
        $driver->render('{{ carbon.addDay() }}', $replacements, $context);
    }

    public function test_sandbox_checks_to_string()
    {
        $user = $this->user();
        try {
            $this->render($this->driver(), '{{ user }}', ['user' => $user]);
            $this->fail('__toString should be rejected');
        } catch (SecurityNotAllowedMethodError $e) {
            $this->assertStringContainsString('Calling "__tostring" method on a', $e->getMessage());
        }

        $driver = $this->driver(['allowed_methods' => [$user::class => ['__toString']]]);
        $this->assertEquals('user', $this->render($driver, '{{ user }}', ['user' => $user]));

        // Twig markup is always printable
        $this->assertEquals('<b>safe</b>', $this->render($this->driver(), '{{ html }}', ['html' => new Markup('<b>safe</b>', 'UTF-8')]));
    }

    public function test_sandbox_validate()
    {
        $driver = $this->driver();
        $driver->validate("{% set greeting = 'hi' %}{% if name %}{{ greeting }} {{ name|upper }}{% endif %}");
        // always allowed tests
        $driver->validate("{{ name is empty ? 'empty' : 'set' }}");
        // methods and properties depend on the objects given at render time: they are not validated
        $driver->validate('{{ user.greet() }} {{ user }}');

        foreach ($this->rejectedCases() as [$template, $class, $message]) {
            try {
                $driver->validate($template);
                $this->fail("$class expected for: $template");
            } catch (InvalidTemplateException $e) {
                $this->assertInstanceOf($class, $e->getPrevious());
                $this->assertStringContainsString($message, $e->getMessage());
                $this->assertStringNotContainsString(' in "', $e->getMessage());
                $this->assertEquals(2, $e->getTemplateLine());
            }
        }
        // the policy decides: with the test allowed, the same template is valid
        $this->driver(['allowed_tests' => ['constant']])->validate("{{ 8 is constant('PHP_INT_SIZE') }}");

        // the template validated is rendered afterwards
        $this->assertEquals('DOE', $this->render($driver, '{{ name|upper }}', ['name' => 'doe']));

        // without the sandbox, the validation is only syntactic
        (new TwigDriver([]))->validate('{{ name|lower }}');
    }

    public function test_validation_rule_with_sandbox()
    {
        config([
            'template-renderer.default_renderer' => 'sandboxed',
            'template-renderer.renderers.sandboxed' => $this->config(),
        ]);

        $validated = Validator::validate(['my_template' => '{{ name|upper }}'], ['my_template' => new RulesTemplate]);
        $this->assertEquals('{{ name|upper }}', $validated['my_template']);

        try {
            Validator::validate(['my_template' => "text\n{{ name|lower }}"], ['my_template' => new RulesTemplate]);
            $this->fail('validation should fail');
        } catch (ValidationException $e) {
            $this->assertEquals('Filter "lower" is not allowed', $e->errors()['my_template'][0]);
        }
    }

    public function test_sandbox_custom_policy()
    {
        // the lists are ignored, the policy decides
        $driver = $this->driver(['policy' => AllowAllPolicy::class, 'allowed_filters' => []]);
        $this->assertTrue($driver->isSandboxed());
        $this->assertEquals('DOE hello user', $this->render($driver, '{{ user.name|upper }} {{ user.greet() }} {{ user }}', ['user' => $this->user()]));

        foreach ([\stdClass::class, 'Unknown\Policy', 42] as $policy) {
            try {
                $this->driver(['policy' => $policy]);
                $this->fail('policy should be rejected');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('must be a class implementing Twig\Sandbox\SecurityPolicyInterface', $e->getMessage());
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be instantiable without arguments');
        $this->driver(['policy' => PolicyWithDependency::class]);
    }

    public function test_sandbox_config_must_be_an_array()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Twig 'sandbox' config must be an array, bool given");
        new TwigDriver(['sandbox' => true]);
    }

    public function test_sandboxed_and_free_renderers_sharing_the_driver()
    {
        config(['template-renderer.renderers.sandboxed' => $this->config()]);
        $template = '{{ user.greet() }}';

        $this->assertFalse(Template::isSandboxed());
        $this->assertTrue(Template::using('sandboxed')->isSandboxed());
        $this->assertEquals('hello', Template::render($template, ['user' => $this->user()]));

        $this->expectException(SecurityNotAllowedMethodError::class);
        Template::using('sandboxed')->render($template, ['user' => $this->user()]);
    }

    public function test_renderer_restores_state_after_security_error()
    {
        config([
            'template-renderer.default_renderer' => 'sandboxed',
            'template-renderer.renderers.sandboxed' => $this->config(),
        ]);
        $intlLocale = \Locale::getDefault();

        try {
            Template::render('{{ user.greet() }}', ['user' => $this->user()], new RenderOptions('fr', 'Asia/Tokyo'));
            $this->fail('render should throw');
        } catch (SecurityError $e) {
        }
        $this->assertEquals('en', app()->getLocale());
        $this->assertEquals($intlLocale, \Locale::getDefault());

        $twig = (new \ReflectionProperty(TwigDriver::class, 'twig'))->getValue(Template::getDriver());
        $this->assertEquals(date_default_timezone_get(), $twig->getExtension(CoreExtension::class)->getTimezone()->getName());
        $this->assertNull($twig->getGlobals()['default_timezone']);
        $this->assertNull($twig->getGlobals()['default_locale']);
    }

    public function test_shipped_sandbox_configuration_rejects_dangerous_constructs()
    {
        $config = config('template-renderer.renderers.twig');
        $config['sandbox']['enabled'] = true;
        $driver = new TwigDriver($config);
        $user = $this->user();
        $magic = new class
        {
            public function __call($method, $arguments)
            {
                return 'called';
            }

            public function __get($name)
            {
                return 'got';
            }
        };

        $templates = [
            // other templates and PHP runtime
            "{% include 'x' %}", "{% extends 'x' %}", "{% embed 'x' %}{% endembed %}", "{% use 'x' %}", '{% import _self as m %}',
            "{{ include('x') }}", "{{ source('x') }}", "{{ block('x') }}", "{{ constant('PHP_VERSION') }}", "{{ attribute(user, 'greet') }}",
            "{{ enum('".addslashes(Suit::class)."') }}", "{{ 8 is constant('PHP_INT_SIZE') }}", '{% for i in 1..10 %}{% endfor %}', '{{ range(1, 10) }}',
            // callables, dumps, unescaped output
            '{{ list|map(v => v) }}', '{{ list|filter(v => v) }}', '{{ list|sort }}', '{{ list|reduce((a, b) => a) }}', '{{ list|find(v => v) }}',
            '{{ list|invoke }}', '{{ list|column(0) }}', '{{ user|json_encode }}', '{{ name|raw }}', '{{ name|spaceless }}', '{{ list|shuffle }}',
            '{% autoescape false %}{{ name }}{% endautoescape %}', '{% flush %}', '{% guard filter upper %}{% endguard %}',
            "{% deprecated 'x' %}", "{% sandbox %}{% include 'x' %}{% endsandbox %}",
            // objects
            '{{ user.greet() }}', '{{ user.name }}', '{{ user }}', '{{ user ~ "" }}',
            "{{ magic.__call('x', []) }}", '{{ magic.anything() }}', '{{ magic.anything }}',
        ];
        foreach ($templates as $template) {
            try {
                $driver->render($template, ['user' => $user, 'magic' => $magic, 'name' => 'doe', 'list' => [1]], new RenderContext);
                $this->fail("should be rejected: $template");
            } catch (SecurityError $e) {
                $this->assertStringContainsString('is not allowed', $e->getMessage(), $template);
            }
        }
    }

    public function test_shipped_sandbox_configuration_renders_an_email()
    {
        Lang::addLines(['messages.hello' => 'Hello :name!'], 'en');

        $config = config('template-renderer.renderers.twig');
        $config['sandbox']['enabled'] = true;
        $renderer = new Renderer(new TwigDriver($config));

        $template = <<<'TWIG'
            {{ __('messages.hello', {name: user.name|capitalize}) }}
            {% for line in lines %}- {{ line.label|upper }}: {{ line.price|format_currency('EUR') }}
            {% endfor %}{% if lines|length > 1 %}Several{% endif %} items: {{ lines|length }}{% with {total: 5.5} %} {{ total|round }}{% endwith %} ({{ lines|keys|join(',') }})
            Ordered on {{ ordered_at|format_datetime('long', 'short') }} ({{ ordered_at|date('H:i') }}), created {{ created_at|date('d/m/Y H:i') }}
            TWIG;
        $rendered = $renderer->render($template, fn (RenderContext $context) => [
            'user' => ['name' => 'doe'],
            'lines' => [['label' => 'coffee', 'price' => 2.5], ['label' => 'tea', 'price' => 3]],
            'ordered_at' => $context->dateTime('2026-12-12 14:00'),
            'created_at' => Carbon::parse('2026-12-12T12:00:00Z'),
        ], new RenderOptions('en', 'Europe/Paris'));

        $expected = <<<'TXT'
            Hello Doe!
            - COFFEE: €2.50
            - TEA: €3.00
            Several items: 2 6 (0,1)
            Ordered on December 12, 2026 at 2:00 PM (14:00), created 12/12/2026 13:00
            TXT;
        // Twig normalizes line endings to \n, the heredocs above depend on the checkout (CRLF on Windows)
        $normalize = fn (string $text) => str_replace(["\r\n", "\u{00A0}", "\u{202F}"], ["\n", ' ', ' '], $text);
        $this->assertEquals($normalize($expected), $normalize($rendered));
    }
}

enum Suit
{
    case Hearts;
}

class PolicyWithDependency extends AllowAllPolicy
{
    public function __construct(public \stdClass $dependency) {}
}
