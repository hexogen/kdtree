<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\FileException;
use Hexogen\KDTree\FSKDTree;
use Hexogen\KDTree\FSTreePersister;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemFactory;
use Hexogen\KDTree\ItemList;
use Hexogen\KDTree\KDTree;
use Hexogen\KDTree\NearestSearch;
use Hexogen\KDTree\Point;
use PHPUnit\Framework\Attributes\Test;

class FSTreePersisterTest extends TreeTestCase
{
    #[Test]
    public function itShouldSaveTreeToTheFile()
    {
        $tree = new KDTree(self::getRandomItemsList(100, 10));
        $saver = new FSTreePersister(__DIR__ . '/storage');
        $saver->convert($tree, 'tree1.bin');

        $this->assertFileExists(__DIR__ . '/storage/tree1.bin');
    }

    #[Test]
    public function itShouldWriteFormatHeader()
    {
        $tree = new KDTree(self::getRandomItemsList(5, 2));
        $saver = new FSTreePersister(__DIR__ . '/storage');
        $saver->convert($tree, 'header.bin');

        $header = file_get_contents(__DIR__ . '/storage/header.bin', false, null, 0, FSKDTree::HEADER_LENGTH);
        $this->assertSame(FSKDTree::MAGIC . chr(FSKDTree::FORMAT_VERSION), $header);
    }

    #[Test]
    public function itShouldPreserveTreeStructureItemsAndSearchResults()
    {
        $tree = new KDTree(self::getRandomItemsList(500, 3));
        $saver = new FSTreePersister(__DIR__ . '/storage');
        $saver->convert($tree, 'roundtrip.bin');

        $fsTree = new FSKDTree(__DIR__ . '/storage/roundtrip.bin', new ItemFactory());

        $this->assertSame($tree->getItemCount(), $fsTree->getItemCount());
        $this->assertSame($tree->getDimensionCount(), $fsTree->getDimensionCount());
        $this->assertSame($tree->getMinBoundary(), $fsTree->getMinBoundary());
        $this->assertSame($tree->getMaxBoundary(), $fsTree->getMaxBoundary());
        $this->checkTree($fsTree);

        $memorySearch = new NearestSearch($tree);
        $fileSearch = new NearestSearch($fsTree);
        for ($i = 0; $i < 20; $i++) {
            $point = new Point([mt_rand(-10, 10) / 3, mt_rand(-10, 10) / 3, mt_rand(-10, 10) / 3]);
            $expected = array_map(fn($item) => $item->getId(), $memorySearch->search($point, 7));
            $actual = array_map(fn($item) => $item->getId(), $fileSearch->search($point, 7));
            $this->assertSame($expected, $actual);
        }
    }

    #[Test]
    public function itShouldRoundTripOneDimensionalTree()
    {
        $tree = new KDTree(self::getRandomItemsList(50, 1));
        $saver = new FSTreePersister(__DIR__ . '/storage');
        $saver->convert($tree, 'one-dim.bin');

        $fsTree = new FSKDTree(__DIR__ . '/storage/one-dim.bin', new ItemFactory());
        $this->checkTree($fsTree);

        $expected = array_map(fn($item) => $item->getId(), (new NearestSearch($tree))->search(new Point([0.5]), 5));
        $actual = array_map(fn($item) => $item->getId(), (new NearestSearch($fsTree))->search(new Point([0.5]), 5));
        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function itShouldPreserveNegativeAndLargeItemIds()
    {
        $ids = [-5, 0, 5000000000, PHP_INT_MAX, PHP_INT_MIN];

        $itemList = new ItemList(2);
        foreach ($ids as $i => $id) {
            $itemList->addItem(new Item($id, [$i, $i]));
        }
        $tree = new KDTree($itemList);

        $saver = new FSTreePersister(__DIR__ . '/storage');
        $saver->convert($tree, 'ids.bin');

        $fsTree = new FSKDTree(__DIR__ . '/storage/ids.bin', new ItemFactory());
        $result = (new NearestSearch($fsTree))->search(new Point([0, 0]), count($ids));

        $this->assertSame($ids, array_map(fn($item) => $item->getId(), $result));
    }

    #[Test]
    public function itShouldThrowWhenWriteFails()
    {
        FailingStreamWrapper::register();
        try {
            $this->expectException(FileException::class);
            $this->expectExceptionMessage('Unable to write');

            $tree = new KDTree(self::getRandomItemsList(5, 2));
            $saver = new FSTreePersister('failing://storage');
            $saver->convert($tree, 'tree.bin');
        } finally {
            FailingStreamWrapper::unregister();
        }
    }

    #[Test]
    public function itShouldThrowWhenDirectoryIsNotWritable()
    {
        $this->expectException(FileException::class);

        $tree = new KDTree(self::getRandomItemsList(5, 2));
        $saver = new FSTreePersister(__DIR__ . '/storage/does-not-exist');
        $saver->convert($tree, 'tree.bin');
    }

    #[Test]
    public function itShouldNotAffectTreeThatIsAlreadyOpen()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Windows cannot rename over a file that is open');
        }
        $saver = new FSTreePersister(__DIR__ . '/storage');
        $oldTree = new KDTree(self::getRandomItemsList(50, 2));
        $saver->convert($oldTree, 'replaced.bin');
        $openTree = new FSKDTree(__DIR__ . '/storage/replaced.bin', new ItemFactory());

