# Proxmox VE API Client for PHP

```bash
composer require corsinvest/cv4pve-api-php
```

## Key Features

- **Tree Structure** - Mirrors the Proxmox VE API hierarchy exactly
- **Auto-Generated** - Generated from official Proxmox VE API documentation
- **Multiple Auth** - Username/password, API tokens, 2FA support
- **Flexible Results** - Dynamic responses with comprehensive metadata
- **Enterprise Ready** - SSL validation, timeouts, logging integration

---

## API Structure

The library follows the exact structure of the [Proxmox VE API](https://pve.proxmox.com/pve-docs/api-viewer/):

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// API Path: /cluster/status
$result = $client->getCluster()->getStatus()->getStatus();

// API Path: /nodes/{node}/qemu/{vmid}/config  
$result = $client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->index();

// API Path: /nodes/{node}/lxc/{vmid}/snapshot
$result = $client->getNodes()->get("pve1")->getLxc()->get(101)->getSnapshot()->get("snap-name");

// API Path: /nodes/{node}/storage/{storage}
$result = $client->getNodes()->get("pve1")->getStorage()->get("local")->diridx();
```

### HTTP Method Mapping

| HTTP Method | PHP Method | Purpose | Example |
|-------------|-----------|---------|---------|
| `GET` | `$resource->index()` | Retrieve information | `$vm->getConfig()->index()` |
| `POST` | `$resource->create($parameters)` | Create resources | `$vm->getSnapshot()->create("snap-name", "description")` |
| `PUT` | `$resource->updateVmAsync(...)` / `$resource->updateVm(...)` | Update resources | `$vm->getConfig()->updateVmAsync(memory: 4096)` |
| `DELETE` | `$resource->delete()` | Remove resources | `$vm->delete()` |

> **Note:** Some endpoints also have specific method names like `vmConfig()`, `snapshot()`, etc. that map to the appropriate HTTP verbs.

---

## Authentication

### Username/Password Authentication

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// Basic login
$success = $client->login("root", "password");

// Login with realm
$success = $client->login("admin@pve", "password", "pam");

// Two-factor authentication
$success = $client->login("root", "password", "pam", "123456");
```

### API Token Authentication (Recommended)

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// Set API token (Proxmox VE 6.2+)
$client->setApiToken("user@realm!tokenid=uuid");

// No login() call needed with API tokens
$result = $client->getVersion()->version();
```

### Advanced Configuration

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Basic configuration
$client = new PveClient("pve.example.com", 8006);

// Custom timeout (default: 0 seconds, no timeout limit)
$client->setTimeout(300); // 5 minutes

// Validate SSL certificates (default: false)  
$client->setValidateCertificate(true);

// Response type: "json" or "png" (for charts)
$client->setResponseType("json");
```

---

## Working with Results

Every API call returns a `Result` object containing comprehensive response information:

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

$result = $client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->index();

// Access response data
$data = $result->getResponse()->data;
echo "VM Name: " . $data->name . "\n";
echo "Memory: " . $data->memory . "\n";
echo "Cores: " . $data->cores . "\n";

// Iterate through response data
foreach ($data as $key => $value) {
    echo "$key: $value\n";
}
```

### Result Properties and Methods

```php
class Result
{
    // Response data from Proxmox VE
    public function getResponse() { } // Returns response data
    
    // HTTP response information
    public function getStatusCode() { } // Returns HTTP status code
    public function getReasonPhrase() { } // Returns HTTP reason phrase
    public function isSuccessStatusCode() { } // Returns boolean
    
    // Utility methods
    public function responseInError() { } // Returns boolean
    public function getError() { } // Returns error message if any
    public function getResponseHeaders() { } // Returns raw HTTP response headers
}
```

---

## Basic Examples

### Virtual Machine Management

<details>
<summary><strong>VM Configuration</strong></summary>

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// Get VM configuration
$vm = $client->getNodes()->get("pve1")->getQemu()->get(100);
$result = $vm->getConfig()->vmConfig();
$vmData = $result->getResponse()->data;
echo "VM Name: " . $vmData->name . "\n";
echo "Memory: " . $vmData->memory . " MB\n";
echo "CPUs: " . $vmData->cores . "\n";
echo "OS Type: " . $vmData->ostype . "\n";

// Update VM configuration
$updateResult = $vm->getConfig()->updateVmAsync(
    memory: 8192,  // 8GB RAM
    cores: 4       // 4 CPU cores
);

echo "Configuration update initiated\n";
```

</details>

<details>
<summary><strong>Snapshot Management</strong></summary>

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// Create snapshot
$vm = $client->getNodes()->get("pve1")->getQemu()->get(100);

// Using specific method
$snapshot = $vm->getSnapshot()->snapshot("backup-before-update", "Pre-update backup");

echo "Snapshot created successfully!\n";

// List snapshots
$snapshots = $vm->getSnapshot()->snapshotList();
echo "Available snapshots:\n";
foreach ($snapshots->getResponse()->data as $snap) {
    echo "  - " . $snap->name . ": " . $snap->description . " (" . date('Y-m-d H:i:s', $snap->snaptime) . ")\n";
}

// Delete snapshot
$vm->getSnapshot()->get("backup-before-update")->delete();
echo "Snapshot deleted successfully!\n";
```

</details>

<details>
<summary><strong>VM Status Management</strong></summary>

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

$vm = $client->getNodes()->get("pve1")->getQemu()->get(100);

// Get current status
$status = $vm->getStatus()->getCurrent()->vmStatus();
$statusData = $status->getResponse()->data;
echo "Current status: " . $statusData->status . "\n";
echo "CPU usage: " . ($statusData->cpu * 100) . "%\n";
echo "Memory usage: " . (($statusData->mem / $statusData->maxmem) * 100) . "%\n";

// Start VM if stopped
if ($statusData->status == "stopped") {
    $vm->getStatus()->getStart()->vmStart();
    echo "VM start initiated\n";
}

// Stop VM
$vm->getStatus()->getStop()->vmStop();
echo "VM stop initiated\n";

// Restart VM
$vm->getStatus()->getReboot()->vmReboot();
echo "VM restarted successfully!\n";
```

</details>

### Container Management

<details>
<summary><strong>LXC Container Operations</strong></summary>

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// Access LXC container
$container = $client->getNodes()->get("pve1")->getLxc()->get(101);

// Get container configuration
$config = $container->getConfig()->vmConfig();
$ctData = $config->getResponse()->data;

echo "Container: " . $ctData->hostname . "\n";
echo "OS Template: " . $ctData->ostemplate . "\n";
echo "Memory: " . $ctData->memory . " MB\n";

// Container status operations
$status = $container->getStatus()->getCurrent()->vmStatus();
echo "Status: " . $status->getResponse()->data->status . "\n";

// Start container
if ($status->getResponse()->data->status == "stopped") {
    $container->getStatus()->getStart()->vmStart();
    echo "Container started!\n";
}

// Create container snapshot
$container->getSnapshot()->snapshot("backup-snapshot", "Backup");
echo "Container snapshot created!\n";
```

</details>

### Cluster Operations

<details>
<summary><strong>Cluster Status and Resources</strong></summary>

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// Get cluster status
$clusterStatus = $client->getCluster()->getStatus()->getStatus();
echo "Cluster Status:\n";
foreach ($clusterStatus->getResponse()->data as $item) {
    echo "  " . $item->type . ": " . $item->name . " - " . $item->status . "\n";
}

// Get cluster resources
$resources = $client->getCluster()->getResources()->resources();
echo "Cluster Resources:\n";
foreach ($resources->getResponse()->data as $resource) {
    if ($resource->type == "node") {
        echo "  Node: " . $resource->node . " - CPU: " . ($resource->cpu * 100) . "%, Memory: " . (($resource->mem / $resource->maxmem) * 100) . "%\n";
    } elseif ($resource->type == "qemu") {
        echo "  VM: " . $resource->vmid . " (" . $resource->name . ") on " . $resource->node . " - " . $resource->status . "\n";
    }
}

// Get node information
$nodes = $client->getNodes()->index();
echo "Available Nodes:\n";
foreach ($nodes->getResponse()->data as $node) {
    echo "  " . $node->node . ": " . $node->status . " - Uptime: " . $node->uptime . "s\n";
}
```

</details>

### Storage Management

<details>
<summary><strong>Storage Operations</strong></summary>

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// List storage on a node
$storages = $client->getNodes()->get("pve1")->getStorage()->index();
echo "Available Storage:\n";
foreach ($storages->getResponse()->data as $storage) {
    $availableGB = $storage->avail / (1024*1024*1024);
    echo "  " . $storage->storage . ": " . $storage->type . " - " . number_format($availableGB, 2) . " GB available\n";
}

// Get specific storage details
$localStorage = $client->getNodes()->get("pve1")->getStorage()->get("local")->diridx();
$storageData = $localStorage->getResponse()->data;

echo "Storage: " . $storageData->storage . "\n";
echo "Type: " . $storageData->type . "\n";
$totalGB = $storageData->total / (1024*1024*1024);
echo "Total: " . number_format($totalGB, 2) . " GB\n";
$usedGB = $storageData->used / (1024*1024*1024);
echo "Used: " . number_format($usedGB, 2) . " GB\n";
$availGB = $storageData->avail / (1024*1024*1024);
echo "Available: " . number_format($availGB, 2) . " GB\n";

// List storage content
$content = $client->getNodes()->get("pve1")->getStorage()->get("local")->getContent()->index();
echo "Storage Content:\n";
foreach ($content->getResponse()->data as $item) {
    $sizeMB = $item->size / (1024*1024);
    echo "  " . $item->volid . ": " . $item->format . " - " . number_format($sizeMB, 2) . " MB\n";
}
```

</details>

---

## Advanced Features

### Response Type Switching

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// Default JSON responses
$client->setResponseType("json");
$result = $client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->vmConfig();

// For charts/graphs, you might need to handle different response types
// though this is less common in the PHP implementation
$client->setResponseType("json"); // Back to default
```

### Task Management

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// Long-running operations return task IDs
// Example: creating a VM would typically return a task ID
$createResult = $client->getNodes()->get("pve1")->getQemu()->createVm(
    vmid: 999,                 // required: VM ID
    name: 'test-vm',           // optional: VM name
    memory: 2048,              // optional: memory in MB
    cores: 2,                  // optional: number of CPU cores
    sockets: 1,                // optional: CPU sockets
    cpu: 'host',               // optional: CPU type
    ostype: 'l26',             // optional: OS type
    net0: 'model=virtio,bridge=vmbr0',  // optional: network interface
    scsi0: 'local-lvm:32'      // optional: storage configuration
);

$taskId = $createResult->getResponse()->data;  // This would contain the task ID
echo "Task started: $taskId\n";

// Monitor task progress
while (true) {
    $taskStatus = $client->getNodes()->get("pve1")->getTasks()->get($taskId)->index();
    $status = $taskStatus->getResponse()->data->status;

    if ($status == "stopped") {
        $exitStatus = $taskStatus->getResponse()->data->exitstatus;
        echo "Task completed with status: $exitStatus\n";
        break;
    } elseif ($status == "running") {
        echo "Task still running...\n";
        sleep(2);
    }
}
```

### SSL and Security

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// Enable SSL certificate validation
$client->setValidateCertificate(true);

// Set custom timeout (in seconds)
$client->setTimeout(600); // 10 minutes

// Use API token for secure authentication
$client->setApiToken("automation@pve!secure-token=uuid-here");

// API calls now use validated SSL and secure token
$result = $client->getVersion()->version();
```

---

## Best Practices

### Recommended Patterns

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// 1. Always check isSuccessStatusCode
$client = new PveClient("pve.example.com", 8006);
$result = $client->getCluster()->getStatus()->getStatus();
if ($result->isSuccessStatusCode()) {
    // Process successful response
    $data = $result->getResponse()->data;
    foreach ($data as $item) {
        echo $item->type . ": " . $item->name . "\n";
    }
} else {
    // Handle error appropriately
    echo "API call failed: " . $result->getError() . "\n";
}

// 2. Use API tokens for automation
$client = new PveClient("pve.cluster.com", 8006);
$client->setApiToken($_ENV['PROXMOX_API_TOKEN'] ?? '');

// 3. Configure timeouts for long operations
$client->setTimeout(900); // 15 minutes

// 4. Enable SSL validation in production
$client->setValidateCertificate(true);
```

### Common Pitfalls to Avoid

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// Don't ignore error handling
$result = $client->getNodes()->get("pve1")->getQemu()->get(100)->getStatus()->getStart()->vmStart();
if (!$result->isSuccessStatusCode()) {
    echo "VM start failed: " . $result->getError() . "\n";
}

// Don't hardcode credentials
$client->login("root", "password123"); // Bad
// Better: Use environment variables or secure storage

// Don't assume response data exists
$data = $result->getResponse()->data;
if (isset($data->property)) {
    echo $data->property;  // Safe access
} else {
    echo "Property does not exist";
}
```