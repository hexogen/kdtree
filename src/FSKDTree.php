<?php

namespace Hexogen\KDTree;

class FSKDTree implements KDTreeInterface
{
    /**
     * @var NodeInterface
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
     * @var
     */
    private $handler;

    /**
     * @var ItemFactoryInterface
     */
    private $factory;

    /**
     * FSKDTree constructor.
     * @param $path
     * @param ItemFactoryInterface $factory
     */
    public function __construct($path, ItemFactoryInterface $factory)
    {
        $this->factory = $factory;
        $this->handler = fopen($path, 'rb');
        $this->readInitData();
    }

    /**
     *  FSKDTree destructor
     */
    public function __destruct()
    {
        fclose($this->handler);
    }

    /**
     * @return int
     */
    public function getItemCount(): int
    {
        return $this->length;
    }

    /**
     * @return NodeInterface
     */
    public function getRoot()
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
     *  Read binary data and convert it to an object
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
     *  read num of dimensions in array
     */
    private function readDimensionsCount()
    {
        $binData = fread($this->handler, 4);
        $this->dimensions = unpack('V', $binData)[1];
    }

    /**
     *  read number of items in the tree
     */
    private function readItemsCount()
    {
        $binData = fread($this->handler, 4);
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
     */
    private function readPoint()
    {
        $dataLength = 8 * $this->dimensions;
        $binData = fread($this->handler, $dataLength);
        $dValues = unpack('d' . $this->dimensions, $binData);
        return array_values($dValues);
    }
}
