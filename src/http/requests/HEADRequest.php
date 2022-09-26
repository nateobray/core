<?php
namespace obray\core\http\requests;

use obray\core\exceptions\HTTPException;
use obray\core\http\StatusCode;

class HEADRequest extends CONSOLERequest
{
    public function __construct()
    {
        parent::__construct();
        if(strtolower($this->getMethod()) !== 'head') throw new HTTPException(StatusCode::REASONS[StatusCode::METHOD_NOT_ALLOWED], StatusCode::METHOD_NOT_ALLOWED);
    }
}