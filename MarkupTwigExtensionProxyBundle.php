<?php

namespace Markup\TwigExtensionProxyBundle;

use Markup\TwigExtensionProxyBundle\DependencyInjection\Compiler\CompileProxyExtensionsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MarkupTwigExtensionProxyBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new CompileProxyExtensionsPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);

        parent::build($container);
    }
}
