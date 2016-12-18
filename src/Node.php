<?php


namespace Hexogen\KDTree;


class Node
{
    private $item;
    private $left;
    private $right;

    public function __construct(Item $item)
    {
        $this->item = $item;
        $this->left = NULL;
        $this->right = NULL;
    }

    public function getItem() {
        return $this->item;
    }

    /**
     * @param Node $node
     */
    public function setRight(Node $node)
    {
        $this->right = $node;
    }

    /**
     * @param Node $node
     */
    public function setLeft(Node $node)
    {
        $this->left = $node;
    }

    /**
     * @api
     * @return Node|null
     */
    public function getRight()
    {
        return $this->right;
    }

    /**
     * @api
     * @return Node|null
     */
    public function getLeft()
    {
        return $this->left;
    }
}