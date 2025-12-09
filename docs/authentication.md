# Authentication Guide

This guide covers all authentication methods available for connecting to Proxmox VE.

## Authentication Methods

### **API Token (Recommended)**

API tokens are the most secure method for automation and applications.

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// Set API token (no login() call needed)
$client->setApiToken("user@realm!tokenid=uuid");

// Ready to use
$version = $client->getVersion()->version();
```

**Format:** `USER@REALM!TOKENID=UUID`

**Example:** `automation@pve!api-token=12345678-1234-1234-1234-123456789abc`

### **Username/Password**

Traditional authentication with username and password.

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// Basic login
$success = $client->login("root", "password");

// Login with specific realm
$success = $client->login("admin@pve", "password");

// Login with PAM realm (default)
$success = $client->login("user@pam", "password");
```

### **Two-Factor Authentication (2FA)**

For accounts with Two-Factor Authentication enabled.

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// Login with TOTP/OTP code
$success = $client->login("admin@pve", "password", "pam", "123456");

// The fourth parameter is the 6-digit code from your authenticator app
```

---

## Creating API Tokens

### **Via Proxmox VE Web Interface**

1. **Login** to Proxmox VE web interface
2. **Navigate** to Datacenter Permissions API Tokens
3. **Click** "Add" button
4. **Configure** token:
   - **User:** Select user (e.g., `root@pam`)
   - **Token ID:** Choose name (e.g., `api-automation`)
   - **Privilege Separation:** Uncheck for full user permissions
   - **Comment:** Optional description
5. **Click** "Add" and **copy the token** (you won't see it again!)

### **Via Command Line**

```bash
# Create API token
pveum user token add root@pam api-automation --privsep=0

# List tokens
pveum user token list root@pam

# Remove token
pveum user token remove root@pam api-automation
```

### **Example Token Creation**

```bash
# Create token for automation user
pveum user add automation@pve --password "secure-password"
pveum user token add automation@pve api-token --privsep=0 --comment "API automation"

# Grant necessary permissions
pveum aclmod / -user automation@pve -role Administrator
```

---

## Security Best Practices

### **DO's**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Use API tokens for automation
$client = new PveClient("pve.company.com", 8006);
$client->setApiToken(getenv("PROXMOX_API_TOKEN"));

// Store credentials securely
$username = getenv("PROXMOX_USER");
$password = getenv("PROXMOX_PASS");

// Enable SSL validation in production
$client->setValidateCertificate(true);

// Use specific user accounts (not root)
$client->login("automation@pve", $password);
```

### **DON'Ts**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Don't hardcode credentials
$client = new PveClient("pve.example.com", 8006);
$client->login("root", "password123"); // Bad!

// Don't disable SSL validation in production
$client->setValidateCertificate(false); // Only for development!

// Don't use overly permissive tokens
// Create tokens with minimal required permissions
```

---

## Permission Management

### **Creating Dedicated Users**

```bash
# Create user for API access
pveum user add api-user@pve --password "secure-password" --comment "API automation user"

# Create custom role with specific permissions
pveum role add ApiUser -privs "VM.Audit,VM.Config.Disk,VM.Config.Memory,VM.PowerMgmt,VM.Snapshot"

# Assign role to user
pveum aclmod / -user api-user@pve -role ApiUser
```

### **Common Permission Sets**

```bash
# Read-only access
pveum role add ReadOnly -privs "VM.Audit,Datastore.Audit,Sys.Audit"

# VM management
pveum role add VMManager -privs "VM.Audit,VM.Config.Disk,VM.Config.Memory,VM.PowerMgmt,VM.Snapshot,VM.Clone"

# Full administrator (use with caution)
pveum aclmod / -user user@pve -role Administrator
```

---

## Environment Configuration

### **Environment Variables**

```bash
# Set environment variables
export PROXMOX_HOST="pve.example.com"
export PROXMOX_API_TOKEN="user@pve!token=uuid"

# Or for username/password
export PROXMOX_USER="admin@pve"
export PROXMOX_PASS="secure-password"
```

### **Application Configuration**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Load from environment variables
$host = getenv("PROXMOX_HOST") ?: "pve.example.com";
$client = new PveClient($host, 8006);

// Use API token if available
$apiToken = getenv("PROXMOX_API_TOKEN");
if (!empty($apiToken)) {
    $client->setApiToken($apiToken);
} else {
    // Fallback to username/password
    $username = getenv("PROXMOX_USER");
    $password = getenv("PROXMOX_PASS");
    $client->login($username, $password);
}
```

### **Configuration File Example**

```json
{
  "Proxmox": {
    "Host": "pve.example.com",
    "ApiToken": "user@pve!token=uuid",
    "ValidateCertificate": true,
    "Timeout": 120
  }
}
```

---

## Troubleshooting Authentication

### **Common Issues**

#### **"Authentication Failed"**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Check credentials
try {
    $client = new PveClient("pve.example.com", 8006);
    $success = $client->login("user@pam", "password");
    if (!$success) {
        echo "Invalid credentials\n";
    }
} catch (Exception $ex) {
    echo "Login error: " . $ex->getMessage() . "\n";
}
```

#### **"Permission Denied"**
```bash
# Check user permissions
pveum user list
pveum aclmod / -user user@pve -role Administrator
```

#### **"Invalid API Token"**
```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Verify token format
$client = new PveClient("pve.example.com", 8006);
$client->setApiToken("user@realm!tokenid=uuid"); // Correct format

// Check if token exists
// Token format: USER@REALM!TOKENID=SECRET
```

### **Testing Authentication**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

function testAuthentication($client)
{
    try {
        $version = $client->getVersion()->version();
        if ($version->isSuccessStatusCode()) {
            echo "Authentication successful\n";
            echo "Connected to Proxmox VE " . $version->getResponse()->data->version . "\n";
            return true;
        } else {
            echo "Authentication failed: " . $version->getReasonPhrase() . "\n";
            return false;
        }
    } catch (Exception $ex) {
        echo "Connection error: " . $ex->getMessage() . "\n";
        return false;
    }
}
```

---

## Authentication Examples

### **Enterprise Setup**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Corporate environment with custom certificates
$client = new PveClient("pve.company.com", 8006);
$client->setValidateCertificate(true);
$client->setTimeout(300); // 5 minutes

$client->setApiToken(getenv("PROXMOX_API_TOKEN"));
```

### **Home Lab Setup**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Simple home lab setup
$client = new PveClient("192.168.1.100", 8006);
$client->setValidateCertificate(false); // Self-signed cert
$client->setTimeout(120); // 2 minutes

$client->login("root@pam", getenv("PVE_PASSWORD"));
```

### **Cloud/Automation Setup**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

// Automated deployment script
$client = new PveClient(getenv("PROXMOX_HOST"), 8006);
$client->setValidateCertificate(true);

// Use API token for automation
$client->setApiToken(getenv("PROXMOX_API_TOKEN"));

// Verify connection before proceeding
if (!testAuthentication($client)) {
    exit(1);
}
```