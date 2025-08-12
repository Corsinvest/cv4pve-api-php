# 🚨 Error Handling

Exception management for cv4pve-api-php library.

## Overview

Proper error handling is crucial when working with Proxmox VE API. This library provides multiple layers of error detection and handling to ensure robust applications.

## Types of Errors

### 1. Connection Errors
- Network connectivity issues
- SSL certificate problems
- DNS resolution failures
- Timeout errors

### 2. Authentication Errors
- Invalid credentials
- Missing two-factor authentication
- Expired sessions
- Invalid API tokens

### 3. API Errors
- Invalid parameters
- Resource not found
- Permission denied
- Validation errors

### 4. Task Errors
- Operation failures
- Resource conflicts
- Insufficient resources

## Error Detection Methods

### Result Object Status Checking

```php
<?php
$result = $client->get('/version');

// Check HTTP status code
if (!$result->isSuccessStatusCode()) {
    echo "HTTP Error: " . $result->getStatusCode() . " " . $result->getReasonPhrase() . "\n";
}

// Check for API errors in response
if ($result->responseInError()) {
    echo "API Error: " . $result->getError() . "\n";
}

// Check both conditions
if ($result->isSuccessStatusCode() && !$result->responseInError()) {
    // Success - process response
    $data = $result->getResponse()->data;
} else {
    // Handle error
    echo "Operation failed\n";
}
```

### Exception Handling

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveExceptionAuthentication;

try {
    $client = new PveClient("proxmox.example.com");
    $client->login('root', 'password', 'pam');
    
    $result = $client->getNodes()->index();
    // Process result...
    
} catch (PveExceptionAuthentication $e) {
    echo "Authentication error: " . $e->getMessage() . "\n";
    
    // Check if 2FA is required
    $result = $e->getResult();
    if (isset($result->getResponse()->data->{'tfa-challenge'})) {
        echo "Two-factor authentication required\n";
    }
    
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
}
```

## Comprehensive Error Handling Pattern

### Complete Error Handling Function

```php
<?php
function handleApiCall($client, $operation, callable $apiCall)
{
    try {
        $result = $apiCall();
        
        // Check HTTP status
        if (!$result->isSuccessStatusCode()) {
            $error = [
                'type' => 'http_error',
                'code' => $result->getStatusCode(),
                'message' => $result->getReasonPhrase(),
                'operation' => $operation
            ];
            
            switch ($result->getStatusCode()) {
                case 401:
                    $error['description'] = 'Authentication required or failed';
                    break;
                case 403:
                    $error['description'] = 'Permission denied';
                    break;
                case 404:
                    $error['description'] = 'Resource not found';
                    break;
                case 500:
                    $error['description'] = 'Internal server error';
                    break;
                case 502:
                    $error['description'] = 'Bad gateway - Proxmox service may be down';
                    break;
                case 503:
                    $error['description'] = 'Service unavailable';
                    break;
                default:
                    $error['description'] = 'HTTP error occurred';
            }
            
            return $error;
        }
        
        // Check API response for errors
        if ($result->responseInError()) {
            return [
                'type' => 'api_error',
                'message' => $result->getError(),
                'operation' => $operation,
                'description' => 'API returned error response'
            ];
        }
        
        // Success
        return [
            'type' => 'success',
            'data' => $result->getResponse(),
            'operation' => $operation
        ];
        
    } catch (PveExceptionAuthentication $e) {
        return [
            'type' => 'auth_error',
            'message' => $e->getMessage(),
            'operation' => $operation,
            'requires_2fa' => isset($e->getResult()->getResponse()->data->{'tfa-challenge'}),
            'description' => 'Authentication failed'
        ];
        
    } catch (Exception $e) {
        return [
            'type' => 'exception',
            'message' => $e->getMessage(),
            'operation' => $operation,
            'description' => 'Unexpected error occurred'
        ];
    }
}

// Usage
$result = handleApiCall($client, 'Get VM Status', function() use ($client) {
    return $client->getNodes()->get('pve1')->getQemu()->get(100)->getStatus()->current();
});

