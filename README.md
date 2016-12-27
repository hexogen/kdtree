# kdtree

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

PHP KD Tree implementation. With availability to create custom search algorithms and tree storage engines.

## Install

Via Composer

``` bash
$ composer require hexogen/kdtree
```

## Usage

``` php
//Item container with 2 dimensional points
$itemList = new ItemList(2);

//Adding 2 - dimension items to the list
$itemList->addItem(new Item(1, [1.2, 4.3]));
$itemList->addItem(new Item(2, [1.3, 3.4]));
$itemList->addItem(new Item(3, [4.5, 1.2]));
$itemList->addItem(new Item(4, [5.2, 3.5]));
$itemList->addItem(new Item(5, [2.1, 3.6]));

//building tree with given item list
$tree = new KDTree($itemList);

//creating search engine with custom algorithm (currently Nearest Search)
$searcher = new NearestSearch($tree);

//retrieving a result ItemInterface[] array with given size (currently 2)
$result = $searcher->search(new Point([1.25, 3.5]), 2);

echo $result[0]->getId(); // 2
echo $result[1]->getId(); // 1
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email volodymyrbas@gmail.com instead of using the issue tracker.

## Credits

- [Volodymyr Basarab][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/hexogen/kdtree.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/hexogen/kdtree/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/hexogen/kdtree.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/hexogen/kdtree.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/hexogen/kdtree.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/hexogen/kdtree
[link-travis]: https://travis-ci.org/hexogen/kdtree
[link-scrutinizer]: https://scrutinizer-ci.com/g/hexogen/kdtree/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/hexogen/kdtree
[link-downloads]: https://packagist.org/packages/hexogen/kdtree
[link-author]: https://github.com/hexogen
[link-contributors]: ../../contributors
