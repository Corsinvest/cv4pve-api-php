# 🔐 Authentication

Login methods and API tokens for cv4pve-api-php library.

## Overview

Proxmox VE supports multiple authentication methods. This library provides support for:

- **Username/Password Authentication** - Traditional login with credentials
- **API Token Authentication** - Secure token-based authentication (Proxmox VE 6.2+)
- **Two-Factor Authentication** - Additional security with OTP codes

## Username/Password Authentication

### Basic Login

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("your-proxmox-host.com");

// Basic login
if ($client->login('root', 'your-password', 'pam')) {
    echo "Authentication successful!\n";
    
    // Use the API
    $version = $client->getVersion()->version();
    echo "Proxmox VE Version: " . $version->getResponse()->data->version . "\n";
} else {
    echo "Authentication failed!\n";
}
```

### Authentication Realms

Proxmox VE supports multiple authentication realms:

```php
<?php
// PAM authentication (Linux system users)
$client->login('root', 'password', 'pam');

// Proxmox VE authentication (built-in users)
$client->login('admin', 'password', 'pve');

// LDAP authentication
$client->login('john.doe', 'password', 'ldap-realm');

// Active Directory authentication
$client->login('john.doe', 'password', 'ad-realm');
```

### Username with Realm

You can specify the realm in the username:

```php
<?php
// These are equivalent:
$client->login('root@pam', 'password');
$client->login('root', 'password', 'pam');

// LDAP user
$client->login('john.doe@company-ldap', 'password');
$client->login('john.doe', 'password', 'company-ldap');
```

## Two-Factor Authentication (2FA)

### TOTP (Time-based One-Time Password)

```php
<?php
// User with TOTP enabled
$totpCode = '123456'; // From authenticator app
if ($client->login('root', 'password', 'pam', $totpCode)) {
    echo "2FA authentication successful!\n";
}
```

### Handling 2FA Requirements

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveExceptionAuthentication;

try {
    // Attempt login without 2FA
    if (!$client->login('root', 'password', 'pam')) {
        echo "Login failed\n";
    }
} catch (PveExceptionAuthentication $e) {
    // Check if 2FA is required
    $result = $e->getResult();
    if (isset($result->getResponse()->data->ticket) && 
        isset($result->getResponse()->data->{'tfa-challenge'})) {
        
        echo "Two-factor authentication required!\n";
        
        // Get TOTP code from user
        echo "Enter TOTP code: ";
        $totpCode = trim(fgets(STDIN));
        
        // Retry with TOTP
        if ($client->login('root', 'password', 'pam', $totpCode)) {
            echo "2FA authentication successful!\n";
        }
    }
}
```

## API Token Authentication

### Creating API Tokens

First, create an API token in Proxmox VE web interface or CLI:

```bash
# Create API token via Proxmox VE CLI
pveum user token add root@pam mytoken --privsep=0
```

### Using API Tokens

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("your-proxmox-host.com");

// Set API token (no login required)
$client->setApiToken("root@pam!mytoken=12345678-1234-1234-1234-123456789abc");

// Use API immediately
$nodes = $client->getNodes()->index();
foreach ($nodes->getResponse()->data as $node) {
    echo "Node: {$node->node} - Status: {$node->status}\n";
}
```

### API Token Format

API tokens follow this format:
```
USER@REALM!TOKENID=UUID
```

Examples:
```php
<?php
// Root user with PAM realm
$client->setApiToken("root@pam!automation=12345678-1234-1234-1234-123456789abc");

// Regular user with PVE realm
$client->setApiToken("admin@pve!backup-script=87654321-4321-4321-4321-cba987654321");

// LDAP user
$client->setApiToken("john.doe@ldap!monitoring=abcdef12-3456-7890-abcd-ef1234567890");
```

### Privilege Separation

API tokens can be created with or without privilege separation:

```php
<?php
// Token without privilege separation (inherits user permissions)
$client->setApiToken("root@pam!full-access=token-uuid");

