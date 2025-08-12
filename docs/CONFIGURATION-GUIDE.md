# 🛠️ Configuration Guide

Setup and customization options for cv4pve-api-php library.

## Basic Configuration

### Client Initialization

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Basic initialization
$client = new PveClient("proxmox-server.local");

// Custom port
$client = new PveClient("proxmox-server.local", 8007);

// IP address
$client = new PveClient("192.168.1.100");
```

## Response Configuration

### Response Format

Control whether responses are returned as objects or arrays:

```php
<?php
// Return responses as objects (default)
$client->setResultIsObject(true);
$result = $client->getVersion()->version();
echo $result->getResponse()->data->version; // Object notation

// Return responses as arrays
$client->setResultIsObject(false);
$result = $client->getVersion()->version();
echo $result->getResponse()['data']['version']; // Array notation

// Check current setting
$isObject = $client->isResultObject(); // Returns boolean
```

### Response Type

Set the expected response format from the API:

```php
<?php
// JSON responses (default)
$client->setResponseType('json');
$version = $client->get('/version');

// PNG images for RRD graphs
$client->setResponseType('png');
$graph = $client->getNodes()->get('pve1')->getRrd()->rrd('cpu', 'day');
echo "<img src='{$graph->getResponse()}' alt='CPU Graph' />";

// Reset to JSON
$client->setResponseType('json');

// Get current response type
$type = $client->getResponseType(); // Returns 'json' or 'png'
```

## Connection Configuration

### Timeout Settings

Configure connection and request timeouts:

```php
<?php
// Set timeout to 30 seconds (30000 milliseconds)
$client->setTimeout(30000);

// For long-running operations
$client->setTimeout(300000); // 5 minutes

// For quick operations
$client->setTimeout(5000);   // 5 seconds

// Chain with API calls
$result = $client->setTimeout(10000)->get('/version');

// Get current timeout
$timeout = $client->getTimeout(); // Returns timeout in milliseconds
```

### SSL Certificate Validation

Control SSL certificate validation for development environments:

```php
<?php
// Disable certificate validation (development only)
$client->setValidateCertificate(false);

// Enable certificate validation (production - default)
$client->setValidateCertificate(true);

// Check current setting
$validateCert = $client->getValidateCertificate(); // Returns boolean
```

## Debug Configuration

### Debug Levels

Enable debug output for troubleshooting:

```php
<?php
// No debug output (default)
$client->setDebugLevel(0);

// Basic debug information
$client->setDebugLevel(1);

// Verbose HTTP debug output
$client->setDebugLevel(2);

// Get current debug level
$debugLevel = $client->getDebugLevel(); // Returns 0, 1, or 2
```

### Debug Output Example

With debug level 2, you'll see detailed HTTP information:

```
> GET /api2/json/version HTTP/1.1
> Host: proxmox-server.local:8006
> Accept: application/json
> Cookie: PVEAuthCookie=...

< HTTP/1.1 200 OK
< Content-Type: application/json
< Content-Length: 85
< 
{"data":{"version":"7.2","release":"11","repoid":"..."}}
```

## Environment-Specific Configuration

### Development Environment

```php
<?php
$client = new PveClient("dev-proxmox.local");

// Relaxed settings for development
$client->setValidateCertificate(false);  // Self-signed certificates
$client->setDebugLevel(2);               // Verbose debugging
$client->setTimeout(60000);              // Longer timeout for debugging
$client->setResultIsObject(true);       // Objects for easier debugging
```

### Production Environment

```php
<?php
$client = new PveClient("prod-proxmox.company.com");

// Secure settings for production
$client->setValidateCertificate(true);   // Validate certificates
$client->setDebugLevel(0);               // No debug output
$client->setTimeout(30000);              // Reasonable timeout
$client->setResultIsObject(true);       // Consistent object responses
```

### Testing Environment

```php
<?php
$client = new PveClient("test-proxmox.local");

// Balanced settings for testing
$client->setValidateCertificate(false);  // May use self-signed certs
$client->setDebugLevel(1);               // Basic debug info
$client->setTimeout(45000);              // Slightly longer timeout
$client->setResultIsObject(false);      // Arrays for assertions
```

## Advanced Configuration

### Configuration Helper Class

Create a configuration helper for consistent settings:

```php
<?php
class ProxmoxConfig
{
    public static function development($hostname)
    {
        $client = new PveClient($hostname);
        $client->setValidateCertificate(false);
        $client->setDebugLevel(2);
        $client->setTimeout(60000);
        return $client;
    }
    
    public static function production($hostname)
    {
        $client = new PveClient($hostname);
        $client->setValidateCertificate(true);
        $client->setDebugLevel(0);
        $client->setTimeout(30000);
        return $client;
    }
}

// Usage
$client = ProxmoxConfig::development("dev-proxmox.local");
```

### Configuration from Environment Variables

```php
<?php
$hostname = $_ENV['PROXMOX_HOST'] ?? 'localhost';
$port = (int)($_ENV['PROXMOX_PORT'] ?? 8006);
$timeout = (int)($_ENV['PROXMOX_TIMEOUT'] ?? 30000);
$debug = (int)($_ENV['PROXMOX_DEBUG'] ?? 0);
$validateCert = filter_var($_ENV['PROXMOX_VALIDATE_CERT'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

$client = new PveClient($hostname, $port);
$client->setTimeout($timeout);
$client->setDebugLevel($debug);
$client->setValidateCertificate($validateCert);
```

### Configuration Validation

```php
<?php
function validateConfiguration(PveClient $client)
{
    $hostname = $client->getHostname();
    $port = $client->getPort();
    $timeout = $client->getTimeout();
    
    if (empty($hostname)) {
        throw new InvalidArgumentException("Hostname cannot be empty");
    }
    
    if ($port < 1 || $port > 65535) {
        throw new InvalidArgumentException("Port must be between 1 and 65535");
    }
    
    if ($timeout < 1000) {
        throw new InvalidArgumentException("Timeout should be at least 1000ms");
    }
    
    return true;
}
```

## Configuration Summary

### Available Methods

| Method | Parameters | Description |
|--------|------------|-------------|
| `setTimeout($timeout)` | int | Set timeout in milliseconds |
| `setResultIsObject($bool)` | bool | Set response format (object/array) |
| `setResponseType($type)` | string | Set response type ('json'/'png') |
| `setDebugLevel($level)` | int | Set debug level (0-2) |
| `setValidateCertificate($bool)` | bool | Enable/disable SSL validation |
| `getTimeout()` | - | Get current timeout |
| `isResultObject()` | - | Check if returning objects |
| `getResponseType()` | - | Get current response type |
| `getDebugLevel()` | - | Get current debug level |
| `getValidateCertificate()` | - | Check SSL validation status |
| `getHostname()` | - | Get configured hostname |
| `getPort()` | - | Get configured port |
| `getApiUrl()` | - | Get full API URL |

### Best Practices

1. **Always validate certificates in production**
2. **Use appropriate timeouts for your use case**
3. **Enable debugging only during development**
4. **Use objects for cleaner code, arrays for performance-critical operations**
5. **Store configuration in environment variables**
6. **Validate configuration before using the client**