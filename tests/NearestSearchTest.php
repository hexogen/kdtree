<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Interfaces\ItemInterface;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemList;
use Hexogen\KDTree\KDTree;
use Hexogen\KDTree\NearestSearch;
use Hexogen\KDTree\Node;
use Hexogen\KDTree\Point;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NearestSearchTest extends TestCase
{
    /**
     * @var ItemInterface[]
     */
    private $items;

    /**
     * @param string $filename
     */
    #[DataProvider('pointsProvider')]
    #[Test]
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


    #[Test]
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

    #[Test]
    public function itShouldReturnEmptyArrayIfTreeIsEmpty()
    {
        $itemList = new ItemList(2);
        $tree = new KDTree($itemList);

        $searcher = new NearestSearch($tree);

        $point = new Point([0.3, 0.3]);
        $result = $searcher->search($point, 1);

        $this->assertCount(0, $result);
    }

    #[Test]
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
     */
    #[Test]
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
     * Circle data provider
     */
    public static function pointsProvider(): array
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
        $path = __DIR__ . '/fixture/' . $dimensions . 'd/' . $name;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $itemList = new ItemList($dimensions);

        $i = 0;
        foreach ($lines as $line) {
            $point = preg_split('/\s+/', trim($line));
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
