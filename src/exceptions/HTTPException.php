<?php
namespace obray\core\exceptions;

class HTTPException extends \Exception
{
    public $errors = [];

    public function add(string $message, $key = 'general')
    {
        $errors['general'] = $message;
    }

}