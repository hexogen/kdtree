<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\FileException;
use Hexogen\KDTree\FSKDTree;
use Hexogen\KDTree\Interfaces\ItemInterface;
use Hexogen\KDTree\ItemFactory;
use Hexogen\KDTree\NearestSearch;
use Hexogen\KDTree\Point;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FSKDTreeTest extends TreeTestCase
{
    /**
     * @param string $path
     * @param int $itemCount
     * @param int $dimensions
     */
    #[DataProvider('FSTreeDataProvider')]
    #[Test]
    public function itShouldGetItemsCountAndNumOfDimensions(string $path, int $dimensions, int $itemCount)
    {
        $factory = new ItemFactory();
        $tree = new FSKDTree($path, $factory);

        $this->assertEquals($itemCount, $tree->getItemCount());
        $this->assertEquals($dimensions, $tree->getDimensionCount());
    }

    /**
     * @param string $path
     */
    #[DataProvider('FSTreeDataProvider')]
    #[Test]
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
     * @param string $path
     * @param int $dimensions
     */
    #[Test]
    #[DataProvider('FSTreeDataProvider')]
    public function itShouldGetMaxBoundary(string $path, int $dimensions)
    {
        $factory = new ItemFactory();
        $tree = new FSKDTree($path, $factory);
        $this->assertCount($dimensions, $tree->getMaxBoundary());
    }

    /**
     * @param string $path
     * @param int $dimensions
     */
    #[Test]
    #[DataProvider('FSTreeDataProvider')]
    public function itShouldGetMinBoundary(string $path, int $dimensions)
    {
        $factory = new ItemFactory();
        $tree = new FSKDTree($path, $factory);
        $this->assertCount($dimensions, $tree->getMinBoundary());
    }

    #[Test]
    public function itShouldReturnNullRootForEmptyTree()
    {
        $tree = new FSKDTree(__DIR__ . '/fixture/fs/tree0x10.bin', new ItemFactory());
        $this->assertNull($tree->getRoot());
    }

    #[Test]
    public function itShouldThrowWhenFileDoesNotExist()
    {
        $this->expectException(FileException::class);
        new FSKDTree(__DIR__ . '/fixture/fs/does-not-exist.bin', new ItemFactory());
    }

    #[Test]
    public function itShouldThrowOnLegacyFileWithoutHeader()
    {
        // legacy (pre-header, 32-bit) layout: dimensions, item count, boundaries
        $legacy = pack('VV', 2, 0) . pack('d2', -INF, -INF) . pack('d2', INF, INF);
        file_put_contents(__DIR__ . '/storage/legacy.bin', $legacy);

        $this->expectException(FileException::class);
        new FSKDTree(__DIR__ . '/storage/legacy.bin', new ItemFactory());
    }

    #[Test]
    public function itShouldThrowOnUnsupportedFormatVersion()
    {
        $data = FSKDTree::MAGIC . chr(FSKDTree::FORMAT_VERSION + 1) . pack('VP', 2, 0);
        file_put_contents(__DIR__ . '/storage/future.bin', $data);

        $this->expectException(FileException::class);
        new FSKDTree(__DIR__ . '/storage/future.bin', new ItemFactory());
    }

    #[Test]
    public function itShouldThrowOnZeroDimensions()
    {
        $data = FSKDTree::MAGIC . chr(FSKDTree::FORMAT_VERSION) . pack('VP', 0, 0);
        file_put_contents(__DIR__ . '/storage/zero-dims.bin', $data);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('dimensions count');
        new FSKDTree(__DIR__ . '/storage/zero-dims.bin', new ItemFactory());
    }

    #[Test]
    public function itShouldThrowOnNegativeItemCount()
    {
        $data = FSKDTree::MAGIC . chr(FSKDTree::FORMAT_VERSION) . pack('VP', 2, -1);
        file_put_contents(__DIR__ . '/storage/negative-count.bin', $data);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('negative item count');
        new FSKDTree(__DIR__ . '/storage/negative-count.bin', new ItemFactory());
    }

    #[Test]
    public function itShouldThrowWhenNodeDataIsTruncated()
    {
        // valid header and boundaries for a 2-D tree with 1 item, then only half a node
        $data = FSKDTree::MAGIC . chr(FSKDTree::FORMAT_VERSION)
            . pack('VP', 2, 1)
            . pack('e2', 1., 1.)
            . pack('e2', 1., 1.)
            . pack('PP', 1, 0);
        file_put_contents(__DIR__ . '/storage/truncated-node.bin', $data);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('header declares 1 items');
        new FSKDTree(__DIR__ . '/storage/truncated-node.bin', new ItemFactory());
    }

    #[Test]
    public function itShouldThrowOnTrailingData()
    {
        $data = file_get_contents(__DIR__ . '/fixture/fs/tree100x10.bin') . 'x';
        file_put_contents(__DIR__ . '/storage/trailing.bin', $data);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('header declares 100 items');
        new FSKDTree(__DIR__ . '/storage/trailing.bin', new ItemFactory());
    }

    #[Test]
    public function itShouldReadStreamsThatDoNotReportTheirSize()
    {
        NoStatStreamWrapper::register();
        try {
            $path = NoStatStreamWrapper::PROTOCOL . '://' . __DIR__ . '/fixture/fs/tree100x10.bin';
            $tree = new FSKDTree($path, new ItemFactory());

            $this->assertSame(100, $tree->getItemCount());
            $this->checkTree($tree);
        } finally {
            NoStatStreamWrapper::unregister();
        }
    }

    #[Test]
    public function itShouldKeepNodesUsableAfterTreeIsReleased()
    {
        $tree = new FSKDTree(__DIR__ . '/fixture/fs/tree100x10.bin', new ItemFactory());
        $root = $tree->getRoot();
        unset($tree);

        $this->assertInstanceOf(ItemInterface::class, $root->getItem());
        $this->assertNotNull($root->getLeft());
        $this->assertInstanceOf(ItemInterface::class, $root->getLeft()->getItem());
    }

    #[Test]
    public function itShouldCacheAllReadNodesByDefault()
    {
        $tree = new FSKDTree(__DIR__ . '/fixture/fs/tree100x10.bin', new ItemFactory());
        $root = $tree->getRoot();

        $this->assertSame($root->getLeft(), $root->getLeft());
        $this->assertSame($root->getLeft()->getRight(), $root->getLeft()->getRight());
    }

    #[Test]
    public function itShouldCacheOnlyGivenNumberOfLevels()
    {
        $tree = new FSKDTree(__DIR__ . '/fixture/fs/tree100x10.bin', new ItemFactory(), 1);
        $root = $tree->getRoot();
        $left = $root->getLeft();

        $this->assertSame($left, $root->getLeft(), 'level 1 should be cached');
        $this->assertNotSame($left->getRight(), $left->getRight(), 'level 2 should not be cached');
        $this->assertEquals($left->getRight()->getItem(), $left->getRight()->getItem());
    }

    #[Test]
    public function itShouldSearchTheSameWithAnyCacheDepth()
    {
        $path = __DIR__ . '/fixture/fs/tree1000x2.bin';
        $cached = new NearestSearch(new FSKDTree($path, new ItemFactory()));
        $uncached = new NearestSearch(new FSKDTree($path, new ItemFactory(), 0));
        $shallow = new NearestSearch(new FSKDTree($path, new ItemFactory(), 3));

        for ($i = 0; $i < 20; $i++) {
            $point = new Point([mt_rand(0, 1000) / 1000, mt_rand(0, 1000) / 1000]);
            $expected = array_map(fn($item) => $item->getId(), $cached->search($point, 5));
            $this->assertSame($expected, array_map(fn($item) => $item->getId(), $uncached->search($point, 5)));
            $this->assertSame($expected, array_map(fn($item) => $item->getId(), $shallow->search($point, 5)));
        }
    }

    #[Test]
    public function itShouldRejectNegativeCacheDepth()
    {
        $this->expectException(\InvalidArgumentException::class);
        new FSKDTree(__DIR__ . '/fixture/fs/tree100x10.bin', new ItemFactory(), -1);
    }

    #[Test]
    public function itShouldThrowOnTruncatedFile()
    {
        $data = FSKDTree::MAGIC . chr(FSKDTree::FORMAT_VERSION) . pack('VP', 2, 5);
        file_put_contents(__DIR__ . '/storage/truncated.bin', $data);

        $this->expectException(FileException::class);
        new FSKDTree(__DIR__ . '/storage/truncated.bin', new ItemFactory());
    }

    /**
     * @return array
     */
    public static function FSTreeDataProvider()
    {
        return [
            [__DIR__ . '/fixture/fs/tree0x10.bin', 10, 0],
            [__DIR__ . '/fixture/fs/tree100x10.bin', 10, 100],
            [__DIR__ . '/fixture/fs/tree1000x2.bin', 2, 1000],
            [__DIR__ . '/fixture/fs/tree10000x10.bin', 10, 10000],
        ];
    }
}
