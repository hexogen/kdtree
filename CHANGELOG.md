# Changelog

All Notable changes to `hexogen/kdtree` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

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
