B2Backblaze
===========

B2Backblaze is a PHP5 library that provides a B2 API Client.

This project is under development, your contributions are greatly appreciated.
[![Build Status](https://secure.travis-ci.org/kamilZ/B2Backblaze.png)](http://travis-ci.org/KamilZ/B2Backblaze)

B2 Cloud Storage?
-----------------

[B2 Cloud Storage](https://www.backblaze.com/b2/cloud-storage.html) is a cloud service for storing files in the cloud. Files are available for download at any time, either through the API or through a browser-compatible URL.

Using the B2 Cloud Storage API, you can:

* Manage the configuration of your account [PENDING]

* Create and manage the buckets that hold files [PENDING]

* Upload [DONE], download [PENDING], and delete files [PENDING]


Installation
------------

```bash
php composer.phar require kamilZ/b2backblaze:0.1.*
```

Try it!
=======

For example:

```php
<?php

use B2Backblaze\B2Client.php;

$client = new B2Client.php();
```