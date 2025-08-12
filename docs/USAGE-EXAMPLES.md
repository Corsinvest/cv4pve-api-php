# 📋 Usage Examples

Practical code examples for cv4pve-api-php library.

## Getting Started

### Basic Connection and Information

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("proxmox.example.com");

if ($client->login('root', 'password', 'pam')) {
    // Get Proxmox VE version
    $version = $client->getVersion()->version();
    echo "Proxmox VE Version: " . $version->getResponse()->data->version . "\n";
    
    // Get cluster information
    $cluster = $client->getCluster()->getStatus()->getStatus();
    foreach ($cluster->getResponse()->data as $item) {
        if ($item->type === 'cluster') {
            echo "Cluster: " . $item->name . "\n";
        }
    }
}
```

## Node Management

### List and Monitor Nodes

```php
<?php
// List all nodes in the cluster
$nodes = $client->getNodes()->index();
echo "Cluster Nodes:\n";
foreach ($nodes->getResponse()->data as $node) {
    echo "- {$node->node}: {$node->status} (CPU: {$node->cpu}%)\n";
}

// Get detailed node status
$nodeStatus = $client->getNodes()->get("pve1")->getStatus()->current();
$data = $nodeStatus->getResponse()->data;
echo "\nNode 'pve1' Details:\n";
echo "- Uptime: " . $data->uptime . " seconds\n";
echo "- Memory: " . round($data->memory->used / 1024/1024/1024, 2) . " GB used\n";
echo "- CPU Usage: " . round($data->cpu * 100, 2) . "%\n";
```

### Node Resource Information

```php
<?php
// Get node resources
$resources = $client->getCluster()->getResources()->resources();
foreach ($resources->getResponse()->data as $resource) {
    switch ($resource->type) {
        case 'node':
            echo "Node {$resource->node}: ";
            echo "CPU {$resource->cpu}%, ";
            echo "Memory " . round($resource->mem / 1024/1024/1024, 2) . "GB\n";
            break;
        case 'storage':
            echo "Storage {$resource->storage} on {$resource->node}: ";
            echo round($resource->disk / 1024/1024/1024, 2) . "GB used\n";
            break;
    }
}
```

## Virtual Machine Management

### List and Monitor VMs

```php
<?php
// List all VMs across all nodes
$allVms = $client->getCluster()->getResources()->resources(['type' => 'vm']);
echo "All Virtual Machines:\n";
foreach ($allVms->getResponse()->data as $vm) {
    echo "VM {$vm->vmid}: {$vm->name} on {$vm->node} - Status: {$vm->status}\n";
}

// List VMs on specific node
$nodeVms = $client->getNodes()->get("pve1")->getQemu()->vmlist();
echo "\nVMs on node 'pve1':\n";
foreach ($nodeVms->getResponse()->data as $vm) {
    echo "VM {$vm->vmid}: {$vm->name} - Status: {$vm->status}\n";
    if (isset($vm->tags)) {
        echo "  Tags: {$vm->tags}\n";
    }
}
```

### VM Operations

```php
<?php
$vmid = 100;
$node = "pve1";

// Get VM status
$status = $client->getNodes()->get($node)->getQemu()->get($vmid)->getStatus()->current();
echo "VM {$vmid} Status: " . $status->getResponse()->data->status . "\n";

// Start VM
echo "Starting VM {$vmid}...\n";
$task = $client->getNodes()->get($node)->getQemu()->get($vmid)->getStatus()->start();
if ($task->isSuccessStatusCode()) {
    $upid = $task->getResponse()->data;
    echo "Start task: {$upid}\n";
    
    // Wait for completion
    $client->waitForTaskToFinish($upid);
    echo "VM started successfully!\n";
}

