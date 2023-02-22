<?php

namespace obray\containers\exceptions;

Class DependencyNotFound extends \Exception
{
    protected $message = "Dependency not found.";
    protected $code = 500;
}
