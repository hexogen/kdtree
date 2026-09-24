# K-D Tree

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Build Status][ico-tests]][link-tests]
[![codecov][ico-codecov]][link-codecov]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

PHP multidimensional K-D Tree implementation with k-nearest-neighbour search.

The tree is built in memory from a list of items (`KDTree`) and can be saved to a
compact binary file. The file-backed tree (`FSKDTree`) reads nodes lazily while
searching, so opening a large index costs almost nothing and searches touch only
the nodes they visit. This is much faster than unserializing a whole tree on every
request, and it is the recommended way to use the library for anything that does
not fit comfortably in memory or is rebuilt rarely and searched often.

## Install

Via Composer

``` bash
$ composer require hexogen/kdtree
```

### Requirements

- PHP 8.2 or newer; tested on PHP 8.2, 8.3, 8.4 and 8.5.
- A 64-bit PHP build for `FSKDTree` / `FSTreePersister`: the binary format uses
  64-bit integers, which `pack()` / `unpack()` only support on 64-bit platforms.
  The in-memory `KDTree` works on 32-bit builds too.

No extensions beyond the PHP core are needed.

## Usage

All classes live in the `Hexogen\KDTree` namespace:

``` php
use Hexogen\KDTree\FSKDTree;
use Hexogen\KDTree\FSTreePersister;
use Hexogen\KDTree\Item;
use Hexogen\KDTree\ItemFactory;
use Hexogen\KDTree\ItemList;
use Hexogen\KDTree\KDTree;
use Hexogen\KDTree\NearestSearch;
use Hexogen\KDTree\Point;
```

### Tree creation

``` php
//Item container with 2 dimensional points
$itemList = new ItemList(2);

//Adding 2 - dimension items to the list
$itemList->addItem(new Item(1, [1.2, 4.3]));
$itemList->addItem(new Item(2, [1.3, 3.4]));
$itemList->addItem(new Item(3, [4.5, 1.2]));
$itemList->addItem(new Item(4, [5.2, 3.5]));
$itemList->addItem(new Item(5, [2.1, 3.6]));

//Building tree with given item list
$tree = new KDTree($itemList);
```

Every item has an integer id and a list of finite numeric coordinates; the number
of coordinates must match the dimension count of the list. Adding an item whose id
is already in the list replaces the earlier item, so ids should be unique. The
tree is built once from the whole list: there is no incremental insert or delete,
rebuild the tree when the data changes. Building is O(n log n) on average
whatever the order of the input (the median is chosen with a random pivot).

Ids are plain `int`s because the binary file stores them as signed 64-bit
integers. Keep other data (names, payloads) in your own storage keyed by id.

### Searching nearest items to the given point

``` php
//Creating search engine with custom algorithm (currently Nearest Search)
$searcher = new NearestSearch($tree);

//Retrieving a result ItemInterface[] array with given size (currently 2)
$result = $searcher->search(new Point([1.25, 3.5]), 2);

echo $result[0]->getId(); // 2
echo $result[0]->getNthDimension(0); // 1.3
echo $result[0]->getNthDimension(1); // 3.4

echo $result[1]->getId(); // 1
echo $result[1]->getNthDimension(0); // 1.2
echo $result[1]->getNthDimension(1); // 4.3
```

Results are ordered nearest first by Euclidean distance. The second argument is
the number of items to return (default `1`); asking for more items than the tree
holds returns all of them, asking for `0` returns an empty array, and searching an
empty tree returns an empty array. The point must have the same number of
dimensions as the tree.

Results carry the items only, not their distances. When you need the distance,
compute it from the coordinates:

``` php
$point = new Point([1.25, 3.5]);
foreach ($searcher->search($point, 2) as $item) {
    $squared = 0.0;
    for ($d = 0; $d < $point->getDimensionsCount(); $d++) {
        $delta = $item->getNthDimension($d) - $point->getNthDimension($d);
        $squared += $delta * $delta;
    }
    echo $item->getId(), ': ', sqrt($squared), PHP_EOL;
}
```

A `NearestSearch` instance can be reused for any number of searches on the same
tree.

### Reproducible tree shape

The build picks pivots from a private random generator, so `mt_srand()` and
`mt_rand()` in your code are not affected. If you need the same tree layout on
every run (for example in tests or to get byte-identical persisted files), pass a
seeded `Random\Randomizer`:

``` php
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

$tree = new KDTree($itemList, new Randomizer(new Xoshiro256StarStar(42)));
```

The shape only affects build time and the on-disk layout; a search finds the same
nearest items whatever the shape (items at exactly equal distances may come back
in a different order).

### Persist tree to a binary file

``` php
//Init tree writer
$persister = new FSTreePersister('/path/to/dir');

//Save the tree to /path/to/dir/treeName.bin
$persister->convert($tree, 'treeName.bin');
```

`FSTreePersister` writes to a temporary file in the same directory and renames it
over the target, so it is safe to rebuild a tree file while other processes are
reading it: readers never see a half-written file, and an `FSKDTree` that already
has the old file open keeps reading the old data until it is re-opened. On
failure the old file is left untouched and the temporary file is removed. Two
things to be aware of:

- On Windows the rename fails while another process has the target file open.
- The new file gets default permissions (your `umask`), not the permissions of the
  file it replaces. Re-apply `chmod()` after persisting if you rely on them.

