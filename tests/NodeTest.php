<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Item;
use Hexogen\KDTree\Node;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NodeTest extends TestCase
{
    /**
     * @var Node
     */
    private $instance;

    protected function setUp(): void
    {
        $this->instance = new Node($this->createStub(Item::class));
    }

    /**
     */
    #[Test]
    public function itShouldCreateAnInstance()
    {
        $this->assertInstanceOf(Node::class, $this->instance);
    }

    #[Test]
    public function itShouldGetAndSetLeftNode()
    {
        $itemMock = $this->createStub(Item::class);
        $this->assertNull($this->instance->getLeft());
        $left = new Node($itemMock);
        $this->instance->setLeft($left);

        $this->assertSame($left, $this->instance->getLeft());
    }


    #[Test]
    public function itShouldGetAndSetRightNode()
    {
        $itemMock = $this->createStub(Item::class);
        $this->assertNull($this->instance->getRight());
        $right = new Node($itemMock);
        $this->instance->setRight($right);

        $this->assertSame($right, $this->instance->getRight());
    }

    #[Test]
    public function itShouldGetAnItem()
    {
        $itemMock = $this->createStub(Item::class);
        $node = new Node($itemMock);
        $this->assertSame($itemMock, $node->getItem());
    }
}
