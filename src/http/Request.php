<?php
namespace obray\core\http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request extends Message implements RequestInterface
{
    private string $target = '';
    private string $method = Method::GET;
    private URI $uri;

    public function __construct($method = Method::GET, $uri = '/', $headers = [], ?StreamInterface $body = null, $version = '1.1')
    {
        $this->uri = $uri instanceof URI ? $uri : new URI((string)$uri);
        $this->method = (string)$method;
        parent::__construct($headers, $body, $version);
    }

    public function getRequestTarget()
    {
        if ($this->target !== '') {
            return $this->target;
        }
        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }
        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }
        return $target;
    }

    public function withRequestTarget($requestTarget)
    {
        if (preg_match('/\s/', $requestTarget)) {
            throw new \InvalidArgumentException('Request target cannot contain whitespace.');
        }
        $nr = clone $this;
        $nr->target = $requestTarget;
        return $nr;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function withMethod($method)
    {
        $nr = clone $this;
        $nr->method = (string)$method;
        return $nr;
    }

    public function getUri()
    {
        return $this->uri;
    }
    
    public function getExplodedPath()
    {
        return $this->uri->getExplodedPath();
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $nr = clone $this;
        $nr->uri = $uri;

        if (!$preserveHost || !$this->hasHeader('Host')) {
            $host = $uri->getHost();
            if ($host === '') {
                $nr = $nr->withoutHeader('Host');
            } else {
                if ($uri->getPort() !== null) {
                    $host .= ':' . $uri->getPort();
                }
                $nr = $nr->withHeader('Host', $host);
            }
        }

        return $nr;
    }
}
