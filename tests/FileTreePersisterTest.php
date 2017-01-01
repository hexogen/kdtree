<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\FileTreePersister;
use Hexogen\KDTree\KDTree;

class FileTreePersisterTest extends TreeTestCase
{
    /**
     * @test
     */
    public function itShouldSaveTreeToTHeFile()
    {
        $tree = new KDTree($this->getRandomItemsList(100, 10));
        $saver = new FileTreePersister(__DIR__ . '/storage');
        $saver->convert($tree, 'tree1.bin');

        $this->assertTrue(file_exists(__DIR__ . '/storage/tree1.bin'));
    }
}
