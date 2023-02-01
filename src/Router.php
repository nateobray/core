<?php

/**
 * @license MIT
 */

namespace obray\core;

use obray\core\exceptions\ClassMethodNotFound;
use obray\core\exceptions\HTTPException;
use obray\core\exceptions\UserLevelException;
use obray\core\http\Method;
use obray\core\http\Response;
use obray\core\http\ServerRequest;
use obray\core\http\StatusCode;
use obray\core\interfaces\EncoderInterface;
use obray\core\interfaces\FactoryInterface;
use obray\core\interfaces\InvokerInterface;
use obray\core\interfaces\PermissionsInterface;
use Psr\Container\ContainerInterface;

/**
 * This class handles incoming HTTP requests by routing them to the
 * associated class/function and outputing the response.
 */
Class Router
{
    private float $start_time = 0;
    private array $encodersByClassProperty = [];
    private array $encodersByContentType = [];
    private string $startingPath;
    private EncoderInterface $encoder;
    private PermissionsInterface $permHandler;
    private ServerRequest $ServerRequest;
    private EncoderInterface $errorEncoder;
    private EncoderInterface $consoleEncoder;
    protected $content_type;
    
    /**
     * The constructor take a a factory, invoker, and container.  Optonall debug mode is also set in
     * the constructor.
     *
     * @param \obray\oFactoryInterface $factory Takes a factory interface
     * @param \obray\oInvokerInterface $invoker Takes an invoker interface
     * @param \Psr\Container\ContainerInterface $container Variable that contains the container
     * @param bool $debug Debug mode controls some of the error output (more detailed in debug mode)
     * @param int $start_time Optionally specify the time that you want to use to determine runtime
     */
    public function __construct(
        FactoryInterface $factory,
        InvokerInterface $invoker,
        ContainerInterface $container=NULL,
        $debug = false,
        $start_time = null
    ) {
        $this->start_time = !empty($start_time) ? $start_time : microtime(true);
        $this->factory = $factory;
        $this->invoker = $invoker;
        $this->container = $container;
        $this->debug_mode = $debug;
    }

    /**
     * This function is used to route an incoming URI to the associated object and formulates
     * the corresponding response.
     *
     * @param string $path This is the path to the object, usually passed from a URI, but could also 
     * come from the console argument
     * 
     * @return never
     */
    public function route($path = '', $params = [], bool $repressResponse = false)
    {
        // generate our server request
        $this->ServerRequest = ServerRequest::createRequest($path, $params);
        // generate out path array to use to search
        $path_array = $this->ServerRequest->getExplodedPath();

        // attempt to route the request with the set factory, invoker, and container
        $code = StatusCode::OK;
        try {
            // if we have an empty path_array, default to controller index path
            if( empty($path_array) ) $path_array[] = 'Index';
            // use the factory and invoker to create an object invoke its methods
            $this->startingPath = (string)$this->ServerRequest->getUri()->getPath();
            $obj = $this->searchForController($path_array, $this->ServerRequest->getQueryParams());
            if(method_exists($obj, 'getCode')) $code = $obj->getCode();
            if($this->ServerRequest->getMethod() === Method::CONSOLE){
                $this->encoder = new ($this->consoleEncoder)();
            } else {
                $this->setEncoderByClassProperty($obj);
            }
        } catch (HTTPException $e) {
            // make our object the exception
            $obj = $e;
            // get the status code from the exception
            $code = $e->getCode();
            // use the error encoder
            $this->encoder = $this->errorEncoder;
        } catch (\Exception $e) {
            // make our object the exception
            $obj = $e;
            // since we don't know what kind of exception we're dealing with, go with code 500
            $code = StatusCode::INTERNAL_SERVER_ERROR;
            // use the error encoder
            $this->encoder = $this->errorEncoder;
        }
        
        // at this point if we don't have an encoder, we're going to throw and exception
        if (empty($this->encoder)) {
            header('Content-Type: text/html' );
            throw new \Exception("Unable to find encoder for this request.");
        }
        
        // encode our response with the selected encoder
        $encoded = $this->encoder->encode($obj, $this->start_time, $this->debug_mode);
        
        // output if we're in console
        if($this->ServerRequest->getMethod() === Method::CONSOLE){
            if(!$repressResponse){
                $this->encoder->out($encoded);
                exit();
            }
            return;
        }
        
        // output HTTP response
        $response = new Response($code, [
            'Content-Type' => $this->encoder->getContentType()
        ], $encoded);
        if(!$repressResponse){
            $response->out();
            exit();
        }
    }

    /**
     * Searches recursively for a controller class based on the path_array and then
     * creates that controller and calls the specified method if any
     *
     * @param array $path_array Array containing the path
     * @param array $params Array containing the parameters to be passed the called method
     * @param bool $direct Specifies if the is is being called directly (skips permission check)
     * @param string $method The name of the method to be called on the created object
     * @param array $remaining An array of the remaining path (useful for dynamic page genration)
     * @param int $depth The depth of the recursive call.  Currenly has a hardcoded max
     * 
     * @return mixed
     */
    private function searchForController($path_array, $params = [], $direct = false, $method = '', $remaining = array(), $depth = 0): mixed
    {
        if($this->ServerRequest->getMethod() === Method::CONSOLE) $direct = true;
        // prevent the possibility of an infinite loop (this should not happen, but is here just in case)
        if( $depth > 20 ){ throw new \Exception("Depth limit for controller search reached.",500); }

        // setup path to controller class
        $object = array_pop($path_array);
        $obray_path = 'obray\\' . (!empty($path_array)?implode('\\',$path_array). '\\': '') . ucfirst($object);
        $path = 'controllers\\' . (!empty($path_array)?implode('\\',$path_array). '\\': '') . ucfirst($object);
        if(empty($path_array)){
            $path = 'controllers\\' . 'Index';
            $method = $object;
        }
        $index_path = 'controllers\\' . (!empty($path_array)?implode('\\',$path_array). '\\': '')  . (!empty($object)?$object.'\\':'') . 'Index' ;

        // check if path to controller exists, if so create object
        if(class_exists('\\'.$path)) {
            $path_array = explode('\\', $path);
            $params["remaining"] = $remaining;
            try{
                $obj = $this->make($path_array, $params, $direct, $method);
            } catch (ClassMethodNotFound $e) {
                $obj = $this->make($path_array, $params, $direct, '');
            }
            return $obj;
        
        // check if index path to controller exists, if so create object
        } else if (class_exists('\\'.$index_path)) {
            $path_array = explode('\\', $index_path);
            $params["remaining"] = $remaining;
            try{
                $obj = $this->make($path_array, $params, $direct, $method);
            } catch (ClassMethodNotFound $e) {
                $obj = $this->make($path_array, $params, $direct, '');
            }
            return $obj;
        
        // check if obray/core path exists, if so create the obray/core object
        } elseif (class_exists('\\' . $obray_path)){
            $path_array = explode('\\', $obray_path);
            $params["remaining"] = $remaining;
            try{
                $obj = $this->make($path_array, $params, $direct, $method);
            } catch (ClassMethodNotFound $e) {
                $obj = $this->make($path_array, $params, $direct, '');
            }
            return $obj;

        // if unable to find objects specified by either path, throw exception
        } else {
            $remaining[] = $object;
            if( empty($path_array) ){
                throw new HTTPException("Path not found (".$this->startingPath.").", 404);
            }
        }
        // recursively search path for controller
        return $this->searchForController($path_array, $params, $direct, $object, $remaining, ++$depth);
    }

    /**
     * This method creates an object based on the supplied parameters with
     * the classes factory object
     *
     * @param array $path_array Array containing the path
     * @param array $params Array containing the parameters to be passed the called method
     * @param bool $direct Specifies if the is is being called directly (skips permission check)
     * @param string $method The name of the method on the object we want to call
     * 
     * @return mixed
     * 
     * @throws \obray\core\exceptions\ClassNotFound
     */
    private function make($path_array, $params = [], $direct = false, $method = ''): mixed
    {
        $this->startingPath = '\\' . implode('\\',$path_array);
        $obj = $this->factory->make('\\' . implode('\\',$path_array));

        if(!$direct) $this->permHandler->checkPermissions($obj, null);
        if( !empty($method) ){
            $this->invoke($obj, $method, $params, $direct);
        } else {
            $this->invoke($obj, 'index', $params, $direct);
        }
        return $obj;
    }

     /**
     * The invoke method checks permission on the method we want to call and then uses
     * the class invoker to call the method
     *
     * @param mixed $obj The object containing the method we want to call
     * @param string $method The name of the method on the object we want to call
     * @param array $params Array of the parameters to be passed to our method
     * @param bool $direct Specifies if the is is being called directly (skips permission check)
     * 
     * @return void
     * 
     * @throws ClassMethodNotFound 
     */
    private function invoke($obj, $method, $params, $direct): void
    {    
        if($method === 'index') $method = strtolower($this->ServerRequest->getMethod());
        if(method_exists($obj,$method)){
            if(!$direct) $this->permHandler->checkPermissions($obj, $method);
            $this->invoker->invoke($this->ServerRequest, $obj, $method, $params);
            return;
        } else {
            throw new ClassMethodNotFound("Unable to find method " . $obj::class . '\\' . $method, 404);
        }
    }

    /**
     * Sets the encoder by the content properties found on the class.  The property
     * that triggers the encoder must be set when calling addEncoder function
     * on this class.
     *
     * @param mixed $obj This is the object to be encoded
     * 
     * @return bool
     */
    private function setEncoderByClassProperty($obj): bool
    {
        forEach ($obj as $key => $value) {
            if (array_key_exists($key, $this->encodersByClassProperty)) {
                $this->encoder = $this->encodersByClassProperty[$key];
                $this->content_type = $this->encoder->getContentType();
		        return true;
            }
        }
	    return false;
    }

    /**
     * This function is used to add to the list of encodres for a given content
     * type.  Only one encoder per type is allowed.
     *
     * @param string $content_type This should be a valid HTTP content type
     * @param string $encoder Stores the object that will be used to encode/decode/out
     * 
     * @return void
     */
    public function addEncoder(EncoderInterface $encoder, string $property, string $content_type): void
    {
        $this->encodersByClassProperty[$property] = $encoder;
        $this->encodersByContentType[$content_type] = $encoder;
    }

    /**
     * Sets the encoder to use when either an HTTPException or Exceptionis caught
     * 
     * @param EncoderInterface $encoder
     * 
     * @return void
     */
    public function setErrorEncoder(EncoderInterface $encoder): void
    {
        set_error_handler([$this, 'errorHandler']);
        register_shutdown_function([Router::class, "fatalHandler"], $encoder, $this->start_time);
        
        $this->errorEncoder = $encoder;
    }

    /**
     * Sets the encoder to use when calling routes from the command line CLI interface
     * 
     * @param EncoderInterface $encoder
     * 
     * @return void
     */
    public function setConsoleEncoder(EncoderInterface $encoder): void
    {
        $this->consoleEncoder = $encoder;
    }

    /**
     * Set the permissions handler which is used to check permissions on objects and function
     * 
     * @param PermissionsInterface $handler The permissions handler
     * 
     * @return void
     */
    public function setCheckPermissionsHandler(PermissionsInterface $handler): void
    {
        $this->permHandler = $handler;
    }

    public function errorHandler($error_level, $error_message, $error_file, $error_line, $error_context=null) 
    {
        switch ($error_level) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_PARSE:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
            default:
        }

        // encode our response with the selected encoder
        $error = new UserLevelException($error_message, $error_level);
        $error->setFile($error_file);
        $error->setLine($error_line);
        $error->setServerRequest($this->ServerRequest);
        $encoded = $this->errorEncoder->encode($error, null, true);
        
        // output HTTP response
        $response = new Response(500, [
            'Content-Type' => $this->errorEncoder->getContentType()
        ], $encoded);
        $response->out();
        exit();
    }

    static public function fatalHandler(EncoderInterface $encoder, $startTime) 
    {
        $errfile = "unknown file";
        $errstr  = "Unknown Error";
        $errno   = E_CORE_ERROR;
        $errline = 0;

        $error = error_get_last();

        if($error !== NULL) {
            $errno   = $error["type"];
            $errfile = $error["file"];
            $errline = $error["line"];
            $errstr  = $error["message"];

            // encode our response with the selected encoder
            $error = new UserLevelException($errstr, $errno);
            $error->setFile($errfile);
            $error->setLine($errline);
            
            $encoded = $encoder->encode($error, $startTime, true);
            
            // output HTTP response
            $response = new Response(500, [
                'Content-Type' => $encoder->getContentType()
            ], $encoded);
            $response->out();
            exit();
        }
    }

}