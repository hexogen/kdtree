<?php

namespace Hexogen\KDTree\Interfaces;

interface NodeInterface
{
    /**
     * @return ItemInterface
     */
    public function getItem() : ItemInterface;

    /**
     * @param NodeInterface $node
     */
    public function setRight(NodeInterface $node);

    /**
     * @param NodeInterface $node
     */
    public function setLeft(NodeInterface $node);

    /**
     * @return NodeInterface|null
     */
    public function getRight();

    /**
     * @return NodeInterface|null
     */
    public function getLeft();
}
