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
}