        $itemList = new ItemList(2);
        for ($i = 0; $i < 3; $i++) {
            $itemList->addItem(new Item(1000 + $i, [$i, $i]));
        }
        $saver->convert(new KDTree($itemList), 'replaced.bin');

        $point = new Point([0.5, 0.5]);
        $expected = array_map(fn($item) => $item->getId(), (new NearestSearch($oldTree))->search($point, 10));
        $actual = array_map(fn($item) => $item->getId(), (new NearestSearch($openTree))->search($point, 10));
        $this->assertSame($expected, $actual);

        $newTree = new FSKDTree(__DIR__ . '/storage/replaced.bin', new ItemFactory());
        $this->assertSame(3, $newTree->getItemCount());
    }

    #[Test]
    public function itShouldNotLeaveTemporaryFiles()
    {
        $saver = new FSTreePersister(__DIR__ . '/storage');
        $saver->convert(new KDTree(self::getRandomItemsList(10, 2)), 'no-temp.bin');

        $this->assertSame([], glob(__DIR__ . '/storage/no-temp.bin.*.tmp'));
    }

    #[Test]
    public function itShouldKeepExistingFileWhenWriteFails()
    {
        $saver = new FSTreePersister(__DIR__ . '/storage');
        $saver->convert(new KDTree(self::getRandomItemsList(10, 2)), 'kept.bin');
        $before = file_get_contents(__DIR__ . '/storage/kept.bin');

        $tree = $this->createStub(\Hexogen\KDTree\Interfaces\KDTreeInterface::class);
        $tree->method('getDimensionCount')->willReturn(2);
        $tree->method('getItemCount')->willReturn(1);
        $tree->method('getMaxBoundary')->willReturn([1., 1.]);
        $tree->method('getMinBoundary')->willReturn([0., 0.]);
        $tree->method('getRoot')->willThrowException(new \RuntimeException('boom'));

        try {
            $saver->convert($tree, 'kept.bin');
            $this->fail('exception expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame($before, file_get_contents(__DIR__ . '/storage/kept.bin'));
        $this->assertSame([], glob(__DIR__ . '/storage/kept.bin.*.tmp'));
    }

    #[Test]
    public function itShouldThrowWhenFlushFails()
    {
        FailingStreamWrapper::register();
        try {
            $this->expectException(FileException::class);
            $this->expectExceptionMessage('Unable to write kd tree file');

            // a single node has no left link to patch, so the non-seekable stream is enough
            $tree = new KDTree(self::getRandomItemsList(1, 2));
            $saver = new FSTreePersister(FailingStreamWrapper::PROTOCOL . '://flush/storage');
            $saver->convert($tree, 'tree.bin');
        } finally {
            FailingStreamWrapper::unregister();
        }
    }

    #[Test]
    public function itShouldThrowWhenTargetCannotBeReplaced()
    {
        // renaming a file over an existing directory fails
        $target = __DIR__ . '/storage/target-is-a-directory';
        if (!is_dir($target)) {
            mkdir($target);
        }

        try {
            $saver = new FSTreePersister(__DIR__ . '/storage');
            $saver->convert(new KDTree(self::getRandomItemsList(5, 2)), 'target-is-a-directory');
            $this->fail('FileException expected');
        } catch (FileException $e) {
            $this->assertStringContainsString('Unable to replace kd tree file', $e->getMessage());
        } finally {
            rmdir($target);
        }

        $this->assertSame([], glob($target . '.*.tmp'));
    }
}