// Get VM configuration
$config = $client->getNodes()->get($node)->getQemu()->get($vmid)->getConfig()->vmConfig();
$vmConfig = $config->getResponse()->data;
echo "VM Configuration:\n";
echo "- Memory: {$vmConfig->memory}MB\n";
echo "- Cores: {$vmConfig->cores}\n";
echo "- OS Type: {$vmConfig->ostype}\n";
```

### Create New VM

```php
<?php
$vmConfig = [
    'vmid' => 999,
    'name' => 'test-vm',
    'memory' => 2048,
    'cores' => 2,
    'sockets' => 1,
    'ostype' => 'l26', // Linux 2.6+
    'ide2' => 'local:iso/debian-12.0.0-amd64-netinst.iso,media=cdrom',
    'scsi0' => 'local-lvm:32,format=qcow2',
    'net0' => 'virtio,bridge=vmbr0',
    'boot' => 'order=scsi0;ide2'
];

echo "Creating VM...\n";
$result = $client->getNodes()->get("pve1")->getQemu()->createVm($vmConfig);
if ($result->isSuccessStatusCode()) {
    echo "VM created successfully!\n";
    
    // Start the new VM
    $startTask = $client->getNodes()->get("pve1")->getQemu()->get(999)->getStatus()->start();
    $upid = $startTask->getResponse()->data;
    $client->waitForTaskToFinish($upid);
    echo "VM started!\n";
}
```

## Container Management

### List and Manage Containers

```php
<?php
// List containers on node
$containers = $client->getNodes()->get("pve1")->getLxc()->vmlist();
echo "Containers on node 'pve1':\n";
foreach ($containers->getResponse()->data as $ct) {
    echo "CT {$ct->vmid}: {$ct->name} - Status: {$ct->status}\n";
}

// Container operations
$ctid = 200;
$node = "pve1";

// Get container status
$status = $client->getNodes()->get($node)->getLxc()->get($ctid)->getStatus()->current();
echo "Container {$ctid} Status: " . $status->getResponse()->data->status . "\n";

// Start container
$task = $client->getNodes()->get($node)->getLxc()->get($ctid)->getStatus()->start();
$upid = $task->getResponse()->data;
$client->waitForTaskToFinish($upid);
echo "Container started!\n";
```

### Create New Container

```php
<?php
$ctConfig = [
    'vmid' => 201,
    'hostname' => 'web-server',
    'password' => 'secure-password',
    'memory' => 1024,
    'swap' => 512,
    'cores' => 2,
    'rootfs' => 'local-lvm:8',
    'ostemplate' => 'local:vztmpl/ubuntu-22.04-standard_22.04-1_amd64.tar.zst',
    'net0' => 'name=eth0,bridge=vmbr0,ip=dhcp',
    'unprivileged' => 1,
    'start' => 1 // Start after creation
];

echo "Creating container...\n";
$result = $client->getNodes()->get("pve1")->getLxc()->createVm($ctConfig);
if ($result->isSuccessStatusCode()) {
    echo "Container created and started successfully!\n";
}
```

## Storage Management

### List and Monitor Storage

```php
<?php
// List all storage across cluster
$storages = $client->getNodes()->get("pve1")->getStorage()->index();
echo "Storage on node 'pve1':\n";
foreach ($storages->getResponse()->data as $storage) {
    echo "- {$storage->storage}: {$storage->type}\n";
    
    // Get storage status
    $status = $client->getNodes()->get("pve1")->getStorage()->get($storage->storage)->status();
    $statusData = $status->getResponse()->data;
    $totalGB = round($statusData->total / 1024/1024/1024, 2);
    $usedGB = round($statusData->used / 1024/1024/1024, 2);
    $availGB = round($statusData->avail / 1024/1024/1024, 2);
    
    echo "  Total: {$totalGB}GB, Used: {$usedGB}GB, Available: {$availGB}GB\n";
}
```

### Storage Content Management

```php
<?php
// List content in storage
$content = $client->getNodes()->get("pve1")->getStorage()->get("local")->content();
echo "Content in 'local' storage:\n";
foreach ($content->getResponse()->data as $item) {
    $sizeGB = round($item->size / 1024/1024/1024, 2);
    echo "- {$item->volid}: {$sizeGB}GB ({$item->content})\n";
}

// Upload ISO file (example for reference)
/*
$uploadParams = [
    'content' => 'iso',
    'filename' => 'debian-12.0.0-amd64-netinst.iso'
];
// Note: File upload requires multipart/form-data handling
*/
```

## Backup and Snapshot Management

### Snapshot Operations

```php
<?php
$vmid = 100;
$node = "pve1";

