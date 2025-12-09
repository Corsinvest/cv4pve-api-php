# Task Management Guide

Understanding and managing long-running operations in Proxmox VE.

## Understanding Tasks

Many Proxmox VE operations are asynchronous and return a task ID instead of immediate results:

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Operations that return task IDs would typically be POST/PUT/DELETE requests
// The actual API endpoints for cloning, backups, etc. would return UPID strings
// This library provides access to task information through:

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// Example: Starting a long-running operation
// The actual task ID would be returned by operations like cloning, creating, etc.

// To list tasks on a node
$tasks = $client->getNodes()->get("pve1")->getTasks()->index();
```

## Task Status

### **Checking Task Status**
```php
<?php
function getTaskStatus($client, $node, $taskId) {
    // Navigate to a specific task
    $task = $client->getNodes()->get($node)->getTasks()->get($taskId);
    
    // Get the status of the task
    $result = $task->index();  // This makes the API call to get task info

    if ($result->isSuccessStatusCode() && isset($result->getResponse()->data)) {
        $data = $result->getResponse()->data;
        return [
            'Status' => isset($data->status) ? $data->status : null,        // "running", "stopped"
            'ExitStatus' => isset($data->exitstatus) ? $data->exitstatus : null, // "OK" if successful
            'StartTime' => isset($data->starttime) ? $data->starttime : null,
            'EndTime' => isset($data->endtime) ? $data->endtime : null,
            'UPID' => isset($data->upid) ? $data->upid : null,
            'Type' => isset($data->type) ? $data->type : null,
            'User' => isset($data->user) ? $data->user : null
        ];
    }

    throw new Exception("Failed to get task status: " . $result->getError());
}
```

### **Waiting for Completion**
```php
<?php
function waitForTaskCompletion(
    $client,
    $node,
    $taskId,
    $timeout = 1800
) {
    $startTime = time();
    $lastStatus = "";

    while (time() - $startTime < $timeout) {
        $statusResult = $client->getNodes()->get($node)->getTasks()->get($taskId)->index();

        if (!$statusResult->isSuccessStatusCode()) {
            throw new Exception("Failed to check task status: " . $statusResult->getError());
        }

        $data = $statusResult->getResponse()->data;
        $currentStatus = $data->status;

        // Report progress if status changed
        if ($currentStatus != $lastStatus) {
            echo "Task $taskId: $currentStatus\n";
            $lastStatus = $currentStatus;
        }

        // Check if task completed
        if ($data->status == "stopped") {
            $exitStatus = $data->exitstatus;
            $success = $exitStatus == "OK";
            echo "Task $taskId " . ($success ? "completed" : "failed") . ": $exitStatus\n";
            return $success;
        }

        sleep(2); // Check every 2 seconds
    }

    throw new Exception("Task $taskId did not complete within $timeout seconds");
}
```

## Task Management

### **Listing Tasks**
```php
<?php
function listNodeTasks($client, $node) {
    // Get all tasks for a specific node
    $result = $client->getNodes()->get($node)->getTasks()->index();
    
    if ($result->isSuccessStatusCode() && isset($result->getResponse()->data)) {
        $tasks = [];
        
        foreach ($result->getResponse()->data as $taskData) {
            $tasks[] = [
                'UPID' => $taskData->upid,
                'Node' => $taskData->node,
                'Type' => $taskData->type,
                'Status' => $taskData->status,
                'StartTime' => date('Y-m-d H:i:s', $taskData->starttime),
                'PID' => $taskData->pid,
                'User' => $taskData->user
            ];
        }
        
        return $tasks;
    }
    
    return [];
}
```

## Task Utilities

### **Task History**
```php
<?php
function getRecentTasks($client, $node, $limit = 10) {
    // Note: The library uses index() method for GET requests to get tasks
    $result = $client->getNodes()->get($node)->getTasks()->index();

    if ($result->isSuccessStatusCode() && isset($result->getResponse()->data)) {
        $tasks = [];

        foreach ($result->getResponse()->data as $task) {
            $tasks[] = [
                'UPID' => $task->upid,
                'Type' => $task->type,
                'Status' => $task->status,
                'ExitStatus' => isset($task->exitstatus) ? $task->exitstatus : null,
                'StartTime' => date('Y-m-d H:i:s', $task->starttime),
                'EndTime' => isset($task->endtime) ? date('Y-m-d H:i:s', $task->endtime) : null,
                'User' => $task->user,
                'Node' => $task->node
            ];
        }

        // Sort by start time (most recent first)
        usort($tasks, function($a, $b) {
            return strtotime($b['StartTime']) - strtotime($a['StartTime']);
        });
        
        // Limit results
        return array_slice($tasks, 0, $limit);
    }

    return [];
}
```

## Best Practices

### **Timeout Management**
```php
<?php
// The library supports timeout through the setTimeout() method
$client->setTimeout(300); // 5 minutes timeout for requests

// When monitoring long-running tasks
function monitorLongRunningTask($client, $node, $taskId) {
    try {
        return waitForTaskCompletion($client, $node, $taskId, 3600); // 1 hour timeout
    } catch (Exception $e) {
        echo "Task monitoring failed: " . $e->getMessage() . "\n";
        return false;
    }
}
```

### **Error Recovery**
```php
<?php
function robustTaskCheck($client, $node, $taskId) {
    $maxRetries = 3;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $result = $client->getNodes()->get($node)->getTasks()->get($taskId)->index();
            return $result;
        } catch (Exception $ex) {
            if ($attempt < $maxRetries) {
                echo "Warning: Error checking task (attempt $attempt): " . $ex->getMessage() . "\n";
                sleep(5);
            } else {
                throw $ex;
            }
        }
    }
}
```

## Task Information Structure

The library returns task information in the Result object. The structure depends on the API response:

```php
<?php
// Example of accessing task information
$result = $client->getNodes()->get("pve1")->getTasks()->index();

if ($result->isSuccessStatusCode() && isset($result->getResponse()->data)) {
    foreach ($result->getResponse()->data as $task) {
        echo "Task UPID: " . $task->upid . "\n";
        echo "Type: " . $task->type . "\n";
        echo "Status: " . $task->status . "\n";
        echo "User: " . $task->user . "\n";
    }
}
```