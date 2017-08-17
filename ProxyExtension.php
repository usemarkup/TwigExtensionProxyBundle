<?php
declare(strict_types=1);

namespace Markup\TwigExtensionProxyBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

class ProxyExtension extends AbstractExtension
{
    /**
     * @var array
     */
    private $functionReferences;

    /**
     * @var array
     */
    private $functionOptions;

    /**
     * @var array
     */
    private $filterReferences;

    /**
     * @var array
     */
    private $filterOptions;

    /**
     * @var array
     */
    private $testReferences;

    /**
     * @var array
     */
    private $testOptions;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->functionReferences = [];
        $this->functionOptions = [];
        $this->filterReferences = [];
        $this->filterOptions = [];
        $this->testReferences = [];
        $this->testOptions = [];
    }

    public function getFunctions()
    {
        return $this->emitFunctors(
            $this->functionReferences,
            $this->functionOptions,
            'getFunctions',
            TwigFunction::class
        );
    }

    public function getFilters()
    {
        return $this->emitFunctors(
            $this->filterReferences,
            $this->filterOptions,
            'getFilters',
            TwigFilter::class
        );
    }

    public function getTests()
    {
        return $this->emitFunctors(
            $this->testReferences,
            $this->testOptions,
            'getTests',
            TwigTest::class
        );
    }

    public function addFunction(string $functionName, string $serviceId, array $options): self
    {
        $this->functionReferences[$functionName] = $serviceId;
        $this->functionOptions[$functionName] = $options;

        return $this;
    }

    public function addFilter(string $filterName, string $serviceId, array $options): self
    {
        $this->filterReferences[$filterName] = $serviceId;
        $this->filterOptions[$filterName] = $options;

        return $this;
    }

    public function addTest(string $testName, string $serviceId, array $options): self
    {
        $this->testReferences[$testName] = $serviceId;
        $this->testOptions[$testName] = $options;

        return $this;
    }

    private function emitFunctors(
        array $references,
        array $optionsCollection,
        string $extensionMethod,
        string $functorClass
    ): array {
        return array_map(
            function (string $functorName) use ($references, $extensionMethod, $optionsCollection, $functorClass) {
                return new $functorClass(
                    $functorName,
                    function (...$args) use ($functorName, $references, $extensionMethod) {
                        $extension = $this->container->get($references[$functorName]);
                        $target = null;
                        foreach ($extension->$extensionMethod() as $functor) {
                            if ($functor->getName() !== $functorName) {
                                continue;
                            }
                            $target = $functor;
                            break;
                        }
                        return \Closure::fromCallable($target->getCallable() ?: function () {
                            return '';
                        })(...$args);
                    },
                    $optionsCollection[$functorName]
                );
            },
            array_keys($references)
        );
    }
}
