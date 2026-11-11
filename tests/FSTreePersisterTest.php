<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\FileException;
use Hexogen\KDTree\FSKDTree;
use Hexogen\KDTree\FSTreePersister;
use Hexogen\KDTree\Interfaces\ItemInterface;
use Hexogen\KDTree\Interfaces\KDTreeInterface;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemFactory;
use Hexogen\KDTree\ItemList;
use Hexogen\KDTree\KDTree;
use Hexogen\KDTree\NearestSearch;
use Hexogen\KDTree\Point;

class FSTreePersisterTest extends TreeTestCase
{
    const STORAGE = __DIR__ . '/storage';

    protected function tearDown(): void
    {
        MemoryStreamWrapper::unregister();

        foreach (glob(self::STORAGE . '/*') as $path) {
            if (is_dir($path) && !is_link($path)) {
                rmdir($path);
                continue;
            }
            unlink($path);
        }
    }

    /**
     * @test
     */
    public function itShouldSaveTreeToTheFile()
    {
        $tree = new KDTree($this->getRandomItemsList(100, 10));
        $saver = new FSTreePersister(self::STORAGE);
        $saver->convert($tree, 'tree1.bin');

        $this->assertFileExists(self::STORAGE . '/tree1.bin');
    }

    /**
     * The file format should stay the same as it was in v0.2.6
     *
     * @test
     */
    public function itShouldWriteTreeInTheFileFormat()
    {
        $itemList = new ItemList(2);
        $itemList->addItem(new Item(7, [1.5, -2.5]));
        $itemList->addItem(new Item(0, [0.5, 4.]));
        $itemList->addItem(new Item(4294967295, [3., 1.]));

        $saver = new FSTreePersister(self::STORAGE);
        $saver->convert(new KDTree($itemList), 'format.bin');

        // header takes 40 bytes and a node takes 28 bytes: root, its right node and then its left one
        $expected = pack('VV', 2, 3)
            . pack('d2', 3., 4.)
            . pack('d2', 0.5, -2.5)
            . pack('VVV', 7, 96, 68) . pack('d2', 1.5, -2.5)
            . pack('VVV', 4294967295, 0, 0) . pack('d2', 3., 1.)
            . pack('VVV', 0, 0, 0) . pack('d2', 0.5, 4.);

        $this->assertSame(bin2hex($expected), bin2hex(file_get_contents(self::STORAGE . '/format.bin')));
    }

    /**
     * @test
     */
    public function itShouldWriteEmptyTree()
    {
        $saver = new FSTreePersister(self::STORAGE);
        $saver->convert(new KDTree(new ItemList(3)), 'empty.bin');

        $expected = pack('VV', 3, 0) . pack('d3', -INF, -INF, -INF) . pack('d3', INF, INF, INF);
        $this->assertSame(bin2hex($expected), bin2hex(file_get_contents(self::STORAGE . '/empty.bin')));

        $tree = new FSKDTree(self::STORAGE . '/empty.bin', new ItemFactory());
        $this->assertSame(0, $tree->getItemCount());
        $this->assertNull($tree->getRoot());
    }

    /**
     * @test
     * @dataProvider treeSizeProvider
     * @param int $itemCount
     * @param int $dimensions
     */
    public function itShouldPreserveTreeStructureItemsAndSearchResults(int $itemCount, int $dimensions)
    {
        mt_srand($itemCount + $dimensions);
        $itemList = $this->getRandomItemsList($itemCount, $dimensions);
        $tree = new KDTree($itemList);
        $saver = new FSTreePersister(self::STORAGE);
        $saver->convert($tree, 'roundtrip.bin');

        $fsTree = new FSKDTree(self::STORAGE . '/roundtrip.bin', new ItemFactory());

        $this->assertSame($tree->getItemCount(), $fsTree->getItemCount());
        $this->assertSame($tree->getDimensionCount(), $fsTree->getDimensionCount());
        $this->assertSame($tree->getMinBoundary(), $fsTree->getMinBoundary());
        $this->assertSame($tree->getMaxBoundary(), $fsTree->getMaxBoundary());
        $this->assertSame($this->getTreeIds($tree->getRoot()), $this->getTreeIds($fsTree->getRoot()));
        $this->checkTree($fsTree);

        $items = $itemList->getItems();
        foreach ($this->getTreeItems($fsTree->getRoot()) as $item) {
            for ($d = 0; $d < $dimensions; $d++) {
                $this->assertSame($items[$item->getId()]->getNthDimension($d), $item->getNthDimension($d));
            }
        }

        $memorySearch = new NearestSearch($tree);
        $fileSearch = new NearestSearch($fsTree);
        for ($i = 0; $i < 20; $i++) {
            $coordinates = [];
            for ($d = 0; $d < $dimensions; $d++) {
                $coordinates[] = mt_rand(-10, 10) / 3;
            }
            $point = new Point($coordinates);

            $result = $fileSearch->search($point, 7);
            $this->assertSame($this->getIds($memorySearch->search($point, 7)), $this->getIds($result));
            $this->checkNearest($items, $result, $point, 7);
        }
    }

