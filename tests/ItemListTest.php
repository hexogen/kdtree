<?php


use Hexogen\KDTree\Exception\ValidationException;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemList;

class ItemListTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ItemList
     */
    private $instance;

    protected function setUp()
    {
        parent::setUp();
        $this->instance = new ItemList(2);
    }

    /**
     * @test
     */
    public function it_should_NOT_create_an_instance() {
        try {
            $itemList = new ItemList(-1);
            $this->fail('ValidationException should be thrown');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * @test
     */
    public function it_should_get_dimension_number() {
        $itemList = new ItemList(1);
        $this->assertEquals(1, $itemList->getDimensionNumber());

        $itemList = new ItemList(3);
        $this->assertEquals(3, $itemList->getDimensionNumber());

        $itemList = new ItemList(7);
        $this->assertEquals(7, $itemList->getDimensionNumber());
    }

    /**
     * @test
     */
    public function it_should_add_item_to_the_list() {
        $item = new Item(1, [1.2, 2.3]);
        $this->instance->addItem($item);

        $item = new Item(2, [1.3, 1.3]);
        $this->instance->addItem($item);

        $item = new Item(3, [1.2, 2.3]);
        $this->instance->addItem($item);

        $item = new Item(4, [1.2, 2.3]);
        $this->instance->addItem($item);

        $item = new Item(3, [1.2, 3.3]);
        $this->instance->addItem($item);

        $items = $this->instance->getItems();
        $this->assertEquals(3.3, $items[2]->getNthDimension(1));
        $this->assertEquals(3, $items[2]->getId());
    }

    /**
     * @test
     */
    public function it_should_NOT_add_item_to_the_list() {
        try {
            $item = new Item(1, [2.5]);
            $this->instance->addItem($item);
            $this->fail('ValidationException should be thrown');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        try {
            $item = new Item(1, [2.5, 22, 12]);
            $this->instance->addItem($item);
            $this->fail('ValidationException should be thrown');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }
    }
}
