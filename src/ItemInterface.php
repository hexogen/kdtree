<?php

namespace Hexogen\KDTree;

interface ItemInterface extends PointInterface
{
    /**
     * get item id
     *
     * @return int item id
     */
    public function getId() : int;
}
