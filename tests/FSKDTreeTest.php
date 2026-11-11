<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\FileException;
use Hexogen\KDTree\FSKDTree;
use Hexogen\KDTree\Interfaces\ItemInterface;
use Hexogen\KDTree\ItemFactory;
use Hexogen\KDTree\NearestSearch;
use Hexogen\KDTree\Point;

class FSKDTreeTest extends TreeTestCase
{
    const FIXTURE_1000X2_ROOT = ['id' => 992, 'coordinates' => [0.169347, 9.249515]];
    const FIXTURE_1000X2_MAX = [9.998142, 9.98434];
    const FIXTURE_1000X2_MIN = [-9.993681, -9.993635];

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

        if ($tree->getItemCount() == 0) {
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
     * Files in tests/fixture/fs have been written by v0.2.6 and are kept as they were
     *
     * @test
     * @dataProvider FSTreeDataProvider
     * @param string $path
     * @param int $dimensions
     * @param int $itemCount
     */
    public function itShouldSearchInFilesWrittenByPreviousVersions(string $path, int $dimensions, int $itemCount)
    {
        $tree = new FSKDTree($path, new ItemFactory());
        $items = $this->getTreeItems($tree->getRoot());
        $searcher = new NearestSearch($tree);

        $this->assertCount($itemCount, $items);

        mt_srand($itemCount);
        for ($i = 0; $i < 10; $i++) {
            $coordinates = [];
            for ($d = 0; $d < $dimensions; $d++) {
                $coordinates[] = mt_rand(-11000, 11000) / 1000;
            }
            $point = new Point($coordinates);

            foreach ([1, 5, 30] as $resultLength) {
                $result = $searcher->search($point, $resultLength);

                $this->assertCount(min($resultLength, $itemCount), $result);
                $this->checkNearest($items, $result, $point, $resultLength);
            }
        }
    }

    /**
     * @test
     */
    public function itShouldReadDataOfTheFileWrittenByPreviousVersions()
    {
        $tree = new FSKDTree(__DIR__ . '/fixture/fs/tree1000x2.bin', new ItemFactory());
        $root = $tree->getRoot()->getItem();

        // values are taken from the file with v0.2.6
        $this->assertSame(self::FIXTURE_1000X2_ROOT['id'], $root->getId());
        $this->assertSame(self::FIXTURE_1000X2_ROOT['coordinates'], [
            $root->getNthDimension(0),
            $root->getNthDimension(1),
        ]);
        $this->assertSame(self::FIXTURE_1000X2_MAX, $tree->getMaxBoundary());
        $this->assertSame(self::FIXTURE_1000X2_MIN, $tree->getMinBoundary());
    }

    /**
     * @test
     */
    public function itShouldGetNullRootOfEmptyTree()
    {
        $tree = new FSKDTree(__DIR__ . '/fixture/fs/tree0x10.bin', new ItemFactory());

        $this->assertNull($tree->getRoot());
        $this->assertSame([], (new NearestSearch($tree))->search(new Point(array_fill(0, 10, 0)), 3));
    }

    /**
     * @test
     */
    public function itShouldNotOpenMissingFile()
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Unable to open kd tree file for reading');

        new FSKDTree(__DIR__ . '/storage/there-is-no-such-file.bin', new ItemFactory());
    }

    /**
     * @test
     * @dataProvider brokenFileProvider
     * @param string $contents
     * @param string $message
     */
    public function itShouldNotOpenBrokenFile(string $contents, string $message)
    {
        $path = __DIR__ . '/storage/broken.bin';
        file_put_contents($path, $contents);

        try {
            new FSKDTree($path, new ItemFactory());
            $this->fail('FileException should be thrown');
        } catch (FileException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        } finally {
            unlink($path);
        }
    }

    /**
     * @return array
     */
    public function brokenFileProvider()
    {
        $point = pack('d2', 1., 2.);

        return [
            'empty file' => ['', 'unexpected end of file'],
            'part of dimensions count' => ["\x02\x00", 'unexpected end of file'],
            'no items count' => [pack('V', 2), 'unexpected end of file'],
            'no boundaries' => [pack('VV', 2, 1), 'unexpected end of file'],
            'part of upper boundary' => [pack('VV', 2, 1) . substr($point, 0, 9), 'unexpected end of file'],
            'no lower boundary' => [pack('VV', 2, 1) . $point, 'unexpected end of file'],
            'part of lower boundary' => [pack('VV', 2, 1) . $point . substr($point, 0, 15), 'unexpected end of file'],
            'no dimensions' => [pack('VV', 0, 1), 'dimensions count should be bigger than 0'],
            'not a tree' => [str_repeat('some text that is not a kd tree ', 100), 'unexpected end of file'],
            'huge dimensions count' => [str_repeat("\xFF", 4096), 'unexpected end of file'],
            'file of v0.3' => [
                'KDT' . chr(1) . pack('VP', 2, 0) . pack('e4', 1., 2., 3., 4.),
                'written by hexogen/kdtree 0.3 or newer',
            ],
        ];
    }

