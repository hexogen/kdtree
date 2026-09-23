<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\ValidationException;
use Hexogen\KDTree\Item;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ItemTest extends TestCase
{
    /**
     * @var Item::class;
     */
    private $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance = new Item(11, [0.1, 1.1, 2.1, 3.1, 1.1]);
    }

    /**
     */
    #[Test]
    public function itShouldCreateInstanceOfAnObject()
    {
        $this->assertInstanceOf(Item::class, $this->instance);
    }

    #[Test]
    public function isShouldNotCreateAnInstance()
    {
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

    #[Test]
    public function itShouldGetItemId()
    {
        $this->assertEquals(11, $this->instance->getId());
    }

    #[Test]
    public function itShouldGetDimensionValue()
    {
        foreach ([0.1, 1.1, 2.1, 3.1, 1.1] as $i => $value) {
            $this->assertEquals($value, $this->instance->getNthDimension($i));
        }
    }

    #[Test]
    public function itShouldNotGetDimensionValue()
    {
        $this->expectException(\OutOfRangeException::class);

        $this->instance->getNthDimension(5);
    }

    #[Test]
    public function itShouldGetNumberOfDimensionsInTheItem()
    {
        $item = new Item(11, [0.1]);
        $this->assertEquals(1, $item->getDimensionsCount());
        $item = new Item(11, [0.1, 1.1]);
        $this->assertEquals(2, $item->getDimensionsCount());
        $item = new Item(11, [0.1, 1.1, 2.1, 3.1, 1.1]);
        $this->assertEquals(5, $item->getDimensionsCount());
    }

    /**
     * @param mixed $value
     */
    #[Test]
    #[DataProvider('nonFiniteProvider')]
    public function itShouldRejectNonFiniteCoordinates($value)
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('finite');
        new Item(1, [0.5, $value]);
    }

    public static function nonFiniteProvider(): array
    {
        return [[NAN], [INF], [-INF], ['1e999']];
    }
}
