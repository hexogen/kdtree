<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\FileException;
use Hexogen\KDTree\FSNode;
use Hexogen\KDTree\Interfaces\ItemInterface;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemFactory;
use Hexogen\KDTree\Node;

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
        $this->root = new FSNode($factory, $this->handler, 168, 10);
    }

    public function tearDown(): void
    {
        if (is_resource($this->handler)) {
            fclose($this->handler);
        }
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

    /**
     * @test
     */
    public function itShouldReadNodeOnce()
    {
        $item = $this->root->getItem();
        $left = $this->root->getLeft();
        $right = $this->root->getRight();

        $this->assertSame($item, $this->root->getItem());
        $this->assertSame($left, $this->root->getLeft());
        $this->assertSame($right, $this->root->getRight());
        $this->assertSame(10, $item->getDimensionsCount());
    }

    /**
     * @test
     */
    public function itShouldGetNullIfThereAreNoChildNodes()
    {
        // the last node of the file is a leaf: header takes 168 bytes and a node takes 92 bytes
        $leaf = new FSNode(new ItemFactory(), $this->handler, 168 + 99 * 92, 10);

        $this->assertNull($leaf->getLeft());
        $this->assertNull($leaf->getRight());
        $this->assertInstanceOf(ItemInterface::class, $leaf->getItem());
    }

    /**
     * @test
     */
    public function itShouldGetNodesThatHaveBeenSet()
    {
        $left = new Node(new Item(1, array_fill(0, 10, 1)));
        $right = new Node(new Item(2, array_fill(0, 10, 2)));

        $this->root->setLeft($left);
        $this->root->setRight($right);

        $this->assertSame($left, $this->root->getLeft());
        $this->assertSame($right, $this->root->getRight());
    }

    /**
     * @test
     */
    public function itShouldNotReadNodeIfFileIsClosed()
    {
        fclose($this->handler);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('kd tree file handle has been closed');

        $this->root->getItem();
    }

    /**
     * @test
     * @dataProvider wrongPositionProvider
     * @param int $position
     * @param string $message
     */
    public function itShouldNotReadNodeOutsideOfTheFile(int $position, string $message)
    {
        $node = new FSNode(new ItemFactory(), $this->handler, $position, 10);

        try {
            $node->getLeft();
            $this->fail('FileException should be thrown');
        } catch (FileException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }

        try {
            $node->getRight();
            $this->fail('FileException should be thrown');
        } catch (FileException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }

        $this->expectException(FileException::class);
        $this->expectExceptionMessage($message);

        $node->getItem();
    }

    /**
     * @return array
     */
    public function wrongPositionProvider()
    {
        return [
            'negative offset' => [-1, 'unable to seek to offset -1'],
            'end of the file' => [168 + 100 * 92, 'unexpected end of file'],
            'part of the last node' => [168 + 100 * 92 - 91, 'unexpected end of file'],
            'far behind the end of the file' => [1 << 40, 'unexpected end of file'],
        ];
    }
}
