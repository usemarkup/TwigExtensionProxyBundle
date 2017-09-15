<?php
declare(strict_types=1);

namespace Markup\TwigExtensionProxyBundle\DependencyInjection\Compiler;

use Markup\TwigExtensionProxyBundle\Exception\UnproxyableExtensionException;
use Markup\TwigExtensionProxyBundle\ProxyExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Extension\AbstractExtension;

class CompileProxyExtensionsPass implements CompilerPassInterface
{
    private const DEFAULT_GROUP = 'default';

    public function process(ContainerBuilder $container)
    {
        $groupProxies = [];
        $addFunctorsForId = function (string $id, array $groups) use ($container, &$groupProxies) {
            foreach ($groups as $group) {
                if (isset($groupProxies[$group])) {
                    continue;
                }
                if ($container->hasDefinition($this->getServiceIdForGroup($group))) {
                    $groupProxies[$group] = $container->getDefinition($this->getServiceIdForGroup($group));
                } else {
                    $groupProxies[$group] = new Definition(
                        ProxyExtension::class,
                        [new Reference('service_container')]
                    );
                }
            }
            $extensionClass = $this->getExtensionClassForServiceId($id, $container);
            if (null === $extensionClass) {
                throw new UnproxyableExtensionException($id);
            }
            /** @var AbstractExtension $fakeInstance */
            $fakeInstance = (new \ReflectionClass($extensionClass))->newInstanceWithoutConstructor();
            //check there are no token parsers, node visitors or operators defined
            if (count($fakeInstance->getTokenParsers()) > 0
                || count($fakeInstance->getNodeVisitors()) > 0
                || count($fakeInstance->getOperators()) > 0
            ) {
                throw new UnproxyableExtensionException($id);
            }
            //check that no functions, filters or tests are defined with objects/resources within their options
            if (!$this->checkExtensionFunctorOptions($fakeInstance)) {
                throw new UnproxyableExtensionException($id);
            }
            //gather functions
            foreach ($fakeInstance->getFunctions() as $function) {
                $options = $this->getOptionsForTwigFunctor($function);
                foreach ($groups as $group) {
                    $groupProxies[$group]->addMethodCall(
                        'addFunction',
                        [$function->getName(), $id, $options]
                    );
                }
            }
            //gather filters
            foreach ($fakeInstance->getFilters() as $filter) {
                $options = $this->getOptionsForTwigFunctor($filter);
                foreach ($groups as $group) {
                    $groupProxies[$group]->addMethodCall(
                        'addFilter',
                        [$filter->getName(), $id, $options]
                    );
                }
            }
            //gather tests
            foreach ($fakeInstance->getTests() as $test) {
                $options = $this->getOptionsForTwigFunctor($test);
                foreach ($groups as $group) {
                    $groupProxies[$group]->addMethodCall(
                        'addTest',
                        [$test->getName(), $id, $options]
                    );
                }
            }
        };
        foreach ($container->findTaggedServiceIds('twig.proxied_extension') as $id => $tags) {
            foreach ($tags as $attributes) {
                $groups = (isset($attributes['groups']))
                    ? preg_split('/,\s*/', $attributes['groups'])
                    : [self::DEFAULT_GROUP];
                $addFunctorsForId($id, $groups);
            }
        }
        //optionally proxify applicable extensions if marked with twig.extension tag
        if ($container->getParameter('markup_twig_extension_proxy.should_proxify_tagged_extensions')) {
            $idsForExtensionTagRemoval = [];
            foreach ($container->findTaggedServiceIds('twig.extension') as $id => $tags) {
                if ($id === 'markup_twig_extension_proxy.proxy.default') {
                    continue;
                }
                try {
                    $addFunctorsForId($id, [self::DEFAULT_GROUP]);
                } catch (UnproxyableExtensionException $e) {
                    //it's OK - just don't tag this one for removal
                    continue;
                }
                $idsForExtensionTagRemoval[] = $id;
            }
            foreach ($idsForExtensionTagRemoval as $idForRemoval) {
                $extension = $container->getDefinition($idForRemoval);
                $extension->clearTag('twig.extension');
                $extension->setPublic(true);
            }
        }
        foreach ($groupProxies as $group => $proxy) {
            if ($container->hasDefinition($this->getServiceIdForGroup($group))) {
                continue;
            }
            $container->setDefinition($this->getServiceIdForGroup($group), $proxy);
        }
    }

    private function getServiceIdForGroup(string $group): string
    {
        return 'markup_twig_extension_proxy.proxy.'.$group;
    }

    private function getExtensionClassForServiceId(string $serviceId, ContainerBuilder $container): ?string
    {
        $classReference = $container->getDefinition($serviceId)->getClass();
        if (null === $classReference) {
            return null;
        }
        if (strpos($classReference, '%') === 0) {
            return $container->getParameter(trim($classReference, '%'));
        }

        return $classReference;
    }

    private function checkExtensionFunctorOptions(AbstractExtension $extension): bool
    {
        $functors = array_merge($extension->getFunctions(), $extension->getFilters(), $extension->getTests());
        foreach ($functors as $functor) {
            $functorResult = $this->checkOptionsContainNoObjectOrReference($this->getOptionsForTwigFunctor($functor));
            if (!$functorResult) {
                return false;
            }
        }

        return true;
    }

    private function getOptionsForTwigFunctor($functor): array
    {
        $optionsProperty = (new \ReflectionObject($functor))->getProperty('options');
        $optionsProperty->setAccessible(true);

        return $optionsProperty->getValue($functor);
    }

    private function checkOptionsContainNoObjectOrReference(array $options): bool
    {
        foreach ($options as $option) {
            if (is_array($option)) {
                $arrayResult = $this->checkOptionsContainNoObjectOrReference($option);
                if (!$arrayResult) {
                    return false;
                }
            } elseif (is_resource($option) || is_object($option)) {
                return false;
            }
        }

        return true;
    }
}
