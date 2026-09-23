# Changelog

All Notable changes to `hexogen/kdtree` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## v0.3.1 - 2026-09-23

### Fixed
- `Point`/`Item` accepted `NAN` and `INF` coordinates; a single NaN item could make
  `NearestSearch` return a wrong nearest neighbour. Non-finite values now throw
  `ValidationException`.
- `FSTreePersister` overwrote the target file in place, so an `FSKDTree` that already had
  it open silently mixed the old header with the new nodes. The tree is now written to a
  temporary file in the same directory and renamed over the target; on failure the old
  file is left untouched and the temporary file is removed.
- Nodes obtained from an `FSKDTree` stopped working (raw `TypeError`) once the tree
  object was released. The file handle now stays open while any node references it.
- `KDTree` used `mt_rand()` for pivots, changing the caller's `mt_srand()` sequence.
  It now uses a private `Random\Randomizer`.

### Added
- `FSKDTree` constructor argument `$cacheDepth` to bound memory: only the given number of
  levels below the root stay cached (default `null` keeps the previous cache-everything
  behaviour).
- `KDTree` constructor argument `$randomizer` for a reproducible tree shape.
- `FSKDTree` validates on open that the file size matches the item count in the header,
  so truncated files fail immediately instead of on the first search that reaches them.
- `FSKDTree::getNodeLength()`.

### Changed
- `NearestSearch::search()` throws `ValidationException` for a negative `$resultLength`
  (it silently returned an empty array).
- Distance computation uses multiplication instead of `pow()`.

## v0.3.0 - 2026-09-23

### Fixed
- `KDTree` build time was O(n²) on sorted or reverse-sorted input (first-element pivot
  in quickselect); partition now uses a random pivot.
- Item ids were silently truncated to unsigned 32-bit by the binary format: negative ids
  and ids above 4294967295 came back corrupted from `FSKDTree`.
- `FSKDTree` and `FSTreePersister` now throw `FileException` instead of emitting PHP
  warnings / `TypeError`s when a file cannot be opened, written or is truncated.
- Test helper `TreeTestCase` skipped right-subtree invariant checks.

### Changed
- **Breaking:** new binary file format (version 1) with a `KDT` magic + version header,
  64-bit item ids, item count and node offsets, and explicit little-endian doubles.
  Files written by earlier releases must be re-created with `FSTreePersister`.
- `FSKDTree::INT_LENGTH` is now 8; added `MAGIC`, `FORMAT_VERSION`, `HEADER_LENGTH`
  and `DIMENSIONS_LENGTH` constants.
- `NearestSearch::prioritySearch()` is private (it was never part of the public API).
- Tests use PHPUnit attributes instead of doc-comment annotations (removed in PHPUnit 12).
- `mockery/mockery` dev dependency pinned to `^1.6` instead of `dev-main`.

## v0.2.6 - 2024-09-16

### Changed
- PHP 8.1 support removed, PHPUnit 11.

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
