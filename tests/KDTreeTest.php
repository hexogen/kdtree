<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\ValidationException;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemList;
use Hexogen\KDTree\KDTree;
use \Mockery as m;

class KDTreeTest extends TreeTestCase
{
    /**
     * @test
     * @throws ValidationException
     */
    public function itShouldCreateAnInstance()
    {
        $itemList = new ItemList(5);
        $tree = new KDTree($itemList);

        $this->assertInstanceOf(KDTree::class, $tree);
    }

    /**
     * @test
     * @throws ValidationException
     */
    public function itShouldGetRoot()
    {
        $itemList = new ItemList(2);
        $item = new Item(1, [2, 3]);
        $itemList->addItem($item);
        $tree = new KDTree($itemList);

        $this->assertSame($item, $tree->getRoot()->getItem());
    }

    /**
     * @test
     * @throws ValidationException
     */
    public function itShouldGetNullRoot()
    {
        $itemList = new ItemList(5);
        $tree = new KDTree($itemList);

        $this->assertNull($tree->getRoot());
    }

    /**
     * @test
     * @dataProvider itemProvider
     * @param ItemList $itemList
     */
    public function itShouldCreateTree(ItemList $itemList)
    {
        $tree = new KDTree($itemList);

        $this->checkTree($tree);
    }

    /**
     * @test
     */
    public function itShouldGetNumberOfDimensionsInItems()
    {
        $tree = new KDTree(self::getRandomItemsList(10, 1));
        $this->assertEquals(1, $tree->getDimensionCount());
        $tree = new KDTree(self::getRandomItemsList(10, 5));
        $this->assertEquals(5, $tree->getDimensionCount());
    }

    /**
     * @test
     */
    public function itShouldGetNumberOfItemsInTheTree()
    {
        $tree = new KDTree(self::getRandomItemsList(0));
        $this->assertEquals(0, $tree->getItemCount());
        $tree = new KDTree(self::getRandomItemsList(10, 5));
        $this->assertEquals(10, $tree->getItemCount());
    }

    /**
     * @test
     */
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

    /**
     * @test
     */
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
            $list = self::getRandomItemsList(100, $i);
            $params[] = $list;
            $lists[] = $params;
        }

        return $lists;
    }
}
