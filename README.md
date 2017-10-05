# eve2pve-api-php

ProxmoVE Client API PHP

[ProxmoxVE Api](https://pve.proxmox.com/pve-docs/api-viewer/)

[Packagist](https://packagist.org/packages/enterpriseve/eve2pve-api-php)

```text
    ______      __                       _              _    ________
   / ____/___  / /____  _________  _____(_)_______     | |  / / ____/
  / __/ / __ \/ __/ _ \/ ___/ __ \/ ___/ / ___/ _ \    | | / / __/
 / /___/ / / / /_/  __/ /  / /_/ / /  / (__  )  __/    | |/ / /___
/_____/_/ /_/\__/\___/_/  / .___/_/  /_/____/\___/     |___/_____/
                         /_/

                                                       (Made in Italy)
```

## General

The client is generated from a JSON Api on ProxmoxVE.

This PHP 5.4+ library allows you to interact with your Proxmox server via API.
The client is generated from a JSON Api on ProxmoxVE.

## Result

The result is class **Result** and contain methods:

* **getResponse()** returned from ProxmoxVE (data,errors,...) Object/Array
* **responseInError** (bool) : Contains errors from ProxmoxVE.
* **getStatusCode()** (int) : Status code of the HTTP response.
* **getReasonPhrase()** (string): The reason phrase which typically is sent by servers together with the status code.
* **isSuccessStatusCode()** (bool) : Gets a value that indicates if the HTTP response was successful.
* **getError()** (string) : Get error.

## Main features

* Easy to learn
* Method named
* Full method generated from documentation
* Comment any method and parameters
* Parameters indexed eg [n] is structured in array index and value
* Tree structure
  * $client->getNodes()->get("pve1")->getQemu()->get(100)->getSnapshot()->snapshotList()->getResponse()->data
* Return data proxmox
* Return result status
  * getStatusCode
  * getReasonPhrase
  * isSuccessStatusCode
* Wait task finish task
  * waitForTaskToFinish
* Method directry access
  * get
  * post
  * put
  * delete
* Login return bool if access
* Return Result class more information
* return object/array data
  * default object disable from client.setResultIsObject(false)

## Installation

Recommended installation is using [Composer], if you do not have [Composer] what are you waiting?

In the root of your project execute the following:

```sh
$ composer require enterpriseve/eve2pve-api-php ~1.0
```

Or add this to your `composer.json` file:

```json
{
    "require": {
        "enterpriseve/eve2pve-api-php": "~1.0"
    }
}
```

## Usage

```php
<?php

// Require the autoloader
require_once 'vendor/autoload.php';

$client = new EnterpriseVE\ProxmoxVE\Api\Client("192.168.0.24");

//login check bool
if($client->login('root','password','pam')){
  //get version from get method
  var_dump($client->get('/version')->getResponse());

  // $client->put
  // $client->post
  // $client->delete

  $retPippo=$client->get("/pippo");
  echo "\n" . $retPippo->getStatusCode();
  echo "\n" . $retPippo->getReasonPhrase();

  //loop nodes
  foreach ($client->getNodes()->Index()->getResponse()->data as $node) {
    echo "\n" . $node->id;
  }

  //loop vm
  foreach ($client->getNodes()->get("pve1")->getQemu()->Vmlist()->getResponse()->data as $vm) {
      echo "\n" . $vm->vmid ." - " .$vm->name;
  }

  //loop snapshots
  foreach ($client->getNodes()->get("pve1")->getQemu()->get(100)->getSnapshot()->snapshotList()->getResponse()->data as $snap) {
    echo "\n" . $snap->name;
  }

  //return object
  var_dump($client->getVersion()->version()->getResponse());

  //disable return object
  $client->setResultIsObject(false);
  //return array
  $retArr = $client->getVersion()->version()->getResponse();
  var_dump($retArr);
  echo "\n" . $retArr['data']['release'];

  //eneble return objet
  $client->setResultIsObject(true);
}

```

Sample output version request:

```php
//object result
var_dump($client->getVersion()->Version()->getResponse());

object(stdClass)#9 (1) {
  ["data"]=>
  object(stdClass)#32 (4) {
    ["version"]=>
    string(3) "5.0"
    ["release"]=>
    string(2) "31"
    ["keyboard"]=>
    string(2) "it"
    ["repoid"]=>
    string(8) "27769b1f"
  }
}

//disable return object
$client->setResultIsObject(false);

//array result
var_dump($client->getVersion()->Version());

array(1) {
  ["data"]=>
  array(4) {
    ["repoid"]=>
    string(8) "2560e073"
    ["release"]=>
    string(2) "32"
    ["version"]=>
    string(3) "5.0"
    ["keyboard"]=>
    string(2) "it"
  }
}
```

The parameter indexed end with '[n]' in documentation (method createVM in Qemu parameter ide) require array whit key and value

```php
[
  1 => "....",
  3 => "....",
]
```
