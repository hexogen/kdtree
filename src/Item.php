<?php


namespace Hexogen\KDTree;

class Item extends Point implements ItemInterface
{
    private $id;

    /**
     * Item constructor.
     * @param $id
     * @param array $dValues
     */
    public function __construct($id, array $dValues)
    {
        parent::__construct($dValues);
        $this->id = $id;
    }

    /**
     * get item id
     *
     * @return mixed item id
     */
    public function getId()
    {
        return $this->id;
    }
}
