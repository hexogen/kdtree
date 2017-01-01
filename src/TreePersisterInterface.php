<?php

namespace Hexogen\KDTree;

interface TreePersisterInterface
{
    /**
     * @param KDTreeInterface $tree
     * @param string $identifier that identifies persisted tree(may be a filename, database name etc.)
     * @return mixed
     */
    public function convert(KDTreeInterface $tree, string $identifier);
}
