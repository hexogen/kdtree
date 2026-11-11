<?php

namespace Hexogen\KDTree\Internal;

use Hexogen\KDTree\Exception\FileException;

/**
 * Reads exact amounts of bytes from a kd tree file.
 *
 * @internal used by FSKDTree and FSNode, not a part of the public API
 */
final class FileReader
{
    /**
     * @const int the biggest amount of bytes requested from the stream at once
     */
    const CHUNK_LENGTH = 1048576;

    /**
     * Read exactly $length bytes
     *
     * @param resource $handler file handler
     * @param int $length number of bytes to read
     * @param int|null $position offset in the file to read from, null to read from the current position
     * @return string
     * @throws FileException if the handler is closed or the file is shorter than expected
     */
    public static function read($handler, int $length, ?int $position = null): string
    {
        if (!is_resource($handler)) {
            throw new FileException('kd tree file handle has been closed');
        }

        if ($position !== null && @fseek($handler, $position) !== 0) {
            throw new FileException('Corrupted kd tree file: unable to seek to offset ' . $position);
        }

        // The data is read in chunks, so a wrong length taken from a corrupted file
        // ends with an exception at the end of the file and not with a huge allocation
        $binData = '';
        while ($length > 0) {
            $chunk = @fread($handler, min($length, self::CHUNK_LENGTH));
            if ($chunk === false || $chunk === '') {
                throw new FileException('Corrupted kd tree file: unexpected end of file');
            }
            $binData .= $chunk;
            $length -= strlen($chunk);
        }
        return $binData;
    }
}
