# Result Handling Guide

Understanding how to work with API responses and the Result class.

## Result Class

Every API call returns a `Result` object:

```php
<?php
class Result
{
    // Response data from Proxmox VE
    public function getResponse() { } // Returns response data as object/array

    // HTTP response information
    public function getStatusCode() { } // Returns HTTP status code (int)
    public function getReasonPhrase() { } // Returns HTTP reason phrase (string)
    public function isSuccessStatusCode() { } // Returns boolean (true if 200)

    // Utility methods
    public function responseInError() { } // Returns boolean
    public function getError() { } // Returns error message if any
    public function getResponseHeaders() { } // Returns raw HTTP response headers
}
```

## Checking Success

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

$result = $client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->vmConfig();

if ($result->isSuccessStatusCode())
{
    // Success - process the data
    echo "VM Name: " . $result->getResponse()->data->name . "\n";
}
else
{
    // Error - handle the failure
    echo "Error: " . $result->getError() . "\n";
    echo "Status: " . $result->getStatusCode() . "\n";
}
```

## Accessing Response Data

### **Object Access**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

$result = $client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->vmConfig();
if ($result->isSuccessStatusCode())
{
    $data = $result->getResponse()->data;
    echo "VM Name: " . $data->name . "\n";
    echo "Memory: " . $data->memory . "\n";
    echo "Cores: " . $data->cores . "\n";
}
```

### **Array Access**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Configure client to return arrays instead of objects
$client = new PveClient("pve.example.com", 8006);
$client->setResultIsObject(false);

$result = $client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->vmConfig();
if ($result->isSuccessStatusCode())
{
    $data = $result->getResponse()->data;
    foreach ($data as $key => $value)
    {
        echo "$key: $value\n";
    }
}
```

### **Object vs Array**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Object access (default)
$client = new PveClient("pve.example.com", 8006);
$client->setResultIsObject(true);

// Now all results are objects
$result = $client->getNodes()->index();
foreach ($result->getResponse()->data as $node) {
    echo $node->node . "\n";  // Object property access
}

// Array access
$client->setResultIsObject(false);
$result = $client->getNodes()->index();
foreach ($result->getResponse()->data as $node) {
    echo $node['node'] . "\n";  // Array key access
}
```

## Error Handling

### **Basic Error Checking**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

$vm = $client->getNodes()->get("pve1")->getQemu()->get(100);
$result = $vm->getStatus()->getStart()->vmStart();

if (!$result->isSuccessStatusCode())
{
    echo "Failed to start VM: " . $result->getError() . "\n";
    echo "HTTP Status: " . $result->getStatusCode() . " - " . $result->getReasonPhrase() . "\n";
}
```

### **Detailed Error Information**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

$vm = $client->getNodes()->get("pve1")->getQemu()->get(100);
// Updating VM configuration through the config endpoint
$vmConfigUpdate = $vm->getConfig()->set([
    'memory' => 999999  // Invalid value to demonstrate error handling
]);
$result = $vmConfigUpdate;

if ($result->responseInError())
{
    echo "Proxmox VE returned an error:\n";
    echo $result->getError() . "\n";
}

if (!$result->isSuccessStatusCode())
{
    echo "HTTP Error: " . $result->getStatusCode() . "\n";

    // Check specific status codes
    switch ($result->getStatusCode())
    {
        case 401:
            echo "Authentication failed\n";
            break;
        case 403:
            echo "Permission denied\n";
            break;
        case 400:
            echo "Bad request: " . $result->getError() . "\n";
            break;
        default:
            echo "HTTP " . $result->getStatusCode() . " - " . $result->getReasonPhrase() . "\n";
            break;
    }
}
```

## Working with Different Response Types

### **List Responses**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

$result = $client->getCluster()->getResources()->resources();
if ($result->isSuccessStatusCode())
{
    foreach ($result->getResponse()->data as $resource)
    {
        echo $resource->type . ": " . $resource->id . "\n";
    }
}

// Filter resources
$filteredResources = array_filter($result->getResponse()->data, function($r) {
    return $r->type == "qemu";
});
foreach ($filteredResources as $vm)
{
    echo "VM: " . $vm->name . " (" . $vm->vmid . ")\n";
}
```

### **Task Responses**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

$vm = $client->getNodes()->get("pve1")->getQemu()->get(100);

// Operations that return task IDs
$result = $vm->getSnapshot()->snapshot("backup-snapshot", "Backup snapshot");
if ($result->isSuccessStatusCode())
{
    $taskId = $result->getResponse()->data;
    echo "Task started: " . $taskId . "\n";

    // Monitor task progress...
}
```

### **Image Responses**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Change response type for charts (if supported)
$client = new PveClient("pve.example.com", 8006);
$client->setResponseType("png");

// Note: Chart operations would be different, and the response type would affect the result
// Typically only certain operations return binary data like PNG

// Switch back to JSON
$client->setResponseType("json");
```

## Best Practices

### **Always Check Success**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Good practice
$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

$vm = $client->getNodes()->get("pve1")->getQemu()->get(100);
$result = $vm->getStatus()->getStart()->vmStart();
if ($result->isSuccessStatusCode())
{
    echo "VM started successfully\n";
}
else
{
    echo "Failed to start VM: " . $result->getError() . "\n";
}

// Don't ignore errors
// Missing error handling example would be bad practice
```

### **Handle Null Values**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

$vm = $client->getNodes()->get("pve1")->getQemu()->get(100);
$result = $vm->getConfig()->vmConfig();
if ($result->isSuccessStatusCode())
{
    $data = $result->getResponse()->data;

    // Safe access
    $vmName = isset($data->name) ? $data->name : "Unnamed VM";
    $description = isset($data->description) ? $data->description : "No description";

    echo "VM: $vmName - $description\n";
}
```

### **Choose Object or Array Consistently**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// Choose one format and stick with it
$client->setResultIsObject(true);

// Now all results are objects
$result = $client->getNodes()->index();
foreach ($result->getResponse()->data as $node) {
    echo $node->node . "\n";  // Consistent object access
}
```