<?php

namespace Akawalko\LatteTemplateRenderer\Tests;

use Akawalko\LatteTemplateRenderer\LatteRenderer;
use InvalidArgumentException;
use Latte\Engine;
use Latte\Loaders\FileLoader;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use ReflectionMethod;
use stdClass;

class LatteRendererTest extends TestCase
{
    protected LatteRenderer $renderer;

    protected function setUp(): void
    {
        $latte = new Engine();
        $latte->setLoader(new FileLoader(__DIR__ . '/templates'));
        $latte->setTempDirectory(__DIR__ . '/../var/templates/');
        $latte->setAutoRefresh(true);
        $this->renderer = new LatteRenderer($latte);
    }

    /** @test */
    public function magic_getter_returns_value_previously_set_via_magic_setter(): void
    {
        $this->renderer->message = 'Hello world!';
        $this->assertEquals('Hello world!', $this->renderer->message);
    }

    /** @test */
    public function magic_getter_returns_null_when_trying_to_reference_a_non_existent_variable(): void
    {
        $this->assertEquals(null, $this->renderer->message);
    }

    /** @test */
    public function magic_isset_confirms_whether_or_not_a_previously_set_variable_exists(): void
    {
        $this->renderer->message = 'Hello world!';

        $this->assertEquals(true, isset($this->renderer->message));
        $this->assertEquals(false, isset($this->renderer->userData));
    }

    /** @test */
    public function magic_unset_destroys_variable(): void
    {
        $this->renderer->message = 'Hello world!';

        unset($this->renderer->message);
        $this->assertEquals(false, isset($this->renderer->userData));
    }

    /** @test */
    public function getter_getvar_returns_value_previously_set__via_setter_setvar(): void
    {
        $this->renderer->setVar('message', 'Hello world!');

        $this->assertEquals('Hello world!', $this->renderer->getVar('message'));
    }

    /** @test */
    public function getter_getvars_returns_array_of_values_previously_set_via_setter_setvars(): void
    {
        $inputData = [
            'message' => 'Hello world!',
            'luckyNumbers' => range(1, 10, 2),
        ];
        $this->renderer->setVars($inputData);

        $this->assertEquals($inputData, $this->renderer->getVars());
    }

    /** @test */
    public function setter_setvars_assign_data_from_object_that_implements_json_serializable_and_returns_array(): void
    {
        $inputData = new class('Hello world!', range(1, 10, 2)) implements \JsonSerializable {
            protected string $message;
            protected array $luckyNumbers;

            public function __construct(string $message, array $luckyNumbers)
            {
                $this->message = $message;
                $this->luckyNumbers = $luckyNumbers;
            }

            public function jsonSerialize(): array
            {
                return get_object_vars($this);
            }
        };
        $this->renderer->setVars($inputData);

        $this->assertEquals(
            [
                'message' => 'Hello world!',
                'luckyNumbers' => range(1, 10, 2),
            ],
            $this->renderer->getVars()
        );
    }

    /** @test */
    public function setter_setvars_assign_data_from_object_that_implements_json_serializable_and_returns_scalar(): void
    {
        $inputData = new class('Hello world!') implements \JsonSerializable {
            protected string $message;

            public function __construct(string $message)
            {
                $this->message = $message;
            }

            public function jsonSerialize(): string
            {
                return $this->message;
            }
        };
        $this->renderer->setVars($inputData);
        // When you assign object that return scalar from jsonSerialize(), TemplateRenderer implementation will
        // assign its value to default key named: object_single_var
        $this->assertEquals(
            [
                'object_single_var' => 'Hello world!',
            ],
            $this->renderer->getVars()
        );
    }

    /** @test */
    public function setter_setvars_assign_data_from_object_that_has_to_array_method(): void
    {
        $inputData = new class('Hello world!', range(1, 10, 2)) {
            protected string $message;
            protected array $luckyNumbers;

            public function __construct(string $message, array $luckyNumbers)
            {
                $this->message = $message;
                $this->luckyNumbers = $luckyNumbers;
            }

            public function toArray(): array
            {
                return get_object_vars($this);
            }
        };
        $this->renderer->setVars($inputData);

        $this->assertEquals(
            [
                'message' => 'Hello world!',
                'luckyNumbers' => range(1, 10, 2),
            ],
            $this->renderer->getVars()
        );
    }

