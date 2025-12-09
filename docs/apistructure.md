# API Structure Guide

Understanding the hierarchical structure of the Proxmox VE API and how it maps to the PHP client.

## Tree Structure

The API follows the exact structure of the [Proxmox VE API](https://pve.proxmox.com/pve-docs/api-viewer/):

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// API Path: /cluster/status
$client->getCluster()->getStatus()->getStatus();

// API Path: /nodes/{node}/qemu/{vmid}/config  
$client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->index();

// API Path: /nodes/{node}/lxc/{vmid}/snapshot
$client->getNodes()->get("pve1")->getLxc()->get(101)->getSnapshot()->get("snap-name");

// API Path: /nodes/{node}/storage/{storage}
$client->getNodes()->get("pve1")->getStorage()->get("local")->diridx();
```

## HTTP Method Mapping

| HTTP Method | PHP Method | Purpose | Example |
|-------------|-----------|---------|---------|
| `GET` | `$resource->index()` | Retrieve information | `$vm->getConfig()->index()` |
| `POST` | `$resource->create($parameters)` | Create resources | `$vm->getSnapshot()->create("snap-name", "description")` |
| `PUT` | `$resource->updateVmAsync(...)` / `$resource->updateVm(...)` | Update resources | `$vm->getConfig()->updateVmAsync(memory: 4096)` |
| `DELETE` | `$resource->delete()` or specific methods | Remove resources | `$vm->get("pve1")->getQemu()->get(100)->delete()` |

## Common Endpoints

### **Cluster Level**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

$client->getCluster()->getStatus()->getStatus();           // GET /cluster/status
$client->getCluster()->getResources()->resources();         // GET /cluster/resources
$client->getVersion()->version();                           // GET /version
```

### **Node Level**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

$client->getNodes()->index();                               // GET /nodes
$client->getNodes()->get("pve1")->vmdiridx();              // GET /nodes/pve1
$client->getNodes()->get("pve1")->getStorage()->index();    // GET /nodes/pve1/storage
```

### **VM Operations**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

$client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->vmConfig();        // GET config
$client->getNodes()->get("pve1")->getQemu()->get(100)->getStatus()->getCurrent()->vmStatus(); // GET status
$client->getNodes()->get("pve1")->getQemu()->get(100)->getStatus()->getStart()->vmStart();   // POST start
$client->getNodes()->get("pve1")->getQemu()->get(100)->getSnapshot()->snapshotList();  // GET snapshots
```

### **Container Operations**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

$client->getNodes()->get("pve1")->getLxc()->get(101)->getConfig()->vmConfig();         // GET config
$client->getNodes()->get("pve1")->getLxc()->get(101)->vmdiridx();                      // GET status info
$client->getNodes()->get("pve1")->getLxc()->get(101)->getStatus()->getStart()->vmStart();    // POST start
```

## Parameters and Indexers

### **Numeric Indexers**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

$client->getNodes()->get("pve1")->getQemu()->get(100);     // VM ID 100
$client->getNodes()->get("pve1")->getLxc()->get(101);      // Container ID 101
```

### **String Indexers**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

$client->getNodes()->get("pve1");                           // Node name
$client->getNodes()->get("pve1")->getStorage()->get("local"); // Storage name
$client->getNodes()->get("pve1")->getQemu()->get(100)->getSnapshot()->get("snap1"); // Snapshot name
```

### **Method Parameters**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// Parameters with named arguments to update VM
$vm = $client->getNodes()->get("pve1")->getQemu()->get(100);
$result = $vm->getConfig()->updateVmAsync(memory: 4096, cores: 2);

// Parameters with specific methods
$result = $vm->getSnapshot()->snapshot("backup", "Description here");
```