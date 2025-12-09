# Basic Examples

This guide provides common usage patterns and practical examples for getting started with the Proxmox VE API.

## Getting Started

### **Basic Connection**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Create client and authenticate
$client = new PveClient("pve.example.com", 8006);
$client->setApiToken("user@pve!token=uuid");

// Test connection
$version = $client->getVersion()->version();
if ($version->isSuccessStatusCode()) {
    echo "Connected to Proxmox VE " . $version->getResponse()->data->version . "\n";
}
```

### **Client Setup with Error Handling**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

function createClient() {
    $client = new PveClient("pve.local", 8006);
    $client->setValidateCertificate(false); // For development
    $client->setTimeout(120); // 2 minutes in seconds

    try {
        // Use API token or login
        $pveToken = getenv("PVE_TOKEN");
        if (!empty($pveToken)) {
            $client->setApiToken($pveToken);
        } else {
            $success = $client->login("root@pam", "password");
            if (!$success) {
                throw new Exception("Authentication failed");
            }
        }

        return $client;
    } catch (Exception $ex) {
        echo "Failed to create client: " . $ex->getMessage() . "\n";
        throw $ex;
    }
}
```

---

## Virtual Machine Operations

### **List Virtual Machines**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// Get all VMs in cluster
$resources = $client->getCluster()->getResources()->resources();
foreach ($resources->getResponse()->data as $resource) {
    if ($resource->type == "qemu") {
        echo "VM {$resource->vmid}: {$resource->name} on {$resource->node} - {$resource->status}\n";
    }
}
```

### **Get VM Configuration**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// Get VM configuration
$vmConfig = $client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->vmConfig();
$config = $vmConfig->getResponse()->data;

echo "VM Name: {$config->name}\n";
echo "Memory: {$config->memory} MB\n";
echo "CPU Cores: {$config->cores}\n";
echo "Boot Order: {$config->boot}\n";
```

### **VM Power Management**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

$vm = $client->getNodes()->get("pve1")->getQemu()->get(100);

// Start VM
$vm->getStatus()->getStart()->vmStart();
echo "VM started successfully\n";

// Stop VM
$vm->getStatus()->getStop()->vmStop();
echo "VM stopped successfully\n";

// Restart VM
$vm->getStatus()->getReboot()->vmReboot();
echo "VM restarted successfully\n";

// Get current status
$status = $vm->getStatus()->getCurrent()->vmStatus();
$data = $status->getResponse()->data;

echo "VM Status: {$data->status}\n";
echo "CPU Usage: " . sprintf("%.2f%%", $data->cpu * 100) . "\n";
echo "Memory: " . sprintf("%.2f%%", ($data->mem / $data->maxmem) * 100) . "\n";
```

### **Snapshot Management**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

$vm = $client->getNodes()->get("pve1")->getQemu()->get(100);

// Create snapshot
$vm->getSnapshot()->snapshot("backup-2024", "Pre-update backup");
echo "Snapshot created successfully\n";

// List snapshots
$snapshots = $vm->getSnapshot()->snapshotList();
echo "Available snapshots:\n";
foreach ($snapshots->getResponse()->data as $snapshot) {
    echo "  - {$snapshot->name}: {$snapshot->description} (" . date('Y-m-d H:i:s', $snapshot->snaptime) . ")\n";
}

// Restore snapshot - this would be implemented differently in the actual API
// There's usually a rollback endpoint for snapshots

// Delete snapshot
$vm->getSnapshot()->get("backup-2024")->delete();
echo "Snapshot deleted successfully\n";
```

---

## Container Operations

