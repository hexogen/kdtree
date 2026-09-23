<?php

namespace Hexogen\KDTree;

use Hexogen\KDTree\Exception\FileException;
use Hexogen\KDTree\Interfaces\ItemFactoryInterface;
use Hexogen\KDTree\Interfaces\ItemInterface;
use Hexogen\KDTree\Interfaces\NodeInterface;

class FSNode implements NodeInterface
{
    /**
     * @var ItemInterface|null item that belongs to the node, null until read from the file
     */
    private $item;

    /**
     * @var NodeInterface|null link to the left node
     */
    private $left;

    /**
     * @var int|null left node offset in file, null until read, 0 if there is no left node
     */
    private $leftPosition;

    /**
     * @var NodeInterface|null right node link
     */
    private $right;

    /**
     * @var int|null right node offset in the file, null until read, 0 if there is no right node
     */
    private $rightPosition;

    /**
     * @var resource file handler
     */
    private $handler;

    /**
     * @var int node start position in the file
     */
    private $position;

    /**
     * @var ItemFactoryInterface item factory
     */
    private $factory;

    /**
     * @var int num of dimensions it item
     */
    private $dimensions;

    /**
     * FSNode constructor.
     * @param ItemFactoryInterface $factory
     * @param resource $handler file handler
     * @param int $position node start position in the file
     * @param int $dimensions number of dimensions in item
     */
    public function __construct(ItemFactoryInterface $factory, $handler, int $position, int $dimensions)
    {
        $this->item = null;
        $this->left = null;
        $this->right = null;
        $this->leftPosition = null;
        $this->rightPosition = null;
        $this->handler = $handler;
        $this->position = $position;
        $this->factory = $factory;
        $this->dimensions = $dimensions;
    }

    /**
     * @return ItemInterface get item from the node
     */
    public function getItem() : ItemInterface
    {
        if ($this->item === null) {
            $this->readNode();
        }
        return $this->item;
    }

    /**
     * @param NodeInterface $node set right node
     */
    public function setRight(NodeInterface $node): void
    {
        $this->right = $node;
    }

    /**
     * @param NodeInterface $node set left node
     */
    public function setLeft(NodeInterface $node): void
    {
        $this->left = $node;
    }

    /**
     * Returns right node if it exists, null otherwise
     * @return NodeInterface|null get right node
     */
    public function getRight(): ?NodeInterface
    {
        if ($this->rightPosition === null) {
            $this->readNode();
        }
        if ($this->right === null && $this->rightPosition !== 0) {
            $this->right = $this->makeChild($this->rightPosition);
        }
        return $this->right;
    }

    /**
     * Returns left node if it exists, null otherwise
     * @return NodeInterface|null left node
     */
    public function getLeft(): ?NodeInterface
    {
        if ($this->leftPosition === null) {
            $this->readNode();
        }
        if ($this->left === null && $this->leftPosition !== 0) {
            $this->left = $this->makeChild($this->leftPosition);
        }
        return $this->left;
    }

    /**
     * @param int $position child node offset in the file
     * @return FSNode
     */
    private function makeChild(int $position): FSNode
    {
        return new FSNode($this->factory, $this->handler, $position, $this->dimensions);
    }

    /**
     * Read node data from the file in a single read
     * @throws FileException
     */
    private function readNode()
    {
        $nodeLength = 3 * FSKDTree::INT_LENGTH + FSKDTree::FLOAT_LENGTH * $this->dimensions;

        fseek($this->handler, $this->position);
        $binData = fread($this->handler, $nodeLength);

        if ($binData === false || strlen($binData) !== $nodeLength) {
            throw new FileException('Corrupted kd tree file: unable to read node at offset ' . $this->position);
        }

        $links = unpack('Pid/Pleft/Pright', $binData);
        $this->leftPosition = $links['left'];
        $this->rightPosition = $links['right'];

        // unpack() names a single value "v" but several "v1", "v2", ... so read the
        // coordinates unnamed and normalise the keys with array_values()
        $dValues = array_values(unpack('e' . $this->dimensions, $binData, 3 * FSKDTree::INT_LENGTH));

        $this->item = $this->factory->make($links['id'], $dValues);
    }
}
