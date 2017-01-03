<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\FSTreePersister;
use Hexogen\KDTree\KDTree;

class FileTreePersisterTest extends TreeTestCase
{
    /**
     * @test
     */
    public function itShouldSaveTreeToTheFile()
    {
        $tree = new KDTree($this->getRandomItemsList(0, 10));
        $saver = new FSTreePersister(__DIR__ . '/storage');
        $saver->convert($tree, 'tree1.bin');

        $this->assertFileExists(__DIR__ . '/storage/tree1.bin');
    }
}
