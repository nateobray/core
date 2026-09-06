<?php

namespace obray\core;

use obray\core\exceptions\ClassMethodNotFound;
use obray\core\exceptions\ClassNotFound;
use obray\core\exceptions\HTTPException;
use obray\core\http\requests\CONNECTRequest;
use obray\core\http\requests\CONSOLERequest;
use obray\core\http\requests\DELETERequest;
use obray\core\http\requests\GETRequest;
use obray\core\http\requests\HEADRequest;
use obray\core\http\requests\OPTIONSRequest;
use obray\core\http\requests\PATCHRequest;
use obray\core\http\requests\POSTRequest;
use obray\core\http\requests\PUTRequest;
use obray\core\http\requests\TRACERequest;
use obray\core\http\ServerRequest;
use obray\core\http\StatusCode;
use obray\core\interfaces\InvokerInterface;
use Psr\Log\LoggerInterface;
use TypeError;

/**
 * This class is used to invoke or call a method on a specified object
 */

Class Invoker implements InvokerInterface
{
    private ?LoggerInterface $logger;
    private static array $reflectorCache = [];
    private static array $methodCache = [];

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }
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
        // Convert parameter keys with hyphens to underscores
        $normalizedParams = [];
        foreach ($params as $key => $value) {
            $normalizedKey = str_replace('-', '_', $key);
            $normalizedParams[$normalizedKey] = $value;
        }
         // reflect the object
         $className = is_object($object) ? get_class($object) : (string)$object;
         try {
             $reflector = self::$reflectorCache[$className] ??= new \ReflectionClass($object);
         } catch (\ReflectionException $e) {
             throw new ClassNotFound("Unable to find object.", 404);
         }

         // reflect method and extract parameters
         $methodCacheKey = $className . '::' . $method;
         try {
             $reflection_method = self::$methodCache[$methodCacheKey] ??= $reflector->getMethod($method);
             $parameters = $reflection_method->getParameters();
         } catch (\ReflectionException $e) {
             throw new ClassMethodNotFound("Unable to find object method.", 404);
         }
     
         // support fully parameratized methods with default values
         $method_parameters = []; $hasNoDefault = false;
         forEach ($parameters as $parameter) {
             $method_parameters[] = self::getParameterValue($normalizedParams, $parameter, $serverRequest);
         }
         
         try{
             $object->$method(...$method_parameters);
             return $object;
         } catch (TypeError $e){
             $message = $e->getMessage();
             if ($this->logger) {
                 $this->logger->debug($message, ['exception' => $e]);
             }
             // Only a binding error at this invocation is a client error. A controller's
             // own calls and return-type failures must propagate as server errors.
             $frame = $e->getTrace()[0] ?? [];
             $argumentErrorPrefix = $reflection_method->getDeclaringClass()->getName()
                 . '::' . $reflection_method->getName() . '(): Argument #';
             if (($frame['file'] ?? null) !== __FILE__ || !str_starts_with($message, $argumentErrorPrefix)) {
                 throw $e;
             }
             $messages = explode(',', $message);
             if(!empty($messages[0])){
                $messages = explode(':', $messages[0]);
                if(!empty($messages[3])){
                    $message = str_replace('$', '', $messages[3]);
                } else {
                    $message = $e->getMessage();
                }
             } else {
                $message = $e->getMessage();
             }
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
        $type = $parameter->getType();
        if (
            $type instanceof \ReflectionNamedType
            && $type->getName() === GETRequest::class
            && $request instanceof HEADRequest
        ) {
            $previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
            $_SERVER['REQUEST_METHOD'] = 'GET';
            try {
                return new GETRequest((string)$request->getUri(), $request->getQueryParams());
            } finally {
                if ($previousMethod === null) {
                    unset($_SERVER['REQUEST_METHOD']);
                } else {
                    $_SERVER['REQUEST_METHOD'] = $previousMethod;
                }
            }
        }

        if($type instanceof \ReflectionNamedType && in_array($type->getName(), [
            ServerRequest::class,
            CONNECTRequest::class,
            CONSOLERequest::class,
            DELETERequest::class,
            GETRequest::class,
            HEADRequest::class,
            OPTIONSRequest::class,
            PATCHRequest::class,
            POSTRequest::class,
            PUTRequest::class,
            TRACERequest::class
        ], true) && !empty($request)) {
            $requestClass = $type->getName();
            if (!$request instanceof $requestClass) {
                throw new HTTPException(StatusCode::REASONS[StatusCode::METHOD_NOT_ALLOWED], StatusCode::METHOD_NOT_ALLOWED);
            }
            return $request;
        }
        if (array_key_exists($parameter->getName(), $params)) {
            $value = $params[$parameter->getName()];
            if ($type instanceof \ReflectionNamedType) {
                if ($type->getName() === 'bool' && $value === 'false') return false;
                if ($type->getName() === 'bool' && $value === 'true') return true;
                if ($type->allowsNull() && ($value === 'null' || $value === '')) return null;
            }
            return $value;
        }

        if ($parameter->isDefaultValueAvailable() && !$parameter->isDefaultValueConstant()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->isDefaultValueAvailable() && $parameter->isDefaultValueConstant()) {
            return self::resolveDefaultValueConstant($parameter);
        }
        if (!$parameter->isOptional() && !$parameter->isDefaultValueAvailable()) {
            throw new HTTPException("Missing parameter " . $parameter->getName() . ".", StatusCode::BAD_REQUEST);
        }
    }

    private static function resolveDefaultValueConstant(\ReflectionParameter $parameter)
    {
        $constant = $parameter->getDefaultValueConstantName();
        if ($constant === null || strpos($constant, '::') === false) {
            return constant($constant);
        }

        [$className, $constantName] = explode('::', $constant, 2);
        if (in_array($className, ['self', 'parent', 'static'], true)) {
            $declaringClass = $parameter->getDeclaringClass();
            if (!$declaringClass instanceof \ReflectionClass) {
                return constant($constant);
            }

            if ($className === 'parent') {
                $declaringClass = $declaringClass->getParentClass();
                if (!$declaringClass instanceof \ReflectionClass) {
                    return constant($constant);
                }
            }

            $className = $declaringClass->getName();
        }

        try {
            $reflectionClass = new \ReflectionClass(ltrim($className, '\\'));
        } catch (\ReflectionException $e) {
            return constant($constant);
        }

        $reflectionConstant = $reflectionClass->getReflectionConstant($constantName);
        if ($reflectionConstant instanceof \ReflectionClassConstant) {
            return $reflectionConstant->getValue();
        }

        return constant($reflectionClass->getName() . '::' . $constantName);
    }

}
