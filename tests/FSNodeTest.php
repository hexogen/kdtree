<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\FileException;
use Hexogen\KDTree\FSKDTree;
use Hexogen\KDTree\FSNode;
use Hexogen\KDTree\Interfaces\ItemInterface;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemFactory;
use Hexogen\KDTree\Node;
use PHPUnit\Framework\Attributes\Test;

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

    protected function setUp(): void
    {
        $this->handler = fopen(__DIR__ . '/fixture/fs/tree100x10.bin', 'rb');
        $factory = new ItemFactory();
        $rootPosition = FSKDTree::HEADER_LENGTH
            + FSKDTree::DIMENSIONS_LENGTH
            + FSKDTree::INT_LENGTH
            + 2 * 10 * FSKDTree::FLOAT_LENGTH;
        $this->root = new FSNode($factory, $this->handler, $rootPosition, 10);
    }

    public function tearDown(): void
    {
        fclose($this->handler);
    }

    /**
     */
    #[Test]
    public function itShouldCreateAnInstance()
    {
        $this->assertInstanceOf(FSNode::class, $this->root);
    }

    #[Test]
    public function itShouldGetLeftNode()
    {
        $left = $this->root->getLeft();

        $this->assertInstanceOf(FSNode::class, $left);
    }


    #[Test]
    public function itShouldGetRightNode()
    {
        $right = $this->root->getRight();

        $this->assertInstanceOf(FSNode::class, $right);
    }

    #[Test]
    public function itShouldSetLeftNode()
    {
        $left = new Node(new Item(-1, array_fill(0, 10, 0.)));
        $this->root->setLeft($left);

        $this->assertSame($left, $this->root->getLeft());
    }

    #[Test]
    public function itShouldSetRightNode()
    {
        $right = new Node(new Item(-1, array_fill(0, 10, 0.)));
        $this->root->setRight($right);

        $this->assertSame($right, $this->root->getRight());
    }

    #[Test]
    public function itShouldGetAnItem()
    {
        $item = $this->root->getItem();
        $this->assertInstanceOf(ItemInterface::class, $item);
    }

    #[Test]
    public function itShouldThrowWhenNodeIsTruncated()
    {
        $path = __DIR__ . '/storage/truncated-node.bin';
        file_put_contents($path, pack('PP', 1, 0));
        $handler = fopen($path, 'rb');
        $node = new FSNode(new ItemFactory(), $handler, 0, 2);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('unable to read node');
        try {
            $node->getItem();
        } finally {
            fclose($handler);
        }
    }

    #[Test]
    public function itShouldThrowWhenHandleIsClosed()
    {
        $handler = fopen(__DIR__ . '/fixture/fs/tree100x10.bin', 'rb');
        $node = new FSNode(new ItemFactory(), $handler, 0, 10);
        fclose($handler);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('closed');
        $node->getItem();
    }
}