### File system version of the tree

``` php
//ItemInterface factory
$itemFactory = new ItemFactory();

//Then init new instance of file system version of the tree
$fsTree = new FSKDTree('/path/to/dir/treeName.bin', $itemFactory);

//Now use fs kdtree to search
$fsSearcher = new NearestSearch($fsTree);

//Retrieving a result ItemInterface[] array with given size (currently 2)
$result = $fsSearcher->search(new Point([1.25, 3.5]), 2);

echo $result[0]->getId(); // 2
echo $result[1]->getId(); // 1
```

`FSKDTree` implements the same `KDTreeInterface` as `KDTree`, so everything that
works on an in-memory tree works on a file-backed one. The factory decides which
class the items read from the file become; implement
`Hexogen\KDTree\Interfaces\ItemFactoryInterface` to get your own item objects
back from a search.

Opening a file reads only the header and validates that the file size matches
the item count it declares. The file is opened read-only, so any number of
processes can search the same file at the same time. The file handle stays open
as long as the tree, or any node obtained from it, is referenced.

Nodes read from the file are cached, so a long-lived `FSKDTree` gradually loads
the whole file into memory. To bound memory, keep only the top levels cached:

``` php
// cache the root and 12 levels below it (at most ~8k nodes), read the rest on demand
$fsTree = new FSKDTree('/path/to/dir/treeName.bin', $itemFactory, 12);
```

With a cache depth of `n` at most `2^(n+1) - 1` nodes stay in memory; `0` keeps
only the root, `null` (the default) keeps everything ever read.

### Boundaries

`getMinBoundary()` and `getMaxBoundary()` return the corners of the bounding box
that contains all items, as arrays indexed by dimension. On an empty tree they
are `[INF, ...]` and `[-INF, ...]` respectively, and `getRoot()` returns `null`.

## Binary file format

`FSTreePersister` writes and `FSKDTree` reads format version 1. All integers and
floats are little-endian, floats are IEEE 754 doubles. The layout is described in
the `FSKDTree` docblock and summarised here (`D` is the number of dimensions,
`N` the number of items):

| Offset            | Size          | Content                                                   |
|-------------------|---------------|-----------------------------------------------------------|
| 0                 | 3 bytes       | magic `KDT`                                               |
| 3                 | 1 byte        | format version (`1`)                                      |
| 4                 | 4 bytes       | `D`, unsigned 32-bit                                      |
| 8                 | 8 bytes       | `N`, unsigned 64-bit                                      |
| 16                | 8 × `D` bytes | max boundary, `D` doubles                                 |
| 16 + 8·D          | 8 × `D` bytes | min boundary, `D` doubles                                 |
| 16 + 16·D         | `N` nodes     | nodes, root first, pre-order (right subtree, then left)   |

Each node is `24 + 8 × D` bytes (`FSKDTree::getNodeLength($dimensions)`):

| Size          | Content                                                          |
|---------------|------------------------------------------------------------------|
| 8 bytes       | item id, signed 64-bit                                           |
| 8 bytes       | absolute file offset of the left child, `0` if none              |
| 8 bytes       | absolute file offset of the right child, `0` if none             |
| 8 × `D` bytes | coordinates, `D` doubles                                         |

Files written by versions before 0.3.0 have no header and cannot be read anymore:
re-create them with `FSTreePersister`. The reader rejects files with a wrong
magic, an unsupported version, a dimension count of `0`, or a size that does not
match `N`.

## Errors

Invalid input and file problems are reported with exceptions, not PHP warnings.

- `Hexogen\KDTree\Exception\ValidationException` for invalid arguments: an empty
  coordinate list, a coordinate that is not numeric or not finite (`NAN`, `INF`),
  an item or search point whose dimension count does not match the tree, an
  `ItemList` with `0` dimensions, or a negative result length.
- `Hexogen\KDTree\Exception\FileException` (a `RuntimeException`) from `FSKDTree`
  and `FSTreePersister` when a file cannot be opened, written or renamed, is
  truncated, has an unsupported format, or when a node is read after the tree's
  file handle was closed.
- `\OutOfRangeException` from `getNthDimension()` for a dimension index outside
  `0 .. getDimensionsCount() - 1`.
- `\InvalidArgumentException` from the `FSKDTree` constructor for a negative
  cache depth.

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
$ composer check-style
```

The test suite runs on PHP 8.2–8.5 in CI with 100% code coverage; contributions
are expected to keep it there.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](.github/CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email volodymyrbas@gmail.com instead of using the issue tracker.

## Credits

- [Volodymyr Basarab][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/hexogen/kdtree.svg?style=flat-square
[ico-tests]: https://img.shields.io/github/actions/workflow/status/hexogen/kdtree/tests.yml?branch=master
[ico-codecov]: https://codecov.io/gh/hexogen/kdtree/graph/badge.svg?token=176L4UA0Y1
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/hexogen/kdtree.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/hexogen/kdtree
[link-tests]: https://github.com/hexogen/kdtree/actions?query=workflow%3ATests
[link-codecov]: https://codecov.io/gh/hexogen/kdtree
[link-downloads]: https://packagist.org/packages/hexogen/kdtree
[link-author]: https://github.com/hexogen
[link-contributors]: ../../contributors
