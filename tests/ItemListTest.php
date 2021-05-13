<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\ValidationException;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemList;
use PHPUnit\Framework\TestCase;

class ItemListTest extends TestCase
{
    /**
     * @var ItemList
     */
    private $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance = new ItemList(2);
    }

    /**
     * @test
     */
    public function itShouldNotCreateAnInstance()
    {
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
    public function itShouldGetDimensionNumber()
    {
        $itemList = new ItemList(1);
        $this->assertEquals(1, $itemList->getDimensionCount());

        $itemList = new ItemList(3);
        $this->assertEquals(3, $itemList->getDimensionCount());

        $itemList = new ItemList(7);
        $this->assertEquals(7, $itemList->getDimensionCount());
    }

    /**
     * @test
     */
    public function itShouldAddItemToTheList()
    {
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
    public function itShouldNotAddItemToTheList()
    {
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