// List snapshots
$snapshots = $client->getNodes()->get($node)->getQemu()->get($vmid)->getSnapshot()->snapshotList();
echo "Snapshots for VM {$vmid}:\n";
foreach ($snapshots->getResponse()->data as $snap) {
    echo "- {$snap->name}: {$snap->description} ({$snap->snaptime})\n";
}

// Create snapshot
$snapParams = [
    'snapname' => 'backup-' . date('Y-m-d-H-i-s'),
    'description' => 'Automated backup snapshot before updates'
];
echo "Creating snapshot...\n";
$task = $client->getNodes()->get($node)->getQemu()->get($vmid)->getSnapshot()->snapshot($snapParams);
$upid = $task->getResponse()->data;
$client->waitForTaskToFinish($upid);
echo "Snapshot created successfully!\n";

// Delete old snapshot
$oldSnapshotName = 'old-snapshot';
echo "Deleting old snapshot...\n";
$deleteTask = $client->getNodes()->get($node)->getQemu()->get($vmid)->getSnapshot()->get($oldSnapshotName)->delsnapshot();
$upid = $deleteTask->getResponse()->data;
$client->waitForTaskToFinish($upid);
echo "Old snapshot deleted!\n";
```

### Backup Operations

```php
<?php
// Create backup
$backupParams = [
    'vmid' => '100,101,102', // Multiple VMs
    'storage' => 'local',
    'mode' => 'snapshot',
    'compress' => 'lzo',
    'notes' => 'Weekly backup ' . date('Y-m-d')
];

echo "Starting backup...\n";
$task = $client->getNodes()->get("pve1")->getVzdump()->vzdump($backupParams);
$upid = $task->getResponse()->data;

echo "Backup task started: {$upid}\n";
echo "Waiting for backup to complete...\n";
$client->waitForTaskToFinish($upid, 1000, 3600000); // Check every second, max 1 hour

$exitStatus = $client->getExitStatusTask($upid);
echo "Backup completed with status: {$exitStatus}\n";
```

## Monitoring and Alerts

### Resource Monitoring

```php
<?php
function monitorCluster($client)
{
    $resources = $client->getCluster()->getResources()->resources();
    $alerts = [];
    
    foreach ($resources->getResponse()->data as $resource) {
        switch ($resource->type) {
            case 'node':
                // Check CPU usage
                if ($resource->cpu > 0.8) {
                    $alerts[] = "High CPU usage on node {$resource->node}: " . 
                               round($resource->cpu * 100, 2) . "%";
                }
                
                // Check memory usage
                $memUsage = $resource->mem / $resource->maxmem;
                if ($memUsage > 0.9) {
                    $alerts[] = "High memory usage on node {$resource->node}: " . 
                               round($memUsage * 100, 2) . "%";
                }
                break;
                
            case 'storage':
                // Check storage usage
                $diskUsage = $resource->disk / $resource->maxdisk;
                if ($diskUsage > 0.85) {
                    $alerts[] = "High storage usage on {$resource->storage}: " . 
                               round($diskUsage * 100, 2) . "%";
                }
                break;
                
            case 'qemu':
            case 'lxc':
                // Check if VM/CT is down unexpectedly
                if ($resource->status !== 'running' && isset($resource->template) && !$resource->template) {
                    $alerts[] = "{$resource->type} {$resource->vmid} ({$resource->name}) is {$resource->status}";
                }
                break;
        }
    }
    
    return $alerts;
}

