<?php
namespace obray\core\http\requests;

use obray\core\exceptions\HTTPException;
use obray\core\http\StatusCode;

class GETRequest extends CONSOLERequest
{
    public function __construct($path = '', $params = [])
    {
        parent::__construct($path, $params);
        if(strtolower($this->getMethod()) !== 'get') throw new HTTPException(StatusCode::REASONS[StatusCode::METHOD_NOT_ALLOWED], StatusCode::METHOD_NOT_ALLOWED);
    }
}
