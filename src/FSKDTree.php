<?php

namespace Hexogen\KDTree;

use Hexogen\KDTree\Exception\FileException;
use Hexogen\KDTree\Interfaces\ItemFactoryInterface;
use Hexogen\KDTree\Interfaces\KDTreeInterface;
use Hexogen\KDTree\Interfaces\NodeInterface;

/**
 * File system backed KD tree. Nodes are lazily read from a binary file
 * produced by FSTreePersister.
 *
 * Read nodes are kept in memory so repeated searches don't hit the disk again.
 * By default every node that was ever read stays cached, so a long-lived tree
 * can grow to hold the whole file as objects; pass $cacheDepth to keep only the
 * top levels cached (memory is then bounded by 2^($cacheDepth + 1) nodes).
 *
 * The file handle stays open as long as the tree or any node obtained from it
 * is referenced.
 *
 * Binary format (version 1, all integers and floats little-endian):
 *
 *   magic          3 bytes   "KDT"
 *   version        1 byte    format version (currently 1)
 *   dimensions     4 bytes   unsigned 32-bit
 *   item count     8 bytes   unsigned 64-bit
 *   max boundary   8 * dimensions bytes, doubles
 *   min boundary   8 * dimensions bytes, doubles
 *   nodes          item count * node size, root first, pre-order (right subtree, then left)
 *
 * Each node:
 *
 *   item id        8 bytes   signed 64-bit
 *   left offset    8 bytes   unsigned 64-bit absolute file offset, 0 if no left child
 *   right offset   8 bytes   unsigned 64-bit absolute file offset, 0 if no right child
 *   coordinates    8 * dimensions bytes, doubles
 */
class FSKDTree implements KDTreeInterface
{
    /**
     * @const string file signature
     */
    const MAGIC = 'KDT';

    /**
     * @const int current binary format version
     */
    const FORMAT_VERSION = 1;

    /**
     * @const int size in bytes of the magic + version header
     */
    const HEADER_LENGTH = 4;

    /**
     * @const int size in bytes of the dimensions count field
     */
    const DIMENSIONS_LENGTH = 4;

    /**
     * @const int integer size in bytes (item ids, item count and node offsets)
     */
    const INT_LENGTH = 8;

    /**
     * @const int float size in bytes
     */
    const FLOAT_LENGTH = 8;

    /**
     * @var NodeInterface|null
     */
    private $root;

    /**
     * @var array
     */
    private $maxBoundary;

    /**
     * @var array
     */
    private $minBoundary;

    /**
     * @var int number of items in the tree
     */
    private $length;

    /**
     * @var int
     */
    private $dimensions;

    /**
     * @var resource|null
     */
    private $handler;

    /**
     * @var ItemFactoryInterface
     */
    private $factory;

    /**
     * @var int|null number of tree levels below the root whose nodes stay cached, null for all
     */
    private $cacheDepth;

    /**
     * FSKDTree constructor.
     * @param string $path path to a file produced by FSTreePersister
     * @param ItemFactoryInterface $factory
     * @param int|null $cacheDepth how many levels below the root keep read nodes in memory:
     *        null (default) caches every node read, 0 caches nothing but the root
     * @throws FileException if the file cannot be opened or has an unsupported format
     * @throws \InvalidArgumentException if $cacheDepth is negative
     */
    public function __construct(string $path, ItemFactoryInterface $factory, ?int $cacheDepth = null)
    {
        if ($cacheDepth !== null && $cacheDepth < 0) {
            throw new \InvalidArgumentException('$cacheDepth should not be negative');
        }
        $this->factory = $factory;
        $this->cacheDepth = $cacheDepth;

        $handler = @fopen($path, 'rb');
        if ($handler === false) {
            throw new FileException('Unable to open kd tree file for reading: ' . $path);
        }
        $this->handler = $handler;

        try {
            $this->readInitData();
        } catch (\Throwable $e) {
            fclose($this->handler);
            $this->handler = null;
            throw $e;
        }
    }

    /**
     * @return int
     */
    public function getItemCount(): int
    {
        return $this->length;
    }

