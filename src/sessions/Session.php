<?php
namespace obray\sessions;

use obray\sessions\exceptions\SessionInitFailure;

#[\AllowDynamicProperties]
Class Session
{
    private function startForRead(): bool
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (\headers_sent($file, $line)) {
            throw new SessionInitFailure("Cannot start session; headers already sent at $file:$line", 500);
        }
        return \session_start(['read_and_close' => true]);
    }

    private function startForWrite(): bool
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (\headers_sent($file, $line)) {
            throw new SessionInitFailure("Cannot start session; headers already sent at $file:$line", 500);
        }
        return \session_start();
    }

    public function get()
    {
        if ($this->startForRead()) {
            $data = isset($_SESSION) && is_array($_SESSION) ? $_SESSION : [];
            return (object) $data;
        }
        throw new SessionInitFailure();
    }

    public function destroy()
    {
        if ($this->startForWrite()) {
            \session_destroy();
            \session_write_close();
            return;
        }
        throw new SessionInitFailure();
    }

    public function __get(string $name)
    {
        if ($this->startForRead()) {
            return array_key_exists($name, $_SESSION) ? $_SESSION[$name] : null;
        }
        throw new SessionInitFailure();
    }

    public function __set(string $name, $value)
    {
        if ($this->startForWrite()) {
            $_SESSION[$name] = $value;
            $this->{$name} = $value;
            \session_write_close();
            return;
        }
        throw new SessionInitFailure();
    }

    public function __isset(string $name): bool
    {
        if ($this->startForRead()) {
            return isset($_SESSION[$name]);
        }
        throw new SessionInitFailure();
    }

    public function __unset(string $name)
    {
        if ($this->startForWrite()) {
            unset($_SESSION[$name]);
            \session_write_close();
            return;
        }
        throw new SessionInitFailure();
    }
}