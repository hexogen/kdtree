<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Exception\ValidationException;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\Point;
use PHPUnit\Framework\TestCase;

class PointTest extends TestCase
{
    /**
     * @test
     */
    public function itShouldAcceptNumericValues()
    {
        $point = new Point([1, -2.5, '3.25', '1e3', 0]);

        $this->assertSame(5, $point->getDimensionsCount());
        $this->assertSame(1., $point->getNthDimension(0));
        $this->assertSame(-2.5, $point->getNthDimension(1));
        $this->assertSame(3.25, $point->getNthDimension(2));
        $this->assertSame(1000., $point->getNthDimension(3));
        $this->assertSame(0., $point->getNthDimension(4));
    }

    /**
     * @test
     */
    public function itShouldAcceptTheBiggestAndTheSmallestFloats()
    {
        $point = new Point([PHP_FLOAT_MAX, -PHP_FLOAT_MAX, PHP_FLOAT_EPSILON]);

        $this->assertSame(PHP_FLOAT_MAX, $point->getNthDimension(0));
        $this->assertSame(-PHP_FLOAT_MAX, $point->getNthDimension(1));
        $this->assertSame(PHP_FLOAT_EPSILON, $point->getNthDimension(2));
    }

    /**
     * @test
     * @dataProvider notFiniteValuesProvider
     * @param mixed $value
     */
    public function itShouldNotAcceptNotFiniteValues($value)
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('$dValues[1] should be a finite number');

        new Point([1.5, $value]);
    }

    /**
     * @test
     * @dataProvider notFiniteValuesProvider
     * @param mixed $value
     */
    public function itShouldNotCreateAnItemWithNotFiniteValues($value)
    {
        $this->expectException(ValidationException::class);

        new Item(1, [1.5, $value]);
    }

    /**
     * @test
     * @dataProvider notNumericValuesProvider
     * @param array $dValues
     */
    public function itShouldNotAcceptNotNumericValues(array $dValues)
    {
        $this->expectException(ValidationException::class);

        new Point($dValues);
    }

    /**
     * @return array
     */
    public function notFiniteValuesProvider()
    {
        return [
            'NAN' => [NAN],
            'INF' => [INF],
            '-INF' => [-INF],
            'string that overflows float' => ['1e999'],
            'negative string that overflows float' => ['-1e999'],
        ];
    }

    /**
     * @return array
     */
    public function notNumericValuesProvider()
    {
        return [
            'empty' => [[]],
            'not a number' => [[1, 'a']],
            'null' => [[1, null]],
            'bool' => [[1, true]],
            'nested array' => [[1, [2]]],
            'string keys' => [['x' => 1, 'y' => 2]],
            'keys with a gap' => [[0 => 1, 2 => 2]],
        ];
    }
}
