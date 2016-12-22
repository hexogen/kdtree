<?php


namespace Hexogen\KDTree;

use Hexogen\KDTree\Exception\ValidationException;

class ItemList
{
    private $dimensions;
    private $items;
    private $ids;
    private $lastId;

    /**
     * ItemList constructor.
     * @param int $dimensions
     * @throws ValidationException
     */
    public function __construct(int $dimensions)
    {
        if ($dimensions <= 0) {
            throw new ValidationException('$dimensions should be bigger than 0');
        }

        $this->lastId = 0;
        $this->dimensions = $dimensions;
        $this->items = [];
        $this->ids = [];
    }

    /**
     * Add or replace an item in the item list
     *
     * @param ItemInterface $item
     */
    public function addItem(ItemInterface $item)
    {
        $this->validateItem($item);
        $id = $item->getId();

        if (isset($this->ids[$id])) {
            $index = $this->ids[$id];
            $this->items[$index] = $item;
        } else {
            $this->items[] = $item;
            $this->ids[$id] = $this->lastId++;
        }
    }

    /**
     * @return ItemInterface[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return int number of dimensions in item
     */
    public function getDimensionCount(): int
    {
        return $this->dimensions;
    }

    /**
     * @param ItemInterface $item
     * @throws ValidationException
     */
    private function validateItem(ItemInterface $item)
    {
        if ($item->getDimensionsCount() !== $this->dimensions) {
            throw new ValidationException('$dValues number dimensions should be equal to ' . $this->dimensions);
        }
    }
}
