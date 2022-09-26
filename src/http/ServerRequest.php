<?php
namespace obray\core\http;

use Psr\Http\Message\ServerRequestInterface;

class ServerRequest extends Request implements ServerRequestInterface
{
    private array $cookies;
    private array $server;
    private array $params;

    public function __construct($path = '')
    {
        $method = Method::GET;
        if(PHP_SAPI === 'cli' || !empty($_SERVER['argv'])){
            if(empty($_SERVER['argv'][1])) throw new \Exception("Console dedected, but not path specified.");
            $uri = $_SERVER['argv'][1];
            $method = 'CONSOLE';
            $this->params = [];
            print_r($uri . "\n");
            $components = parse_url($uri);
            if(!empty($components['query'])) parse_str($components['query'], $this->params);
            $this->cookies = [];
        } else {
            $uri = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER["REQUEST_URI"];
            $method = $_SERVER['REQUEST_METHOD'];
            $this->params = $_GET;
            $this->cookies = $_COOKIE;
        }
        $headers = $this->getServerHeaders();
        $this->server = $_SERVER;
        parent::__construct($method, $uri, $headers);
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
}