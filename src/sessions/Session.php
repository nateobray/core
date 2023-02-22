<?php
namespace obray\sessions;

use obray\sessions\exceptions\SessionInitFailure;

Class Session
{
    public function get()
    {
        if(\session_start()){
            $session = (object) $_SESSION;
            \session_write_close();
            return $session;
        }
        throw new SessionInitFailure();
    }

    public function destroy()
    {
        if(\session_start()){
            \session_destroy();
            \session_write_close();
            return;
        }
        throw new \Exception("Unable to initialize session.",500);
    }

    public function __get(string $name)
    {
        if(\session_start()){
            $value = NULL;
            if(array_key_exists($name, $_SESSION)) {
                $value = $_SESSION[$name];
            }
            \session_write_close();
            return $value;
        }
        throw new SessionInitFailure();
    }

    public function __set(string $name,$value)
    {
        
        if(\session_start()){
            $_SESSION[$name] = $value;
            $this->{$name} = $value;
            \session_write_close();
            return;
        }
        throw new SessionInitFailure();
    }

    public function __isset(string $name)
    {
        if(\session_start()){
            $isSet = isSet($_SESSION[$name]);
            \session_write_close();
            return $isSet;
        }
        throw new \Exception("Uanble to intialize session.",500);
    }

    public function __unset(string $name)
    {
        if(\session_start()){
            unset($_SESSION[$name]);
            \session_write_close();
            return;
        }
        throw new SessionInitFailure();
    }
}