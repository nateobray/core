<?php
namespace obray\core\http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class ServerRequest implements ServerRequestInterface
{

    private string $target = '/';
    private string $method = Methods::GET;
    private UriInterface $uri;

    public function getProtocolVersion()
    {

    }

    public function withProtocolVersion($version)
    {

    }

    public function getHeaders()
    {

    }

    public function hasHeader($name)
    {

    }

    public function getHeader($name)
    {

    }

    public function getHeaderLine($name)
    {

    }

    public function withHeader($name, $value)
    {

    }

    public function withAddedHeader($name, $value)
    {

    }

    public function withoutHeader($name)
    {

    }

    public function getBody()
    {

    }

    public function withBody(StreamInterface $body)
    {
        
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

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $this->uri = $uri;
        return $this;
    }

    public function getServerParams()
    {

    }

    public function getCookieParams()
    {

    }

    public function withCookieParams(array $cookies)
    {

    }

    public function getQueryParams()
    {

    }

    public function withQueryParams(array $query)
    {

    }

    public function getUploadedFiles()
    {

    }

    public function withUploadedFiles(array $uploadedFiles)
    {

    }

    public function getParsedBody()
    {

    }

    public function withParsedBody($data)
    {

    }

    public function getAttributes()
    {

    }

    public function getAttribute($name, $default = null)
    {

    }

    public function withAttribute($name, $value)
    {

    }

    public function withoutAttribute($name)
    {

    }
}