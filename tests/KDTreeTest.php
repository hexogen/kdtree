<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Interfaces\ItemInterface;
use Hexogen\KDTree\Interfaces\ItemListInterface;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemList;
use Hexogen\KDTree\KDTree;

class KDTreeTest extends TreeTestCase
{
    /**
     * @test
     */
    public function itShouldCreateAnInstance()
    {
        $itemList = new ItemList(5);
        $tree = new KDTree($itemList);

        $this->assertInstanceOf(KDTree::class, $tree);
    }

    /**
     * @test
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
        $tree = new KDTree($this->getRandomItemsList(10, 1));
        $this->assertEquals(1, $tree->getDimensionCount());
        $tree = new KDTree($this->getRandomItemsList(10, 5));
        $this->assertEquals(5, $tree->getDimensionCount());
    }

    /**
     * @test
     */
    public function itShouldGetNumberOfItemsInTheTree()
    {
        $tree = new KDTree($this->getRandomItemsList(0));
        $this->assertEquals(0, $tree->getItemCount());
        $tree = new KDTree($this->getRandomItemsList(10, 5));
        $this->assertEquals(10, $tree->getItemCount());
    }

    /**
     * @test
     */
    public function itShouldGetMinBoundary()
    {
        $tree = new KDTree($this->getRandomItemsList(0));
        $this->assertEquals(INF, $tree->getMinBoundary()[0]);
        $this->assertEquals(INF, $tree->getMinBoundary()[1]);
        $tree = new KDTree($this->getRandomItemsList(5, 2, [
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
        $tree = new KDTree($this->getRandomItemsList(0));
        $this->assertEquals(-INF, $tree->getMaxBoundary()[0]);
        $this->assertEquals(-INF, $tree->getMaxBoundary()[1]);
        $tree = new KDTree($this->getRandomItemsList(5, 2, [
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
     * @test
     */
    public function itShouldBuildSortedInputInLinearithmicTime()
    {
        // With the first element as a pivot quickselect degrades to O(n^2) on sorted input:
        // about 7500 coordinate reads per item for 10k items. A pseudo-random pivot keeps it
        // around 45 per item, the same as for random input. Reads are counted instead of
        // measuring the time to keep the test stable.
        $n = 10000;
        $itemList = new ItemList(2);
        for ($i = 0; $i < $n; $i++) {
            $itemList->addItem(new CountingItem($i, [$i, $i]));
        }

        CountingItem::$reads = 0;
        $tree = new KDTree($itemList);

        $this->checkTree($tree);
        $this->assertCount($n, $this->getTreeIds($tree->getRoot()));
        $this->assertLessThan(
            200 * $n,
            CountingItem::$reads,
            'building a tree from sorted input took ' . CountingItem::$reads . ' coordinate reads'
        );
    }

    /**
     * @test
     */
    public function itShouldBuildTheSameTreeForTheSameItems()
    {
        $itemList = $this->getRandomItemsList(500, 3);

        $first = new KDTree($itemList);
        $second = new KDTree($itemList);

        $ids = $this->getTreeIds($first->getRoot());
        $this->assertCount(500, $ids);
        $this->assertSame($ids, $this->getTreeIds($second->getRoot()));
    }

    /**
     * @test
     */
    public function itShouldNotAffectRandomNumberGenerators()
    {
        $itemList = $this->getRandomItemsList(200, 2);

        mt_srand(42);
        srand(42);
        $expected = [mt_rand(), rand()];

        mt_srand(42);
        srand(42);
        new KDTree($itemList);

        $this->assertSame($expected, [mt_rand(), rand()]);
    }

    /**
     * @test
     */
    public function itShouldNotChangeGivenItemList()
    {
        $itemList = $this->getRandomItemsList(50, 2);
        $items = $itemList->getItems();

        new KDTree($itemList);

        $this->assertSame($items, $itemList->getItems());
    }

    /**
     * @test
     */
    public function itShouldBuildTreeFromItemsWithArbitraryArrayKeys()
    {
        $items = [];
        for ($i = 1; $i <= 25; $i++) {
            $items[$i * 10] = new Item($i, [$i % 5, $i % 7]);
        }
        $items['last'] = new Item(26, [3, 3]);

        $itemList = new class($items) implements ItemListInterface
        {
            private $items;

            public function __construct(array $items)
            {
                $this->items = $items;
            }

            public function addItem(ItemInterface $item)
            {
            }

            public function getItems(): array
            {
                return $this->items;
            }

            public function getDimensionCount(): int
            {
                return 2;
            }
        };

        $tree = new KDTree($itemList);

        $this->checkTree($tree);
        $ids = $this->getTreeIds($tree->getRoot());
        sort($ids);
        $this->assertSame(range(1, 26), $ids);
    }

    /**
     * item provider
     */
    public function itemProvider()
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
            $lists[] = [$this->getRandomItemsList(100, $i)];
        }

        return $lists;
    }
}
