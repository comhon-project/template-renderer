<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Comhon\TemplateRenderer\Dates\PlainDate;
use Comhon\TemplateRenderer\Dates\PlainDateTime;
use Comhon\TemplateRenderer\Drivers\DriverInterface;
use Comhon\TemplateRenderer\Drivers\TwigDriver;
use Comhon\TemplateRenderer\Facades\Template;
use Comhon\TemplateRenderer\Locale\LaravelLocaleHandler;
use Comhon\TemplateRenderer\Locale\LocaleHandler;
use Comhon\TemplateRenderer\RenderContext;
use Comhon\TemplateRenderer\Renderer;
use Comhon\TemplateRenderer\RenderOptions;
use Comhon\TemplateRenderer\Rules\Template as RulesTemplate;
use Comhon\TemplateRenderer\TemplateManager;
use Comhon\TemplateRenderer\Tests\Support\TestDriver;
use Comhon\TemplateRenderer\Tests\TestCase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;

class TemplateManagerTest extends TestCase
{
    public function test_twig_renderer()
    {
        $rendered = Template::render('test {{ user.name }} test', ['user' => ['name' => 'doe']]);
        $this->assertEquals('test doe test', $rendered);
    }

    public function test_twig_renderer_locale_and_timezone()
    {
        $template = "test {{ datetime|format_datetime('full', 'full') }} test";
        $replacements = ['datetime' => Carbon::parse('2022-12-12T12:12:12Z')];
        $rendered = Template::render($template, $replacements);
        $this->assertEquals(
            'test Monday, December 12, 2022 at 12:12:12 PM Coordinated Universal Time test',
            str_replace(' ', ' ', $rendered)
        );

        Template::setDefaultLocale('fr');
        Template::setDefaultTimezone('Europe/Paris');
        $rendered = Template::render($template, $replacements);
        $this->assertEquals(
            'test lundi 12 décembre 2022 à 13:12:12 heure normale d’Europe centrale test',
            str_replace(' ', ' ', $rendered)
        );
    }

    public function test_twig_renderer_dynamic_translation_app_locale()
    {
        Lang::addLines(['messages.hello' => 'Hello!'], 'en');
        Lang::addLines(['messages.hello' => 'Salut!'], 'fr');

        $template = 'test {{ hello.translate() }} test';
        $replacements = ['hello' => new class
        {
            public function translate()
            {
                return __('messages.hello');
            }
        }];
        $rendered = Template::render($template, $replacements);
        $this->assertEquals('test Hello! test', $rendered);

        Template::setDefaultLocale('fr');
        Template::setDefaultTimezone('Europe/Paris');
        $rendered = Template::render($template, $replacements);
        $this->assertEquals('test Salut! test', $rendered);
    }

    public function test_twig_renderer_laravel_translation_functions()
    {
        Lang::addLines(['messages.hello' => 'Hello!'], 'en');
        Lang::addLines(['messages.hello' => 'Salut!'], 'fr');
        Lang::addLines(['messages.number' => '{1} one|{2} two'], 'en');
        Lang::addLines(['messages.number' => '{1} un|{2} deux'], 'fr');

        $template = "test {{ __('messages.hello') }} {{ trans('messages.hello') }} {{ trans_choice('messages.number', 1) }} {{ trans_choice('messages.number', 2) }} test";
        $rendered = Template::render($template, []);
        $this->assertEquals('test Hello! Hello! one two test', $rendered);

        Template::setDefaultLocale('fr');
        Template::setDefaultTimezone('Europe/Paris');
        $rendered = Template::render($template, []);
        $this->assertEquals('test Salut! Salut! un deux test', $rendered);
    }

    public function test_create_context()
    {
        // falls back to the app locale
        $context = Template::createContext();
        $this->assertEquals('en', $context->locale());
        $this->assertEquals('UTC', $context->timezone());
        $this->assertEquals('UTC', $context->preferredTimezone());

        Template::setDefaultLocale('fr');
        Template::setDefaultTimezone('Europe/Paris');
        $context = Template::createContext();
        $this->assertEquals('fr', $context->locale());
        $this->assertEquals('Europe/Paris', $context->timezone());
        $this->assertEquals('Europe/Paris', $context->preferredTimezone());

        $context = Template::createContext(new RenderOptions('es', 'Asia/Tokyo', 'Asia/Shanghai'));
        $this->assertEquals('es', $context->locale());
        $this->assertEquals('Asia/Tokyo', $context->timezone());
        $this->assertEquals('Asia/Shanghai', $context->preferredTimezone());
    }

