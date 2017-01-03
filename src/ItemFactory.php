<?php


namespace Hexogen\KDTree;

class ItemFactory implements ItemFactoryInterface
{
    public function make(int $id, array $dValues) : ItemInterface
    {
        return new Item($id, $dValues);
    }
}
