<?php

namespace obray\core\exceptions;

use obray\core\http\ServerRequest;

Class UserLevelException extends \Exception
{
    protected ServerRequest $serverRequest;

    public function appendToMessage(string $textToAppend)
    {
        $this->message = $this->message . ' ' . $textToAppend;
    }

    public function setLine(int $line)
    {
        $this->line = $line;
    }

    public function setFile(string $file)
    {
        $this->file = $file;
    }

    public function setServerRequest(ServerRequest $serverRequest)
    {
        $this->serverRequest = $serverRequest;
    }

    public function getServerRequest(): ServerRequest
    {
        return $this->serverRequest;
    }
}
