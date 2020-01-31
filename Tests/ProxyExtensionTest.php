<?php

namespace Markup\TwigExtensionProxyBundle\Tests;

use Markup\TwigExtensionProxyBundle\ProxyExtension;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

class ProxyExtensionTest extends MockeryTestCase
{
    /**
     * @var ContainerInterface|m\MockInterface
     */
    private $container;

    /**
     * @var ProxyExtension
     */
    private $extension;

    protected function setUp()
    {
        $this->container = m::mock(ContainerInterface::class);
        $this->extension = new ProxyExtension($this->container);
    }

    public function testIsExtension()
    {
        $this->assertInstanceOf(\Twig\Extension\AbstractExtension::class, $this->extension);
    }

    public function testCreateAndUseProxiedFunction()
    {
        $serviceId = 'my_proxied_extension';
        $this->container
            ->shouldReceive('get')
            ->with($serviceId)
            ->andReturn(new TestExtension());
        $this->extension->addFunction('test_function', $serviceId, ['is_safe' => ['html']]);
        $twig = new Environment(new ArrayLoader());
        $twig->addExtension($this->extension);
        $template = $twig->createTemplate('this is {{ test_function("bana", "nas") }}');
        $this->assertEquals('this is bananas', $template->render([]));
    }

    public function testCreateAndUseProxiedFilter()
    {
        $serviceId = 'my_proxied_extension';
        $this->container
            ->shouldReceive('get')
            ->with($serviceId)
            ->andReturn(new TestExtension());
        $this->extension->addFilter('test_filter', $serviceId, ['is_safe' => ['html']]);
        $twig = new Environment(new ArrayLoader());
        $twig->addExtension($this->extension);
        $template = $twig->createTemplate('this is {{ 42|test_filter(3) }}');
        $this->assertEquals('this is 126', $template->render([]));
    }

    public function testCreateAndUseProxiedTest()
    {
        $serviceId = 'my_proxied_extension';
        $this->container
            ->shouldReceive('get')
            ->with($serviceId)
            ->andReturn(new TestExtension());
        $this->extension->addTest('numberwang', $serviceId, ['is_safe' => ['html']]);
        $twig = new Environment(new ArrayLoader());
        $twig->addExtension($this->extension);
        $template = $twig->createTemplate('that\'s {{ 42 is numberwang ? "numberwang" : "not numberwang" }}');
        $this->assertEquals('that\'s numberwang', $template->render([]));
    }
}

class TestExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('test_function', function (string $a, string $b) {
                return $a.$b;
            }, ['is_safe' => ['html']]),
        ];
    }

    public function getFilters()
    {
        return [
            new TwigFilter('test_filter', function ($input, int $multiplier) {
                return $input*$multiplier;
            }, ['is_safe' => ['html']]),
        ];
    }

    public function getTests()
    {
        return [
            new TwigTest('numberwang', function ($input) {
                return $input === 42;
            }, ['is_safe' => ['html']]),
        ];
    }
}
