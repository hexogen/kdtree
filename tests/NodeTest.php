<?php

use Hexogen\KDTree\Item;
use Hexogen\KDTree\Node;
use \Mockery as m;

class NodeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Node
     */
    private $instance;

    protected function setUp()
    {
        $itemMock = m::mock(Item::class);
        $this->instance = new Node($itemMock);
    }

    public function tearDown()
    {
        m::close();
    }

    /**
     * @test
     */
    public function it_should_create_an_instance() {
        $this->assertInstanceOf(Node::class, $this->instance);
    }

    /**
     * @test
     */
    public function it_should_get_and_set_left_node() {
        $itemMock = m::mock(Item::class);
        $this->assertNull($this->instance->getLeft());
        $left = new Node($itemMock);
        $this->instance->setLeft($left);

        $this->assertSame($left, $this->instance->getLeft());
    }


    /**
     * @test
     */
    public function it_should_get_and_set_right_node() {
        $itemMock = m::mock(Item::class);
        $this->assertNull($this->instance->getRight());
        $right = new Node($itemMock);
        $this->instance->setRight($right);

        $this->assertSame($right, $this->instance->getRight());
    }

    /**
     * @test
     */
    public function it_should_get_an_item() {
        $itemMock = m::mock(Item::class);
        $node = new Node($itemMock);
        $this->assertSame($itemMock, $node->getItem());
    }
}