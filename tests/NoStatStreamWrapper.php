<?php

namespace Hexogen\KDTree\Tests;

/**
 * Read-only stream wrapper that serves a file but cannot report its size (no stream_stat),
 * like some remote or custom streams. Open "nostat:///absolute/path".
 */
class NoStatStreamWrapper
{
    const PROTOCOL = 'nostat';

    /**
     * @var resource|null set by PHP when a context is passed to fopen()
     */
    public $context;

    /**
     * @var resource underlying file
     */
    private $handler;

    public static function register(): void
    {
        if (!in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::PROTOCOL, self::class);
        }
    }

    public static function unregister(): void
    {
        if (in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::PROTOCOL);
        }
    }

    // phpcs:disable PSR1.Methods.CamelCapsMethodName -- names are fixed by PHP's stream wrapper API

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $handler = fopen(substr($path, strlen(self::PROTOCOL . '://')), 'rb');
        if ($handler === false) {
            return false;
        }
        $this->handler = $handler;
        return true;
    }

    public function stream_read(int $count): string|false
    {
        return fread($this->handler, $count);
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        return fseek($this->handler, $offset, $whence) === 0;
    }

    public function stream_tell(): int
    {
        return ftell($this->handler);
    }

    public function stream_eof(): bool
    {
        return feof($this->handler);
    }

    public function stream_close(): void
    {
        fclose($this->handler);
    }

    // phpcs:enable
}
