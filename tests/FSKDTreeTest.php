<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\FileException;
use Hexogen\KDTree\FSKDTree;
use Hexogen\KDTree\ItemFactory;
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
