<?php

namespace Markup\TwigExtensionProxyBundle\DependencyInjection\Compiler;

use Markup\TwigExtensionProxyBundle\ProxyExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Extension\AbstractExtension;

class CompileProxyExtensionsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $groupProxies = [];
        foreach ($container->findTaggedServiceIds('twig.proxied_extension') as $id => $tags) {
            foreach ($tags as $attributes) {
                $groups = (isset($attributes['groups']))
                    ? preg_split('/,\s*/', $attributes['groups'])
                    : ['default'];
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
                $extensionClass = $container->getDefinition($id)->getClass();
                /** @var AbstractExtension $fakeInstance */
                $fakeInstance = (new \ReflectionClass($extensionClass))->newInstanceWithoutConstructor();
                //check there are no token parsers, node visitors or operators defined
                if (count($fakeInstance->getTokenParsers()) > 0
                    || count($fakeInstance->getNodeVisitors()) > 0
                    || count($fakeInstance->getOperators()) > 0
                ) {
                    throw new \InvalidArgumentException(sprintf(
                        'The Twig extension service "%s" should declare only functions, filters'
                        .' and tests in order to allow proxying.',
                        $id
                    ));
                }
                //gather functions
                foreach ($fakeInstance->getFunctions() as $function) {
                    $optionsProperty = (new \ReflectionObject($function))->getProperty('options');
                    $optionsProperty->setAccessible(true);
                    $options = $optionsProperty->getValue($function);
                    foreach ($groups as $group) {
                        $groupProxies[$group]->addMethodCall(
                            'addFunction',
                            [$function->getName(), $id, $options]
                        );
                    }
                }
                //gather filters
                foreach ($fakeInstance->getFilters() as $filter) {
                    $optionsProperty = (new \ReflectionObject($filter))->getProperty('options');
                    $optionsProperty->setAccessible(true);
                    $options = $optionsProperty->getValue($filter);
                    foreach ($groups as $group) {
                        $groupProxies[$group]->addMethodCall(
                            'addFilter',
                            [$filter->getName(), $id, $options]
                        );
                    }
                }
                //gather tests
                foreach ($fakeInstance->getTests() as $test) {
                    $optionsProperty = (new \ReflectionObject($test))->getProperty('options');
                    $optionsProperty->setAccessible(true);
                    $options = $optionsProperty->getValue($test);
                    foreach ($groups as $group) {
                        $groupProxies[$group]->addMethodCall(
                            'addTest',
                            [$test->getName(), $id, $options]
                        );
                    }
                }
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
}
