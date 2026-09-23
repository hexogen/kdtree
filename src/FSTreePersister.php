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
 *
 * The tree is written to a temporary file in the same directory which is then
 * renamed over the target, so readers never see a half-written file and an
 * FSKDTree that already has the old file open keeps reading the old data.
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
     * @var string|null temporary file being written
     */
    private $tempFilename;

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
        $filename = $this->path . '/' . $identifier;
        $this->openTempFile($filename);

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

            $this->closeFile();
            $this->replaceTarget($filename);
        } catch (\Throwable $e) {
            if ($this->handler !== null) {
                @fclose($this->handler);
                $this->handler = null;
            }
            @unlink($this->tempFilename);
            throw $e;
        } finally {
            $this->tempFilename = null;
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
     * Create a uniquely named temporary file next to the target
     * @param string $filename target file name
     * @throws FileException
     */
    private function openTempFile(string $filename)
    {
        $tempFilename = $filename . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $handler = @fopen($tempFilename, 'xb');
        if ($handler === false) {
            throw new FileException('Unable to open kd tree file for writing: ' . $tempFilename);
        }
        $this->handler = $handler;
        $this->tempFilename = $tempFilename;
    }

    /**
     * Flush and close the file, failing loudly if buffered data could not be written
     * @throws FileException
     */
    private function closeFile()
    {
        $handler = $this->handler;
        $this->handler = null;
        $flushed = fflush($handler);
        if (!fclose($handler) || !$flushed) {
            throw new FileException('Unable to write kd tree file: ' . $this->tempFilename);
        }
    }

    /**
     * Atomically move the fully written temporary file over the target
     * @param string $filename
     * @throws FileException
     */
    private function replaceTarget(string $filename)
    {
        if (!@rename($this->tempFilename, $filename)) {
            throw new FileException('Unable to replace kd tree file: ' . $filename);
        }
    }

    /**
     * Calculate memory size in file needed for single node
     */
    private function calculateNodeSize()
    {
        $this->nodeMemorySize = FSKDTree::getNodeLength($this->dimensions);
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
