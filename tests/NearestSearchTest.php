<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Interfaces\ItemInterface;
use Hexogen\KDTree\Interfaces\KDTreeInterface;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemList;
use Hexogen\KDTree\KDTree;
use Hexogen\KDTree\NearestSearch;
use Hexogen\KDTree\Node;
use Hexogen\KDTree\Point;
use League\Csv\Reader;
use League\Csv\Statement;

class NearestSearchTest extends TreeTestCase
{
    /**
     * @var ItemInterface[]
     */
    private $items;

    /**
     * @dataProvider pointsProvider
     * @test
     * @param string $filename
     */
    public function itShouldFindNearestPointsInDataSet(string $filename)
    {
        $itemList = $this->getItemList($filename);

        $tree = new KDTree($itemList);

        $searcher = new NearestSearch($tree);

        $point = new Point([0.3, 0.3]);
        $result = $searcher->search($point, 1);

        $this->checkResult($result, $point);

        $point = new Point([0.3, 0.44]);
        $result = $searcher->search($point, 1);

        $this->checkResult($result, $point);

        $point = new Point([0.17, 0.75]);
        $result = $searcher->search($point, 100);

        $this->checkResult($result, $point);

        $point = new Point([0.17, 0.75]);
        $result = $searcher->search($point, 10);

        $this->checkResult($result, $point);

        $point = new Point([0.17, 0.75]);
        $result = $searcher->search($point, 1);

        $this->checkResult($result, $point);

        $point = new Point([0.5, 0.5]);
        $result = $searcher->search($point, 100);

        $this->checkResult($result, $point);

        $point = new Point([0.5, 0.5]);
        $result = $searcher->search($point, 10);

        $this->checkResult($result, $point);

        $point = new Point([0.1, 0.2]);
        $result = $searcher->search($point, 10);

        $this->checkResult($result, $point);

        $point = new Point([0., 0.]);
        $result = $searcher->search($point, 10);

        $this->checkResult($result, $point);

        $point = new Point([0., 1.]);
        $result = $searcher->search($point, 10);

        $this->checkResult($result, $point);

        $point = new Point([1., 0.]);
        $result = $searcher->search($point, 10);

        $this->checkResult($result, $point);

        $point = new Point([1., 1.]);
        $result = $searcher->search($point, 10);

        $this->checkResult($result, $point);

        $point = new Point([0.5, 1.]);
        $result = $searcher->search($point, 10);

        $this->checkResult($result, $point);

        $point = new Point([1., 0.5]);
        $result = $searcher->search($point, 10);

        $this->checkResult($result, $point);

        $point = new Point([0., 0.5]);
        $result = $searcher->search($point, 10);

        $this->checkResult($result, $point);

        $point = new Point([0.5, 0.]);
        $result = $searcher->search($point, 10);

        $this->checkResult($result, $point);
    }


    /**
     * @test
     */
    public function itShouldNotValidatePoint()
    {
        $this->expectException(\Hexogen\KDTree\Exception\ValidationException::class);

        $itemList = new ItemList(3);
        $itemList->addItem(new Item(1, [2., 3., 4.]));
        $tree = new KDTree($itemList);
        $searcher = new NearestSearch($tree);
        $dValues = [0.17, 0.75];
        $result = $searcher->search(new Point($dValues), 10);
    }

    /**
     * @test
     */
    public function itShouldReturnEmptyArrayIfTreeIsEmpty()
    {
        $itemList = new ItemList(2);
        $tree = new KDTree($itemList);

        $searcher = new NearestSearch($tree);

        $point = new Point([0.3, 0.3]);
        $result = $searcher->search($point, 1);

        $this->assertCount(0, $result);
    }

    /**
     * @test
     */
    public function resultShouldNotBeLongerThanTree()
    {
        $itemList = new ItemList(2);
        $itemList->addItem(new Item(1, [2., 3.]));
        $tree = new KDTree($itemList);
        $searcher = new NearestSearch($tree);
        $dValues = [0.17, 0.75];
        $result = $searcher->search(new Point($dValues), 10);
        $this->assertCount(1, $result);
    }

    /**
     * test search with tree modification
     * @test
     */
    public function itShouldCheckLeftNode()
    {
        $itemList = new ItemList(2);
        $itemList->addItem(new Item(1, [2., 3.]));
        $tree = new KDTree($itemList);
        $leftNode = new Node(new Item(2, [1., 4]));
        $tree->getRoot()->setLeft($leftNode);
        $searcher = new NearestSearch($tree);
        $result = $searcher->search(new Point([0, 5]), 1);
        $this->assertEquals(2, $result[0]->getId());
    }

