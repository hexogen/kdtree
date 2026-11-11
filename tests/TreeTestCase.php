<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Interfaces\ItemInterface;
use Hexogen\KDTree\Interfaces\KDTreeInterface;
use Hexogen\KDTree\Interfaces\NodeInterface;
use Hexogen\KDTree\Interfaces\PointInterface;
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
     */
    protected function getRandomItemsList(int $num = 100, int $dimensions = 2, array $coordinatesSet = [])
    {
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
     * Get ids of all items in the tree: node, its left branch, its right branch
     *
     * @param NodeInterface|null $node
     * @return int[]
     */
    protected function getTreeIds(?NodeInterface $node): array
    {
        if ($node === null) {
            return [];
        }
        return array_merge(
            [$node->getItem()->getId()],
            $this->getTreeIds($node->getLeft()),
            $this->getTreeIds($node->getRight())
        );
    }

    /**
     * Get all items of the tree
     *
     * @param NodeInterface|null $node
     * @return ItemInterface[]
     */
    protected function getTreeItems(?NodeInterface $node): array
    {
        if ($node === null) {
            return [];
        }
        return array_merge(
            [$node->getItem()],
            $this->getTreeItems($node->getLeft()),
            $this->getTreeItems($node->getRight())
        );
    }

    /**
     * Square distances from the point to the nearest items, found without the tree
     *
     * @param ItemInterface[] $items
     * @param PointInterface $point
     * @param int $resultLength
     * @return float[]
     */
    protected function getNearestDistances(array $items, PointInterface $point, int $resultLength): array
    {
        $distances = [];
        foreach ($items as $item) {
            $distances[] = $this->getDistance($item, $point);
        }
        sort($distances);
        return array_slice($distances, 0, $resultLength);
    }

    /**
     * @param PointInterface $item
     * @param PointInterface $point
     * @return float square distance between the points
     */
    protected function getDistance(PointInterface $item, PointInterface $point): float
    {
        $distance = 0.;
        for ($i = 0; $i < $point->getDimensionsCount(); $i++) {
            $distance += pow($item->getNthDimension($i) - $point->getNthDimension($i), 2);
        }
        return $distance;
    }

    /**
     * Check that the search result is the nearest items to the point, the nearest one first
     *
     * @param ItemInterface[] $allItems all items of the tree
     * @param ItemInterface[] $result
     * @param PointInterface $point
     * @param int $resultLength
     */
    protected function checkNearest(array $allItems, array $result, PointInterface $point, int $resultLength)
    {
        $distances = [];
        $ids = [];
        foreach ($result as $item) {
            $this->assertInstanceOf(ItemInterface::class, $item);
            $distances[] = $this->getDistance($item, $point);
            $ids[] = $item->getId();
        }
        $this->assertSame($this->getNearestDistances($allItems, $point, $resultLength), $distances);
        $this->assertSame($ids, array_unique($ids), 'an item should not be returned twice');
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
        if ($right) {
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
        if ($right) {
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
        if ($right) {
            $this->checkRightBranch($right, $value, $d);
            $this->checkNode($right, $nextD);
        }
    }
}
