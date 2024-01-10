# latte-template-renderer
[Interface](https://github.com/akawalko/template-renderer-interface) implementation for a class that renders templates 
for string and [PSR-7](https://www.php-fig.org/psr/psr-7/) response objects using [Latte](https://github.com/nette/latte) engine - a Next-Generation 
Templating System for PHP.

## Installation
You can download this library using [Composer](https://getcomposer.org/):

Require
php: >=8.0

```
composer require akawalko/latte-template-renderer
```

## Setup
Example integration using the [PHP-DI](https://php-di.org/) container.

```php
// app/dependencies.php
return function (ContainerBuilder $containerBuilder) {
        LatteRenderer::class => function (ContainerInterface $c) {
            $latte = new \Latte\Engine();
            $latte->setLoader(new FileLoader($c->get('templates.directory.host')));
            $latte->setTempDirectory($c->get('templates.directory.temp_directory'));
            $latte->setAutoRefresh($c->get('templates.directory.auto_refresh'));

            // If you have any additional filters or extensions, register them now.
            //$latte->addExtension(new SomekindExtension());
            //$latte->addFilter('some_filter', fn(string $s) => ...);

            return new LatteTemplateRenderer($latte);
        },
        // A convenient alias
        'template' => get(TemplateRenderer::class),
};
```

## Methods

### __get(string $name);
Get template variable. Return mixed value.

### __set(string $name, $value): void;
Set template variable.

### __isset(string $name): bool;
Determine if a variable is declared and is different than null.

### __unset(string $name): void;
Unset a given variable

### getVar(string $name);
Get template variable. Return mixed value.

### setVar(string $name, $value): self;
Set template variable.

### getVars(): array;
Get template variables.

### setVars($data = []): self;
Set template variables from associative arrays or object.
When an object is given, this method will work in a way similar to the spread operator known from handling arrays.
That is, each object property will be assigned to the template as a standalone, e.g.
Let's consider this example,

```php
$obj = new \stdClass();
$obj->firstname = 'John';
$obj->lastname = 'Connor';
$obj->age = '34';

$latteRenderer->setVars($obj);
```
In the template, however, the data will be available without using a prefix in the form of an object
```latte
// this
{$firstname}<br>
{$lastname}<br>
{$age}<br>
// instead of
{$someObjectname->firstname}<br>
{$someObjectname->$lastname}<br>
{$someObjectname->$age}<br>
```
For this to work, the given object must meet 1 of 3 conditions:
- implement JsonSerializable interface
- implement toArray() method
- simply be an object of the stdClass class

However, there is a caveat when your class implements the JsonSerializable interface.
Namely, when the jsonSerialize() method returns a scalar instead of an array, 
its value will be assigned to a key called **object_single_var**. 
Subsequent assignments will overwrite this value. 
At this moment it is not possible to set a key name for the returned scalar.
This may change in the future depending on how useful this feature will be.

If you are not sure what data the objects will return after executing the jsonSerialize method, 
it will be better to assign them using **setVar(string $name, $value)** method.


### renderToString(string $templatePath, $data = []): string;
Render the template to string with given data.

### renderToResponse(ResponseInterface $response, string $templatePath, $data = []): ResponseInterface;
Render the template to PSR compliant Response class with given data.

### render(...$arguments);
A shortcut method that executes renderToString() or renderToResponse() based on the arguments passed.
Return ResponseInterface or string.
