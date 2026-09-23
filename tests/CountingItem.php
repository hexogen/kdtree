<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Item;

/**
 * Item that counts coordinate reads, to measure tree build work deterministically
 */
class CountingItem extends Item
{
    /**
     * @var int
     */
    public static $reads = 0;

    public function getNthDimension(int $d): float
    {
        self::$reads++;
        return parent::getNthDimension($d);
    }
}
