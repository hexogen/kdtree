<?php

namespace Hexogen\KDTree\Tests;

/**
 * Stream wrapper whose writes always fail, to simulate a full disk.
 * Register with FailingStreamWrapper::register() and open "failing://anything".
 */
class FailingStreamWrapper
{
    const PROTOCOL = 'failing';

    /**
     * @var resource|null set by PHP when a context is passed to fopen()
     */
    public $context;

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
        return true;
    }

    public function stream_write(string $data): int
    {
        return 0;
    }

    public function stream_eof(): bool
    {
        return true;
    }

    public function stream_close(): void
    {
    }

    // phpcs:enable
}
