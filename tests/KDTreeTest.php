<?php

use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemList;
use Hexogen\KDTree\KDTree;
use Hexogen\KDTree\Node;

class KDTreeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_should_create_an_instance() {
        $itemList = new ItemList(5);
        $tree = new KDTree($itemList);

        $this->assertInstanceOf(KDTree::class, $tree);
    }

    /**
     * @test
     * @dataProvider itemProvider
     * @param ItemList $itemList
     */
    public function it_should_create_tree(ItemList $itemList) {
        $itemList->getDimensionNumber();
        $tree = new KDTree($itemList);

        $this->checkTree($tree);
    }

    /**
     * item provider
     */
    public function itemProvider() {
        $lists = [];

        $params = [];
        $list = new ItemList(5);
        for($id = 0, $i = -10.; $i < 10.; $i += .1, $id++) {
            $item = new Item($id, [$i, $i, $i, $i, $i]);
            $list->addItem($item);
        }
        $params[] = $list;
        $lists[] = $params;

        $params = [];
        $list = new ItemList(5);
        for($id = 0, $i = 10.; $i > -10.; $i -= .1, $id++) {
            $item = new Item($id, [$i, $i, $i, $i, $i]);
            $list->addItem($item);
        }
        $params[] = $list;
        $lists[] = $params;


        $params = [];
        $list = new ItemList(5);
        for($i = 0; $i < 100; $i++) {
            $item = new Item($id, [0, 0, 0, 0, 0]);
            $list->addItem($item);
        }
        $params[] = $list;
        $lists[] = $params;

        $params = [];
        $list = new ItemList(2);
        for($i = 0; $i < 100; $i++) {
            $item = new Item($i, [rand(-10, 10),rand(-10, 10)]);

            $list->addItem($item);
        }
        $params[] = $list;
        $lists[] = $params;

        return $lists;
    }

    private function checkTree(KDTree $tree)
    {
        $root = $tree->getRoot();
        $this->checkNode($root, 0);
    }

    private function checkLeftBranch(Node $node, float $value, int $d)
    {
        $val = $node->getItem()->getNthDimension($d);

        $this->assertLessThanOrEqual($value, $val);

        $left = $node->getLeft();
        if($left){
            $this->checkLeftBranch($left, $value, $d);
        }
        $right = $node->getRight();
        if($left){
            $this->checkLeftBranch($right, $value, $d);
        }
    }

    private function checkRightBranch(Node $node, float $value, int $d)
    {
        $val = $node->getItem()->getNthDimension($d);

        $this->assertGreaterThanOrEqual($value, $val);

        $left = $node->getLeft();
        if($left){
            $this->checkRightBranch($left, $value, $d);
        }
        $right = $node->getRight();
        if($left){
            $this->checkRightBranch($right, $value, $d);
        }
    }

    public function checkNode(Node $node, int $d) {
        $value = $node->getItem()->getNthDimension($d);
        $left = $node->getLeft();
        if($left){
            $this->checkLeftBranch($left, $value, $d);
        }


        $right = $node->getRight();
        if($left){
            $this->checkRightBranch($right, $value, $d);
        }
    }
}
