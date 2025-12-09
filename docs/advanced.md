# Advanced Usage Guide

This guide covers complex scenarios, best practices, and advanced patterns for experienced developers.

## Enterprise Configuration

### **Custom HttpClient Setup**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// For enterprise scenarios, configuration is handled through the PveClient class
$client = new PveClient("pve.company.com", 8006);

// Configure timeout (in seconds)
$client->setTimeout(600); // 10 minutes

// SSL certificate validation (default is false)
$client->setValidateCertificate(true);

// API token authentication (recommended)
$client->setApiToken(getenv("PROXMOX_API_TOKEN"));
```

### **Resilient Operations**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Retry policy with exponential backoff
function withRetry($operation, $maxRetries = 3) {
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            return $operation();
        } catch (Exception $ex) {
            if ($attempt < $maxRetries && isRetriableError($ex)) {
                $delay = pow(2, $attempt);
                echo "Attempt $attempt failed, retrying in {$delay}s: " . $ex->getMessage() . "\n";
                sleep($delay);
            } else {
                throw $ex;
            }
        }
    }
    
    return $operation(); // Final attempt
}

function isRetriableError($ex) {
    // For PHP, we consider generic exceptions that might be network-related
    return true; // Simplified for PHP implementation
}

// Usage
$result = withRetry(function() use ($client) {
    return $client->getNodes()->get("pve1")->getQemu()->get(100)->getStatus()->getStart()->vmStart();
});
```

---

## Task and Resource Management

### **Long-Running Operations**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Complete task management with progress
function executeWithProgress($client, $operation, $node, $description) {
    echo "Starting: $description\n";
    
    $result = $operation();
    if (!$result->isSuccessStatusCode()) {
        echo "Failed to start $description: " . $result->getError() . "\n";
        return false;
    }
    
    // The actual task ID retrieval depends on the specific API call
    // This example assumes the result data contains the task ID
    $taskId = $result->getResponse()->data ?? null;
    if ($taskId) {
        return waitForTaskCompletion($client, $node, $taskId, $description);
    } else {
        return $result->isSuccessStatusCode(); // For operations that don't return task IDs
    }
}

function waitForTaskCompletion($client, $node, $taskId, $description) {
    $timeout = 1800; // 30 minutes in seconds
    $start = time();
    
    while ((time() - $start) < $timeout) {
        $status = $client->getNodes()->get($node)->getTasks()->get($taskId)->index();
        
        if ($status->isSuccessStatusCode()) {
            $statusData = $status->getResponse()->data;
            if ($statusData->status == "stopped") {
                $success = $statusData->exitstatus == "OK";
                echo "$description: {$statusData->exitstatus} (" . ($success ? "Success" : "Failed") . ")\n";
                return $success;
            }
        }
        
        sleep(2);
    }
    
    echo "Timeout: $description timed out\n";
    return false;
}
```

### **Bulk Operations**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Perform operations on multiple VMs
function bulkVmOperation($client, $vmIds, $operation, $operationName) {
    // Get all resources to find VM locations
    $resources = $client->getCluster()->getResources()->resources();
    $vmLocations = [];
    
    if ($resources->isSuccessStatusCode() && isset($resources->getResponse()->data)) {
        foreach ($resources->getResponse()->data as $resource) {
            if ($resource->type == "qemu" && in_array($resource->vmid, $vmIds)) {
                $vmLocations[$resource->vmid] = $resource->node;
            }
        }
    }
    
    $results = [];
    
    foreach ($vmIds as $vmId) {
        if (!isset($vmLocations[$vmId])) {
            echo "VM $vmId not found\n";
            $results[$vmId] = false;
            continue;
        }
        
        $node = $vmLocations[$vmId];
        
        try {
            $result = $operation($client, $node, $vmId);
            $success = $result->isSuccessStatusCode();
            
            echo "VM $vmId $operationName: " . ($success ? "Success" : "Failed - " . $result->getError()) . "\n";
            $results[$vmId] = $success;
        } catch (Exception $ex) {
            echo "VM $vmId $operationName: Exception - " . $ex->getMessage() . "\n";
            $results[$vmId] = false;
        }
    }
    
    return $results;
}

// Usage examples
$startResults = bulkVmOperation(
    $client,
    [100, 101, 102],
    function($client, $node, $vmId) {
        return $client->getNodes()->get($node)->getQemu()->get($vmId)->getStatus()->getStart()->vmStart();
    },
    "start"
);

$snapshotResults = bulkVmOperation(
    $client,
    [100, 101, 102],
    function($client, $node, $vmId) {
        return $client->getNodes()->get($node)->getQemu()->get($vmId)->getSnapshot()->snapshot("backup-" . date('Ymd'));
    },
    "snapshot"
);
```

