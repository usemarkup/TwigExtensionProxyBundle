# TwigExtensionProxyBundle
A Symfony bundle that can provide lazy access to simpler, more common kinds of registered Twig extensions.

[![Build Status](https://api.travis-ci.org/usemarkup/TwigExtensionProxyBundle.png?branch=master)](http://travis-ci.org/usemarkup/TwigExtensionProxyBundle)

## Why this bundle?

In a typical application using Twig, all extension services need to be instantiated to be added to a Twig environment even if a small minority of extensions will actually be used in any given execution. In a large application, the number of Twig extensions instantiated in this way can become excessive, causing mushrooming memory usage and slowdowns.

For Twig extensions that only declare functions, filters and tests into the Twig environment, this bundle provides a mechanism by which these extensions can be used lazily within a Symfony-based application.

## How to use

Typically, a Twig extension will be declared into an application using a service definition such as the following:

```yaml
my_extension:
    class: MyBundle\MyExtension
    arguments:
        - '@my_heavy_dependency'
    tags:
        - { name: twig.extension }
```

By default, Symfony's TwigBundle will then ensure that `my_extension` will be added to the `twig` environment service.

As long as the `my_extension` extension adheres to the following rules:

1. the extension must only declare functions, filters and tests, and must instantiate them independently of any injected dependencies
2. the options for the functions, filters and tests must themselves not be dependent on injected dependencies

then it can be declared as a proxied extension instead.

The same functional effect as above can be achieved by the following declaration:

```yaml
my_extension:
    class: MyBundle\MyExtension
    arguments:
        - '@my_heavy_dependency'
    tags:
        - { name: twig.proxied_extension }
```

With this example, any functions, filters or tests will still be declared into the `twig` environment, but the extension object itself will only be instantiated (and therefore, `my_heavy_dependency` will itself only be instantiated) once one of these Twig functors are actually _used_.

### Grouped proxies

In cases where it is useful for Twig extensions to be arranged into groups of proxies (in order to sandbox certain extensions to particular areas of an application), extension services can be tagged with the optional "groups" option. The default group is "default", so to declare additional groups on top of this there should be a comma-separated list in the tag declaration.

The following is the above extension service declaration but declaring in addition a group named "custom":

```yaml
my_extension:
    class: MyBundle\MyExtension
    arguments:
        - '@my_heavy_dependency'
    tags:
        - { name: twig.proxied_extension, groups: "default, custom" }
```

This custom proxy extension can then be accessed with the service ID `markup_twig_extension_proxy.proxy.custom`. Typically this would then be added to a manually-declared Twig environment.
