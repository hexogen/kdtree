<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\ValidationException;
use Hexogen\KDTree\Interfaces\KDTreeInterface;
use Hexogen\KDTree\Interfaces\NodeInterface;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemList;
use PHPUnit\Framework\TestCase;

abstract class TreeTestCase extends TestCase
{
    /**
     * @param int $num
     * @param int $dimensions
     * @param array $coordinatesSet
     * @return ItemList
     * @throws ValidationException
     */
    protected static function getRandomItemsList(
        int $num = 100,
        int $dimensions = 2,
        array $coordinatesSet = []
    ) : ItemList {
        $list = new ItemList($dimensions);
        for ($i = 0; $i < $num; $i++) {
            if (empty($coordinatesSet)) {
                $coordinates = [];
                for ($j = 0; $j < $dimensions; $j++) {
                    $coordinates[] = mt_rand(-10000000, 10000000) / 1000000.;
                }
            } else {
                $coordinates = $coordinatesSet[$i];
            }
            $item = new Item($i, $coordinates);

            $list->addItem($item);
        }
        return $list;
    }

    /**
     * @param KDTreeInterface $tree
     */
    protected function checkTree(KDTreeInterface $tree)
    {
        $root = $tree->getRoot();
        if ($root) {
            $this->checkNode($root, 0);
        }
    }

    /**
     * @param NodeInterface $node
     * @param float $value
     * @param int $d
     */
    protected function checkLeftBranch(NodeInterface $node, float $value, int $d)
    {
        $val = $node->getItem()->getNthDimension($d);

        $this->assertLessThanOrEqual($value, $val);

        $left = $node->getLeft();
        if ($left) {
            $this->checkLeftBranch($left, $value, $d);
        }
        $right = $node->getRight();
        if ($left) {
            $this->checkLeftBranch($right, $value, $d);
        }
    }

    /**
     * @param NodeInterface $node
     * @param float $value
     * @param int $d
     */
    protected function checkRightBranch(NodeInterface $node, float $value, int $d)
    {
        $val = $node->getItem()->getNthDimension($d);

        $this->assertGreaterThanOrEqual($value, $val);

        $left = $node->getLeft();
        if ($left) {
            $this->checkRightBranch($left, $value, $d);
        }
        $right = $node->getRight();
        if ($left) {
            $this->checkRightBranch($right, $value, $d);
        }
    }

    /**
     * @param NodeInterface $node
     * @param int $d
     */
    protected function checkNode(NodeInterface $node, int $d)
    {
        $value = $node->getItem()->getNthDimension($d);
        $nextD = ($d + 1) % $node->getItem()->getDimensionsCount();
        $left = $node->getLeft();
        if ($left) {
            $this->checkLeftBranch($left, $value, $d);
            $this->checkNode($left, $nextD);
        }

        $right = $node->getRight();
        if ($left) {
            $this->checkRightBranch($right, $value, $d);
            $this->checkNode($right, $nextD);
        }
    }
}
