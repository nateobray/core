<?php
namespace obray\core\http;

use Psr\Http\Message\StreamInterface;

/**
 * Describes a data stream.
 *
 * Typically, an instance will wrap a PHP stream; this interface provides
 * a wrapper around the most common operations, including serialization of
 * the entire stream to a string.
 */
class Body implements StreamInterface
{
    private const DEFAULT_STREAM = 'php://temp';

    /** @var resource|null */
    private $streamHandle;

    public function __construct(string $contents = '')
    {
        $this->streamHandle = fopen(self::DEFAULT_STREAM, 'r+');
        if ($this->streamHandle === false) {
            throw new \RuntimeException('Unable to open temp stream.');
        }
        if ($contents !== '') {
            $this->write($contents);
            $this->rewind();
        }
    }

    public function __toString()
    {
        if ($this->streamHandle === null) {
            return '';
        }
        try {
            $this->rewind();
            return stream_get_contents($this->streamHandle) ?: '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function close()
    {
        if ($this->streamHandle !== null) {
            fclose($this->streamHandle);
            $this->streamHandle = null;
        }
    }

    public function detach()
    {
        $handle = $this->streamHandle;
        $this->streamHandle = null;
        return $handle;
    }

    public function getSize()
    {
        if ($this->streamHandle === null) {
            return null;
        }
        $stats = fstat($this->streamHandle);
        return isset($stats['size']) ? $stats['size'] : null;
    }

    public function tell()
    {
        $this->assertStream();
        $position = ftell($this->streamHandle);
        if ($position === false) {
            throw new \RuntimeException('Unable to determine stream position.');
        }
        return $position;
    }

    public function eof()
    {
        return $this->streamHandle === null ? true : feof($this->streamHandle);
    }

    public function isSeekable()
    {
        if ($this->streamHandle === null) {
            return false;
        }
        $meta = stream_get_meta_data($this->streamHandle);
        return !empty($meta['seekable']);
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        $this->assertStream();
        if (!$this->isSeekable()) {
            throw new \RuntimeException('Stream is not seekable.');
        }
        if (fseek($this->streamHandle, $offset, $whence) === -1) {
            throw new \RuntimeException('Unable to seek to stream position.');
        }
    }

    public function rewind()
    {
        $this->seek(0);
    }

    public function isWritable()
    {
        if ($this->streamHandle === null) {
            return false;
        }
        $mode = $this->getStreamMode();
        return strpbrk($mode, 'waxc+') !== false;
    }

    public function write($string)
    {
        $this->assertStream();
        if (!$this->isWritable()) {
            throw new \RuntimeException('Stream is not writable.');
        }
        $result = fwrite($this->streamHandle, $string);
        if ($result === false) {
            throw new \RuntimeException('Unable to write to stream.');
        }
        return $result;
    }

    public function isReadable()
    {
        if ($this->streamHandle === null) {
            return false;
        }
        $mode = $this->getStreamMode();
        return strpbrk($mode, 'r+') !== false;
    }

    public function read($length)
    {
        $this->assertStream();
        if (!$this->isReadable()) {
            throw new \RuntimeException('Stream is not readable.');
        }
        $result = fread($this->streamHandle, $length);
        if ($result === false) {
            throw new \RuntimeException('Unable to read from stream.');
        }
        return $result;
    }

    public function getContents()
    {
        $this->assertStream();
        if (!$this->isReadable()) {
            throw new \RuntimeException('Stream is not readable.');
        }
        $result = stream_get_contents($this->streamHandle);
        if ($result === false) {
            throw new \RuntimeException('Unable to read stream contents.');
        }
        return $result;
    }

    public function getMetadata($key = null)
    {
        if ($this->streamHandle === null) {
            return $key === null ? [] : null;
        }
        $metadata = stream_get_meta_data($this->streamHandle);
        if ($key === null) {
            return $metadata;
        }
        return $metadata[$key] ?? null;
    }

    private function assertStream(): void
    {
        if ($this->streamHandle === null) {
            throw new \RuntimeException('Stream is detached.');
        }
    }

    private function getStreamMode(): string
    {
        $meta = $this->getMetadata();
        return is_array($meta) && !empty($meta['mode']) ? $meta['mode'] : '';
    }
}