    /**
     * @return array
     */
    public function treeSizeProvider()
    {
        return [[1, 1], [2, 1], [50, 1], [3, 2], [500, 3], [200, 10]];
    }

    /**
     * @test
     */
    public function itShouldPersistTreeThatIsReadFromFile()
    {
        $saver = new FSTreePersister(self::STORAGE);
        $saver->convert(new FSKDTree(__DIR__ . '/fixture/fs/tree100x10.bin', new ItemFactory()), 'copy.bin');

        $this->assertFileEquals(__DIR__ . '/fixture/fs/tree100x10.bin', self::STORAGE . '/copy.bin');
    }

    /**
     * @test
     * @dataProvider wrongIdProvider
     * @param int $id
     */
    public function itShouldNotSaveItemIdThatDoesNotFitFileFormat(int $id)
    {
        $saver = new FSTreePersister(self::STORAGE);
        $saver->convert(new KDTree($this->getRandomItemsList(10, 2)), 'ids.bin');
        $before = file_get_contents(self::STORAGE . '/ids.bin');

        $itemList = new ItemList(2);
        $itemList->addItem(new Item(1, [1., 1.]));
        $itemList->addItem(new Item($id, [2., 2.]));
        $itemList->addItem(new Item(3, [3., 3.]));

        try {
            $saver->convert(new KDTree($itemList), 'ids.bin');
            $this->fail('FileException should be thrown');
        } catch (FileException $e) {
            $this->assertStringContainsString('Item id ' . $id . ' cannot be stored', $e->getMessage());
        }

        $this->assertSame(bin2hex($before), bin2hex(file_get_contents(self::STORAGE . '/ids.bin')));
        $this->assertSame(['ids.bin'], $this->getStoredFiles());
    }

    /**
     * @return array
     */
    public function wrongIdProvider()
    {
        return [[-1], [-5], [4294967296], [5000000000], [PHP_INT_MAX], [PHP_INT_MIN]];
    }

    /**
     * @test
     */
    public function itShouldNotSaveTreeThatDoesNotFitFileFormat()
    {
        // 40 bytes of the header and 28 bytes for a node: one node more than 4 GiB is able to hold
        $tree = $this->getTreeStub(2, intdiv(4294967295 - 40, 28) + 1);

        try {
            (new FSTreePersister(self::STORAGE))->convert($tree, 'big.bin');
            $this->fail('FileException should be thrown');
        } catch (FileException $e) {
            $this->assertStringContainsString('The tree is too big to be stored', $e->getMessage());
        }

        $this->assertSame([], $this->getStoredFiles());
    }

    /**
     * @test
     */
    public function itShouldSaveTheBiggestTreeThatFitsFileFormat()
    {
        // the size is only checked against the limit here, a tree of that size would not be written
        $tree = $this->getTreeStub(2, intdiv(4294967295 - 40, 28));
        $tree->method('getRoot')->willReturn(null);

        (new FSTreePersister(self::STORAGE))->convert($tree, 'big.bin');

        $this->assertSame(40, filesize(self::STORAGE . '/big.bin'));
    }

    /**
     * @test
     */
    public function itShouldThrowWhenDirectoryDoesNotExist()
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Unable to open kd tree file for writing');