    public function test_twig_renderer_closure_replacements()
    {
        Lang::addLines(['messages.hello' => 'Hello!'], 'en');
        Lang::addLines(['messages.hello' => 'Salut!'], 'fr');

        $template = '{{ hello }} {{ locale }} {{ timezone }} {{ preferred_timezone }}';
        $rendered = Template::render($template, fn (RenderContext $context) => [
            // the locale of the context is applied when the closure is called
            'hello' => __('messages.hello'),
            'locale' => $context->locale(),
            'timezone' => $context->timezone(),
        ], new RenderOptions('fr', 'Europe/Paris', 'Asia/Tokyo'));

        $this->assertEquals('Salut! fr Europe/Paris Asia/Tokyo', $rendered);
        $this->assertEquals('en', app()->getLocale());
    }

    public function test_twig_renderer_plain_date_time()
    {
        $template = "{{ meeting|date('H:i') }} {{ meeting|date('H:i', 'Asia/Shanghai') }} {{ meeting|format_time('short', timezone='Asia/Shanghai') }}"
            ." {{ meeting|date_modify('+1 hour')|date('H:i') }} {{ meeting < instant ? 'before' : 'after' }} {{ meeting.timestamp == instant.timestamp - 1800 ? 'ok' : 'ko' }}";

        $rendered = Template::render($template, fn (RenderContext $context) => [
            'meeting' => new PlainDateTime('2026-12-12 14:00', $context),
            'instant' => Carbon::parse('2026-12-12T13:30:00Z'), // 14:30 in Paris
        ], new RenderOptions('fr', 'Europe/Paris'));

        $this->assertEquals('14:00 21:00 21:00 15:00 before ok', $rendered);
    }

    public function test_twig_renderer_plain_date()
    {
        $template = "{{ birthday|date('Y-m-d') }} {{ birthday|date('Y-m-d', 'Pacific/Kiritimati') }} {{ birthday|date('Y-m-d', 'Pacific/Pago_Pago') }}"
            ." {{ birthday|format_date('short', timezone='Pacific/Kiritimati') }} {{ birthday|format_date('short', timezone='Pacific/Pago_Pago') }}"
            ." {{ birthday < instant ? 'before' : 'after' }}";

        foreach (['America/New_York', 'Pacific/Kiritimati', 'Pacific/Pago_Pago'] as $timezone) {
            $rendered = Template::render($template, fn (RenderContext $context) => [
                'birthday' => new PlainDate('2026-12-12 23:30', $context),
                'instant' => new \DateTimeImmutable('2026-12-12 00:30', new \DateTimeZone($timezone)),
            ], new RenderOptions('fr', $timezone));

            $this->assertEquals('2026-12-12 2026-12-12 2026-12-12 12/12/2026 12/12/2026 before', $rendered, $timezone);
        }
    }

    public function test_plain_values_in_daylight_saving_time_gap()
    {
        // in America/Nuuk, 2026-03-28 23:30 doesn't exist (clocks jump to 2026-03-29 00:30)
        $context = new RenderContext(new RenderOptions('fr', 'America/Nuuk'));

        // a day is never shifted
        $this->assertEquals('2026-03-28', $context->date('2026-03-28 23:30')->format('Y-m-d'));
        $this->assertEquals('2026-03-28', $context->date(new \DateTimeImmutable('2026-03-28 23:30'))->format('Y-m-d'));

        // a date time is shifted by PHP, whatever the given value
        $this->assertEquals('2026-03-29 00:30', $context->dateTime('2026-03-28 23:30')->format('Y-m-d H:i'));
        $this->assertEquals('2026-03-29 00:30', $context->dateTime(new \DateTimeImmutable('2026-03-28 23:30'))->format('Y-m-d H:i'));
    }

