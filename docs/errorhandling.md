# Error Handling Guide

Comprehensive guide to handling errors and exceptions when working with the Proxmox VE API.

## Types of Errors

### **Network Errors**
Errors can occur during cURL requests as the library uses cURL for HTTP communication.

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("invalid-host.local", 8006);

// Check result for network errors
$result = $client->getVersion()->version();
if (!$result->isSuccessStatusCode()) {
    echo "Network error: " . $result->getReasonPhrase() . "\n";
    // Handle: DNS resolution, connection refused, network timeout
}
```

### **Authentication Errors**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.local", 8006);
$success = $client->login("user@pam", "wrong-password");

if (!$success) {
    echo "Authentication failed - check credentials\n";
    
    // Check specific error from result
    $lastResult = $client->getLastResult();
    if ($lastResult) {
        echo "Error: " . $lastResult->getReasonPhrase() . "\n";
    }
}
```

### **API Response Errors**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

$result = $client->getNodes()->get("pve1")->getQemu()->get(999)->getConfig()->vmConfig();

if (!$result->isSuccessStatusCode()) {
    switch ($result->getStatusCode()) {
        case 404:
            echo "VM not found\n";
            break;
        case 403:
            echo "Permission denied\n";
            break;
        case 400:
            echo "Bad request: " . $result->getError() . "\n";
            break;
        default:
            echo "API error: " . $result->getStatusCode() . " - " . $result->getReasonPhrase() . "\n";
            break;
    }
}
```

## Error Handling Patterns

### **Basic Pattern**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

function safeVmOperation($client, $node, $vmId) {
    $result = $client->getNodes()->get($node)->getQemu()->get($vmId)->getStatus()->getStart()->vmStart();

    if ($result->isSuccessStatusCode()) {
        echo "VM $vmId started successfully\n";
        return true;
    } else {
        echo "Failed to start VM $vmId: " . $result->getError() . "\n";
        return false;
    }
}
```

### **Centralized Error Handler**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

class ErrorHandler {
    public static function safeApiCall($apiCall, $operation = "API call") {
        $result = $apiCall();

        if (!$result->isSuccessStatusCode()) {
            self::logApiError($result, $operation);
        }

        return $result;
    }

    private static function logApiError($result, $operation) {
        echo "$operation failed:\n";
        echo "   Status: " . $result->getStatusCode() . " - " . $result->getReasonPhrase() . "\n";

        if ($result->responseInError()) {
            echo "   Details: " . $result->getError() . "\n";
        }
    }
}

// Usage
$result = ErrorHandler::safeApiCall(
    function() use ($client) {
        return $client->getNodes()->get("pve1")->getQemu()->get(100)->getStatus()->getStart()->vmStart();
    },
    "Starting VM 100"
);
```

### **Retry Logic**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

function withRetry($operation, $maxRetries = 3, $operationName = "operation") {
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $result = $operation();

        if ($result->isSuccessStatusCode()) {
            return $result;
        }

        // Don't retry client errors (4xx), only server errors (5xx)
        if ($result->getStatusCode() < 500) {
            echo "$operationName failed with client error: " . $result->getStatusCode() . "\n";
            return $result;
        }

        if ($attempt < $maxRetries) {
            echo "Warning: $operationName failed (attempt $attempt/$maxRetries), retrying...\n";
            sleep(pow(2, $attempt)); // Exponential backoff
        }
    }

    return $result;
}

// Usage
$result = withRetry(
    function() use ($client) {
        return $client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->vmConfig();
    },
    3,
    "Get VM config"
);
```

## Common Error Scenarios

### **Permission Issues**
```php
<?php
function handlePermissionError($result) {
    if ($result->getStatusCode() == 403) {
        echo "Permission denied. Check:\n";
        echo "   - User has required permissions\n";
        echo "   - API token has correct privileges\n";
        echo "   - Resource exists and user has access\n";
    }
}
```

### **Resource Not Found**
```php
<?php
function vmExists($client, $node, $vmId) {
    $result = $client->getNodes()->get($node)->getQemu()->get($vmId)->getConfig()->vmConfig();
    return $result->isSuccessStatusCode();
}

// Usage
if (!vmExists($client, "pve1", 100)) {
    echo "VM 100 does not exist on node pve1\n";
    return;
}
```

### **Timeout Handling**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.local", 8006);
$client->setTimeout(300); // Increase timeout for long operations (5 minutes in seconds)

$result = $client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->vmConfig();
if (!$result->isSuccessStatusCode()) {
    echo "Operation timed out or failed: " . $result->getReasonPhrase() . "\n";
}
```

## Best Practices

### **Defensive Programming**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Always validate input
function getVmInfo($client, $node, $vmId) {
    if (empty($node)) {
        throw new InvalidArgumentException("Node name cannot be empty");
    }

    if ($vmId <= 0) {
        throw new InvalidArgumentException("VM ID must be positive");
    }

    return $client->getNodes()->get($node)->getQemu()->get($vmId)->getConfig()->vmConfig();
}

// Check for null responses
$result = $client->getCluster()->getResources()->resources();
if ($result->isSuccessStatusCode() && $result->getResponse() !== null && isset($result->getResponse()->data)) {
    foreach ($result->getResponse()->data as $resource) {
        // Process resource
    }
}
```

### **Graceful Degradation**
```php
<?php
function getClusterStatus($client) {
    $result = $client->getCluster()->getStatus()->getStatus();
    if ($result->isSuccessStatusCode()) {
        return parseClusterStatus($result->getResponse()->data);
    } else {
        echo "Warning: Could not get cluster status: " . $result->getReasonPhrase() . "\n";
    }

    // Return fallback status
    return [
        'Status' => 'unknown',
        'LastUpdate' => date('Y-m-d H:i:s')
    ];
}
```

### **Detailed Logging**
```php
<?php
function loggedApiCall($apiCall, $operation) {
    echo "Starting: $operation\n";
    $startTime = microtime(true);

    $result = $apiCall();
    $elapsed = (microtime(true) - $startTime) * 1000;

    if ($result->isSuccessStatusCode()) {
        echo "$operation completed in " . round($elapsed) . "ms\n";
    } else {
        echo "$operation failed after " . round($elapsed) . "ms: " . $result->getError() . "\n";
    }

    return $result;
}
```