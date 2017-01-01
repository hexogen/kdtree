<?php

namespace Hexogen\KDTree;

class FileTreePersister implements TreePersisterInterface
{
    /**
     * @var string path to the file
     */
    private $path;

    /**
     * @var resource file handler
     */
    private $handler;

    /**
     * @var int
     */
    private $dimensions;

    /**
     * @var int
     */
    private $nodeMemorySize;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * @param KDTreeInterface $tree
     * @param string $identifier that identifies persisted tree(may be a filename, database name etc.)
     * @return mixed
     */
    public function convert(KDTreeInterface $tree, string $identifier)
    {
        $dataChunk = null;
        $this->handler = fopen($this->path . '/' . $identifier, 'wb');

        $this->dimensions = $tree->getDimensionCount();

        $this->nodeMemorySize = $this->dimensions * 8 + 3 * 4;

        $dataChunk = pack('V', $this->dimensions);
        fwrite($this->handler, $dataChunk);

        $itemCount = $tree->getItemCount();
        $dataChunk = pack('V', $itemCount);
        fwrite($this->handler, $dataChunk);

        $upperBound = $tree->getMaxBoundary();
        $this->writeCoordinate($upperBound);

        $lowerBound = $tree->getMinBoundary();
        $this->writeCoordinate($lowerBound);

        $this->writeNode($tree->getRoot());
        fclose($this->handler);
    }

    /**
     * @param NodeInterface $node
     */
    private function writeNode(NodeInterface $node)
    {
        $position = ftell($this->handler);
        $coordinate = [];
        $item = $node->getItem();

        $itemId = $item->getId();
        $dataChunk = pack('V', $itemId);
        fwrite($this->handler, $dataChunk);

        $dataChunk = pack('V', 0); // left position currently unknown so it equal 0/null
        fwrite($this->handler, $dataChunk);

        $rightNode = $node->getRight();

        $rightPosition = 0;
        if ($rightNode) {
            $rightPosition = $position + $this->nodeMemorySize;
        }
        $dataChunk = pack('V', $rightPosition);
        fwrite($this->handler, $dataChunk);


        for ($i = 0; $i < $this->dimensions; $i++) {
            $coordinate[] = $item->getNthDimension($i);
        }
        $this->writeCoordinate($coordinate);

        if ($rightNode) {
            $this->writeNode($rightNode);
        }

        $leftNode = $node->getLeft();

        if ($leftNode == null) {
            return;
        }
        $leftPosition = ftell($this->handler);
        fseek($this->handler, $position + 4);
        $dataChunk = pack('V', $leftPosition);
        fwrite($this->handler, $dataChunk);

        fseek($this->handler, $leftPosition);
        $this->writeNode($leftNode);
    }

    /**
     * @param array $coordinate
     */
    private function writeCoordinate(array $coordinate)
    {
        $dataChunk = pack('d'.$this->dimensions, ...$coordinate);
        fwrite($this->handler, $dataChunk);
    }
}
