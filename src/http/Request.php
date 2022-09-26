<?php
namespace obray\core\http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request extends Message implements RequestInterface
{
    private string $target = '/';
    private string $method = Method::GET;
    private URI $uri;

    public function __construct($method= Method::GET, $uri='/', $headers=[], StreamInterface $body=null, $version='1.1')
    {
        $this->uri = new URI($uri);
        $this->method = $method;
        parent::__construct($headers, $body, $version);
    }

    public function getRequestTarget()
    {
        return $this->target;
    }

    public function withRequestTarget($requestTarget)
    {
        $this->target = $requestTarget;
        return $this;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function withMethod($method)
    {
        $this->method = $method;
        return $this;
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
        $this->uri = $uri;
        return $this;
    }
}