    /** @test */
    public function setter_setvars_assign_data_from_std_object(): void
    {
        $inputData = new stdClass();
        $inputData->message = 'Hello world!';
        $inputData->luckyNumbers = range(1, 10, 2);
        $this->renderer->setVars($inputData);

        $this->assertEquals(
            [
                'message' => 'Hello world!',
                'luckyNumbers' => range(1, 10, 2),
            ],
            $this->renderer->getVars()
        );
    }

    /** @test */
    public function setter_setvars_throws_exception_when_an_incompatible_object_was_passed(): void
    {
        $inputData = new class() {};
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('setVars() method expects the passed object to implement'
            . ' JsonSerializable interface, custom toArray() method or simply was an object of class stdClass.');

        $this->renderer->setVars($inputData);
    }

    /** @test */
    public function rendertostring_returns_rendered_template(): void
    {
        $html = $this->renderer->renderToString(
            'test_template_with_multiple_vars',
            [
                'message' => 'Hello world!',
                'luckyNumbers' => range(1, 10, 2),
            ]
        );

        $this->assertEquals($this->getExpectedHtmlOutput(), $html);
    }

    /** @test */
    public function rendertoresponse_returns_response_with_body_filled_with_rendered_template(): void
    {
        $responseFactory = $this->getResponseFactory();
        $response = $this->renderer->renderToResponse(
            $responseFactory->createResponse(),
            'test_template_with_multiple_vars',
            [
                'message' => 'Hello world!',
                'luckyNumbers' => range(1, 10, 2),
            ]
        );

        $this->assertEquals($this->getExpectedHtmlOutput(), $response->getBody());
    }

    /** @test */
    public function render_behaves_like_rendertostring_when_you_pass_in_the_arguments_it_requires(): void
    {
        $html = $this->renderer->render(
            'test_template_with_multiple_vars',
            [
                'message' => 'Hello world!',
                'luckyNumbers' => range(1, 10, 2),
            ]
        );

        $this->assertEquals($this->getExpectedHtmlOutput(), $html);
    }

    /** @test */
    public function render_behaves_like_rendertoresponse_when_you_pass_in_the_arguments_it_requires(): void
    {
        $responseFactory = $this->getResponseFactory();
        $response = $this->renderer->render(
            $responseFactory->createResponse(),
            'test_template_with_multiple_vars',
            [
                'message' => 'Hello world!',
                'luckyNumbers' => range(1, 10, 2),
            ]
        );

        $this->assertEquals($this->getExpectedHtmlOutput(), $response->getBody());
    }

    /** @test */
    public function render_throws_exception_when_no_argument_was_passed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('render() method expects at least 1 argument. 0 arguments provided.');
        $this->renderer->render();
    }

    /** @test */
    public function file_extension_will_be_added_if_not_provided(): void
    {
        $method = new ReflectionMethod($this->renderer, 'appendExtensionIfNeeded');
        $method->setAccessible(true);

        $fileNameWithoutExtension = 'test_template_with_multiple_vars';

        $this->assertEquals(
            $fileNameWithoutExtension . LatteRenderer::TEMPLATE_EXTENSION,
            $method->invoke($this->renderer, $fileNameWithoutExtension)
        );
    }

    protected function getExpectedHtmlOutput(): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
            </head>
            <body>
                <span class="message">Hello world!</span>
            
                <span>Today lucky numbers are:</span>
                <ul>
                    <li>1</li>
                    <li>3</li>
                    <li>5</li>
                    <li>7</li>
                    <li>9</li>
                </ul>
            </body>
            </html>

            HTML;
    }

    protected function getResponseFactory(): ResponseFactoryInterface
    {
        return new Psr17Factory();
    }
}
