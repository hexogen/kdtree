<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\FSKDTree;
use Hexogen\KDTree\ItemFactory;
use \Mockery as m;

class FSKDTreeTest extends TreeTestCase
{
    /**
     * @dataProvider FSTreeDataProvider
     * @test
     * @param string $path
     * @param int $itemCount
     * @param int $dimensions
     */
    public function itShouldGetItemsCountAndNumOfDimensions(string $path, int $dimensions, int $itemCount)
    {
        $factory = new ItemFactory();
        $tree = new FSKDTree($path, $factory);

        $this->assertEquals($itemCount, $tree->getItemCount());
        $this->assertEquals($dimensions, $tree->getDimensionCount());
    }

    /**
     * @dataProvider FSTreeDataProvider
     * @test
     * @param $path
     */
    public function itShouldCheckTreeStructure(string $path)
    {
        $factory = new ItemFactory();
        $tree = new FSKDTree($path, $factory);

        $this->checkTree($tree);

        if($tree->getItemCount() == 0)
        {
            $this->assertTrue(true); // preform an assert to avoid warning
        }
    }

    /**
     * @test
     * @dataProvider FSTreeDataProvider
     * @param string $path
     * @param int $dimensions
     */
    public function itShouldGetMaxBoundary(string $path, int $dimensions)
    {
        $factory = new ItemFactory();
        $tree = new FSKDTree($path, $factory);
        $this->assertCount($dimensions, $tree->getMaxBoundary());
    }

    /**
     * @test
     * @dataProvider FSTreeDataProvider
     * @param string $path
     * @param int $dimensions
     */
    public function itShouldGetMinBoundary(string $path, int $dimensions)
    {
        $factory = new ItemFactory();
        $tree = new FSKDTree($path, $factory);
        $this->assertCount($dimensions, $tree->getMinBoundary());
    }

    /**
     * @return array
     */
    public function FSTreeDataProvider()
    {
        return [
            [__DIR__ . '/fixture/fs/tree0x10.bin', 10, 0],
            [__DIR__ . '/fixture/fs/tree100x10.bin', 10, 100],
            [__DIR__ . '/fixture/fs/tree1000x2.bin', 2, 1000],
            [__DIR__ . '/fixture/fs/tree10000x10.bin', 10, 10000],
        ];
    }
}
