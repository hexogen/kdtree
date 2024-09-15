<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\FSTreePersister;
use Hexogen\KDTree\KDTree;

class FSTreePersisterTest extends TreeTestCase
{
    /**
     * @test
     */
    public function itShouldSaveTreeToTheFile()
    {
        $tree = new KDTree(self::getRandomItemsList(100, 10));
        $saver = new FSTreePersister(__DIR__ . '/storage');
        $saver->convert($tree, 'tree1.bin');

        $this->assertFileExists(__DIR__ . '/storage/tree1.bin');
    }
}
