<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\ValidationException;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemList;
use Hexogen\KDTree\KDTree;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class KDTreeTest extends TreeTestCase
{
    /**
     * @throws ValidationException
     */
    #[Test]
    public function itShouldCreateAnInstance()
    {
        $itemList = new ItemList(5);
        $tree = new KDTree($itemList);

        $this->assertInstanceOf(KDTree::class, $tree);
    }

    /**
     * @throws ValidationException
     */
    #[Test]
    public function itShouldGetRoot()
    {
        $itemList = new ItemList(2);
        $item = new Item(1, [2, 3]);
        $itemList->addItem($item);
        $tree = new KDTree($itemList);

        $this->assertSame($item, $tree->getRoot()->getItem());
    }

    /**
     * @throws ValidationException
     */
    #[Test]
    public function itShouldGetNullRoot()
    {
        $itemList = new ItemList(5);
        $tree = new KDTree($itemList);

        $this->assertNull($tree->getRoot());
    }

    /**
     * @param ItemList $itemList
     */
    #[Test]
    #[DataProvider('itemProvider')]
    public function itShouldCreateTree(ItemList $itemList)
    {
        $tree = new KDTree($itemList);

        $this->checkTree($tree);
    }

    #[Test]
    public function itShouldGetNumberOfDimensionsInItems()
    {
        $tree = new KDTree(self::getRandomItemsList(10, 1));
        $this->assertEquals(1, $tree->getDimensionCount());
        $tree = new KDTree(self::getRandomItemsList(10, 5));
        $this->assertEquals(5, $tree->getDimensionCount());
    }

    #[Test]
    public function itShouldGetNumberOfItemsInTheTree()
    {
        $tree = new KDTree(self::getRandomItemsList(0));
        $this->assertEquals(0, $tree->getItemCount());
        $tree = new KDTree(self::getRandomItemsList(10, 5));
        $this->assertEquals(10, $tree->getItemCount());
    }

    #[Test]
    public function itShouldGetMinBoundary()
    {
        $tree = new KDTree(self::getRandomItemsList(0));
        $this->assertEquals(INF, $tree->getMinBoundary()[0]);
        $this->assertEquals(INF, $tree->getMinBoundary()[1]);
        $tree = new KDTree(self::getRandomItemsList(5, 2, [
            [1.2, 2.2],
            [2.3, 2.4],
            [3.2, 2.1],
            [1.1, 2.0],
            [1.3, 2.2]
        ]));
        $this->assertEquals(1.1, $tree->getMinBoundary()[0]);
        $this->assertEquals(2.0, $tree->getMinBoundary()[1]);
    }

    #[Test]
    public function itShouldGetMaxBoundary()
    {
        $tree = new KDTree(self::getRandomItemsList(0));
        $this->assertEquals(-INF, $tree->getMaxBoundary()[0]);
        $this->assertEquals(-INF, $tree->getMaxBoundary()[1]);
        $tree = new KDTree(self::getRandomItemsList(5, 2, [
            [1.2, 2.2],
            [2.3, 2.4],
            [3.2, 2.1],
            [1.1, 2.0],
            [1.3, 2.2]
        ]));
        $this->assertEquals(3.2, $tree->getMaxBoundary()[0]);
        $this->assertEquals(2.4, $tree->getMaxBoundary()[1]);
    }

    #[Test]
    public function itShouldBuildSortedInputInLinearithmicTime()
    {
        // With a fixed first-element pivot, quickselect degrades to O(n^2) on sorted
        // input: ~7500 coordinate reads per item for 10k items. A random pivot keeps
        // it around 45 per item, the same as for random input. Count reads instead of
        // measuring wall-clock time so the test is stable under Xdebug/CI.
        $n = 10000;
        $itemList = new ItemList(2);
        for ($i = 0; $i < $n; $i++) {
            $itemList->addItem(new CountingItem($i, [$i, $i]));
        }

        CountingItem::$reads = 0;
        $tree = new KDTree($itemList);

        $this->checkTree($tree);
        $this->assertLessThan(
            200 * $n,
            CountingItem::$reads,
            'building a tree from sorted input took ' . CountingItem::$reads . ' coordinate reads'
        );
    }

    /**
     * item provider
     * @throws ValidationException
     */
    public static function itemProvider(): array
    {
        $lists = [];

        $params = [];
        $list = new ItemList(5);
        for ($id = 0, $i = -10.; $i < 10.; $i += .1, $id++) {
            $item = new Item($id, [$i, $i, $i, $i, $i]);
            $list->addItem($item);
        }
        $params[] = $list;
        $lists[] = $params;

        $params = [];
        $list = new ItemList(5);
        for ($id = 0, $i = 10.; $i > -10.; $i -= .1, $id++) {
            $item = new Item($id, [$i, $i, $i, $i, $i]);
            $list->addItem($item);
        }
        $params[] = $list;
        $lists[] = $params;


        $params = [];
        $list = new ItemList(5);
        for ($i = 0; $i < 100; $i++) {
            $item = new Item($i, [0, 0, 0, 0, 0]);
            $list->addItem($item);
        }
        $params[] = $list;
        $lists[] = $params;

        $params = [];
        $list = new ItemList(2);
        for ($i = 0; $i < 100; $i++) {
            if ($i % 2 == 0) {
                $item = new Item($i, [rand(-10, 10),rand(-10, 10)]);
            } else {
                $item = new Item($i, [2.,2.]);
            }

            $list->addItem($item);
        }
        $params[] = $list;
        $lists[] = $params;

        for ($i = 1; $i < 6; $i++) {
            $lists[] = [self::getRandomItemsList(100, $i)];
        }

        return $lists;
    }
}
