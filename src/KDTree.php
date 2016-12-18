<?php


namespace Hexogen\KDTree;

class KDTree
{
    /**
     * @var Node
     */
    private $root;

    /**
     * @var Item[]
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
    private $lenght;

    /**
     * @var int
     */
    private $dimensions;

    /**
     * KDTree constructor.
     * @param ItemList $itemList
     */
    public function __construct(ItemList $itemList)
    {
        $this->dimensions = $itemList->getDimensionNumber();
        $this->items = $itemList->getItems();
        $this->lenght = count($this->items);

        $this->maxBoundary = [];
        $this->minBoundary = [];

        for ($i = 0; $i < $this->dimensions; $i++) {
            $this->maxBoundary[$i] = -INF;
            $this->minBoundary[$i] = INF;
        }

        foreach ($this->items as $item) {
            for ($i = 0; $i < $this->dimensions; $i++) {
                $this->maxBoundary[$i] = max($this->maxBoundary[$i], $item->getNthDimension($i));
                $this->minBoundary[$i] = min($this->maxBoundary[$i], $item->getNthDimension($i));
            }
        }

        $this->root = NULL;
        if ($this->lenght > 0) {
            $hi = $this->lenght - 1;
            $mid = (int)($hi / 2);
            $item = $this->select($mid, 0, $hi, 0);

            $this->root = new Node($item);
            if ($mid > 0) {
                $this->root->setLeft($this->buildSubTree(0, $mid - 1, 1));
            }
            if ($mid < $hi) {
                $this->root->setRight($this->buildSubTree($mid + 1, $hi, 1));
            }
        }
        $this->items = NULL;
    }

    /**
     * get kd tree root
     *
     * @return Node|null
     */
    public function getRoot()
    {
        return $this->root;
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
     * @return Item
     */
    private function select(int $k, int $lo, int $hi, int $d)
    {
        while ($hi > $lo) {
            $j = $this->partition($lo, $hi, $d);
            if ($j > $k) {
                $hi = $j - 1;
            } else if ($j < $k) {
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
        while (true) {
            while ($this->items[++$i]->getNthDimension($d) < $val) {
                if ($i == $hi) break;
            }

            while ($this->items[--$j]->getNthDimension($d) > $val) {
                if ($j == $lo) break;
            }

            if ($i >= $j) break;

            $this->exch($i, $j);
        }

        $this->exch($lo, $j);

        return $j;
    }
}
