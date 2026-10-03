# <img src="https://raw.githubusercontent.com/Corsinvest/cv4pve-api-php/master/icon.svg" alt="" height="36" align="top"> cv4pve-api-php

```
   ______                _                      __
  / ____/___  __________(_)___ _   _____  _____/ /_
 / /   / __ \/ ___/ ___/ / __ \ | / / _ \/ ___/ __/
/ /___/ /_/ / /  (__  ) / / / / |/ /  __(__  ) /_
\____/\____/_/  /____/_/_/ /_/|___/\___/____/\__/

Proxmox VE API Client for PHP (Made in Italy)
```

[![License](https://img.shields.io/github/license/Corsinvest/cv4pve-api-php.svg?style=flat-square)](https://github.com/Corsinvest/cv4pve-api-php/blob/master/LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/corsinvest/cv4pve-api-php.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/corsinvest/cv4pve-api-php)
[![Packagist Downloads](https://img.shields.io/packagist/dt/corsinvest/cv4pve-api-php?style=flat-square&logo=packagist)](https://packagist.org/packages/corsinvest/cv4pve-api-php)
[![PHP Version](https://img.shields.io/packagist/php-v/corsinvest/cv4pve-api-php.svg?style=flat-square&logo=php)](https://packagist.org/packages/corsinvest/cv4pve-api-php)

> **The Proxmox VE API from PHP**: a client with a method for every endpoint of the Proxmox VE API, running in your application and talking only to the API.
>
> **[Documentation](https://corsinvest.github.io/cv4pve-api-php/)**

---

<p align="center">
  <img src="https://raw.githubusercontent.com/Corsinvest/cv4pve-api-php/master/docs/src/assets/php.svg" alt="PHP logo" width="110">
</p>

## Why

An application that manages Proxmox VE (a customer portal, a scheduled job, a monitoring or billing tool) has to speak its REST API: tickets and tokens, paths, parameters, JSON, tasks that end later. Written by hand it is a layer of HTTP code to build and to keep up with every Proxmox VE release.

cv4pve-api-php is that layer, generated from the API itself. The calls follow the tree of the API, so the [Proxmox VE API viewer](https://pve.proxmox.com/pve-docs/api-viewer/) is also the reference of the client.

It **runs in your application and uses only the Proxmox VE API**: nothing to install on the nodes, no SSH.

---

## Features

- **The whole API**: a method for every endpoint and HTTP method, generated from the Proxmox VE API schema; `/nodes/{node}/qemu/{vmid}/config` is `$client->getNodes()->get('pve01')->getQemu()->get(100)->getConfig()`.
- **One Result for every call**: the HTTP outcome and the Proxmox VE data, read as objects or as arrays. A failed call does not throw.
- **API token or password**: with two-factor authentication, certificate validation and timeout.
- **Tasks**: start a backup, a clone or a migration, wait for its task and read whether it succeeded.
- **Raw calls**: GET, POST, PUT and DELETE on any path with an array of parameters, for the calls with many options and for endpoints newer than the library.
- **No dependency**: PHP with the curl extension, no other package.

---

## Quick start

```bash
composer require corsinvest/cv4pve-api-php
```

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Corsinvest\ProxmoxVE\Api\PveClient;

// connect to any node of the cluster, with an API token
$client = new PveClient('pve01', 8006);
$client->setApiToken('automation@pve!app=aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

// GET /nodes/{node}/qemu/{vmid}/status/current
$result = $client->getNodes()->get('pve01')->getQemu()->get(100)->getStatus()->getCurrent()->vmStatus();

echo $result->isSuccessStatusCode()
    ? "VM {$result->getResponse()->data->vmid} is {$result->getResponse()->data->status}"
    : "{$result->getStatusCode()} {$result->getReasonPhrase()}";
```

What the token needs: [Permissions](https://corsinvest.github.io/cv4pve-api-php/permissions/).

The first two numbers of the version are the Proxmox VE version the client was generated from: 9.2.x is for Proxmox VE 9.2. Changes of each release: [CHANGELOG.md](https://github.com/Corsinvest/cv4pve-api-php/blob/master/CHANGELOG.md).

---

## Documentation

| | |
|---|---|
| [Getting started](https://corsinvest.github.io/cv4pve-api-php/getting-started/) | Install, connect, first calls |
| [Connection](https://corsinvest.github.io/cv4pve-api-php/connection/) | API token or password, two-factor authentication, certificates, timeout |
| [Permissions](https://corsinvest.github.io/cv4pve-api-php/permissions/) | The user, the token and the privileges an application needs |
| [Concepts](https://corsinvest.github.io/cv4pve-api-php/concepts/api-structure/) | API structure, results, indexed parameters, tasks, errors |
| [Examples](https://corsinvest.github.io/cv4pve-api-php/examples/common-tasks/) | Common tasks, creating a VM, bulk operations |
| [Troubleshooting](https://corsinvest.github.io/cv4pve-api-php/troubleshooting/) | Debug output and the common errors |

---

## Related tools

Prefer a command line? [cv4pve-cli](https://github.com/Corsinvest/cv4pve-cli) calls the same API from any shell. The same client for .NET: [cv4pve-api-dotnet](https://github.com/Corsinvest/cv4pve-api-dotnet). For Java: [cv4pve-api-java](https://github.com/Corsinvest/cv4pve-api-java). From PowerShell: [cv4pve-api-powershell](https://github.com/Corsinvest/cv4pve-api-powershell). The whole suite: [corsinvest.it/cv4pve](https://www.corsinvest.it/en/cv4pve/).

---

## Support

Professional support and consulting available through [Corsinvest](https://www.corsinvest.it/en/cv4pve/).

---

Part of [cv4pve](https://www.corsinvest.it/cv4pve) suite | Made with ❤️ in Italy by [Corsinvest](https://www.corsinvest.it)

Copyright © Corsinvest Srl