    public function test_plain_values_unsupported_year()
    {
        $context = new RenderContext(new RenderOptions('fr', 'Europe/Paris'));
        foreach ([10000, -5, 0] as $year) {
            $value = (new \DateTimeImmutable('2026-01-01'))->setDate($year, 1, 1);
            foreach ([PlainDateTime::class, PlainDate::class] as $class) {
                try {
                    new $class($value, $context);
                    $this->fail("$class should reject year $year");
                } catch (\InvalidArgumentException $e) {
                    $this->assertStringContainsString('is not a supported date', $e->getMessage());
                }
            }
        }
    }

    public function test_plain_values_inherited_factories_are_not_supported()
    {
        $calls = [
            fn ($class) => $class::createFromFormat('Y-m-d', '2026-12-12'),
            fn ($class) => $class::createFromInterface(new \DateTimeImmutable),
            fn ($class) => $class::createFromMutable(new \DateTime),
            fn ($class) => $class::createFromTimestamp(0),
            fn ($class) => $class::__set_state([]),
        ];
        foreach ([PlainDateTime::class, PlainDate::class] as $class) {
            foreach ($calls as $call) {
                try {
                    $call($class);
                    $this->fail("$class factory should not be supported");
                } catch (\BadMethodCallException $e) {
                    $this->assertStringContainsString('is not supported', $e->getMessage());
                }
            }
        }
    }

    public function test_context_plain_value_helpers()
    {
        $context = new RenderContext(new RenderOptions('fr', 'Europe/Paris'));

        $this->assertInstanceOf(PlainDateTime::class, $context->dateTime('2026-12-12 14:00'));
        $this->assertEquals('2026-12-12 14:00 Europe/Paris', $context->dateTime('2026-12-12 14:00')->format('Y-m-d H:i e'));
        $this->assertInstanceOf(PlainDate::class, $context->date(Carbon::parse('2026-12-12 14:00')));
        $this->assertEquals('2026-12-12', $context->date(Carbon::parse('2026-12-12 14:00'))->format('Y-m-d'));
        $this->assertNull($context->dateTime(null));
        $this->assertNull($context->date(null));
        $this->assertNull(PlainDateTime::from(null, $context));
        $this->assertInstanceOf(PlainDate::class, PlainDate::from('2026-12-12', $context));
    }

    public function test_plain_values_ignore_timezone()
    {
        $context = new RenderContext(new RenderOptions('fr', 'Europe/Paris'));
        $values = [
            '2026-12-12 14:30',
            '2026-12-12T14:30:00Z',
            '2026-12-12 14:30+09:00',
            '2026-12-12 14:30 America/New_York',
            Carbon::parse('2026-12-12 14:30', 'UTC'),
            new \DateTimeImmutable('2026-12-12 14:30', new \DateTimeZone('Asia/Tokyo')),
        ];
        foreach ($values as $value) {
            $this->assertEquals('2026-12-12 14:30 Europe/Paris', (new PlainDateTime($value, $context))->format('Y-m-d H:i e'));
            $this->assertEquals('2026-12-12 00:00 Europe/Paris', (new PlainDate($value, $context))->format('Y-m-d H:i e'));
        }
    }

    public function test_plain_values_relative_in_context_timezone()
    {
        $context = new RenderContext(new RenderOptions('fr', 'Pacific/Kiritimati'));
        $timezone = new \DateTimeZone('Pacific/Kiritimati');

        // the day may change between calls (midnight), so both days around the call are accepted
        $before = (new \DateTimeImmutable('today', $timezone))->format('Y-m-d');
        $date = (new PlainDate('today', $context))->format('Y-m-d');
        $dateTime = (new PlainDateTime('today', $context))->format('Y-m-d H:i');
        $after = (new \DateTimeImmutable('today', $timezone))->format('Y-m-d');

        $this->assertContains($date, [$before, $after]);
        $this->assertContains($dateTime, ["$before 00:00", "$after 00:00"]);
    }

    public function test_plain_values_invalid()
    {
        $context = new RenderContext;
        foreach (['invalid', '', ' ', '2026-02-30', '2026-13-01', 'Feb 30', '02/30', '2026-12-12 24:00', '2026-12-12 14:00:60'] as $value) {
            foreach ([PlainDateTime::class, PlainDate::class] as $class) {
                try {
                    new $class($value, $context);
                    $this->fail("$class should reject '$value'");
                } catch (\InvalidArgumentException $e) {
                    $this->assertEquals("'$value' is not a valid date", $e->getMessage());
                }
            }
        }
    }

