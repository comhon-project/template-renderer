<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Comhon\TemplateRenderer\Facades\Template;
use Comhon\TemplateRenderer\Renderers\RendererInterface;
use Comhon\TemplateRenderer\Rules\Template as RulesTemplate;
use Comhon\TemplateRenderer\Tests\Support\TestTemplaterRender;
use Comhon\TemplateRenderer\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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

    public function test_twig_validate()
    {
        // valid
        Template::validate('test {{ user.name }} test');

        // invalid should throw exception
        $this->expectException(\Exception::class);
        Template::validate('test {{ "true }} test');
    }

    public function test_extend()
    {
        Template::extend('test', function ($app) {
            $this->assertInstanceOf(Application::class, $app);

            return new TestTemplaterRender;
        });
        config(['template-renderer.default_renderer' => 'test']);

        $rendered = Template::render('test {{ to_replace }} test', []);
        $this->assertEquals('test replaced test', $rendered);
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
        Template::extend('test', function ($app) {
            $this->assertInstanceOf(Application::class, $app);

            return new \stdClass;
        });
        config(['template-renderer.default_renderer' => 'test']);

        $this->expectExceptionMessage("Renderer resolver 'test' should return instance of ".RendererInterface::class);
        Template::render('test {{ to_replace }} test', []);
    }

    public function test_validation_rule_validated()
    {
        $validated = Validator::validate(
            ['my_template' => 'test {{ user.name }} test'],
            ['my_template' => new RulesTemplate]
        );
        $this->assertEquals('test {{ user.name }} test', $validated['my_template']);
    }

    public function test_validation_rule_not_validated()
    {
        $this->expectException(ValidationException::class);
        Validator::validate(
            ['my_template' => 'test {{ "true }} test'],
            ['my_template' => new RulesTemplate]
        );
    }
}