    /**
     * @test
     */
    public function itShouldReturnItemsOrderedFromTheNearestOne()
    {
        $itemList = new ItemList(2);
        $itemList->addItem(new Item(1, [1.2, 4.3]));
        $itemList->addItem(new Item(2, [1.3, 3.4]));
        $itemList->addItem(new Item(3, [4.5, 1.2]));
        $itemList->addItem(new Item(4, [5.2, 3.5]));
        $itemList->addItem(new Item(5, [2.1, 3.6]));
        $searcher = new NearestSearch(new KDTree($itemList));

        $result = $searcher->search(new Point([1.25, 3.5]), 5);

        $this->assertSame([2, 1, 5, 4, 3], $this->getIds($result));
        $this->assertSame([2], $this->getIds($searcher->search(new Point([1.25, 3.5]))));
    }

    /**
     * @test
     * @dataProvider randomTreeProvider
     * @param int $dimensions
     * @param int $itemCount
     * @param int $duplicates number of copies of every point
     */
    public function itShouldFindTheSameItemsAsFullScan(int $dimensions, int $itemCount, int $duplicates)
    {
        mt_srand($dimensions * 1000 + $itemCount);
        $itemList = new ItemList($dimensions);
        for ($id = 0; $id < $itemCount; $id++) {
            if ($id % $duplicates !== 0) {
                $itemList->addItem(new Item($id, $coordinates));
                continue;
            }
            $coordinates = [];
            for ($d = 0; $d < $dimensions; $d++) {
                $coordinates[] = mt_rand(-50, 50) / 4;
            }
            $itemList->addItem(new Item($id, $coordinates));
        }
        $searcher = new NearestSearch(new KDTree($itemList));

        for ($i = 0; $i < 25; $i++) {
            $coordinates = [];
            for ($d = 0; $d < $dimensions; $d++) {
                $coordinates[] = mt_rand(-60, 60) / 4;
            }
            $point = new Point($coordinates);

            foreach ([1, 2, 7, $itemCount, $itemCount + 5] as $resultLength) {
                $result = $searcher->search($point, $resultLength);

                $this->assertCount(min($resultLength, $itemCount), $result);
                $this->checkNearest($itemList->getItems(), $result, $point, $resultLength);
            }
        }
    }

    /**
     * @return array
     */
    public function randomTreeProvider()
    {
        return [
            '1 dimension' => [1, 200, 1],
            '2 dimensions' => [2, 300, 1],
            '3 dimensions' => [3, 300, 1],
            '5 dimensions' => [5, 300, 1],
            '8 dimensions' => [8, 200, 1],
            '1 dimension with duplicates' => [1, 200, 4],
            '2 dimensions with duplicates' => [2, 300, 3],
            '4 dimensions with duplicates' => [4, 300, 2],
            'single item' => [2, 1, 1],
            'two items' => [3, 2, 1],
            'the same point only' => [2, 64, 64],
        ];
    }

    /**
     * @test
     * @dataProvider notPositiveResultLengthProvider
     * @param int $resultLength
     */
    public function itShouldReturnEmptyArrayIfResultLengthIsNotPositive(int $resultLength)
    {
        $itemList = new ItemList(2);
        $itemList->addItem(new Item(1, [2., 3.]));
        $itemList->addItem(new Item(2, [4., 5.]));
        $itemList->addItem(new Item(3, [6., 7.]));
        $searcher = new NearestSearch(new KDTree($itemList));

        $this->assertSame([], $searcher->search(new Point([2., 3.]), $resultLength));
    }

    /**
     * @return array
     */
    public function notPositiveResultLengthProvider()
    {
        return [[0], [-1], [PHP_INT_MIN]];
    }

    /**
     * Square of a distance between coordinates that are far from each other does not fit into float,
     * such items used to be skipped and placeholders (nulls) were returned instead of them
     *
     * @test
     * @dataProvider farPointProvider
     * @param array $coordinates
     */
    public function itShouldReturnItemsIfDistancesAreNotRepresentable(array $coordinates)
    {
        $itemList = new ItemList(2);
        for ($i = 0; $i < 15; $i++) {
            $itemList->addItem(new Item($i, [$i, $i % 4]));
        }
        $searcher = new NearestSearch(new KDTree($itemList));
        $point = new Point($coordinates);

        foreach ([1, 4, 15, 20] as $resultLength) {
            $result = $searcher->search($point, $resultLength);

            $this->assertCount(min($resultLength, 15), $result);
            $this->assertContainsOnlyInstancesOf(ItemInterface::class, $result);
            $this->assertSame($this->getIds($result), array_unique($this->getIds($result)));
        }
    }

    /**
     * @return array
     */
    public function farPointProvider()
    {
        return [
            'far to the right' => [[1e200, 0]],
            'far to the left' => [[-1e200, 0]],
            'far above' => [[7, 1e200]],
            'far in both dimensions' => [[-1e200, -1e200]],
            'the biggest float' => [[PHP_FLOAT_MAX, -PHP_FLOAT_MAX]],
        ];
    }

