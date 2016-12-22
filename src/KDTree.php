<?php

namespace Hexogen\KDTree;

class KDTree implements KDTreeInterface
{
    /**
     * @var NodeInterface
     */
    private $root;

    /**
     * @var ItemInterface[] array of items or null after tree has been built
     */
    private $items;

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

    public function __construct(ItemList $itemList)
    {
        $this->dimensions = $itemList->getDimensionCount();
        $this->items = $itemList->getItems();
        $this->length = count($this->items);

        $this->setBoundaries();

        $this->buildTree();

        $this->items = null;
    }

    public function getItemCount(): int
    {
        return $this->length;
    }

    public function getRoot(): NodeInterface
    {
        return $this->root;
    }

    public function getMinBoundary(): array
    {
        return $this->minBoundary;
    }

    public function getMaxBoundary(): array
    {
        return $this->maxBoundary;
    }

    public function getDimensionCount(): int
    {
        return $this->dimensions;
    }

    private function buildSubTree(int $lo, int $hi, int $d): Node
    {
        $mid = (int)(($hi - $lo) / 2) + $lo;

        $item = $this->select($mid, $lo, $hi, $d);
        $node = new Node($item);

        $d++;
        $d = $d % $this->dimensions;

        if ($mid > $lo) {
            $node->setLeft($this->buildSubTree($lo, $mid - 1, $d));
        }
        if ($mid < $hi) {
            $node->setRight($this->buildSubTree($mid + 1, $hi, $d));
        }
        return $node;
    }

    private function exch(int $i, int $j)
    {
        $tmp = $this->items[$i];
        $this->items[$i] = $this->items[$j];
        $this->items[$j] = $tmp;
    }

    /**
     * @param int $k
     * @param int $lo
     * @param int $hi
     * @param int $d
     * @return ItemInterface
     */
    private function select(int $k, int $lo, int $hi, int $d)
    {
        while ($hi > $lo) {
            $j = $this->partition($lo, $hi, $d);
            if ($j > $k) {
                $hi = $j - 1;
            } elseif ($j < $k) {
                $lo = $j + 1;
            } else {
                return $this->items[$k];
            }
        }
        return $this->items[$k];
    }

    /**
     * @param int $lo
     * @param int $hi
     * @param int $d
     * @return int
     */
    private function partition(int $lo, int $hi, int $d)
    {
        $i = $lo;
        $j = $hi + 1;
        $v = $this->items[$lo];
        $val = $v->getNthDimension($d);

        do {
            while ($this->items[++$i]->getNthDimension($d) < $val && $i != $hi) {
            }

            while ($this->items[--$j]->getNthDimension($d) > $val) {
            }

            if ($i < $j) {
                $this->exch($i, $j);
            }
        } while ($i < $j);

        $this->exch($lo, $j);

        return $j;
    }

    private function setBoundaries()
    {
        $this->maxBoundary = [];
        $this->minBoundary = [];

        for ($i = 0; $i < $this->dimensions; $i++) {
            $this->maxBoundary[$i] = -INF;
            $this->minBoundary[$i] = INF;
        }

        foreach ($this->items as $item) {
            for ($i = 0; $i < $this->dimensions; $i++) {
                $this->maxBoundary[$i] = max($this->maxBoundary[$i], $item->getNthDimension($i));
                $this->minBoundary[$i] = min($this->minBoundary[$i], $item->getNthDimension($i));
            }
        }
    }

    private function buildTree()
    {
        if ($this->length > 0) {
            $hi = $this->length - 1;
            $mid = (int)($hi / 2);
            $item = $this->select($mid, 0, $hi, 0);

            $this->root = new Node($item);

            $nextDimension = 1 % $this->dimensions;
            if ($mid > 0) {
                $this->root->setLeft($this->buildSubTree(0, $mid - 1, $nextDimension));
            }
            if ($mid < $hi) {
                $this->root->setRight($this->buildSubTree($mid + 1, $hi, $nextDimension));
            }
        }
    }
}
