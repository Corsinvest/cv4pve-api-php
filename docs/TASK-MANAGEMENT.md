# ⚙️ Task Management

Handle long-running operations with cv4pve-api-php library.

## Overview

Many Proxmox VE operations are asynchronous and return a task identifier (UPID) instead of immediate results. This library provides comprehensive task management functionality to monitor and wait for these operations to complete.

## Understanding UPID Format

Task identifiers follow this format:
```
UPID:node:pid:starttime:type:id:user:status
```

Example: `UPID:pve1:00001234:5F123456:qmstart:100:root@pam:`

Components:
- **node**: Node where the task is running (pve1)
- **pid**: Process ID (00001234)
- **starttime**: Unix timestamp when task started (5F123456)
- **type**: Operation type (qmstart, qmstop, vzdump, etc.)
- **id**: Resource ID (VM ID 100)
- **user**: User who started the task (root@pam)
- **status**: Current status (usually empty for running tasks)

## Core Task Management Methods

### Check if Task is Running

```php
<?php
$upid = 'UPID:pve1:00001234:5F123456:qmstart:100:root@pam:';

if ($client->taskIsRunning($upid)) {
    echo "Task is still running\n";
} else {
    echo "Task has completed\n";
}
```

### Wait for Task Completion

```php
<?php
// Basic wait (default: check every 500ms, timeout after 10 seconds)
$client->waitForTaskToFinish($upid);

// Custom intervals and timeout
$client->waitForTaskToFinish($upid, 1000, 60000); // Check every 1s, timeout after 1 minute

// For long operations like backups
$client->waitForTaskToFinish($upid, 2000, 3600000); // Check every 2s, timeout after 1 hour
```

### Get Task Exit Status

```php
<?php
$exitStatus = $client->getExitStatusTask($upid);

switch ($exitStatus) {
    case 'OK':
        echo "Task completed successfully\n";
        break;
    case '':
        echo "Task is still running\n";
        break;
    default:
        echo "Task failed with status: {$exitStatus}\n";
        break;
}
```

## Practical Examples

### VM Start with Task Management

```php
<?php
$node = 'pve1';
$vmid = 100;

echo "Starting VM {$vmid}...\n";
$result = $client->getNodes()->get($node)->getQemu()->get($vmid)->getStatus()->start();

if ($result->isSuccessStatusCode()) {
    $upid = $result->getResponse()->data;
    echo "Start task initiated: {$upid}\n";
    
    // Wait for completion
    echo "Waiting for VM to start...\n";
    $client->waitForTaskToFinish($upid);
    
    // Check result
    $exitStatus = $client->getExitStatusTask($upid);
    if ($exitStatus === 'OK') {
        echo "VM {$vmid} started successfully!\n";
    } else {
        echo "VM start failed: {$exitStatus}\n";
    }
} else {
    echo "Failed to initiate VM start: " . $result->getError() . "\n";
}
```

### Backup with Progress Monitoring

```php
<?php
function performBackupWithProgress($client, $node, $vmids, $storage)
{
    $backupParams = [
        'vmid' => implode(',', $vmids),
        'storage' => $storage,
        'mode' => 'snapshot',
        'compress' => 'lzo'
    ];
    
    echo "Starting backup of VMs: " . implode(', ', $vmids) . "\n";
    $result = $client->getNodes()->get($node)->getVzdump()->vzdump($backupParams);
    
    if (!$result->isSuccessStatusCode()) {
        echo "Failed to start backup: " . $result->getError() . "\n";
        return false;
    }
    
    $upid = $result->getResponse()->data;
    echo "Backup task started: {$upid}\n";
    
    // Monitor progress
    $startTime = time();
    while ($client->taskIsRunning($upid)) {
        $elapsed = time() - $startTime;
        echo "Backup running... ({$elapsed}s elapsed)\n";
        sleep(10); // Check every 10 seconds for backups
    }
    
    $exitStatus = $client->getExitStatusTask($upid);
    $totalTime = time() - $startTime;
    
    if ($exitStatus === 'OK') {
        echo "Backup completed successfully in {$totalTime} seconds!\n";
        return true;
    } else {
        echo "Backup failed after {$totalTime} seconds: {$exitStatus}\n";
        return false;
    }
}

// Usage
$success = performBackupWithProgress($client, 'pve1', [100, 101, 102], 'local');
```

### Bulk Operations with Task Management