// Token with privilege separation (requires explicit permissions)
$client->setApiToken("root@pam!limited-access=token-uuid");
```

## Authentication Methods Comparison

| Method | Security | Use Case | Proxmox VE Version |
|--------|----------|----------|-------------------|
| Username/Password | Medium | Interactive applications | All versions |
| Username/Password + 2FA | High | Interactive applications with high security | 4.0+ |
| API Token | High | Automation and scripts | 6.2+ |

## Best Practices

### For Interactive Applications

```php
<?php
function authenticateInteractive($hostname, $username, $password, $realm = 'pam')
{
    $client = new PveClient($hostname);
    
    try {
        if ($client->login($username, $password, $realm)) {
            return $client;
        }
    } catch (PveExceptionAuthentication $e) {
        $result = $e->getResult();
        
        // Check if 2FA is required
        if (isset($result->getResponse()->data->{'tfa-challenge'})) {
            echo "Two-factor authentication required.\n";
            echo "Enter TOTP code: ";
            $totpCode = trim(fgets(STDIN));
            
            if ($client->login($username, $password, $realm, $totpCode)) {
                return $client;
            }
        }
    }
    
    throw new Exception("Authentication failed");
}
```

### For Automation Scripts

```php
<?php
function authenticateAutomation($hostname, $apiToken)
{
    $client = new PveClient($hostname);
    $client->setApiToken($apiToken);
    
    // Test the connection
    try {
        $client->getVersion()->version();
        return $client;
    } catch (Exception $e) {
        throw new Exception("API token authentication failed: " . $e->getMessage());
    }
}

// Usage
$client = authenticateAutomation("proxmox.local", "root@pam!script=token-uuid");
```

### Environment Variable Authentication

```php
<?php
function authenticateFromEnv()
{
    $hostname = $_ENV['PROXMOX_HOST'] ?? 'localhost';
    $client = new PveClient($hostname);
    
    // Try API token first (preferred for automation)
    if (!empty($_ENV['PROXMOX_API_TOKEN'])) {
        $client->setApiToken($_ENV['PROXMOX_API_TOKEN']);
        return $client;
    }
    
    // Fall back to username/password
    $username = $_ENV['PROXMOX_USER'] ?? 'root';
    $password = $_ENV['PROXMOX_PASSWORD'] ?? '';
    $realm = $_ENV['PROXMOX_REALM'] ?? 'pam';
    $otp = $_ENV['PROXMOX_OTP'] ?? null;
    
    if ($client->login($username, $password, $realm, $otp)) {
        return $client;
    }
    
    throw new Exception("Authentication failed");
}
```

## Security Considerations

### API Token Security

1. **Store tokens securely** - Never commit tokens to version control
2. **Use environment variables** - Store tokens in environment variables
3. **Rotate tokens regularly** - Create new tokens and delete old ones
4. **Use privilege separation** - Limit token permissions when possible
5. **Monitor token usage** - Check Proxmox VE logs for token usage

### Password Security

1. **Use strong passwords** - Enforce strong password policies
2. **Enable 2FA** - Use two-factor authentication for interactive access
3. **Limit session duration** - Configure appropriate session timeouts
4. **Monitor login attempts** - Watch for failed authentication attempts

### Example Secure Configuration

```php
<?php
class SecureProxmoxClient
{
    private static function getTokenFromSecureSource()
    {
        // In production, get from secure key management system
        return $_ENV['PROXMOX_API_TOKEN'] ?? null;
    }
    
    public static function create($hostname)
    {
        $client = new PveClient($hostname);
        
        // Always validate SSL certificates in production
        $client->setValidateCertificate(true);
        
        // Use secure authentication
        $token = self::getTokenFromSecureSource();
        if (empty($token)) {
            throw new Exception("No API token available");
        }
        
        $client->setApiToken($token);
        
        // Test authentication
        try {
            $client->getVersion()->version();
        } catch (Exception $e) {
            throw new Exception("Authentication test failed: " . $e->getMessage());
        }
        
        return $client;
    }
}

// Usage
$client = SecureProxmoxClient::create("proxmox.company.com");
```