        $saver = new FSTreePersister(self::STORAGE . '/does-not-exist');
        $saver->convert(new KDTree($this->getRandomItemsList(5, 2)), 'tree.bin');
    }

    /**
     * @test
     */
    public function itShouldNotLeaveTemporaryFiles()
    {
        $saver = new FSTreePersister(self::STORAGE);
        $saver->convert(new KDTree($this->getRandomItemsList(10, 2)), 'no-temp.bin');
        $saver->convert(new KDTree($this->getRandomItemsList(20, 2)), 'no-temp.bin');

        $this->assertSame(['no-temp.bin'], $this->getStoredFiles());
    }

    /**
     * @test
     */
    public function itShouldKeepExistingFileWhenTreeCannotBeRead()
    {
        $saver = new FSTreePersister(self::STORAGE);
        $saver->convert(new KDTree($this->getRandomItemsList(10, 2)), 'kept.bin');
        $before = file_get_contents(self::STORAGE . '/kept.bin');

        $tree = $this->getTreeStub(2, 1);
        $tree->method('getRoot')->willThrowException(new \RuntimeException('boom'));

        try {
            $saver->convert($tree, 'kept.bin');
            $this->fail('exception should be thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(bin2hex($before), bin2hex(file_get_contents(self::STORAGE . '/kept.bin')));
        $this->assertSame(['kept.bin'], $this->getStoredFiles());
    }

    /**
     * @test
     */
    public function itShouldNotAffectTreeThatIsAlreadyOpen()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('an open file cannot be replaced on Windows');
        }

        $saver = new FSTreePersister(self::STORAGE);
        $oldTree = new KDTree($this->getRandomItemsList(200, 2));
        $saver->convert($oldTree, 'replaced.bin');
        $openTree = new FSKDTree(self::STORAGE . '/replaced.bin', new ItemFactory());

        $itemList = new ItemList(2);
        for ($i = 0; $i < 3; $i++) {
            $itemList->addItem(new Item(1000 + $i, [$i, $i]));
        }
        $saver->convert(new KDTree($itemList), 'replaced.bin');

        $point = new Point([0.4, 0.3]);
        $expected = $this->getIds((new NearestSearch($oldTree))->search($point, 10));
        $this->assertSame($expected, $this->getIds((new NearestSearch($openTree))->search($point, 10)));
        $this->assertSame($this->getTreeIds($oldTree->getRoot()), $this->getTreeIds($openTree->getRoot()));

        $newTree = new FSKDTree(self::STORAGE . '/replaced.bin', new ItemFactory());
        $this->assertSame(3, $newTree->getItemCount());
        $this->assertSame([1000], $this->getIds((new NearestSearch($newTree))->search($point, 1)));
    }

    /**
     * @test
     */
    public function itShouldKeepAccessRightsOfReplacedFile()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('there are no such access rights on Windows');
        }

        $path = self::STORAGE . '/rights.bin';
        $saver = new FSTreePersister(self::STORAGE);
        $saver->convert(new KDTree($this->getRandomItemsList(5, 2)), 'rights.bin');

        foreach ([0640, 0604, 0666, 0600] as $permissions) {
            chmod($path, $permissions);
            $saver->convert(new KDTree($this->getRandomItemsList(5, 2)), 'rights.bin');

            clearstatcache();
            $this->assertSame($permissions, fileperms($path) & 0777);
        }
    }

    /**
     * @test
     */
    public function itShouldWriteThroughSymbolicLink()
    {
        $target = self::STORAGE . '/link-target.bin';
        $link = self::STORAGE . '/link.bin';
        file_put_contents($target, 'previous contents');
        if (!@symlink($target, $link)) {
            $this->markTestSkipped('symbolic links are not available');
        }

        $tree = new KDTree($this->getRandomItemsList(30, 2));
        (new FSTreePersister(self::STORAGE))->convert($tree, 'link.bin');

        $this->assertTrue(is_link($link));
        $this->assertSame(['link-target.bin', 'link.bin'], $this->getStoredFiles());

        $fsTree = new FSKDTree($target, new ItemFactory());
        $this->assertSame($this->getTreeIds($tree->getRoot()), $this->getTreeIds($fsTree->getRoot()));
    }

    /**
     * @test
     */
    public function itShouldThrowWhenTargetCannotBeReplaced()
    {
        // neither renaming a file over a directory nor copying it there is possible
        mkdir(self::STORAGE . '/directory.bin');

        try {
            $saver = new FSTreePersister(self::STORAGE);
            $saver->convert(new KDTree($this->getRandomItemsList(5, 2)), 'directory.bin');
            $this->fail('FileException should be thrown');
        } catch (FileException $e) {
            $this->assertStringContainsString('Unable to replace kd tree file', $e->getMessage());
        }

        $this->assertSame(['directory.bin'], $this->getStoredFiles());
        $this->assertTrue(is_dir(self::STORAGE . '/directory.bin'));
    }

    /**
     * @test
     */
    public function itShouldSaveTreeToStreamThatIsNotPlainFile()
    {
        MemoryStreamWrapper::register();
        $directory = MemoryStreamWrapper::PROTOCOL . '://storage';
        $tree = new KDTree($this->getRandomItemsList(50, 3));

        (new FSTreePersister($directory))->convert($tree, 'tree.bin');
        (new FSTreePersister(self::STORAGE))->convert($tree, 'tree.bin');

        $this->assertSame([$directory . '/tree.bin'], array_keys(MemoryStreamWrapper::$files));
        $this->assertSame(
            bin2hex(file_get_contents(self::STORAGE . '/tree.bin')),
            bin2hex(MemoryStreamWrapper::$files[$directory . '/tree.bin'])
        );
    }

    /**
     * A file is written in place, the way it was done before v0.2.7,
     * if a temporary file cannot be created (e.g. the directory is read only)
     *
     * @test
     */
    public function itShouldWriteInPlaceWhenTemporaryFileCannotBeCreated()
    {
        MemoryStreamWrapper::register([MemoryStreamWrapper::FAIL_EXCLUSIVE_OPEN]);
        $path = MemoryStreamWrapper::PROTOCOL . '://storage/tree.bin';
        MemoryStreamWrapper::$files[$path] = 'previous contents';
        $tree = new KDTree($this->getRandomItemsList(50, 3));

        (new FSTreePersister(MemoryStreamWrapper::PROTOCOL . '://storage'))->convert($tree, 'tree.bin');

        $this->assertSame([$path], array_keys(MemoryStreamWrapper::$files));
        $fsTree = new FSKDTree($path, new ItemFactory());
        $this->assertSame($this->getTreeIds($tree->getRoot()), $this->getTreeIds($fsTree->getRoot()));
    }

    /**
     * A file is copied over the target if it cannot be renamed (e.g. the target is locked on Windows)
     *
     * @test
     */
    public function itShouldCopyOverTargetWhenItCannotBeRenamed()
    {
        MemoryStreamWrapper::register([MemoryStreamWrapper::FAIL_RENAME]);
        $path = MemoryStreamWrapper::PROTOCOL . '://storage/tree.bin';
        MemoryStreamWrapper::$files[$path] = 'previous contents';
        $tree = new KDTree($this->getRandomItemsList(50, 3));

        (new FSTreePersister(MemoryStreamWrapper::PROTOCOL . '://storage'))->convert($tree, 'tree.bin');

        $this->assertSame([$path], array_keys(MemoryStreamWrapper::$files));
        $fsTree = new FSKDTree($path, new ItemFactory());
        $this->assertSame($this->getTreeIds($tree->getRoot()), $this->getTreeIds($fsTree->getRoot()));
    }

    /**
     * @test
     * @dataProvider writeFailureProvider
     * @param string[] $failures
     * @param string[] $expectedFiles
     */
    public function itShouldThrowWhenFileCannotBeWritten(array $failures, array $expectedFiles)
    {
        MemoryStreamWrapper::register($failures);
        $directory = MemoryStreamWrapper::PROTOCOL . '://storage';
        MemoryStreamWrapper::$files[$directory . '/kept.bin'] = 'previous contents';

        try {
            $saver = new FSTreePersister($directory);
            $saver->convert(new KDTree($this->getRandomItemsList(5, 2)), 'kept.bin');
            $this->fail('FileException should be thrown');
        } catch (FileException $e) {
            $this->assertStringContainsString('Unable to write kd tree file', $e->getMessage());
        }

        $this->assertSame($expectedFiles, MemoryStreamWrapper::$files);
    }

    /**
     * @return array
     */
    public function writeFailureProvider()
    {
        $path = MemoryStreamWrapper::PROTOCOL . '://storage/kept.bin';

        return [
            'full disk' => [
                [MemoryStreamWrapper::FAIL_WRITE],
                [$path => 'previous contents'],
            ],
            'flush failure' => [
                [MemoryStreamWrapper::FAIL_FLUSH],
                [$path => 'previous contents'],
            ],
            // nothing protects the previous file if it has to be written in place
            'full disk, file is written in place' => [
                [MemoryStreamWrapper::FAIL_WRITE, MemoryStreamWrapper::FAIL_EXCLUSIVE_OPEN],
                [$path => ''],
            ],
        ];
    }

    /**
     * @test
     */
    public function itShouldBeReusableAfterFailure()
    {
        $saver = new FSTreePersister(self::STORAGE);
        $itemList = new ItemList(1);
        $itemList->addItem(new Item(-1, [1.]));

        try {
            $saver->convert(new KDTree($itemList), 'reused.bin');
            $this->fail('FileException should be thrown');
        } catch (FileException $e) {
            $this->assertSame([], $this->getStoredFiles());
        }

        $tree = new KDTree($this->getRandomItemsList(10, 1));
        $saver->convert($tree, 'reused.bin');

        $fsTree = new FSKDTree(self::STORAGE . '/reused.bin', new ItemFactory());
        $this->assertSame($this->getTreeIds($tree->getRoot()), $this->getTreeIds($fsTree->getRoot()));
    }

    /**
     * @param int $dimensions
     * @param int $itemCount
     * @return KDTreeInterface|\PHPUnit\Framework\MockObject\Stub
     */
    private function getTreeStub(int $dimensions, int $itemCount)
    {
        $tree = $this->createStub(KDTreeInterface::class);
        $tree->method('getDimensionCount')->willReturn($dimensions);
        $tree->method('getItemCount')->willReturn($itemCount);
        $tree->method('getMaxBoundary')->willReturn(array_fill(0, $dimensions, 1.));
        $tree->method('getMinBoundary')->willReturn(array_fill(0, $dimensions, 0.));

        return $tree;
    }

    /**
     * @return string[] names of files in the storage directory
     */
    private function getStoredFiles(): array
    {
        return array_map('basename', glob(self::STORAGE . '/*'));
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
}