```php
<?php
function bulkVmStartWithProgress($client, $vms)
{
    $tasks = [];
    
    // Start all VMs
    foreach ($vms as $vm) {
        list($node, $vmid) = explode(':', $vm);
        
        echo "Starting VM {$vmid} on {$node}...\n";
        $result = $client->getNodes()->get($node)->getQemu()->get($vmid)->getStatus()->start();
        
        if ($result->isSuccessStatusCode()) {
            $upid = $result->getResponse()->data;
            $tasks[] = [
                'node' => $node,
                'vmid' => $vmid,
                'upid' => $upid,
                'started_at' => time()
            ];
            echo "Task initiated: {$upid}\n";
        } else {
            echo "Failed to start VM {$vmid}: " . $result->getError() . "\n";
        }
    }
    
    // Wait for all tasks to complete
    echo "\nWaiting for all VMs to start...\n";
    $completed = [];
    $maxWaitTime = 300; // 5 minutes max
    
    while (count($completed) < count($tasks)) {
        foreach ($tasks as $index => $task) {
            if (in_array($index, $completed)) {
                continue; // Already completed
            }
            
            $elapsed = time() - $task['started_at'];
            if ($elapsed > $maxWaitTime) {
                echo "Timeout waiting for VM {$task['vmid']} (task: {$task['upid']})\n";
                $completed[] = $index;
                continue;
            }
            
            if (!$client->taskIsRunning($task['upid'])) {
                $exitStatus = $client->getExitStatusTask($task['upid']);
                if ($exitStatus === 'OK') {
                    echo "VM {$task['vmid']} started successfully!\n";
                } else {
                    echo "VM {$task['vmid']} failed to start: {$exitStatus}\n";
                }
                $completed[] = $index;
            }
        }
        
        if (count($completed) < count($tasks)) {
            sleep(2); // Check every 2 seconds
        }
    }
    
    echo "All operations completed.\n";
}

// Usage
$vms = ['pve1:100', 'pve1:101', 'pve2:200', 'pve2:201'];
bulkVmStartWithProgress($client, $vms);
```

### Snapshot Creation with Verification

```php
<?php
function createSnapshotWithVerification($client, $node, $vmid, $snapname, $description = '')
{
    echo "Creating snapshot '{$snapname}' for VM {$vmid}...\n";
    
    $snapParams = [
        'snapname' => $snapname,
        'description' => $description
    ];
    
    $result = $client->getNodes()->get($node)->getQemu()->get($vmid)->getSnapshot()->snapshot($snapParams);
    
    if (!$result->isSuccessStatusCode()) {
        echo "Failed to initiate snapshot: " . $result->getError() . "\n";
        return false;
    }
    
    $upid = $result->getResponse()->data;
    echo "Snapshot task started: {$upid}\n";
    
    // Wait for completion
    $client->waitForTaskToFinish($upid, 500, 60000); // 1 minute timeout
    
    $exitStatus = $client->getExitStatusTask($upid);
    if ($exitStatus === 'OK') {
        echo "Snapshot created successfully!\n";
        
        // Verify snapshot exists
        $snapshots = $client->getNodes()->get($node)->getQemu()->get($vmid)->getSnapshot()->snapshotList();
        foreach ($snapshots->getResponse()->data as $snap) {
            if ($snap->name === $snapname) {
                echo "Snapshot verified: {$snap->name} ({$snap->description})\n";
                return true;
            }
        }
        
        echo "Warning: Snapshot created but not found in list\n";
        return false;
    } else {
        echo "Snapshot creation failed: {$exitStatus}\n";
        return false;
    }
}

// Usage
$success = createSnapshotWithVerification(
    $client, 
    'pve1', 
    100, 
    'pre-update-' . date('Y-m-d-H-i'),
    'Snapshot before system updates'
);
```

## Advanced Task Management

### Task Status Monitoring

```php
<?php
function getTaskDetails($client, $upid)
{
    // Extract node from UPID
    $parts = explode(':', $upid);
    $node = $parts[1] ?? '';
    
    if (empty($node)) {
        throw new InvalidArgumentException("Invalid UPID format");
    }
    
    // Get task status from API
    $result = $client->get("/nodes/{$node}/tasks/{$upid}/status");
    
    if ($result->isSuccessStatusCode()) {
        return $result->getResponse()->data;
    }
    
    return null;
}

function monitorTaskProgress($client, $upid)
{
    echo "Monitoring task: {$upid}\n";
    
    while ($client->taskIsRunning($upid)) {
        $details = getTaskDetails($client, $upid);
        
        if ($details) {
            $status = $details->status ?? 'unknown';
            $progress = isset($details->progress) ? round($details->progress * 100, 1) : 'N/A';
            
            echo "Status: {$status}, Progress: {$progress}%\n";
        }
        
        sleep(5); // Check every 5 seconds
    }
    
    $exitStatus = $client->getExitStatusTask($upid);
    echo "Task completed with status: {$exitStatus}\n";
}
```

### Task Timeout and Cleanup