    /**
     * @test
     */
    public function itShouldPreferItemsWithRepresentableDistances()
    {
        // square distances from the point: INF, INF, 0, 1e300, INF, INF
        $itemList = new ItemList(1);
        $itemList->addItem(new Item(1, [-1e160]));
        $itemList->addItem(new Item(2, [0]));
        $itemList->addItem(new Item(3, [1e160]));
        $itemList->addItem(new Item(4, [1e160 + 1e150]));
        $itemList->addItem(new Item(5, [5e159]));
        $itemList->addItem(new Item(6, [2e160]));
        $searcher = new NearestSearch(new KDTree($itemList));
        $point = new Point([1e160]);

        $this->assertSame([3], $this->getIds($searcher->search($point, 1)));
        $this->assertSame([3, 4], $this->getIds($searcher->search($point, 2)));
        $this->assertSame([3, 4], array_slice($this->getIds($searcher->search($point, 4)), 0, 2));
        $this->assertCount(6, $searcher->search($point, 6));
    }

    /**
     * @test
     */
    public function itShouldReturnOnlyExistingItemsIfTreeReportsMoreItemsThanItHas()
    {
        $itemList = new ItemList(2);
        $itemList->addItem(new Item(1, [2., 3.]));
        $itemList->addItem(new Item(2, [4., 5.]));
        $itemList->addItem(new Item(3, [6., 7.]));
        $realTree = new KDTree($itemList);

        $tree = $this->createStub(KDTreeInterface::class);
        $tree->method('getItemCount')->willReturn(10);
        $tree->method('getDimensionCount')->willReturn(2);
        $tree->method('getMinBoundary')->willReturn($realTree->getMinBoundary());
        $tree->method('getMaxBoundary')->willReturn($realTree->getMaxBoundary());
        $tree->method('getRoot')->willReturn($realTree->getRoot());

        $result = (new NearestSearch($tree))->search(new Point([6., 6.]), 10);

        $this->assertSame([3, 2, 1], $this->getIds($result));
    }

    /**
     * @test
     */
    public function itShouldBeReusable()
    {
        $itemList = $this->getRandomItemsList(100, 2);
        $searcher = new NearestSearch(new KDTree($itemList));
        $point = new Point([1., 2.]);

        $first = $searcher->search($point, 9);
        $searcher->search(new Point([-5., 7.]), 30);
        $searcher->search($point, 0);

        $this->assertSame($this->getIds($first), $this->getIds($searcher->search($point, 9)));
    }

    /**
     * @param ItemInterface[] $items
     * @return int[]
     */
    private function getIds(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $ids[] = $item->getId();
        }
        return $ids;
    }

    /**
     * Circle data provider
     */
    public function pointsProvider()
    {
        return [
            ['circle4.txt'],
            ['circle10.txt'],
            ['circle100.txt'],
            ['circle1000.txt'],
            ['circle10000.txt'],
            ['horizontal8.txt'],
            ['input100.txt'],
            ['input10K.txt'],
            ['inputhzK.txt'],
            ['vertical7.txt'],
        ];
    }

    /**
     * @param string $name
     * @param int $dimensions
     * @return ItemList
     */
    private function getItemList(string $name, int $dimensions = 2) : ItemList
    {
        $reader = Reader::createFromPath(__DIR__ . '/fixture/' . $dimensions . 'd/' . $name);
        $reader->setDelimiter(' ');
        $itemList = new ItemList($dimensions);

        $stmt = Statement::create();
        $points =  $stmt->process($reader);


        $i = 0;
        foreach ($points as $point) {
            $dValues = [];
            for ($j = 0; $j < $dimensions; $j++) {
                $dValues[$j] = $point[$j];
            }
            $itemList->addItem(new Item($i++, $dValues));
            $this->items[] = $dValues;
        }
        return $itemList;
    }

    /**
     * @param ItemInterface[] $result
     * @param Point $point
     */
    private function checkResult(array $result, Point $point)
    {
        $resultLength = count($result);
        $dimensions = $point->getDimensionsCount();

        $queue = new \SplPriorityQueue();
        $queue->setExtractFlags(\SplPriorityQueue::EXTR_PRIORITY);

        $maxDistance = INF;
        for ($i = 0; $i < $resultLength; $i++) {
            $queue->insert(null, INF);
        }

        foreach ($this->items as $item) {
            $distance = 0.;
            for ($i = 0; $i < $dimensions; $i++) {
                $distance += pow($point->getNthDimension($i) - $item[$i], 2);
            }
            if ($distance < $maxDistance) {
                $queue->insert($item, $distance);
                $queue->extract();

                $maxDistance = $queue->current();
            }
        }

        $checkResult = [];
        while (!$queue->isEmpty()) {
            $checkResult[] = $queue->extract();
        }
        $checkResult = array_reverse($checkResult);

        $k = 0;
        foreach ($result as $item) {
            $distance = 0.;
            for ($i = 0; $i < $dimensions; $i++) {
                $distance += pow($point->getNthDimension($i) - $item->getNthDimension($i), 2);
            }
            $this->assertEquals($checkResult[$k++], $distance);
        }
    }
}