### **List Containers**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// Get all containers
$resources = $client->getCluster()->getResources()->resources();
if ($resources->isSuccessStatusCode()) {
    foreach ($resources->getResponse()->data as $resource) {
        if ($resource->type == "lxc") {
            echo "CT {$resource->vmid}: {$resource->name} on {$resource->node} - {$resource->status}\n";
        }
    }
}
```

### **Container Management**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

$container = $client->getNodes()->get("pve1")->getLxc()->get(101);

// Get container configuration
$config = $container->getConfig()->vmConfig();
if ($config->isSuccessStatusCode()) {
    $ctConfig = $config->getResponse()->data;
    echo "Container: {$ctConfig->hostname}\n";
    echo "OS Template: {$ctConfig->ostemplate}\n";
    echo "Memory: {$ctConfig->memory} MB\n";
}

// Start container
$startResult = $container->getStatus()->getStart()->vmStart();
if ($startResult->isSuccessStatusCode()) {
    echo "Container started\n";
}

// Get container status
$status = $container->getStatus()->getCurrent()->vmStatus();
if ($status->isSuccessStatusCode()) {
    echo "Status: {$status->getResponse()->data->status}\n";
    echo "Uptime: {$status->getResponse()->data->uptime} seconds\n";
}
```

---

## Cluster Operations

### **Cluster Status**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// Get cluster status
$clusterStatus = $client->getCluster()->getStatus()->getStatus();
if ($clusterStatus->isSuccessStatusCode()) {
    echo "Cluster Status:\n";
    foreach ($clusterStatus->getResponse()->data as $item) {
        echo "  {$item->type}: {$item->name} - {$item->status}\n";
    }
}
```

### **Node Information**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// Get all nodes
$nodes = $client->getNodes()->index();
if ($nodes->isSuccessStatusCode()) {
    echo "Available Nodes:\n";
    foreach ($nodes->getResponse()->data as $node) {
        echo "  {$node->node}: {$node->status}\n";
        echo "    CPU: " . sprintf("%.2f%%", $node->cpu * 100) . "\n";
        echo "    Memory: " . sprintf("%.2f%%", ($node->mem / $node->maxmem) * 100) . "\n";
        echo "    Uptime: " . gmdate("H:i:s", $node->uptime) . "\n";
    }
}
```

### **Storage Information**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// Get storage for a specific node
$storages = $client->getNodes()->get("pve1")->getStorage()->index();
if ($storages->isSuccessStatusCode()) {
    echo "Available Storage:\n";
    foreach ($storages->getResponse()->data as $storage) {
        $usedPercent = ($storage->used / $storage->total) * 100;
        echo "  {$storage->storage} ({$storage->type}): " . sprintf("%.1f%%", $usedPercent) . " used\n";
        echo "    Total: " . sprintf("%.2f GB", $storage->total / (1024*1024*1024)) . "\n";
        echo "    Available: " . sprintf("%.2f GB", $storage->avail / (1024*1024*1024)) . "\n";
    }
}
```

---

## Common Patterns

### **Resource Monitoring**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

function monitorResources($client) {
    while (true) {
        $resources = $client->getCluster()->getResources()->resources();
        
        system('clear'); // For Unix-like systems, use "cls" for Windows
        echo "Proxmox VE Resource Monitor - " . date("H:i:s") . "\n";
        echo str_repeat("=", 50) . "\n";

        // Group by type
        $nodes = array_filter($resources->getResponse()->data, function($r) { return $r->type == "node"; });
        $vms = array_filter($resources->getResponse()->data, function($r) { return $r->type == "qemu"; });
        $containers = array_filter($resources->getResponse()->data, function($r) { return $r->type == "lxc"; });

        echo "Nodes: " . count($nodes) . "\n";
        foreach ($nodes as $node) {
            echo "  {$node->node}: CPU " . sprintf("%.1f%%", $node->cpu * 100) . ", Memory " . sprintf("%.1f%%", ($node->mem / $node->maxmem) * 100) . "\n";
        }

        $runningVms = count(array_filter($vms, function($v) { return $v->status == "running"; }));
        echo "\nVMs: " . count($vms) . " ($runningVms running)\n";

        $runningContainers = count(array_filter($containers, function($c) { return $c->status == "running"; }));
        echo "Containers: " . count($containers) . " ($runningContainers running)\n";

        sleep(5); // Update every 5 seconds
    }
}
```

### **Batch Operations**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