    /**
     * @return NodeInterface|null
     */
    public function getRoot(): ?NodeInterface
    {
        return $this->root;
    }

    /**
     * @return array
     */
    public function getMinBoundary(): array
    {
        return $this->minBoundary;
    }

    /**
     * @return array
     */
    public function getMaxBoundary(): array
    {
        return $this->maxBoundary;
    }

    /**
     * @return int
     */
    public function getDimensionCount(): int
    {
        return $this->dimensions;
    }

    /**
     * Read binary data and convert it to an object
     * @throws FileException
     */
    private function readInitData()
    {
        $this->readHeader();
        $this->readDimensionsCount();
        $this->readItemsCount();
        $this->maxBoundary = $this->readPoint();
        $this->minBoundary = $this->readPoint();
        $this->validateFileSize();
        $this->setRoot();
    }

    /**
     * Get size in bytes of a single node in the file
     * @param int $dimensions
     * @return int
     */
    public static function getNodeLength(int $dimensions): int
    {
        return 3 * self::INT_LENGTH + $dimensions * self::FLOAT_LENGTH;
    }

    /**
     * Check that the file holds exactly the number of nodes declared in the header
     * @throws FileException
     */
    private function validateFileSize()
    {
        $stat = @fstat($this->handler);
        if ($stat === false) {
            return; // stream does not report its size, nodes are still validated on read
        }
        $nodesLength = $stat['size'] - ftell($this->handler);
        $nodeLength = self::getNodeLength($this->dimensions);

        if ($nodesLength % $nodeLength !== 0 || intdiv($nodesLength, $nodeLength) !== $this->length) {
            throw new FileException(
                'Corrupted kd tree file: header declares ' . $this->length . ' items but the file holds '
                . $nodesLength . ' bytes of node data (' . $nodeLength . ' bytes per node)'
            );
        }
    }

    /**
     * Validate magic bytes and format version
     * @throws FileException
     */
    private function readHeader()
    {
        $header = $this->read(self::HEADER_LENGTH);

        if (substr($header, 0, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new FileException(
                'Not a kd tree file (or a legacy format without header); re-create it with FSTreePersister'
            );
        }

        $version = ord($header[strlen(self::MAGIC)]);
        if ($version !== self::FORMAT_VERSION) {
            throw new FileException(
                'Unsupported kd tree file format version ' . $version . ', expected ' . self::FORMAT_VERSION
            );
        }
    }

    /**
     * Read num of dimensions in array
     * @throws FileException
     */
    private function readDimensionsCount()
    {
        $this->dimensions = unpack('V', $this->read(self::DIMENSIONS_LENGTH))[1];
        if ($this->dimensions <= 0) {
            throw new FileException('Corrupted kd tree file: dimensions count should be bigger than 0');
        }
    }

    /**
     * Read number of items in the tree
     * @throws FileException
     */
    private function readItemsCount()
    {
        $this->length = unpack('P', $this->read(self::INT_LENGTH))[1];
        if ($this->length < 0) {
            throw new FileException('Corrupted kd tree file: negative item count');
        }
    }

    /**
     * Set tree root
     */
    private function setRoot()
    {
        if ($this->length == 0) {
            $this->root = null;
            return;
        }
        $position = ftell($this->handler);
        $this->root = new FSNode($this->factory, $this->handler, $position, $this->dimensions, $this->cacheDepth);
    }

    /**
     * Read point
     * @return array
     * @throws FileException
     */
    private function readPoint(): array
    {
        $binData = $this->read(self::FLOAT_LENGTH * $this->dimensions);
        return array_values(unpack('e' . $this->dimensions, $binData));
    }

    /**
     * Read exactly $length bytes from the file
     * @param int $length
     * @return string
     * @throws FileException
     */
    private function read(int $length): string
    {
        $binData = fread($this->handler, $length);
        if ($binData === false || strlen($binData) !== $length) {
            throw new FileException('Corrupted kd tree file: unexpected end of file');
        }
        return $binData;
    }
}
