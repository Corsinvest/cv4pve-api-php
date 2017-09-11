# EnterpriseVE.ProxmoxVE.Api-PHP
ProxmoVE Client API PHP

General
------------

This PHP 5.4+ library allows you to interact with your Proxmox server via API.
The client is generated from a JSON Api on ProxmoxVE.
The result is a complete response from server.

[ProxmoxVE Api](https://pve.proxmox.com/pve-docs/api-viewer/)


Installation
------------

Recommended installation is using [Composer], if you do not have [Composer] what are you waiting?

In the root of your project execute the following:

```sh
$ composer require enterpriseve/enterpriseve.proxmoxve.api-php ~1.0
```

Or add this to your `composer.json` file:

```json
{
    "require": {
        "enterpriseve/enterpriseve.proxmoxve.api-php": "~1.0"
    }
}
```

Usage
-----

```php
<?php

// Require the autoloader
require_once 'vendor/autoload.php';

$client = new EnterpriseVE\ProxmoxVE\Api\Client("192.168.0.24");
$client->login('root','password','pam');

//loop nodes
foreach ($client->getNodes()->Index()->data as $node) {
  echo "\n" . $node->id;
}

//loop vm
foreach ($client->getNodes()->get("pve1")->getQemu()->Vmlist()->data as $vm) {
    echo "\n" . $vm->vmid ." - " .$vm->name;
}
```

Sample output version request:

```php
$result = $client->getVersion()->Version();
var_dump($result);

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
```
