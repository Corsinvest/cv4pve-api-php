# 📚 API Documentation

## Overview

The `cv4pve-api-php` library provides a comprehensive PHP client for interacting with Proxmox VE through its REST API. This documentation covers the main classes, methods, and usage patterns.


## Core Classes

### PveClient

The main client class that provides access to all Proxmox VE API endpoints.

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient($hostname, $port = 8006);
```

### PveClientBase

A lightweight base client for basic operations (get, post, put, delete).

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClientBase;

$client = new PveClientBase($hostname, $port = 8006);
```

### Result

Every API call returns a `Result` object containing response data and metadata.

#### Methods

```php
public function getResponse()
```
**Returns:** `object|array` - Response data (format depends on client settings)

```php
public function getStatusCode()
```
**Returns:** `int` - HTTP status code

```php
public function getReasonPhrase()
```
**Returns:** `string` - HTTP reason phrase

```php
public function isSuccessStatusCode()
```
**Returns:** `bool` - True if status code is 200

```php
public function responseInError()
```
**Returns:** `bool` - True if response contains errors

```php
public function getError()
```
**Returns:** `string` - Error message if any

```php
public function getResponseHeaders()
```
**Returns:** `string` - Raw HTTP response headers

## Authentication Methods

### 1. Username/Password Authentication

```php
<?php
$client = new PveClient("proxmox.example.com");

// Basic authentication
if ($client->login('root', 'password', 'pam')) {
    echo "Authenticated successfully\n";
}

// With two-factor authentication
if ($client->login('root', 'password', 'pam', 'totp_code')) {
    echo "Authenticated with 2FA\n";
}
```

### 2. API Token Authentication

```php
<?php
$client = new PveClient("proxmox.example.com");

// Format: USER@REALM!TOKENID=TOKEN
$client->setApiToken("root@pam!automation=your-token-here");

// No login() call needed when using API tokens
$version = $client->getVersion()->version();
```

## API Structure Navigation

The library follows Proxmox VE's API structure using a fluent interface:

```php
<?php
// API Path: /nodes/{node}/qemu/{vmid}/status
$result = $client->getNodes()           // /nodes
                ->get('pve1')          // /nodes/pve1  
                ->getQemu()            // /nodes/pve1/qemu
                ->get(100)             // /nodes/pve1/qemu/100
                ->getStatus()          // /nodes/pve1/qemu/100/status
                ->current();           // GET request

// API Path: /nodes/{node}/storage
$storages = $client->getNodes()
                  ->get('pve1')
                  ->getStorage()
                  ->index();
```

### Cluster Operations

```php
$client->getCluster()
    ->getStatus()->getStatus()          // Get cluster status
    ->getResources()->resources()       // Get cluster resources
    ->getConfig()->totem()             // Get cluster config
```

### Node Operations

```php
$client->getNodes()
    ->index()                          // List all nodes
    ->get($nodeName)                   // Select specific node
        ->getStatus()->current()       // Get node status
        ->getTasks()->nodeTasksList()  // List node tasks
        ->getStorage()->index()        // List storage on node
```

### Virtual Machine Operations

```php
$client->getNodes()->get($nodeName)->getQemu()
    ->vmlist()                         // List VMs
    ->get($vmid)                       // Select specific VM
        ->getStatus()->current()       // Get VM status
        ->getStatus()->start()         // Start VM
        ->getStatus()->stop()          // Stop VM
        ->getConfig()->vmConfig()      // Get VM config
        ->getSnapshot()->snapshotList() // List snapshots
```

### Container Operations

```php
$client->getNodes()->get($nodeName)->getLxc()
    ->vmlist()                         // List containers
    ->get($ctid)                       // Select specific container
        ->getStatus()->current()       // Get container status
        ->getStatus()->start()         // Start container
        ->getStatus()->stop()          // Stop container
```

### Storage Operations

```php
$client->getNodes()->get($nodeName)->getStorage()
    ->index()                          // List storage
    ->get($storageName)                // Select storage
        ->status()                     // Get storage status
        ->content()                    // List storage content
```