    public function test_context_locale_falls_back_to_app_locale()
    {
        Template::extend('test', fn () => new TestDriver);
        $locale = 'not set';
        Template::using('test')->render('test', function (RenderContext $context) use (&$locale) {
            $locale = $context->locale();

            return [];
        });

        $this->assertEquals('en', $locale);
    }

    public function test_renderer_restores_state_after_exception()
    {
        $intlLocale = \Locale::getDefault();
        $failing = new class
        {
            public function fail()
            {
                throw new \RuntimeException('failure');
            }
        };

        // exception thrown in the closure
        try {
            Template::render('test', function () {
                throw new \RuntimeException('failure');
            }, new RenderOptions('fr', 'Asia/Tokyo'));
            $this->fail('render should throw');
        } catch (\RuntimeException $e) {
        }
        $this->assertEquals('en', app()->getLocale());
        $this->assertEquals($intlLocale, \Locale::getDefault());

        // exception thrown during the twig render
        try {
            Template::render('{{ failing.fail() }}', ['failing' => $failing], new RenderOptions('fr', 'Asia/Tokyo'));
            $this->fail('render should throw');
        } catch (RuntimeError $e) {
        }
        $this->assertEquals('en', app()->getLocale());
        $this->assertEquals($intlLocale, \Locale::getDefault());

        // twig state (timezone and globals) is restored too
        $twig = (new \ReflectionProperty(TwigDriver::class, 'twig'))->getValue(Template::using('twig')->getDriver());
        $this->assertEquals(date_default_timezone_get(), $twig->getExtension(CoreExtension::class)->getTimezone()->getName());
        $this->assertNull($twig->getGlobals()['default_timezone']);
        $this->assertNull($twig->getGlobals()['default_locale']);
    }

    public function test_locale_handler_without_locale()
    {
        $handler = new class implements LocaleHandler
        {
            public ?string $locale = null;

            public array $calls = [];

            public function getLocale(): ?string
            {
                return $this->locale;
            }

            public function setLocale(?string $locale): void
            {
                $this->calls[] = $locale;
                $this->locale = $locale;
            }
        };
        (new Renderer(new TestDriver, $handler))->render('test', [], new RenderOptions('fr'));

        // the render locale is applied, then the missing locale is restored
        $this->assertEquals(['fr', null], $handler->calls);
        $this->assertNull($handler->locale);
    }

    public function test_plain_date_conversion_keeps_plain_date()
    {
        $rendered = Template::render("{{ birthday|date('Y-m-d') }}", fn (RenderContext $context) => [
            'birthday' => (new PlainDate('2026-12-12', $context))->setTimezone(new \DateTimeZone('Pacific/Kiritimati')),
        ], new RenderOptions(timezone: 'Europe/Paris'));

        $this->assertEquals('2026-12-12', $rendered);
    }

    public function test_renderer_restores_locale_when_applying_locale_fails()
    {
        $intlLocale = \Locale::getDefault();
        try {
            Template::render('test', [], new RenderOptions('fr/x'));
            $this->fail('render should throw');
        } catch (\InvalidArgumentException $e) {
        }

        $this->assertEquals('en', app()->getLocale());
        $this->assertEquals($intlLocale, \Locale::getDefault());
    }

    public function test_closure_replacements_are_converted_to_array()
    {
        $this->assertEquals('1 2', Template::render('{{ a }} {{ b }}', fn () => collect(['a' => 1, 'b' => 2])));

        // only the first level is converted, nested objects are kept
        $order = new class
        {
            public function label()
            {
                return 'order label';
            }
        };
        $this->assertEquals('order label', Template::render('{{ order.label() }}', fn () => collect(['order' => $order])));
        $this->assertEquals('1', Template::render('{{ a }}', fn () => new \ArrayIterator(['a' => 1])));

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Replacements closure must return an array or a Traversable, string given');
        Template::render('{{ a }}', fn () => 'a');
    }

