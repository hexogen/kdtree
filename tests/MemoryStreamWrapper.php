<?php

namespace Hexogen\KDTree\Tests;

/**
 * In-memory file system for tests, files are available as "kdtree-test://any/path".
 *
 * It behaves like a stream that is not a plain file (data comes in chunks of 8192 bytes at most)
 * and it is able to simulate failures, see the constants below.
 */
class MemoryStreamWrapper
{
    const PROTOCOL = 'kdtree-test';

    /**
     * New files cannot be created with the "x" mode, as in a directory without write permission
     */
    const FAIL_EXCLUSIVE_OPEN = 'exclusive-open';

    /**
     * Nothing can be written, as on a full disk
     */
    const FAIL_WRITE = 'write';

    /**
     * Writes succeed and flushing fails
     */
    const FAIL_FLUSH = 'flush';

    /**
     * Files cannot be renamed, as a file locked by another process on Windows
     */
    const FAIL_RENAME = 'rename';

    /**
     * @var string[] contents of files by their url
     */
    public static $files = [];

    /**
     * @var string[] simulated failures
     */
    private static $failures = [];

    /**
     * @var resource|null set by PHP when a context is passed to fopen()
     */
    public $context;

    /**
     * @var string url of the opened file
     */
    private $path;

    /**
     * @var int
     */
    private $position = 0;

    /**
     * @param string[] $failures list of the FAIL_* constants
     */
    public static function register(array $failures = []): void
    {
        self::unregister();
        self::$failures = $failures;
        stream_wrapper_register(self::PROTOCOL, self::class);
    }

    public static function unregister(): void
    {
        if (in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::PROTOCOL);
        }
        self::$files = [];
        self::$failures = [];
    }

    private static function fails(string $failure): bool
    {
        return in_array($failure, self::$failures, true);
    }

    // phpcs:disable PSR1.Methods.CamelCapsMethodName -- names are fixed by PHP's stream wrapper API

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if ($mode[0] === 'x' && (self::fails(self::FAIL_EXCLUSIVE_OPEN) || isset(self::$files[$path]))) {
            return false;
        }
        if ($mode[0] === 'r' && !isset(self::$files[$path])) {
            return false;
        }
        if ($mode[0] !== 'r') {
            self::$files[$path] = '';
        }
        $this->path = $path;
        return true;
    }

    public function stream_write(string $data): int
    {
        if (self::fails(self::FAIL_WRITE)) {
            return 0;
        }
        $contents = str_pad(self::$files[$this->path], $this->position, "\0");
        self::$files[$this->path] = substr_replace($contents, $data, $this->position, strlen($data));
        $this->position += strlen($data);
        return strlen($data);
    }

    public function stream_read(int $count): string
    {
        $data = (string)substr(self::$files[$this->path], $this->position, $count);
        $this->position += strlen($data);
        return $data;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::$files[$this->path]);
    }

    public function stream_tell(): int
    {
        return $this->position;
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        if ($whence !== SEEK_SET || $offset < 0) {
            return false;
        }
        $this->position = $offset;
        return true;
    }

    public function stream_flush(): bool
    {
        return !self::fails(self::FAIL_FLUSH);
    }

    public function stream_close(): void
    {
    }

    public function stream_stat(): array
    {
        return ['mode' => 0100644, 'size' => strlen(self::$files[$this->path])];
    }

    /**
     * @return array|false
     */
    public function url_stat(string $path, int $flags)
    {
        if (!isset(self::$files[$path])) {
            return false;
        }
        return ['mode' => 0100644, 'size' => strlen(self::$files[$path])];
    }

    public function stream_metadata(string $path, int $option, $value): bool
    {
        return true;
    }

    public function rename(string $from, string $to): bool
    {
        if (self::fails(self::FAIL_RENAME) || !isset(self::$files[$from])) {
            return false;
        }
        self::$files[$to] = self::$files[$from];
        unset(self::$files[$from]);
        return true;
    }

    public function unlink(string $path): bool
    {
        unset(self::$files[$path]);
        return true;
    }

    // phpcs:enable
}
