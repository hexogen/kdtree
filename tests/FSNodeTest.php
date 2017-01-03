<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\FSNode;
use Hexogen\KDTree\ItemFactory;
use Hexogen\KDTree\ItemInterface;
use \Mockery as m;

class FSNodeTest extends TreeTestCase
{
    /**
     * @var FSNode
     */
    private $root;

    /**
     * @var resource
     */
    private $handler;

    protected function setUp()
    {
        $this->handler = fopen(__DIR__ . '/fixture/fs/tree100x10.bin', 'rb');
        $factory = new ItemFactory();
        $this->root = new FSNode($factory, $this->handler, 168, 10);
    }

    public function tearDown()
    {
        fclose($this->handler);
    }

    /**
     * @test
     */
    public function itShouldCreateAnInstance()
    {
        $this->assertInstanceOf(FSNode::class, $this->root);
    }

    /**
     * @test
     */
    public function itShouldGetLeftNode()
    {
        $left = $this->root->getLeft();

        $this->assertInstanceOf(FSNode::class, $left);
    }


    /**
     * @test
     */
    public function itShouldGetRightNode()
    {
        $right = $this->root->getRight();

        $this->assertInstanceOf(FSNode::class, $right);
    }

    /**
     * @test
     */
    public function itShouldGetAnItem()
    {
        $item = $this->root->getItem();
        $this->assertInstanceOf(ItemInterface::class, $item);
    }
}