    public function test_twig_renderer_restores_state_after_nested_render()
    {
        $template = "{{ date|date('H:i') }} {{ default_timezone }} {{ nested.render() }} {{ date|date('H:i') }} {{ default_timezone }}";
        $nested = new class
        {
            public function render()
            {
                return Template::render("{{ date|date('H:i') }} {{ default_timezone }}", [
                    'date' => Carbon::parse('2026-12-12T12:00:00Z'),
                ], new RenderOptions(timezone: 'Asia/Tokyo'));
            }
        };
        $rendered = Template::render($template, [
            'date' => Carbon::parse('2026-12-12T12:00:00Z'),
            'nested' => $nested,
        ], new RenderOptions(timezone: 'Europe/Paris'));

        $this->assertEquals('13:00 Europe/Paris 21:00 Asia/Tokyo 13:00 Europe/Paris', $rendered);
    }

    public function test_renderer_with_own_driver()
    {
        $template = "{{ datetime|format_datetime('full', 'none') }}";
        $renderer = new Renderer(new TwigDriver([]), new LaravelLocaleHandler(app()));

        $rendered = $renderer->render($template, fn (RenderContext $context) => [
            'datetime' => new PlainDateTime('2026-12-12 00:30', $context),
        ], new RenderOptions('fr', 'Europe/Paris'));

        $this->assertEquals('samedi 12 décembre 2026', $rendered);
        $this->assertEquals('en', app()->getLocale());
    }

    public function test_using()
    {
        $resolved = 0;
        Template::extend('test', function () use (&$resolved) {
            $resolved++;

            return new TestDriver;
        });

        $this->assertInstanceOf(Renderer::class, Template::using('test'));
        $this->assertInstanceOf(TestDriver::class, Template::using('test')->getDriver());
        $this->assertEquals('test replaced test', Template::using('test')->render('test {{ to_replace }} test', []));
        $this->assertEquals('test replaced test', Template::using('test')->render('test {{ to_replace }} test', fn () => []));

        // calls are forwarded to the driver
        $this->assertEquals('custom', Template::using('test')->customMethod());

        // the default renderer is unchanged
        $this->assertEquals('test doe test', Template::render('test {{ name }} test', ['name' => 'doe']));

        // the renderer instance is created once and shared
        $this->assertSame(Template::using('test'), Template::using('test'));
        $this->assertEquals(1, $resolved);
    }

    public function test_twig_template_name_collision()
    {
        $driver = new TwigDriver([]);
        $template = 'genuine {{ name }}';

        // simulate a crafted template stored under the same xxh128 name
        $loader = (new \ReflectionProperty(TwigDriver::class, 'loader'))->getValue($driver);
        $loader->setTemplate(hash('xxh128', $template), 'crafted {{ name }}');

        $this->assertEquals('genuine doe', $driver->render($template, ['name' => 'doe'], new RenderContext));
        $this->assertEquals('genuine doe', $driver->render($template, ['name' => 'doe'], new RenderContext));
    }

    public function test_twig_validate()
    {
        // valid
        Template::validate('test {{ user.name }} test');

        // invalid should throw exception
        $this->expectException(\Exception::class);
        Template::validate('test {{ "true }} test');
    }

    public function test_manager_without_locale_handler()
    {
        $manager = new TemplateManager([
            'default_renderer' => 'twig',
            'renderers' => ['twig' => ['driver' => 'twig']],
        ]);
        $locale = 'not set';
        $rendered = $manager->render('test {{ name }}', function (RenderContext $context) use (&$locale) {
            $locale = $context->locale();

            return ['name' => 'doe'];
        }, new RenderOptions(timezone: 'Europe/Paris'));

        $this->assertEquals('test doe', $rendered);
        $this->assertNull($locale);
        $this->assertEquals('fr', $manager->render('{{ default_locale }}', [], new RenderOptions('fr')));
    }

    public function test_render_options_resolution()
    {
        Template::setDefaultTimezone('Europe/Paris');

        // given values first, then renderer defaults, then app locale and PHP timezone
        $context = Template::createContext(new RenderOptions('es', 'Asia/Tokyo'));
        $this->assertEquals(['es', 'Asia/Tokyo', 'Asia/Tokyo'], [$context->locale(), $context->timezone(), $context->preferredTimezone()]);

        $context = Template::createContext();
        $this->assertEquals(['en', 'Europe/Paris', 'Europe/Paris'], [$context->locale(), $context->timezone(), $context->preferredTimezone()]);

        Template::setDefaultLocale('fr');
        $this->assertEquals('fr', Template::createContext()->locale());
    }

