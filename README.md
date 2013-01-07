PHP-TINYPNG-COMMAND-LINE-TOOL
=============================

This PHP command line tool allows you to shrink your png files using the 
tinypng service.

See (http://tinypng.org/) for more informations.

:warning: 


*It requires at least PHP 5.3 to run*

Installation
============

``` 
curl -s https://getcomposer.org/installer | php

php composer.phar install

chmod +x bin/tiny
``` 

How to
======

``` 
Usage:
 bin/tiny client:shrink [--output-dir="..."] [--override] [--no-recursive] file

Arguments:
 file            What do you want to shrink? (can be a single file or a directory)

Options:
 --output-dir    Where do you want the shrinked images to go ?
 --override      Override existing images
 --no-recursive  Do not recurse into directories

``` 

All shrinked images will be prefixed by this string **shrinked.**

Unit testing
============

[![Build Status](https://secure.travis-ci.org/nlegoff/tiny-client.png?branch=master)](http://travis-ci.org/nlegoff/tiny-client)

Licence
=======

This project is licensed under the [MIT license](http://opensource.org/licenses/MIT).