    /**
     * @test
     */
    public function itShouldNotOpenDirectory()
    {
        $this->expectException(FileException::class);

        new FSKDTree(__DIR__ . '/storage', new ItemFactory());
    }

    /**
     * @test
     * @dataProvider truncatedFileProvider
     * @param int $length
     */
    public function itShouldNotReadNodesOfTruncatedFile(int $length)
    {
        $path = __DIR__ . '/storage/truncated.bin';
        $contents = file_get_contents(__DIR__ . '/fixture/fs/tree100x10.bin');
        file_put_contents($path, substr($contents, 0, $length));

        try {
            // header is complete, so the tree is opened and fails when a missing node is needed
            $tree = new FSKDTree($path, new ItemFactory());
            $this->assertSame(100, $tree->getItemCount());

            $this->getTreeItems($tree->getRoot());
            $this->fail('FileException should be thrown');
        } catch (FileException $e) {
            $this->assertStringContainsString('unexpected end of file', $e->getMessage());
        } finally {
            unlink($path);
        }
    }

    /**
     * @return array
     */
    public function truncatedFileProvider()
    {
        // header takes 168 bytes and every node takes 92 bytes
        return [
            'no nodes' => [168],
            'part of the root' => [168 + 50],
            'root only' => [168 + 92],
            'half of nodes' => [168 + 50 * 92],
            'part of the last node' => [168 + 100 * 92 - 1],
        ];
    }

    /**
     * @test
     */
    public function itShouldKeepNodesUsableAfterTreeIsReleased()
    {
        $tree = new FSKDTree(__DIR__ . '/fixture/fs/tree100x10.bin', new ItemFactory());
        $root = $tree->getRoot();
        unset($tree);
        gc_collect_cycles();

        $this->assertInstanceOf(ItemInterface::class, $root->getItem());
        $this->assertCount(100, $this->getTreeItems($root));
    }

    /**
     * @test
     */
    public function itShouldReleaseFileWhenTreeIsDestructed()
    {
        $tree = new FSKDTree(__DIR__ . '/fixture/fs/tree100x10.bin', new ItemFactory());
        $root = $tree->getRoot();
        $root->getItem();

        $tree->__destruct();

        $this->assertNull($tree->getRoot());
        // the node that is still referenced keeps working
        $this->assertCount(100, $this->getTreeItems($root));
    }

    /**
     * @test
     */
    public function itShouldCloseFileWhenNothingUsesIt()
    {
        $path = __DIR__ . '/storage/closed.bin';
        copy(__DIR__ . '/fixture/fs/tree100x10.bin', $path);
        $before = count(get_resources('stream'));

        $tree = new FSKDTree($path, new ItemFactory());
        $left = $tree->getRoot()->getLeft();
        $this->assertSame($before + 1, count(get_resources('stream')));

        unset($tree);
        $this->assertSame($before + 1, count(get_resources('stream')));

        unset($left);
        $this->assertSame($before, count(get_resources('stream')));

        unlink($path);
    }

    /**
     * Boundaries and nodes of this tree are bigger than a chunk
     * that is read at once from a stream that is not a plain file
     *
     * @test
     */
    public function itShouldReadTreeFromStreamThatIsNotPlainFile()
    {
        $dimensions = 1100;
        $coordinates = [];
        for ($i = 0; $i < 3; $i++) {
            for ($d = 0; $d < $dimensions; $d++) {
                $coordinates[$i][$d] = (float)$i + $d / 1000;
            }
        }
        $contents = pack('VV', $dimensions, 3)
            . pack('d*', ...$coordinates[2])
            . pack('d*', ...$coordinates[0]);
        $nodeLength = 12 + 8 * $dimensions;
        $rootPosition = strlen($contents);
        $contents .= pack('VVV', 11, $rootPosition + 2 * $nodeLength, $rootPosition + $nodeLength)
            . pack('d*', ...$coordinates[1]);
        $contents .= pack('VVV', 12, 0, 0) . pack('d*', ...$coordinates[2]);
        $contents .= pack('VVV', 10, 0, 0) . pack('d*', ...$coordinates[0]);

        MemoryStreamWrapper::register();
        try {
            $path = MemoryStreamWrapper::PROTOCOL . '://storage/chunks.bin';
            MemoryStreamWrapper::$files[$path] = $contents;

            $tree = new FSKDTree($path, new ItemFactory());

            $this->assertSame($coordinates[2], $tree->getMaxBoundary());
            $this->assertSame($coordinates[0], $tree->getMinBoundary());
            $this->checkTree($tree);
            $this->assertSame([11, 10, 12], $this->getTreeIds($tree->getRoot()));

            $result = (new NearestSearch($tree))->search(new Point($coordinates[2]), 3);
            $this->assertSame(12, $result[0]->getId());
            $this->assertSame($coordinates[2][1099], $result[0]->getNthDimension(1099));
            $this->assertSame(11, $result[1]->getId());
            $this->assertSame(10, $result[2]->getId());
        } finally {
            MemoryStreamWrapper::unregister();
        }
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
