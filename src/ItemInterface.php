<?php

namespace Hexogen\KDTree;

interface ItemInterface extends PointInterface
{
    /**
     * get item id
     *
     * @return mixed item id
     */
    public function getId();
}
