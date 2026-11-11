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
 * renamed over the target, so a failed write leaves the previous file untouched
 * and an FSKDTree that already has the previous file open keeps reading it.
 *
 * The target is overwritten in place, the way it has been done before v0.2.7, when
 * it is a symbolic link, when a temporary file cannot be created next to it or when
 * it cannot be replaced by renaming (a file locked by another process on Windows).
 */
class FSTreePersister implements TreePersisterInterface
{
    /**
     * @const int the biggest item id and the biggest file offset the file format is able to hold
     */
    private const MAX_UNSIGNED_INT = 4294967295;

    /**
     * @var string path to the directory
     */
    private $path;

    /**
     * @var resource|null file handler
     */
    private $handler;

    /**
     * @var string|null temporary file being written, null if the target is written in place
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
     * @return mixed
     * @throws FileException if the file cannot be created or written, or if the tree cannot be
     * stored in the file format (an item id is not in 0..4294967295 or the file would exceed 4 GiB)
     */
    public function convert(KDTreeInterface $tree, string $identifier)
    {
        $filename = $this->path . '/' . $identifier;

        $this->dimensions = $tree->getDimensionCount();

        $this->calculateNodeSize();

        $this->validateTreeSize($tree);

        $this->initTree($filename);

        try {
            $this->specifyNumberOfDimensions();

            $this->specifyNumberOfItems($tree);

            $upperBound = $tree->getMaxBoundary();
            $this->writeCoordinate($upperBound);

            $lowerBound = $tree->getMinBoundary();
            $this->writeCoordinate($lowerBound);

            $root  = $tree->getRoot();
            if ($root) {
                $this->writeNode($root);
            }

            $this->closeFile();
            $this->replaceTarget($filename);
        } catch (\Throwable $e) {
            $this->discardFile();
            throw $e;
        }
    }

    /**
     * @param NodeInterface $node
     * @throws FileException
     */
    private function writeNode(NodeInterface $node)
    {
        $position = ftell($this->handler);
        $item = $node->getItem();

        $this->writeItemId($item);

        $dataChunk = pack('V', 0); // left position currently unknown so it equal 0/null
        $this->write($dataChunk);

        $rightNode = $node->getRight();

        $rightPosition = 0;
        if ($rightNode) {
            $rightPosition = $position + $this->nodeMemorySize;
        }
        $dataChunk = pack('V', $rightPosition);
        $this->write($dataChunk);

        $this->saveItemCoordinate($item);

        if ($rightNode) {
            $this->writeNode($rightNode);
        }

        $leftNode = $node->getLeft();

        if ($leftNode == null) {
            return;
        }
        $this->persistLeftLink($position);
        $this->writeNode($leftNode);
    }

    /**
     * @param array $coordinate
     * @throws FileException
     */
    private function writeCoordinate(array $coordinate)
    {
        $dataChunk = pack('d'.$this->dimensions, ...$coordinate);
        $this->write($dataChunk);
    }

    /**
     * Open a file the tree is written to: a uniquely named temporary file next to the target
     * (it gets access rights of the file it is going to replace) or, if that is not possible,
     * the target itself
     *
     * @param string $filename target file name
     * @throws FileException
     */
    private function initTree(string $filename)
    {
        $this->tempFilename = null;

        if (!is_link($filename)) {
            $tempFilename = $filename . '.' . bin2hex(random_bytes(6)) . '.tmp';
            $handler = @fopen($tempFilename, 'xb');

            if ($handler !== false) {
                $permissions = @fileperms($filename);
                if ($permissions !== false) {
                    @chmod($tempFilename, $permissions & 0777);
                }

                $this->tempFilename = $tempFilename;
                $this->handler = $handler;
                return;
            }
        }

        $handler = @fopen($filename, 'wb');
        if ($handler === false) {
            throw new FileException('Unable to open kd tree file for writing: ' . $filename);
        }

        $this->handler = $handler;
    }

    /**
     * Flush and close the file, failing loudly if buffered data could not be written
     * @throws FileException
     */
    private function closeFile()
    {
        $handler = $this->handler;
        $this->handler = null;

        $flushed = @fflush($handler);
        $closed = @fclose($handler);

        if (!$flushed || !$closed) {
            throw new FileException('Unable to write kd tree file (disk full?)');
        }
    }

