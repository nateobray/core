<?php

namespace obray\sessions\exceptions;

Class SessionInitFailure extends \Exception
{
    protected $message = 'Session failed to initialize.';
    protected $code = 500;
}
