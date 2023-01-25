<?php

namespace obray\core\exceptions;

Class UserLevelException extends \Exception
{
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
}
