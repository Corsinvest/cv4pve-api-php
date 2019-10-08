# cv4pve-api-php

[![License](https://img.shields.io/github/license/Corsinvest/cv4pve-api-php.svg)](https://www.gnu.org/licenses/gpl-3.0.en.html) ![Packagist Version](https://img.shields.io/packagist/v/corsinvest/cv4pve-api-php.svg) [![Donate to this project using Paypal](https://img.shields.io/badge/paypal-donate-yellow.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=PPM9JHLQLRV2S&item_name=Open+Source+Project&currency_code=EUR&source=url)

Proxmox VE Client API PHP

[Proxmox VE Api](https://pve.proxmox.com/pve-docs/api-viewer/)

[Packagist](https://packagist.org/packages/Corsinvest/cv4pve-api-php)

# **Donations**

If you like my work and want to support it, then please consider to deposit a donation through **Paypal** by clicking on the next button:

[![paypal](https://www.paypalobjects.com/en_US/IT/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=PPM9JHLQLRV2S&item_name=Open+Source+Project&currency_code=EUR&source=url)

```text
   ______                _                      __
  / ____/___  __________(_)___ _   _____  _____/ /_
 / /   / __ \/ ___/ ___/ / __ \ | / / _ \/ ___/ __/
/ /___/ /_/ / /  (__  ) / / / / |/ /  __(__  ) /_
\____/\____/_/  /____/_/_/ /_/|___/\___/____/\__/

Corsinvest for Proxmox VE Api Client  (Made in Italy)
```

## General

The client is generated from a JSON Api on Proxmox VE.

This PHP 5.4+ library allows you to interact with your Proxmox server via API.
The client is generated from a JSON Api on Proxmox VE.

## Result

The result is class **Result** and contain methods:

* **getResponse()** returned from Proxmox VE (data,errors,...) Object/Array
* **responseInError** (bool) : Contains errors from Proxmox VE.
* **getStatusCode()** (int) : Status code of the HTTP response.
* **getReasonPhrase()** (string): The reason phrase which typically is sent by servers together with the status code.
* **isSuccessStatusCode()** (bool) : Gets a value that indicates if the HTTP response was successful.
* **getError()** (string) : Get error.

## Main features

* Easy to learn
* No dependency external library only native curl
* Method named
* Method no named rest (same parameters)
  * getRest
  * setRest
  * createRest
  * deleteRest
* Set ResponseType json, png
* Full method generated from documentation
* Comment any method and parameters
* Parameters indexed eg [n] is structured in array index and value
* Tree structure
  * $client->getNodes()->get("pve1")->getQemu()->get(100)->getSnapshot()->snapshotList()->getResponse()->data
* Return data proxmox
* Return result
  * Request
  * Response
  * Status
* Wait task finish task
  * waitForTaskToFinish
  * taskIsRunning
  * getExitStatusTask
* Method direct access
  * get
  * set
  * create
  * delete
* Login return bool if access
* Return Result class more information
* Return object/array data
  * default object disable from client.setResultIsObject(false)
* ClientBase lite function

## Installation

Recommended installation is using [Composer], if you do not have [Composer] what are you waiting?

In the root of your project execute the following:

```sh
composer require Corsinvest/cv4pve-api-php ~1.0
```

Or add this to your `composer.json` file:

```json
{
    "require": {
        "Corsinvest/cv4pve-api-php": "~1.0"
    }
}
```

## Usage

```php
<?php

// Require the autoloader
require_once 'vendor/autoload.php';

$client = new Corsinvest\ProxmoxVE\Api\PveClient("192.168.0.24");

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

  //enable return objet
  $client->setResultIsObject(true);

  //image rrd
  $client->setResponseType('png');
  echo "<img src='{$client->getNodes()->get("pve1")->getRrd()->rrd('cpu','day')->getResponse()}' \>";

  //result json result
  $client->setResponseType('json');
  var_dump($client->get('/version')->getResponse());
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
