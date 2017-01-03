<?php


namespace Hexogen\KDTree;

interface ItemFactoryInterface
{
    public function make(int $id, array $dValues) : ItemInterface;
}
