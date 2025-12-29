<?php
namespace obray\core\exceptions;

class HTTPException extends \Exception
{
    public $errors = [];

    public function add(string $message, $key = 'general')
    {
        $this->errors[$key] = $message;
        return $this;
    }

}
