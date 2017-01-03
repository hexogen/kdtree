<?php


namespace Hexogen\KDTree\Interfaces;

interface ItemFactoryInterface
{
    public function make(int $id, array $dValues) : ItemInterface;
}
