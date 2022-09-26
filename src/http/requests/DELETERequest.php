<?php
namespace obray\core\http\requests;

use obray\core\exceptions\HTTPException;
use obray\core\http\StatusCode;

class DELETERequest extends CONSOLERequest
{
    public function __construct()
    {
        parent::__construct();
        if(strtolower($this->getMethod()) !== 'delete') throw new HTTPException(StatusCode::REASONS[StatusCode::METHOD_NOT_ALLOWED], StatusCode::METHOD_NOT_ALLOWED);
    }
}