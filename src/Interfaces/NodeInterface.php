<?php

namespace Hexogen\KDTree\Interfaces;

interface NodeInterface
{
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
     * @api
     * @return NodeInterface|null
     */
    public function getRight();

    /**
     * @api
     * @return NodeInterface|null
     */
    public function getLeft();
}
