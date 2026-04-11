<?php
namespace obray\core\http;

use JsonSerializable;
use obray\core\exceptions\HTTPException;
use Psr\Http\Message\ServerRequestInterface;

class ServerRequest extends Request implements ServerRequestInterface, JsonSerializable
{
    private array $cookies;
    private array $server;
    private array $params;
    private array $uploadedFiles = [];
    private $parsedBody = null;
    private array $attributes = [];
    private string $rawBody = '';

    public function __construct($path = '', $params = [])
    {
        $method = Method::GET;
        $uri = $path;
        $forceHttp = defined('OBRAY_FORCE_HTTP_REQUEST') && OBRAY_FORCE_HTTP_REQUEST;
        if((PHP_SAPI === 'cli' || !empty($_SERVER['argv'])) && !$forceHttp){
            if(empty($_SERVER['argv'][1])) throw new \Exception("Console dedected, but no path specified.");
            $uri = $_SERVER['argv'][1];
            if(!empty($path)) $uri = $path;
            $method = 'CONSOLE';
            $this->params = $params;
            $components = parse_url($uri);
            if(!empty($components['query'])) parse_str($components['query'], $this->params);
            $this->cookies = [];
        } else if(!empty($_REQUEST['TENANT'])){
            if(empty($path)){
                $uri = $_REQUEST['PATH'];
            } else {
                $uri = $path;
            }
            if(!empty($path)) $uri = $path;
            $method = 'CONSOLE';
            $this->params = $params;
            $components = parse_url($uri);
            if(!empty($components['query'])) parse_str($components['query'], $this->params);
            $this->cookies = [];
        } else {
            if(empty($path)){
                $uri = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER["REQUEST_URI"];
            } else {
                $uri = $path;
            }
            
            $method = $_SERVER['REQUEST_METHOD'] ?? Method::GET;
            if(empty($params)){
                $this->params = array_merge($_GET ?? [], $_POST ?? []);
            } else {
                $this->params = $params;
            }

            $this->rawBody = $this->readRawBody();
            $parsedBody = $this->parseRequestBody($method, $this->getIncomingContentType(), $this->rawBody);
            if ($parsedBody !== null) {
                $this->parsedBody = $parsedBody;
                if (empty($params) && is_array($parsedBody)) {
                    $this->params = array_merge($this->params, $parsedBody);
                }
            }

            $this->cookies = $_COOKIE ?? [];
        }
        $headers = $this->getServerHeaders();
        $this->server = $_SERVER ?? [];
        parent::__construct($method, $uri, $headers, $this->rawBody !== '' ? new Body($this->rawBody) : null);
    }

    protected function readRawBody(): string
    {
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            return '';
        }
        return $rawBody;
    }

    private function getIncomingContentType(): string
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        return strtolower(trim((string)$contentType));
    }

    private function parseRequestBody(string $method, string $contentType, string $rawBody)
    {
        $method = strtoupper($method);
        if (in_array($method, [Method::GET, Method::HEAD, Method::OPTIONS, Method::TRACE, Method::CONSOLE], true)) {
            return null;
        }

        if ($contentType !== '' && strpos($contentType, 'application/json') !== false) {
            if (trim($rawBody) === '') {
                return null;
            }
            $decoded = json_decode($rawBody, true);
            return is_array($decoded) ? $decoded : null;
        }

        if ($contentType !== '' && strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            if ($rawBody === '') {
                return !empty($_POST) ? $_POST : null;
            }
            $parsed = [];
            parse_str($rawBody, $parsed);
            return $parsed;
        }

        if ($contentType !== '' && strpos($contentType, 'multipart/form-data') !== false) {
            return !empty($_POST) ? $_POST : null;
        }

        if (!empty($_POST)) {
            return $_POST;
        }

        return null;
    }

    private function getServerHeaders()
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        } else {
            if (!is_array($_SERVER)) {
                return array();
            }
            $headers = array();
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $key = str_replace(' ', '-', strtolower(
                        str_replace('_', ' ', substr($name, 5))
                    ));
                    $headers[$key] = $value;
                }
            }
        }
        return $headers;
    }

    public function getServerParams()
    {
        return $this->server;
    }

    public function getCookieParams()
    {
        return $this->cookies;
    }

    public function withCookieParams(array $cookies)
    {
        $nsr = clone $this;
        $nsr->cookies = $cookies;
        return $nsr;
    }

    public function getQueryParams()
    {
        return $this->params;
    }

    public function withQueryParams(array $query)
    {
        $nsr = clone $this;
        $nsr->params = $query;
        return $nsr;
    }

    public function getUploadedFiles()
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $nsr = clone $this;
        $nsr->uploadedFiles = $uploadedFiles;
        return $nsr;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data)
    {
        if (!is_array($data) && !is_object($data) && $data !== null) {
            throw new \InvalidArgumentException('Parsed body must be array, object, or null.');
        }
        $nsr = clone $this;
        $nsr->parsedBody = $data;
        return $nsr;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute($name, $value)
    {
        $nsr = clone $this;
        $nsr->attributes[$name] = $value;
        return $nsr;
    }

    public function withoutAttribute($name)
    {
        $nsr = clone $this;
        unset($nsr->attributes[$name]);
        return $nsr;
    }

    public function jsonSerialize(): mixed
    {
        $obj = new \stdClass();
        $obj->method = $this->getMethod();
        $obj->version = $this->getProtocolVersion();
        $obj->uri = (string)$this->getUri();
        $obj->headers = $this->getHeaders();
        // attach cookie
        $cookie = $this->getCookieParams();
        if(!empty($cookie)) $obj->cookie = $cookie;
        // attach params
        $params = $this->getQueryParams();
        if(!empty($params)) $obj->params = $params;
        // attach body
        $body = $this->getBody();
        if(!empty($body)) $obj->body = (string)$body;
        return $obj;
    }

    public static function createRequest(string $path = '', array $params = [])
    {
        try {
            $forceHttp = defined('OBRAY_FORCE_HTTP_REQUEST') && OBRAY_FORCE_HTTP_REQUEST;
            if((empty($_SERVER['REQUEST_METHOD']) || !empty($_REQUEST['TENANT'])) && (PHP_SAPI === 'cli' || !empty($_SERVER['argv']) || !empty($_REQUEST['TENANT'])) && !$forceHttp ){
                $method = 'CONSOLE';
            } else {
                $method = $_SERVER['REQUEST_METHOD'];
            }
            $requestType = '\\obray\\core\\http\\requests\\' . strtoupper($method) . 'Request';     
            return new $requestType($path, $params);
        } catch (\Exception $e){
            throw new HTTPException(StatusCode::REASONS[StatusCode::METHOD_NOT_ALLOWED], StatusCode::METHOD_NOT_ALLOWED);
        }
    }
}
