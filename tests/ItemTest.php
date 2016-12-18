<?php

use Hexogen\KDTree\Exception\ValidationException;
use Hexogen\KDTree\Item;

class ItemTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Item::class;
     */
    private $instance;

    protected function setUp()
    {
        parent::setUp();
        $this->instance = new Item(11, [0.1, 1.1, 2.1, 3.1, 1.1]);
    }

    /**
     * @test
     */
    public function it_should_create_instance_of_an_object() {
        $this->assertInstanceOf(Item::class, $this->instance);
    }

    /**
     * @test
     */
    public function is_should_NOT_create_an_instance() {
        try {
            $item = new Item(11, [0.1, 1.1, 2.1, 3.1, 1.1, 6 => 2.1]);
            $this->fail('ValidationException should be thrown');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        try {
            $item = new Item(11, []);
            $this->fail('ValidationException should be thrown');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * @test
     */
    public function it_should_get_item_id() {
        $this->assertEquals(11, $this->instance->getId());
    }

    /**
     * @test
     */
    public function it_should_get_dimension_value() {
        foreach ([0.1, 1.1, 2.1, 3.1, 1.1] as $i => $value) {
            $this->assertEquals($value, $this->instance->getNthDimension($i));
        }
    }

    /**
     * @test
     * @expectedException OutOfRangeException
     */
    public function it_should_NOT_get_dimension_value() {
        $this->instance->getNthDimension(5);
    }

    /**
     * @test
     */
    public function it_should_get_number_of_dimensions_in_the_item() {
        $item = new Item(11, [0.1]);
        $this->assertEquals(1, $item->getDimensionsCount());
        $item = new Item(11, [0.1, 1.1]);
        $this->assertEquals(2, $item->getDimensionsCount());
        $item = new Item(11, [0.1, 1.1, 2.1, 3.1, 1.1]);
        $this->assertEquals(5, $item->getDimensionsCount());
    }

}