function batchVmOperation($client, $vmIds, $operation) {
    $tasks = [];

    foreach ($vmIds as $vmId) {
        // Find VM location
        $resources = $client->getCluster()->getResources()->resources();
        $vm = null;
        if ($resources->isSuccessStatusCode()) {
            foreach ($resources->getResponse()->data as $r) {
                if ($r->type == "qemu" && $r->vmid == $vmId) {
                    $vm = $r;
                    break;
                }
            }
        }

        if ($vm !== null) {
            $vmInstance = $client->getNodes()->get($vm->node)->getQemu()->get($vmId);

            switch (strtolower($operation)) {
                case "start":
                    $task = $vmInstance->getStatus()->getStart()->vmStart();
                    break;
                case "stop":
                    $task = $vmInstance->getStatus()->getStop()->vmStop();
                    break;
                case "restart":
                    $task = $vmInstance->getStatus()->getReboot()->vmReboot();
                    break;
                default:
                    throw new Exception("Unknown operation: $operation");
            }

            $tasks[] = ['vmId' => $vmId, 'result' => $task];
        }
    }

    foreach ($tasks as $task) {
        $success = $task['result']->isSuccessStatusCode();
        echo "VM {$task['vmId']} $operation: " . ($success ? "Success" : "Failed") . "\n";
    }
}
```

### **Performance Monitoring**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

function getVmPerformance($client, $node, $vmId) {
    $vm = $client->getNodes()->get($node)->getQemu()->get($vmId);

    // Get current status
    $status = $vm->getStatus()->getCurrent()->vmStatus();
    if ($status->isSuccessStatusCode()) {
        $data = $status->getResponse()->data;

        echo "VM $vmId Performance:\n";
        echo "  Status: {$data->status}\n";
        echo "  CPU Usage: " . sprintf("%.2f%%", $data->cpu * 100) . "\n";
        echo "  Memory: " . formatBytes($data->mem) . " / " . formatBytes($data->maxmem) . " (" . sprintf("%.1f%%", ($data->mem / $data->maxmem) * 100) . ")\n";
        echo "  Disk Read: " . formatBytes($data->diskread) . "\n";
        echo "  Disk Write: " . formatBytes($data->diskwrite) . "\n";
        echo "  Network In: " . formatBytes($data->netin) . "\n";
        echo "  Network Out: " . formatBytes($data->netout) . "\n";
        echo "  Uptime: " . gmdate("H:i:s", $data->uptime) . "\n";
    }
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
```

---

## Best Practices

### **Error Handling**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

function safeVmOperation($client, $node, $vmId, $operation) {
    try {
        $vm = $client->getNodes()->get($node)->getQemu()->get($vmId);

        switch (strtolower($operation)) {
            case "start":
                $result = $vm->getStatus()->getStart()->vmStart();
                break;
            case "stop":
                $result = $vm->getStatus()->getStop()->vmStop();
                break;
            default:
                throw new Exception("Unknown operation: $operation");
        }

        if ($result->isSuccessStatusCode()) {
            echo "VM $vmId $operation successful\n";
            return true;
        } else {
            echo "VM $vmId $operation failed: " . $result->getError() . "\n";
            return false;
        }
    } catch (Exception $ex) {
        echo "Exception during $operation on VM $vmId: " . $ex->getMessage() . "\n";
        return false;
    }
}
```

### **Resource Discovery**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

function findVm($client, $vmName) {
    $resources = $client->getCluster()->getResources()->resources();
    if ($resources->isSuccessStatusCode()) {
        foreach ($resources->getResponse()->data as $r) {
            if ($r->type == "qemu" && strcasecmp($r->name, $vmName) === 0) {
                return ['node' => $r->node, 'vmId' => $r->vmid];
            }
        }
    }

    return null;
}

// Usage
$vmLocation = findVm($client, "web-server");
if ($vmLocation !== null) {
    $node = $vmLocation['node'];
    $vmId = $vmLocation['vmId'];
    $vm = $client->getNodes()->get($node)->getQemu()->get($vmId);
    // ... work with VM
}
```