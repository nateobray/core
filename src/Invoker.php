<?php

namespace obray\core;

use obray\core\exceptions\ClassMethodNotFound;
use obray\core\exceptions\ClassNotFound;
use obray\core\exceptions\HTTPException;
use obray\core\http\requests\CONNECTRequest;
use obray\core\http\requests\CONSOLERequest;
use obray\core\http\requests\DELETERequest;
use obray\core\http\requests\GETRequest;
use obray\core\http\requests\OPTIONSRequest;
use obray\core\http\requests\PATCHRequest;
use obray\core\http\requests\POSTRequest;
use obray\core\http\requests\PUTRequest;
use obray\core\http\requests\TRACERequest;
use obray\core\http\ServerRequest;
use obray\core\http\StatusCode;
use obray\core\interfaces\InvokerInterface;
use TypeError;

/**
 * This class is used to invoke or call a method on a specified object
 */

Class Invoker implements InvokerInterface
{
    /**
     * The invoke method attempts to call a specified method on an object
     *
     * @param mixed $object This is the object that contains the method we want to call
     * @param string $method The name of the function on the object you want to call
     * @param array $params This is an array of parameters to be passed to the method
     *
     * @return mixed
     */

    public function invoke(ServerRequest $serverRequest, $object, $method, $params = [])
    {
        // reflect the object 
        try {
            $reflector = new \ReflectionClass($object);
        } catch (\ReflectionException $e) {
            throw new ClassNotFound("Unable to find object.", 404);
        }

        // reflect method and extract parameters
        try {
            $reflection_method = $reflector->getMethod($method);
            $parameters = $reflection_method->getParameters();
        } catch (\ReflectionException $e) {
            throw new ClassMethodNotFound("Unable to find object method.", 404);
        }
    
        // support fully parameratized methods with default values
        $method_parameters = []; $hasNoDefault = false;
        forEach ($parameters as $parameter) {
            $method_parameters[] = self::getParameterValue($params, $parameter, $serverRequest);
        }
        
        try{
            $object->$method(...$method_parameters);
            return $object;
        } catch (TypeError $e){
            $message = $e->getMessage();
            print_r($e);
            if(
                str_contains($message, 'must be of type ' . GETRequest::class) ||
                str_contains($message, 'must be of type ' . POSTRequest::class) ||
                str_contains($message, 'must be of type ' . PUTRequest::class) ||
                str_contains($message, 'must be of type ' . CONNECTRequest::class) ||
                str_contains($message, 'must be of type ' . DELETERequest::class) ||
                str_contains($message, 'must be of type ' . HEADRequest::class) ||
                str_contains($message, 'must be of type ' . OPTIONSRequest::class) ||
                str_contains($message, 'must be of type ' . PATCHRequest::class) ||
                str_contains($message, 'must be of type ' . TRACERequest::class)
            ){
                throw new HTTPException(StatusCode::REASONS[StatusCode::METHOD_NOT_ALLOWED], StatusCode::METHOD_NOT_ALLOWED);
            }
            $messages = explode(',', $message);
            $messages = explode(':', $messages[0]);
            $message = str_replace('$', '', $messages[3]);
            throw new HTTPException($message, StatusCode::NOT_ACCEPTED);
        }
    }

    /**
     * @param array $params
     * @param ReflectionParameter $parameter
     * @return mixed
     * @throws \Exception
     */
    private static function getParameterValue($params, $parameter, $request)
    {
        if (isSet($params[$parameter->getName()])) {
            if($parameter->getType() instanceof \ReflectionNamedType && $parameter->getType()->getName() == 'bool' && $params[$parameter->getName()] == 'false') return false;
            if($parameter->getType() instanceof \ReflectionNamedType && $parameter->getType()->getName() == 'bool' && $params[$parameter->getName()] == 'true') return true;
            if($parameter->getType() instanceof \ReflectionNamedType && ($parameter->getType()->getName() == 'null' || $parameter->getType()->allowsNull()) && $params[$parameter->getName()] == 'null') return null;
            return $params[$parameter->getName()];
        }

        if ($parameter->isDefaultValueAvailable() && !$parameter->isDefaultValueConstant()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->isDefaultValueAvailable() && $parameter->isDefaultValueConstant()) {
            $constant = $parameter->getDefaultValueConstantName();
            return constant($constant);
        }

        $type = $parameter->getType();
        if(in_array((string)$type, [
            ServerRequest::class,
            CONNECTRequest::class,
            CONSOLERequest::class,
            DELETERequest::class,
            GETRequest::class,
            HEADClass::class,
            OPTIONSRequest::class,
            PATCHRequest::class,
            POSTRequest::class,
            PUTRequest::class,
            TRACERequest::class
        ]) && !empty($request) ){
            return $request;
        }

        if (!$parameter->isOptional() && !$parameter->isDefaultValueAvailable()) {
            throw new \Exception("Missing parameter " . $parameter->getName() . ".", 500);
        }
    }

}