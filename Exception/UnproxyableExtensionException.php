<?php

namespace Markup\TwigExtensionProxyBundle\Exception;

/**
 * Exception pertaining to when an extension has been expected to be proxyable but is not.
 */
class UnproxyableExtensionException extends \InvalidArgumentException
{
    public function __construct($id, $message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct(
            $message ?: sprintf('The Twig extension service "%s" should declare only functions, filters'
                .' and tests in order to allow proxying.', $id),
            $code,
            $previous
        );
    }
}