```php
<?php
class TaskManager
{
    private $client;
    private $activeTasks = [];
    
    public function __construct($client)
    {
        $this->client = $client;
    }
    
    public function startTask($operation, $upid, $timeout = 300)
    {
        $this->activeTasks[$upid] = [
            'operation' => $operation,
            'started_at' => time(),
            'timeout' => $timeout,
            'status' => 'running'
        ];
        
        echo "Task registered: {$operation} ({$upid})\n";
    }
    
    public function checkTasks()
    {
        $currentTime = time();
        
        foreach ($this->activeTasks as $upid => $task) {
            if ($task['status'] !== 'running') {
                continue;
            }
            
            $elapsed = $currentTime - $task['started_at'];
            
            // Check for timeout
            if ($elapsed > $task['timeout']) {
                echo "Task timeout: {$task['operation']} ({$upid}) after {$elapsed}s\n";
                $this->activeTasks[$upid]['status'] = 'timeout';
                continue;
            }
            
            // Check if task is still running
            if (!$this->client->taskIsRunning($upid)) {
                $exitStatus = $this->client->getExitStatusTask($upid);
                $this->activeTasks[$upid]['status'] = $exitStatus;
                $this->activeTasks[$upid]['completed_at'] = $currentTime;
                
                echo "Task completed: {$task['operation']} ({$upid}) - Status: {$exitStatus}\n";
            }
        }
    }
    
    public function waitForAllTasks()
    {
        while ($this->hasRunningTasks()) {
            $this->checkTasks();
            sleep(2);
        }
        
        echo "All tasks completed.\n";
    }
    
    private function hasRunningTasks()
    {
        foreach ($this->activeTasks as $task) {
            if ($task['status'] === 'running') {
                return true;
            }
        }
        return false;
    }
    
    public function getTaskSummary()
    {
        $summary = ['completed' => 0, 'failed' => 0, 'timeout' => 0];
        
        foreach ($this->activeTasks as $task) {
            switch ($task['status']) {
                case 'OK':
                    $summary['completed']++;
                    break;
                case 'timeout':
                    $summary['timeout']++;
                    break;
                case 'running':
                    // Still running
                    break;
                default:
                    $summary['failed']++;
                    break;
            }
        }
        
        return $summary;
    }
}

// Usage
$taskManager = new TaskManager($client);

// Start multiple operations
$vms = ['pve1:100', 'pve1:101', 'pve2:200'];
foreach ($vms as $vm) {
    list($node, $vmid) = explode(':', $vm);
    $result = $client->getNodes()->get($node)->getQemu()->get($vmid)->getStatus()->start();
    if ($result->isSuccessStatusCode()) {
        $upid = $result->getResponse()->data;
        $taskManager->startTask("Start VM {$vmid}", $upid, 120); // 2 minute timeout
    }
}

// Wait for all to complete
$taskManager->waitForAllTasks();

// Get summary
$summary = $taskManager->getTaskSummary();
echo "Summary - Completed: {$summary['completed']}, Failed: {$summary['failed']}, Timeout: {$summary['timeout']}\n";
```

## Task Types and Expected Duration

Different operations have different expected completion times:

| Operation | Typical Duration | Recommended Timeout |
|-----------|-----------------|-------------------|
| VM Start/Stop | 10-60 seconds | 2 minutes |
| VM Creation | 1-5 minutes | 10 minutes |
| Snapshot Creation | 10-120 seconds | 5 minutes |
| Backup (small VM) | 2-10 minutes | 30 minutes |
| Backup (large VM) | 10-60 minutes | 2 hours |
| Migration | 2-30 minutes | 1 hour |
| Template Creation | 5-30 minutes | 1 hour |

## Best Practices

### 1. Always Check Task Status

```php
<?php
// Good practice
$result = $client->getNodes()->get($node)->getQemu()->get($vmid)->getStatus()->start();
if ($result->isSuccessStatusCode()) {
    $upid = $result->getResponse()->data;
    $client->waitForTaskToFinish($upid);
    $exitStatus = $client->getExitStatusTask($upid);
    
    if ($exitStatus === 'OK') {
        echo "Operation completed successfully\n";
    } else {
        echo "Operation failed: {$exitStatus}\n";
    }
}
```

### 2. Use Appropriate Timeouts

```php
<?php
// Different timeouts for different operations
$client->waitForTaskToFinish($vmStartUpid, 500, 120000);    // 2 minutes for VM start
$client->waitForTaskToFinish($backupUpid, 2000, 3600000);  // 1 hour for backup
$client->waitForTaskToFinish($migrationUpid, 1000, 1800000); // 30 minutes for migration
```

### 3. Implement Progress Feedback

```php
<?php
function waitWithProgress($client, $upid, $operation)
{
    $startTime = time();
    $lastUpdate = 0;
    
    echo "Starting {$operation}...\n";
    
    while ($client->taskIsRunning($upid)) {
        $elapsed = time() - $startTime;
        
        // Update every 10 seconds
        if ($elapsed - $lastUpdate >= 10) {
            echo "{$operation} running... ({$elapsed}s elapsed)\n";
            $lastUpdate = $elapsed;
        }
        
        sleep(2);
    }
    
    $totalTime = time() - $startTime;
    $exitStatus = $client->getExitStatusTask($upid);
    
    if ($exitStatus === 'OK') {
        echo "{$operation} completed successfully in {$totalTime} seconds!\n";
    } else {
        echo "{$operation} failed after {$totalTime} seconds: {$exitStatus}\n";
    }
    
    return $exitStatus === 'OK';
}
```