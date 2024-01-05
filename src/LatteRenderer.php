<?php

declare(strict_types=1);

namespace Akawalko\LatteTemplateRenderer;

use Akawalko\TemplateRendererInterface\TemplateRenderer;
use InvalidArgumentException;
use JsonSerializable;
use Latte\Engine;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class LatteRenderer implements TemplateRenderer
{
    public const TEMPLATE_EXTENSION = '.latte';

    /** @var Engine */
    protected Engine $latte;
    protected array $data = [];

    public function __construct(Engine $latte)
    {
        $this->latte = $latte;
    }

    /** @inheritDoc */
    public function __get(string $name)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined variable via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }

    /** @inheritDoc */
    public function __set(string $name, $value): void
    {
        $this->data[$name] = $value;
    }

    /** @inheritDoc */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /** @inheritDoc */
    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    /** @inheritDoc */
    public function getVar(string $name)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined variable via getVar(' . $name . ')' .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;
    }

    /** @inheritDoc */
    public function setVar(string $name, $value): TemplateRenderer
    {
        $this->data[$name] = $value;
        return $this;
    }

    /** @inheritDoc */
    public function getVars(): array
    {
        return $this->data;
    }

    /** @inheritDoc */
    public function setVars($data = []): TemplateRenderer
    {
        if (is_array($data)) {
            $this->data = array_merge($this->data, $data);
        } elseif (is_object($data)) {
            if ($data instanceof JsonSerializable) {
                $serializable = $data->jsonSerialize();
                if (!is_array($serializable)) {
                    $vars['object_single_var'] = $serializable;
                } else {
                    $vars = $serializable;
                }
            } elseif ($data instanceof stdClass) {
                $vars = (array) $data;
            } elseif (method_exists($data, 'toArray')) {
                $vars = $data->toArray();
                if (!is_array($vars)) {
                    throw new InvalidArgumentException(sprintf(
                        'setVars() method expects the passed object->toArray() to return an array.'
                    ));
                }
            } else {
                throw new InvalidArgumentException(
                    'setVars() method expects the passed object to implement JsonSerializable interface,'
                    . ' custom toArray() method or simply was an object of class stdClass.'
                );
            }

            $this->data = array_merge($this->data, $vars);
        } else {
            throw new InvalidArgumentException(sprintf(
                'setVars() method expects array or object. %s was provided.',
                gettype($data)
            ));
        }

        return $this;
    }

    /** @inheritDoc */
    public function renderToString(string $templatePath, $data = []): string
    {
        $this->setVars($data);
        return $this->latte->renderToString($this->appendExtensionIfNeeded($templatePath), $this->data);
    }

    /** @inheritDoc */
    public function renderToResponse(ResponseInterface $response, string $templatePath, $data = []): ResponseInterface
    {
        $response->getBody()->write($this->renderToString($templatePath, $data));

        return $response;
    }

    /** @inheritDoc */
    public function render(...$arguments)
    {
        $count = count($arguments);

        if ($count >= 2 && $arguments[0] instanceof ResponseInterface) {
            return $this->renderToResponse(...$arguments);
        } elseif ($count > 0) {
            return $this->renderToString(...$arguments);
        } else {
            throw new InvalidArgumentException(
                'render() method expects at least 1 argument. 0 arguments provided.'
            );
        }
    }

    protected function appendExtensionIfNeeded($templatePath): string
    {
        $extension = pathinfo($templatePath, PATHINFO_EXTENSION);

        if (empty($extension)) {
            $templatePath .= self::TEMPLATE_EXTENSION;
        }

        return $templatePath;
    }
}
