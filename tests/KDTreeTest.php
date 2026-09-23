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
    public function itShouldBuildSortedInputInReasonableTime()
    {
        // first-element pivot quickselect is O(n^2) on sorted input (~3s for 10k
        // items); with the shuffle in the constructor it should stay well under a second
        $itemList = new ItemList(2);
        for ($i = 0; $i < 10000; $i++) {
            $itemList->addItem(new Item($i, [$i, $i]));
        }

        $start = microtime(true);
        $tree = new KDTree($itemList);
        $elapsed = microtime(true) - $start;

        $this->checkTree($tree);
        $this->assertLessThan(1.0, $elapsed, 'building a tree from sorted input took ' . round($elapsed, 2) . 's');
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