    /**
     * Move the fully written temporary file over the target
     *
     * @param string $filename target file name
     * @throws FileException
     */
    private function replaceTarget(string $filename)
    {
        if ($this->tempFilename === null) {
            return;
        }

        if (@rename($this->tempFilename, $filename)) {
            $this->tempFilename = null;
            return;
        }

        if (!@copy($this->tempFilename, $filename)) {
            throw new FileException('Unable to replace kd tree file: ' . $filename);
        }
        @unlink($this->tempFilename);
        $this->tempFilename = null;
    }

    /**
     * Close the file and remove the temporary one after a failure
     */
    private function discardFile()
    {
        if ($this->handler !== null) {
            @fclose($this->handler);
            $this->handler = null;
        }

        if ($this->tempFilename !== null) {
            @unlink($this->tempFilename);
            $this->tempFilename = null;
        }
    }

    /**
     * Calculate memory size in file needed for single node
     */
    private function calculateNodeSize()
    {
        $this->nodeMemorySize = 3 * FSKDTree::INT_LENGTH + $this->dimensions * FSKDTree::FLOAT_LENGTH;
    }

    /**
     * Node offsets are stored as unsigned 32-bit integers, so the whole file has to fit into them
     *
     * @param KDTreeInterface $tree
     * @throws FileException
     */
    private function validateTreeSize(KDTreeInterface $tree)
    {
        $headerSize = 2 * FSKDTree::INT_LENGTH + 2 * $this->dimensions * FSKDTree::FLOAT_LENGTH;
        $fileSize = $headerSize + $tree->getItemCount() * $this->nodeMemorySize;

        if ($fileSize > self::MAX_UNSIGNED_INT) {
            throw new FileException(
                'The tree is too big to be stored: the file would take ' . $fileSize
                . ' bytes and the file format is limited to ' . self::MAX_UNSIGNED_INT
            );
        }
    }

    /**
     * Specify number of dimensions according to file format
     * @throws FileException
     */
    private function specifyNumberOfDimensions()
    {
        $dataChunk = pack('V', $this->dimensions);
        $this->write($dataChunk);
    }

    /**
     * @param KDTreeInterface $tree
     * @throws FileException
     */
    private function specifyNumberOfItems(KDTreeInterface $tree)
    {
        $itemCount = $tree->getItemCount();
        $dataChunk = pack('V', $itemCount);
        $this->write($dataChunk);
    }

    /**
     * @param ItemInterface $item
     * @throws FileException
     */
    private function saveItemCoordinate(ItemInterface $item)
    {
        $coordinate = [];
        for ($i = 0; $i < $this->dimensions; $i++) {
            $coordinate[] = $item->getNthDimension($i);
        }
        $this->writeCoordinate($coordinate);
    }

    /**
     * Persist current position before writing left node
     * @param int $position
     * @throws FileException
     */
    private function persistLeftLink(int $position)
    {
        $leftPosition = ftell($this->handler);
        fseek($this->handler, $position + FSKDTree::INT_LENGTH);
        $dataChunk = pack('V', $leftPosition);
        $this->write($dataChunk);
        fseek($this->handler, $leftPosition);
    }

    /**
     * @param ItemInterface $item
     * @throws FileException if the id cannot be stored as an unsigned 32-bit integer
     */
    private function writeItemId(ItemInterface $item)
    {
        $itemId = $item->getId();

        if ($itemId < 0 || $itemId > self::MAX_UNSIGNED_INT) {
            throw new FileException(
                'Item id ' . $itemId . ' cannot be stored: the file format keeps ids as unsigned'
                . ' 32-bit integers (0..' . self::MAX_UNSIGNED_INT . ')'
            );
        }

        $dataChunk = pack('V', $itemId);
        $this->write($dataChunk);
    }

    /**
     * Write a chunk to the file, failing loudly on short writes
     * @param string $dataChunk
     * @throws FileException
     */
    private function write(string $dataChunk)
    {
        $written = @fwrite($this->handler, $dataChunk);

        if ($written !== strlen($dataChunk)) {
            throw new FileException('Unable to write kd tree file (disk full or file is not writable?)');
        }
    }
}
