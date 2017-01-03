<?php

namespace Hexogen\KDTree\Interfaces;

interface ItemInterface extends PointInterface
{
    /**
     * get item id
     *
     * @return int item id
     */
    public function getId() : int;
}
