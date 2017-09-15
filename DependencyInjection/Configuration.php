<?php

namespace Markup\TwigExtensionProxyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('markup_twig_extension_proxy');

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('proxify_tagged_extensions')
                    ->info('Option for proxifying any applicable extensions marked with twig.extension tag.')
                    ->defaultFalse()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
