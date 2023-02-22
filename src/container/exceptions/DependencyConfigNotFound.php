<?php

namespace obray\containers\exceptions;

Class DependencyConfigNotFound extends \Exception
{
    protected $message = "Uanble to find the dependencies config file.";
    protected $code = 500;
}