---

## Monitoring and Health Checks

### **Cluster Health Assessment**

```php
<?php
class ClusterHealthMonitor
{
    private $client;
    
    public function __construct($client)
    {
        $this->client = $client;
    }
    
    public function getHealthReport() {
        $resources = $this->client->getCluster()->getResources()->resources();
        
        if (!$resources->isSuccessStatusCode()) {
            throw new Exception("Could not retrieve cluster resources: " . $resources->getError());
        }
        
        $allResources = $resources->getResponse()->data;
        $nodes = array_filter($allResources, function($r) { return $r->type == "node"; });
        $vms = array_filter($allResources, function($r) { return $r->type == "qemu"; });
        $containers = array_filter($allResources, function($r) { return $r->type == "lxc"; });
        
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'nodes' => [
                'total' => count($nodes),
                'online' => count(array_filter($nodes, function($n) { return $n->status == "online"; })),
                'average_cpu_usage' => array_sum(array_map(function($n) { return $n->cpu ?? 0; }, $nodes)) / max(count($nodes), 1),
                'average_memory_usage' => array_sum(array_map(function($n) { return ($n->mem ?? 0) / max($n->maxmem ?? 1, 1); }, $nodes)) / max(count($nodes), 1)
            ],
            'virtual_machines' => [
                'total' => count($vms),
                'running' => count(array_filter($vms, function($v) { return $v->status == "running"; })),
                'stopped' => count(array_filter($vms, function($v) { return $v->status == "stopped"; })),
                'high_cpu_usage' => count(array_filter($vms, function($v) { return ($v->cpu ?? 0) > 0.8; }))
            ],
            'containers' => [
                'total' => count($containers),
                'running' => count(array_filter($containers, function($c) { return $c->status == "running"; })),
                'stopped' => count(array_filter($containers, function($c) { return $c->status == "stopped"; }))
            ]
        ];
    }
    
    public function checkAlerts() {
        $alerts = [];
        $resources = $this->client->getCluster()->getResources()->resources();
        
        if (!$resources->isSuccessStatusCode()) {
            return $alerts;
        }
        
        $allResources = $resources->getResponse()->data;
        
        // Check for offline nodes
        $offlineNodes = array_filter($allResources, function($r) {
            return $r->type == "node" && $r->status != "online";
        });
        
        foreach ($offlineNodes as $node) {
            $alerts[] = [
                'severity' => 'critical',
                'message' => "Node {$node->node} is offline",
                'resource' => $node->node
            ];
        }
        
        // Check for high resource usage
        $highCpuNodes = array_filter($allResources, function($r) {
            return $r->type == "node" && ($r->cpu ?? 0) > 0.9;
        });
        
        foreach ($highCpuNodes as $node) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => "Node {$node->node} has high CPU usage: " . sprintf("%.1f%%", ($node->cpu ?? 0) * 100),
                'resource' => $node->node
            ];
        }
        
        return $alerts;
    }
}

// Usage
$monitor = new ClusterHealthMonitor($client);
$health = $monitor->getHealthReport();
$alerts = $monitor->checkAlerts();

echo "Cluster Health: {$health['nodes']['online']}/{$health['nodes']['total']} nodes online\n";
echo "VMs: {$health['virtual_machines']['running']}/{$health['virtual_machines']['total']} running\n";

foreach (array_filter($alerts, function($a) { return $a['severity'] == 'critical'; }) as $alert) {
    echo "CRITICAL: {$alert['message']}\n";
}
```