switch ($result['type']) {
    case 'success':
        echo "VM Status: " . $result['data']->data->status . "\n";
        break;
        
    case 'http_error':
        echo "HTTP Error {$result['code']}: {$result['description']}\n";
        break;
        
    case 'api_error':
        echo "API Error: {$result['message']}\n";
        break;
        
    case 'auth_error':
        echo "Authentication Error: {$result['message']}\n";
        if ($result['requires_2fa']) {
            echo "Two-factor authentication is required\n";
        }
        break;
        
    case 'exception':
        echo "Unexpected Error: {$result['message']}\n";
        break;
}
```

## Specific Error Scenarios

### Connection Errors

```php
<?php
function testConnection($hostname, $port = 8006)
{
    try {
        $client = new PveClient($hostname, $port);
        $client->setTimeout(5000); // 5 second timeout for testing
        
        $result = $client->get('/version');
        
        if ($result->isSuccessStatusCode()) {
            echo "Connection successful to {$hostname}:{$port}\n";
            return true;
        } else {
            echo "Connection failed: HTTP {$result->getStatusCode()}\n";
            return false;
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        
        if (strpos($message, 'Connection timed out') !== false) {
            echo "Connection timeout - server may be unreachable\n";
        } elseif (strpos($message, 'SSL certificate') !== false) {
            echo "SSL certificate error - try disabling certificate validation\n";
        } elseif (strpos($message, 'Name or service not known') !== false) {
            echo "DNS resolution failed - check hostname\n";
        } elseif (strpos($message, 'Connection refused') !== false) {
            echo "Connection refused - check if Proxmox is running and port is correct\n";
        } else {
            echo "Connection error: {$message}\n";
        }
        
        return false;
    }
}

// Usage
if (!testConnection('proxmox.example.com')) {
    echo "Cannot connect to Proxmox VE server\n";
    exit(1);
}
```

### Authentication Error Handling

```php
<?php
function authenticateWithRetry($client, $username, $password, $realm = 'pam', $maxRetries = 3)
{
    $attempts = 0;
    
    while ($attempts < $maxRetries) {
        try {
            $attempts++;
            echo "Authentication attempt {$attempts}...\n";
            
            if ($client->login($username, $password, $realm)) {
                echo "Authentication successful\n";
                return true;
            }
            
        } catch (PveExceptionAuthentication $e) {
            $result = $e->getResult();
            
            // Check if 2FA is required
            if (isset($result->getResponse()->data->{'tfa-challenge'})) {
                echo "Two-factor authentication required\n";
                echo "Enter TOTP code: ";
                $totpCode = trim(fgets(STDIN));
                
                try {
                    if ($client->login($username, $password, $realm, $totpCode)) {
                        echo "2FA authentication successful\n";
                        return true;
                    }
                } catch (Exception $tfaException) {
                    echo "2FA authentication failed: " . $tfaException->getMessage() . "\n";
                }
            } else {
                echo "Authentication failed: " . $e->getMessage() . "\n";
            }
            
        } catch (Exception $e) {
            echo "Authentication error: " . $e->getMessage() . "\n";
        }
        
        if ($attempts < $maxRetries) {
            echo "Retrying in 2 seconds...\n";
            sleep(2);
        }
    }
    
    echo "Authentication failed after {$maxRetries} attempts\n";
    return false;
}
```

### Resource Not Found Handling

```php
<?php
function getVmStatusSafe($client, $node, $vmid)
{
    try {
        $result = $client->getNodes()->get($node)->getQemu()->get($vmid)->getStatus()->current();
        
        if (!$result->isSuccessStatusCode()) {
            switch ($result->getStatusCode()) {
                case 404:
                    return [
                        'success' => false,
                        'error' => 'VM not found',
                        'vmid' => $vmid,
                        'node' => $node
                    ];
                case 403:
                    return [
                        'success' => false,
                        'error' => 'Permission denied',
                        'vmid' => $vmid,
                        'node' => $node
                    ];
                default:
                    return [
                        'success' => false,
                        'error' => "HTTP {$result->getStatusCode()}: {$result->getReasonPhrase()}",
                        'vmid' => $vmid,
                        'node' => $node
                    ];
            }
        }
        
        if ($result->responseInError()) {
            return [
                'success' => false,
                'error' => $result->getError(),
                'vmid' => $vmid,
                'node' => $node
            ];
        }
        
        return [
            'success' => true,
            'status' => $result->getResponse()->data->status,
            'data' => $result->getResponse()->data,
            'vmid' => $vmid,
            'node' => $node
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Exception: ' . $e->getMessage(),
            'vmid' => $vmid,
            'node' => $node
        ];
    }
}

// Usage
$result = getVmStatusSafe($client, 'pve1', 100);
if ($result['success']) {
    echo "VM {$result['vmid']} status: {$result['status']}\n";
} else {
    echo "Failed to get VM status: {$result['error']}\n";
}
```

### Task Error Handling

```php
<?php
function startVmWithErrorHandling($client, $node, $vmid)
{
    echo "Starting VM {$vmid} on {$node}...\n";
    
    try {
        // Initiate start
        $result = $client->getNodes()->get($node)->getQemu()->get($vmid)->getStatus()->start();
        
        if (!$result->isSuccessStatusCode()) {
            return [
                'success' => false,
                'error' => "Failed to start VM: HTTP {$result->getStatusCode()}",
                'details' => $result->getReasonPhrase()
            ];
        }
        
        if ($result->responseInError()) {
            return [
                'success' => false,
                'error' => 'Failed to start VM: ' . $result->getError()
            ];
        }
        
        $upid = $result->getResponse()->data;
        echo "Start task initiated: {$upid}\n";
        
        // Wait for completion with timeout
        $timeout = 120; // 2 minutes
        $start = time();
        
        while ($client->taskIsRunning($upid)) {
            if ((time() - $start) > $timeout) {
                return [
                    'success' => false,
                    'error' => 'Task timeout after ' . $timeout . ' seconds',
                    'upid' => $upid
                ];
            }
            sleep(2);
        }
        
        // Check task result
        $exitStatus = $client->getExitStatusTask($upid);
        
        if ($exitStatus === 'OK') {
            return [
                'success' => true,
                'message' => "VM {$vmid} started successfully",
                'upid' => $upid
            ];
        } else {
            return [
                'success' => false,
                'error' => "VM start failed with status: {$exitStatus}",
                'upid' => $upid
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Exception: ' . $e->getMessage()
        ];
    }
}

// Usage
$result = startVmWithErrorHandling($client, 'pve1', 100);
if ($result['success']) {
    echo $result['message'] . "\n";
} else {
    echo "Error: " . $result['error'] . "\n";
    if (isset($result['details'])) {
        echo "Details: " . $result['details'] . "\n";
    }
}
```

## Error Logging

### Structured Error Logging

```php
<?php
class ProxmoxErrorLogger
{
    private $logFile;
    
    public function __construct($logFile = 'proxmox_errors.log')
    {
        $this->logFile = $logFile;
    }
    
    public function logError($operation, $error, $context = [])
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'operation' => $operation,
            'error' => $error,
            'context' => $context
        ];
        
        $logLine = json_encode($logEntry) . "\n";
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    public function logHttpError($operation, $statusCode, $reasonPhrase, $context = [])
    {
        $this->logError($operation, [
            'type' => 'http_error',
            'status_code' => $statusCode,
            'reason' => $reasonPhrase
        ], $context);
    }
    
    public function logApiError($operation, $message, $context = [])
    {
        $this->logError($operation, [
            'type' => 'api_error',
            'message' => $message
        ], $context);
    }
    
    public function logException($operation, Exception $e, $context = [])
    {
        $this->logError($operation, [
            'type' => 'exception',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], $context);
    }
}

// Usage
$logger = new ProxmoxErrorLogger();

try {
    $result = $client->getNodes()->get('pve1')->getQemu()->get(999)->getStatus()->current();
    
    if (!$result->isSuccessStatusCode()) {
        $logger->logHttpError(
            'Get VM Status',
            $result->getStatusCode(),
            $result->getReasonPhrase(),
            ['node' => 'pve1', 'vmid' => 999]
        );
    }
    
} catch (Exception $e) {
    $logger->logException('Get VM Status', $e, ['node' => 'pve1', 'vmid' => 999]);
}
```

## Recovery and Retry Strategies

### Automatic Retry with Exponential Backoff

```php
<?php
class ProxmoxRetryManager
{
    private $client;
    private $maxRetries;
    private $baseDelay;
    
    public function __construct($client, $maxRetries = 3, $baseDelay = 1)
    {
        $this->client = $client;
        $this->maxRetries = $maxRetries;
        $this->baseDelay = $baseDelay;
    }
    
    public function executeWithRetry(callable $operation, $operationName = 'API Call')
    {
        $attempt = 0;
        $lastError = null;
        
        while ($attempt <= $this->maxRetries) {
            try {
                $result = $operation();
                
                if ($result->isSuccessStatusCode() && !$result->responseInError()) {
                    if ($attempt > 0) {
                        echo "Operation '{$operationName}' succeeded on attempt " . ($attempt + 1) . "\n";
                    }
                    return $result;
                }
                
                $lastError = "HTTP {$result->getStatusCode()}: {$result->getReasonPhrase()}";
                if ($result->responseInError()) {
                    $lastError .= " - " . $result->getError();
                }
                
            } catch (Exception $e) {
                $lastError = "Exception: " . $e->getMessage();
                
                // Don't retry on authentication errors
                if ($e instanceof PveExceptionAuthentication) {
                    throw $e;
                }
            }
            
            $attempt++;
            
            if ($attempt <= $this->maxRetries) {
                $delay = $this->baseDelay * pow(2, $attempt - 1); // Exponential backoff
                echo "Operation '{$operationName}' failed (attempt {$attempt}), retrying in {$delay} seconds...\n";
                sleep($delay);
            }
        }
        
        throw new Exception("Operation '{$operationName}' failed after " . ($this->maxRetries + 1) . " attempts. Last error: {$lastError}");
    }
}

// Usage
$retryManager = new ProxmoxRetryManager($client, 3, 1);

try {
    $result = $retryManager->executeWithRetry(function() use ($client) {
        return $client->getNodes()->get('pve1')->getStatus()->current();
    }, 'Get Node Status');
    
    echo "Node status retrieved successfully\n";
    
} catch (Exception $e) {
    echo "Failed to get node status: " . $e->getMessage() . "\n";
}
```

## Best Practices

### 1. Always Check Both HTTP and API Errors

```php
<?php
// Good practice
if ($result->isSuccessStatusCode() && !$result->responseInError()) {
    // Process successful response
    $data = $result->getResponse()->data;
} else {
    // Handle error
    if (!$result->isSuccessStatusCode()) {
        echo "HTTP Error: " . $result->getStatusCode() . "\n";
    }
    if ($result->responseInError()) {
        echo "API Error: " . $result->getError() . "\n";
    }
}
```

### 2. Use Specific Exception Types

```php
<?php
try {
    $client->login('user', 'password', 'pam');
} catch (PveExceptionAuthentication $e) {
    // Handle authentication specifically
    echo "Login failed: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    // Handle other exceptions
    echo "Unexpected error: " . $e->getMessage() . "\n";
}
```

### 3. Implement Graceful Degradation

```php
<?php
function getClusterStatus($client)
{
    try {
        $result = $client->getCluster()->getStatus()->getStatus();
        if ($result->isSuccessStatusCode() && !$result->responseInError()) {
            return ['success' => true, 'data' => $result->getResponse()->data];
        }
    } catch (Exception $e) {
        // Log error but continue
        error_log("Cluster status unavailable: " . $e->getMessage());
    }
    
    // Fallback to node-level information
    try {
        $nodes = $client->getNodes()->index();
        if ($nodes->isSuccessStatusCode() && !$nodes->responseInError()) {
            return ['success' => true, 'data' => $nodes->getResponse()->data, 'fallback' => true];
        }
    } catch (Exception $e) {
        error_log("Node information unavailable: " . $e->getMessage());
    }
    
    return ['success' => false, 'error' => 'Unable to retrieve cluster information'];
}
```

### 4. Validate Inputs Before API Calls

```php
<?php
function validateVmId($vmid)
{
    if (!is_numeric($vmid) || $vmid < 100 || $vmid > 999999999) {
        throw new InvalidArgumentException("Invalid VM ID: {$vmid}. Must be numeric between 100-999999999");
    }
}

function validateNodeName($nodeName)
{
    if (empty($nodeName) || !preg_match('/^[a-zA-Z0-9\-_.]+$/', $nodeName)) {
        throw new InvalidArgumentException("Invalid node name: {$nodeName}");
    }
}

// Usage
try {
    validateNodeName('pve1');
    validateVmId(100);
    
    $result = $client->getNodes()->get('pve1')->getQemu()->get(100)->getStatus()->current();
    // Process result...
    
} catch (InvalidArgumentException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "API error: " . $e->getMessage() . "\n";
}
```