# Nzymes
Version 3 of my undestructible [Enzymes](https://wordpress.org/plugins/enzymes/) plugin for WordPress.

[![Build Status](https://travis-ci.org/aercolino/nzymes.svg?branch=master)](https://travis-ci.org/aercolino/nzymes)


## Dev env

### Build

```
andrea at Lock-and-Stock in ~/dev/wordpress/plugins/nzymes on master [!$]
$ rake nzymes:clean
$ rake nzymes:update
```


### Tests

```
andrea at Lock-and-Stock in ~/dev/wordpress/plugins/nzymes on master [!$]
$ bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
$ vendor/bin/phpunit
```
