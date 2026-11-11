<?php

namespace Hexogen\KDTree;

use Hexogen\KDTree\Exception\FileException;
use Hexogen\KDTree\Interfaces\ItemFactoryInterface;
use Hexogen\KDTree\Interfaces\KDTreeInterface;
use Hexogen\KDTree\Interfaces\NodeInterface;
use Hexogen\KDTree\Internal\FileReader;

/**
 * File system backed KD tree. Nodes are lazily read from a binary file
 * produced by FSTreePersister.
 *
 * The file stays open as long as the tree or any node obtained from it is referenced.
 *
 * Binary format (integers are little-endian, doubles are in machine byte order):
 *
 *   dimensions     4 bytes   unsigned 32-bit
 *   item count     4 bytes   unsigned 32-bit
 *   max boundary   8 * dimensions bytes, doubles
 *   min boundary   8 * dimensions bytes, doubles
 *   nodes          item count * node size, root first
 *
 * Each node:
 *
 *   item id        4 bytes   unsigned 32-bit
 *   left offset    4 bytes   unsigned 32-bit absolute file offset, 0 if there is no left node
 *   right offset   4 bytes   unsigned 32-bit absolute file offset, 0 if there is no right node
 *   coordinates    8 * dimensions bytes, doubles
 */
class FSKDTree implements KDTreeInterface
{
    /**
     * @const int integer size in bytes
     */
    const INT_LENGTH = 4;

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
     * @var resource|null file handler
     */
    private $handler;

    /**
     * @var ItemFactoryInterface
     */
    private $factory;

    /**
     * FSKDTree constructor.
     * @param string $path path to a file produced by FSTreePersister
     * @param ItemFactoryInterface $factory
     * @throws FileException if the file cannot be opened or it is not a valid kd tree file
     */
    public function __construct($path, ItemFactoryInterface $factory)
    {
        $this->factory = $factory;

        $handler = @fopen($path, 'rb');
        if ($handler === false) {
            throw new FileException('Unable to open kd tree file for reading: ' . $path);
        }
        $this->handler = $handler;

        $this->readInitData();
    }

    /**
     * FSKDTree destructor.
     * The tree only gives up its own references here. The file is closed as soon as nothing
     * else uses it, so nodes that outlive the tree are still able to read their data.
     */
    public function __destruct()
    {
        $this->root = null;
        $this->handler = null;
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
        $this->readDimensionsCount();
        $this->readItemsCount();
        $this->readUpperBound();
        $this->readLowerBound();
        $this->setRoot();
    }

    /**
     * read num of dimensions in array
     * @throws FileException
     */
    private function readDimensionsCount()
    {
        $binData = FileReader::read($this->handler, FSKDTree::INT_LENGTH);

        if (substr($binData, 0, 3) === 'KDT') {
            throw new FileException(
                'Unsupported kd tree file: it has been written by hexogen/kdtree 0.3 or newer,'
                . ' re-create it with FSTreePersister of the installed version'
            );
        }

        $this->dimensions = unpack('V', $binData)[1];

        if ($this->dimensions < 1) {
            throw new FileException('Corrupted kd tree file: dimensions count should be bigger than 0');
        }
    }

    /**
     * read number of items in the tree
     * @throws FileException
     */
    private function readItemsCount()
    {
        $binData = FileReader::read($this->handler, FSKDTree::INT_LENGTH);
        $this->length = unpack('V', $binData)[1];
    }

    /**
     *  read upper boundary point
     */
    private function readUpperBound()
    {
        $this->maxBoundary = $this->readPoint();
    }

    /**
     *  read lower boundary point
     */
    private function readLowerBound()
    {
        $this->minBoundary = $this->readPoint();
    }

    /**
     *  set tree root
     */
    private function setRoot()
    {
        if ($this->length == 0) {
            return;
        }
        $position = ftell($this->handler);
        $this->root = new FSNode($this->factory, $this->handler, $position, $this->dimensions);
    }

    /**
     * Read point
     * @return array
     * @throws FileException
     */
    private function readPoint(): array
    {
        $dataLength = FSKDTree::FLOAT_LENGTH * $this->dimensions;
        $binData = FileReader::read($this->handler, $dataLength);
        $dValues = unpack('d' . $this->dimensions, $binData);
        return array_values($dValues);
    }
}
