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

    public function __construct($path = '', $params = [])
    {
        $method = Method::GET;
        if(PHP_SAPI === 'cli' || !empty($_SERVER['argv'])){
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
            
            $method = $_SERVER['REQUEST_METHOD'];
            if(empty($params)){
                $this->params = array_merge($_GET, $_POST);
            } else {
                $this->params = $params;
            }
            switch(strtolower($method))
            {
                case 'put': $this->parseRequest($method, $_SERVER["CONTENT_TYPE"]); break;
                case 'delete': $this->parseRequest($method, $_SERVER["CONTENT_TYPE"]); break;
            }
            $this->cookies = $_COOKIE;
        }
        $headers = $this->getServerHeaders();
        $this->server = $_SERVER;
        parent::__construct($method, $uri, $headers);
    }

    private function parseRequest($method, $contentType)
    {
        if(strpos($contentType, 'multipart/form-data')){
            $params = [];
            parse_str(file_get_contents('php://input'), $params);
            $this->params = array_merge($this->params, $params);
        }

        if(strpos($contentType, 'application/x-www-form-urlencoded')){

        }
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
        $this->cooks = array_merge($this->cookies, $cookies);
    }

    public function getQueryParams()
    {
        return $this->params;
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
        if(!empty($body)) $obj->body = $body;
        return $obj;
    }

    public static function createRequest(string $path = '', array $params = [])
    {
        try {
            if((empty($_SERVER['REQUEST_METHOD']) || !empty($_REQUEST['TENANT'])) && (PHP_SAPI === 'cli' || !empty($_SERVER['argv']) || !empty($_REQUEST['TENANT'])) ){
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