# Changelog

All Notable changes to `hexogen/kdtree` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## v0.2.8 - 2026-11-12

### Fixed
- `FSTreePersister` (since v0.2.7) looked at the file it replaces through PHP's cache of file
  information. If access rights of the file had been changed after PHP cached them, or the
  file had been replaced with a symbolic link, the new file got the previous access rights or
  the link was replaced instead of being written through. With PHP 7.4 it happened right
  after `chmod()` in the same process. The cache is cleared for the target now.

## v0.2.7 - 2026-11-12

Bug fix release of the 0.2 line. The public API and the binary file format are not changed:
files written by earlier 0.2 releases are read by this one and the other way around.

### Fixed
- `KDTree` build time was O(n²) on sorted, reverse-sorted and partially ordered input
  (the first element was used as a quickselect pivot). A pseudo-random pivot from a private
  generator is used now, so the tree shape stays reproducible and `mt_rand()`/`rand()`
  sequences of the caller are not affected.
- `NearestSearch::search()` returned `null` entries instead of items when the square of
  a distance did not fit into float (coordinates about 1e154 apart) or when a tree reported
  more items than it had. Only items are returned now.
- `Point`/`Item` accepted `NAN` and infinite coordinates (including numeric strings like
  `'1e999'`); a single NaN item could make `NearestSearch` return a wrong nearest neighbour.
  Such values throw `ValidationException` now.
- `FSKDTree` failed with PHP warnings / `TypeError` / `ValueError`, or ran out of memory, on
  a missing, truncated or damaged file and on a file that is not a kd tree. It throws
  `FileException` now, also for a file written by v0.3 or newer.
- Nodes obtained from an `FSKDTree` stopped working (`TypeError`) once the tree object was
  released. The file stays open while the tree or any of its nodes is referenced.
- `FSTreePersister` silently damaged item ids that do not fit into unsigned 32-bit integer
  of the file format (`-5` was read back as `4294967291`, `5000000000` as `705032704`).
  It throws `FileException` now, as well as for a tree that needs a file bigger than 4 GiB.
- `FSTreePersister` overwrote the target file in place: an `FSKDTree` that had it open mixed
  the old header with the new nodes and a failed write left a truncated file. The tree is
  written to a temporary file in the same directory which is then moved over the target
  (keeping its access rights). The target is still written in place if it is a symbolic
  link or if a temporary file cannot be created next to it.
- `FSTreePersister` failed with a `TypeError` if the file could not be opened and ignored
  failed writes (full disk). It throws `FileException` now and always closes the file.
- `KDTree` required `ItemListInterface::getItems()` of a custom item list to return
  an array with sequential keys starting from 0. Any keys are accepted now.
- Test helper `TreeTestCase` skipped checks of right branches.
- Broken link to the code of conduct in README.

### Added
- `Hexogen\KDTree\Exception\FileException` (extends `\RuntimeException`).

### Upgrade notes
- Code that passed `NAN`/infinite coordinates or persisted items with negative ids used to
  get wrong results silently and gets an exception now.
- The tree built from the same items has a different (still valid) shape than in v0.2.6,
  so items that are equally distant from a point may be returned in a different order.

## v0.2.0 - 2018-12-23

### Changed
- PHP 7.0 support removed
- Interfaces now returns types according to documentation (where it was not possible according
  to php 7.0 limitations)

## v0.1.1 - 2018-09-04

### Added
- Support phpunit v7.x

### Deprecated
- PHP 7.0 support
