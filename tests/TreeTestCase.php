<?php

namespace Hexogen\KDTree\Tests;

use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemList;

abstract class TreeTestCase extends \PHPUnit_Framework_TestCase
{
    protected function getRandomItemsList(int $num = 100, int $dimensions = 2, array $coordinatesSet = [])
    {
        $list = new ItemList($dimensions);
        for ($i = 0; $i < $num; $i++) {
            if (empty($coordinatesSet)) {
                $coordinates = [];
                for ($j = 0; $j < $dimensions; $j++) {
                    $coordinates[] = rand(-10, 10);
                }
            } else {
                $coordinates = $coordinatesSet[$i];
            }
            $item = new Item($i, $coordinates);

            $list->addItem($item);
        }
        return $list;
    }
}
