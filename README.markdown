B2Backblaze
===========

B2Backblaze is a PHP5 library that provides a B2 API Client.

This project is under development, your contributions are greatly appreciated.
[![Build Status](https://secure.travis-ci.org/kamilZ/B2Backblaze.png)](http://travis-ci.org/KamilZ/B2Backblaze)

B2 Cloud Storage?
-----------------

[B2 Cloud Storage](https://www.backblaze.com/b2/cloud-storage.html) is a cloud service for storing files in the cloud. Files are available for download at any time, either through the API or through a browser-compatible URL.

Using the B2 Cloud Storage API, you can:

* Manage the configuration of your account

* Create and manage the buckets that hold files

* Upload, download, and delete files


Installation
------------

```bash
php composer.phar require kamilZ/b2backblaze:0.1.*
```

Try it!
=======

You can use B2API.php class if you only need the API integration. Otherwise use B2Service.php.
B2Service is a custom integration, oriented to file names, which is the information we normally keep in the database.

```php
<?php

use B2Backblaze\B2Service;

$client = new B2Service($account_id, $application_key);

//Authenticate with server. Anyway, all methods will ensure the authorization.
$client->authorize()

// Returns true if bucket exists
$client->isBucketExist($bucketId)

//Returns the bucket information array.
$client->getBucketById($bucketId)

//Returns the file content and file metadata. Set $metadataOnly to true if you only need metadata information.
$client->get($bucketName, $fileName, $private = false, $metadataOnly = false)

//Return ziped foled of list files by name.
$client->getAllZip($bucketName, array $filesName, $zipFileName, $private = false)

//Inserts file and returns array of file metadata.
$client->insert($bucketId, $file, $fileName)

//Delete last file version
$client->delete($bucketName, $fileName, $private = false)

//Rename file in bucket
$client->rename($bucketName, null, $fileName, $targetBucketId, $newFileName, $private = false)

//Returns the list of files in bucket.
$client->all($bucketId)

//Check if the file exists in a bucket
$client->exists($bucketId, $fileName)

```

Integrations!
=======

Bundle for easy usage with symfony2: [https://github.com/kamilZ/B2BackblazeBundle](https://github.com/kamilZ/B2BackblazeBundle)

Gaufrette filesystem abstraction library fork: [https://github.com/kamilZ/Gaufrette](https://github.com/kamilZ/Gaufrette)

Provides a Gaufrette integration for your Symfony projects: [https://github.com/kamilZ/KnpGaufretteBundle](https://github.com/kamilZ/KnpGaufretteBundle)