    public function test_options_reach_the_driver()
    {
        $options = new RenderOptions(preferredTimezone: 'Asia/Tokyo');
        $driver = new class implements DriverInterface
        {
            public ?RenderContext $context = null;

            public function render(string $template, array $replacements, RenderContext $context): string
            {
                $this->context = $context;

                return '';
            }

            public function validate(string $template) {}
        };
        (new Renderer($driver))->render('test', [], $options);

        $this->assertSame($options, $driver->context->options());
        $this->assertEquals('Asia/Tokyo', $driver->context->preferredTimezone());

        // options can be extended with driver specific options
        $specific = new class(locale: 'fr') extends RenderOptions
        {
            public readonly bool $minify;

            public function __construct(?string $locale = null)
            {
                parent::__construct($locale);
                $this->minify = true;
            }
        };
        (new Renderer($driver))->render('test', [], $specific);
        $this->assertTrue($driver->context->options()->minify);
        $this->assertEquals('fr', $driver->context->locale());
    }

    public function test_renderer_without_driver_key()
    {
        config(['template-renderer.renderers.no_driver' => []]);

        $this->expectExceptionMessage("template renderer driver '' is not defined");
        Template::using('no_driver');
    }

    public function test_extend()
    {
        // the configuration is read when the manager is created (first facade call)
        config(['template-renderer.default_renderer' => 'test']);
        Template::extend('test', function ($config) {
            $this->assertEquals(['driver' => 'test'], $config);

            return new TestDriver;
        });

        $rendered = Template::render('test {{ to_replace }} test', []);
        $this->assertEquals('test replaced test', $rendered);
    }

    public function test_renderers_sharing_a_driver()
    {
        config([
            'template-renderer.renderers.first' => ['driver' => 'test', 'option' => 'first'],
            'template-renderer.renderers.second' => ['driver' => 'test', 'option' => 'second'],
        ]);
        $configs = [];
        Template::extend('test', function ($config) use (&$configs) {
            $configs[] = $config;

            return new TestDriver;
        });

        $this->assertNotSame(Template::using('first'), Template::using('second'));
        $this->assertEquals([
            ['driver' => 'test', 'option' => 'first'],
            ['driver' => 'test', 'option' => 'second'],
        ], $configs);
    }

    public function test_undefined_default_renderer()
    {
        $manager = new TemplateManager(['renderers' => ['twig' => ['driver' => 'twig']]]);

        $this->expectExceptionMessage('default template renderer is not defined');
        $manager->render('test', []);
    }

    public function test_undefined_name()
    {
        config(['template-renderer.default_renderer' => 'unknown']);

        $this->expectExceptionMessage("template renderer name 'unknown' is not defined");
        Template::render('test {{ to_replace }} test', []);
    }

    public function test_undefined_driver()
    {
        config(['template-renderer.renderers.twig.driver' => 'unknown']);

        $this->expectExceptionMessage("template renderer driver 'unknown' is not defined");
        Template::render('test {{ to_replace }} test', []);
    }

    public function test_bad_resolver_instance()
    {
        config(['template-renderer.default_renderer' => 'test']);
        Template::extend('test', fn () => new \stdClass);

        $this->expectExceptionMessage("Driver creator 'test' should return instance of ".DriverInterface::class);
        Template::render('test {{ to_replace }} test', []);
    }

    public function test_validation_rule_valid()
    {
        $validated = Validator::validate(
            ['my_template' => 'test {{ user.name }} test'],
            ['my_template' => new RulesTemplate]
        );
        $this->assertEquals('test {{ user.name }} test', $validated['my_template']);
    }

    public function test_validation_rule_invalid_string()
    {
        $this->expectException(ValidationException::class);
        Validator::validate(
            ['my_template' => 'test {{ "true }} test'],
            ['my_template' => new RulesTemplate]
        );
    }

    public function test_validation_rule_invalid_not_string()
    {
        $this->expectException(ValidationException::class);
        Validator::validate(
            ['my_template' => ['array']],
            ['my_template' => new RulesTemplate]
        );
    }
}