// Usage
$alerts = monitorCluster($client);
if (!empty($alerts)) {
    echo "ALERTS FOUND:\n";
    foreach ($alerts as $alert) {
        echo "- {$alert}\n";
    }
} else {
    echo "All systems normal.\n";
}
```

### Performance Data Collection

```php
<?php
function collectPerformanceData($client, $node, $vmid)
{
    // Get VM performance data
    $rrdData = $client->getNodes()->get($node)->getQemu()->get($vmid)->getRrd()->rrd('cpu,mem', 'hour');
    
    // Note: For actual data processing, you'd parse the RRD response
    // This is a simplified example
    
    $performanceData = [
        'timestamp' => time(),
        'node' => $node,
        'vmid' => $vmid,
        'cpu_usage' => 0, // Parse from RRD data
        'memory_usage' => 0, // Parse from RRD data
    ];
    
    return $performanceData;
}
```

## Bulk Operations

### Bulk VM Management

```php
<?php
function bulkVmOperation($client, $vmids, $operation)
{
    $results = [];
    
    foreach ($vmids as $nodeVm) {
        list($node, $vmid) = explode(':', $nodeVm);
        
        try {
            switch ($operation) {
                case 'start':
                    $task = $client->getNodes()->get($node)->getQemu()->get($vmid)->getStatus()->start();
                    break;
                case 'stop':
                    $task = $client->getNodes()->get($node)->getQemu()->get($vmid)->getStatus()->stop();
                    break;
                case 'shutdown':
                    $task = $client->getNodes()->get($node)->getQemu()->get($vmid)->getStatus()->shutdown();
                    break;
                default:
                    throw new InvalidArgumentException("Unknown operation: {$operation}");
            }
            
            if ($task->isSuccessStatusCode()) {
                $upid = $task->getResponse()->data;
                $results[] = [
                    'node' => $node,
                    'vmid' => $vmid,
                    'upid' => $upid,
                    'status' => 'started'
                ];
            }
        } catch (Exception $e) {
            $results[] = [
                'node' => $node,
                'vmid' => $vmid,
                'error' => $e->getMessage(),
                'status' => 'failed'
            ];
        }
    }
    
    return $results;
}

// Usage
$vmList = ['pve1:100', 'pve1:101', 'pve2:200'];
$results = bulkVmOperation($client, $vmList, 'start');

foreach ($results as $result) {
    if ($result['status'] === 'started') {
        echo "Started VM {$result['vmid']} on {$result['node']}\n";
    } else {
        echo "Failed to start VM {$result['vmid']}: {$result['error']}\n";
    }
}
```

## Integration Examples

### With Logging

```php
<?php
use Psr\Log\LoggerInterface;

class ProxmoxManager
{
    private $client;
    private $logger;
    
    public function __construct(PveClient $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }
    
    public function createVmWithLogging($node, $vmConfig)
    {
        $this->logger->info("Creating VM", ['node' => $node, 'vmid' => $vmConfig['vmid']]);
        
        try {
            $result = $this->client->getNodes()->get($node)->getQemu()->createVm($vmConfig);
            
            if ($result->isSuccessStatusCode()) {
                $this->logger->info("VM created successfully", ['vmid' => $vmConfig['vmid']]);
                return true;
            } else {
                $this->logger->error("VM creation failed", [
                    'vmid' => $vmConfig['vmid'],
                    'error' => $result->getError()
                ]);
                return false;
            }
        } catch (Exception $e) {
            $this->logger->error("VM creation exception", [
                'vmid' => $vmConfig['vmid'],
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }
}
```

### Configuration Management

```php
<?php
class VmConfigTemplate
{
    public static function webServer($vmid, $name)
    {
        return [
            'vmid' => $vmid,
            'name' => $name,
            'memory' => 2048,
            'cores' => 2,
            'sockets' => 1,
            'ostype' => 'l26',
            'scsi0' => 'local-lvm:20,format=qcow2',
            'net0' => 'virtio,bridge=vmbr0',
            'boot' => 'order=scsi0',
            'tags' => 'web-server,production'
        ];
    }
    
    public static function database($vmid, $name)
    {
        return [
            'vmid' => $vmid,
            'name' => $name,
            'memory' => 8192,
            'cores' => 4,
            'sockets' => 1,
            'ostype' => 'l26',
            'scsi0' => 'local-lvm:100,format=qcow2',
            'net0' => 'virtio,bridge=vmbr0',
            'boot' => 'order=scsi0',
            'tags' => 'database,production'
        ];
    }
}

// Usage
$webConfig = VmConfigTemplate::webServer(300, 'web-01');
$dbConfig = VmConfigTemplate::database(301, 'db-01');
```