---

## Architecture Patterns

### **Repository Pattern**

```php
<?php
interface ProxmoxRepositoryInterface
{
    public function getVms($nodeFilter = null);
    public function getVmConfig($node, $vmId);
    public function startVm($node, $vmId);
    public function createSnapshot($node, $vmId, $name, $description = null);
}

class ProxmoxRepository implements ProxmoxRepositoryInterface
{
    private $client;
    
    public function __construct($client)
    {
        $this->client = $client;
    }
    
    public function getVms($nodeFilter = null)
    {
        echo "Getting VMs for node filter: $nodeFilter\n";
        
        $resources = $this->client->getCluster()->getResources()->resources();
        $vms = array_filter($resources->getResponse()->data ?? [], function($r) { 
            return $r->type == "qemu"; 
        });
        
        if (!empty($nodeFilter)) {
            $vms = array_filter($vms, function($vm) use ($nodeFilter) { 
                return strcasecmp($vm->node, $nodeFilter) === 0; 
            });
        }
        
        return $vms;
    }
    
    public function getVmConfig($node, $vmId)
    {
        echo "Getting config for VM $vmId on node $node\n";
        
        $result = $this->client->getNodes()->get($node)->getQemu()->get($vmId)->getConfig()->vmConfig();
        return $result->isSuccessStatusCode() ? $result->getResponse()->data : null;
    }
    
    public function startVm($node, $vmId)
    {
        echo "Starting VM $vmId on node $node\n";
        
        $result = $this->client->getNodes()->get($node)->getQemu()->get($vmId)->getStatus()->getStart()->vmStart();
        
        if ($result->isSuccessStatusCode()) {
            echo "Successfully started VM $vmId\n";
            return true;
        } else {
            echo "Failed to start VM $vmId: " . $result->getError() . "\n";
            return false;
        }
    }
    
    public function createSnapshot($node, $vmId, $name, $description = null)
    {
        echo "Creating snapshot $name for VM $vmId on node $node\n";
        
        $result = $this->client->getNodes()->get($node)->getQemu()->get($vmId)->getSnapshot()->snapshot($name, $description);
        return $result->isSuccessStatusCode();
    }
}
```

---

## Error Handling and Logging

### **Centralized Error Management**

```php
<?php
class ProxmoxOperations
{
    public static function safeExecute($operation, $operationName, $logger = null)
    {
        try {
            if ($logger) {
                $logger->log("debug", "Executing: $operationName");
            }
            
            $startTime = microtime(true);
            
            $result = $operation();
            $elapsed = (microtime(true) - $startTime) * 1000; // milliseconds
            
            if ($result->isSuccessStatusCode()) {
                if ($logger) {
                    $logger->log("info", "{$operationName} completed in " . round($elapsed) . "ms");
                }
            } else {
                if ($logger) {
                    $logger->log("warning", "{$operationName} failed: " . $result->getError() . " (took " . round($elapsed) . "ms)");
                }
            }
            
            return $result;
        } catch (Exception $ex) {
            if ($logger) {
                $logger->log("error", "Unexpected error during $operationName: " . $ex->getMessage());
            }
            throw $ex;
        }
    }
}

// Usage
$result = ProxmoxOperations::safeExecute(
    function() use ($client) {
        return $client->getNodes()->get("pve1")->getQemu()->get(100)->getStatus()->getStart()->vmStart();
    },
    "Start VM 100"
);
```

---

## Best Practices Summary

### **Performance**
- Configure appropriate timeouts using setTimeout()
- Implement retry policies for resilience 
- Cache frequently accessed data
- Use connection pooling if using persistent connections

### **Security**
- Always use API tokens in production
- Enable SSL certificate validation with setValidateCertificate()
- Store credentials securely (environment variables)
- Implement proper audit logging

### **Architecture**
- Use repository pattern for testability
- Implement centralized error handling
- Separate concerns with proper abstractions

### **Monitoring**
- Log all operations with appropriate levels
- Implement health checks and alerting
- Monitor task completion and failures
- Track performance metrics
```