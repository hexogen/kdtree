<?php

namespace Hexogen\KDTree;

use Hexogen\KDTree\Exception\FileException;
use Hexogen\KDTree\Interfaces\ItemInterface;
use Hexogen\KDTree\Interfaces\KDTreeInterface;
use Hexogen\KDTree\Interfaces\NodeInterface;
use Hexogen\KDTree\Interfaces\TreePersisterInterface;

/**
 * Writes a KD tree to a binary file readable by FSKDTree.
 * See FSKDTree for the file format description.
 */
class FSTreePersister implements TreePersisterInterface
{
    /**
     * @var string path to the directory
     */
    private $path;

    /**
     * @var resource|null file handler
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
     * @api
     * @param KDTreeInterface $tree
     * @param string $identifier file name inside the directory given to the constructor
     * @return void
     * @throws FileException if the file cannot be created or written
     */
    public function convert(KDTreeInterface $tree, string $identifier)
    {
        $this->openFile($identifier);

        try {
            $this->dimensions = $tree->getDimensionCount();
            $this->calculateNodeSize();

            $this->writeHeader();
            $this->writeNumberOfDimensions();
            $this->writeNumberOfItems($tree);
            $this->writeCoordinate($tree->getMaxBoundary());
            $this->writeCoordinate($tree->getMinBoundary());

            $root = $tree->getRoot();
            if ($root) {
                $this->writeNode($root);
            }
        } finally {
            fclose($this->handler);
            $this->handler = null;
        }
    }

    /**
     * @param NodeInterface $node
     */
    private function writeNode(NodeInterface $node)
    {
        $position = ftell($this->handler);
        $item = $node->getItem();

        $rightNode = $node->getRight();
        $rightPosition = $rightNode ? $position + $this->nodeMemorySize : 0;

        // item id, left position (unknown yet, patched in persistLeftLink), right position
        $this->write(pack('PPP', $item->getId(), 0, $rightPosition));
        $this->writeItemCoordinate($item);

        if ($rightNode) {
            $this->writeNode($rightNode);
        }

        $leftNode = $node->getLeft();
        if ($leftNode === null) {
            return;
        }
        $this->persistLeftLink($position);
        $this->writeNode($leftNode);
    }

    /**
     * @param array $coordinate
     */
    private function writeCoordinate(array $coordinate)
    {
        $this->write(pack('e' . $this->dimensions, ...$coordinate));
    }

    /**
     * @param string $identifier
     * @throws FileException
     */
    private function openFile(string $identifier)
    {
        $filename = $this->path . '/' . $identifier;
        $handler = @fopen($filename, 'wb');
        if ($handler === false) {
            throw new FileException('Unable to open kd tree file for writing: ' . $filename);
        }
        $this->handler = $handler;
    }

    /**
     * Calculate memory size in file needed for single node
     */
    private function calculateNodeSize()
    {
        $this->nodeMemorySize = 3 * FSKDTree::INT_LENGTH + $this->dimensions * FSKDTree::FLOAT_LENGTH;
    }

    /**
     * Write file signature and format version
     */
    private function writeHeader()
    {
        $this->write(FSKDTree::MAGIC . chr(FSKDTree::FORMAT_VERSION));
    }

    /**
     * Write number of dimensions according to file format
     */
    private function writeNumberOfDimensions()
    {
        $this->write(pack('V', $this->dimensions));
    }

    /**
     * @param KDTreeInterface $tree
     */
    private function writeNumberOfItems(KDTreeInterface $tree)
    {
        $this->write(pack('P', $tree->getItemCount()));
    }

    /**
     * @param ItemInterface $item
     */
    private function writeItemCoordinate(ItemInterface $item)
    {
        $coordinate = [];
        for ($i = 0; $i < $this->dimensions; $i++) {
            $coordinate[] = $item->getNthDimension($i);
        }
        $this->writeCoordinate($coordinate);
    }

    /**
     * Persist current position as the left link of the node written at $position
     * @param int $position
     */
    private function persistLeftLink(int $position)
    {
        $leftPosition = ftell($this->handler);
        fseek($this->handler, $position + FSKDTree::INT_LENGTH);
        $this->write(pack('P', $leftPosition));
        fseek($this->handler, $leftPosition);
    }

    /**
     * Write a chunk to the file, failing loudly on short writes
     * @param string $dataChunk
     * @throws FileException
     */
    private function write(string $dataChunk)
    {
        $written = fwrite($this->handler, $dataChunk);
        if ($written === false || $written !== strlen($dataChunk)) {
            throw new FileException('Unable to write kd tree file (disk full or file not writable?)');
        }
    }
}
