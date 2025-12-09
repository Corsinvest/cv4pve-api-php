# Common Issues and Troubleshooting

This guide addresses common problems, configuration patterns, and solutions when working with the Proxmox VE API.

---

## Authentication Issues

### **Authentication Failure**

Common authentication problems and solutions:

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);

// Wrong: Using incorrect realm
$success = $client->login("user@pam", "password");  // This should work

// Correct: Using specific realm
$success = $client->login("user@pve", "password");

// Using API token (recommended for automation)
$client->setApiToken("user@pve!token-name=uuid-string");

if (!$success && empty($client->getApiToken())) {
    echo "Authentication failed. Check:\n";
    echo "  - Username and password are correct\n";
    echo "  - Realm is specified correctly (@pve, @pam, etc.)\n";
    echo "  - Network connectivity to Proxmox VE host\n";
    echo "  - Correct port (default 8006 for HTTPS, 8006 for HTTP)\n";
}
```

### **Token Authentication**

```php
<?php
// Using API tokens (recommended)
$client = new PveClient("pve.example.com", 8006);
$client->setApiToken("root@pam!automation-token=uuid-here");

// No login() call needed with API tokens
$version = $client->getVersion()->version();
if ($version->isSuccessStatusCode()) {
    echo "Connected using API token\n";
}
```

---

## API Usage Patterns

### **Correct Parameter Usage**

The API methods accept individual parameters, not arrays. Here's the correct usage:

```php
<?php
// Wrong: Passing an array as first parameter to createVm
$result = $client->getNodes()->get("pve1")->getQemu()->createVm([
    'vmid' => 100,
    'name' => 'test-vm',
    'memory' => 2048
]); // This will NOT work as expected

// Correct: Using individual parameters
$result = $client->getNodes()->get("pve1")->getQemu()->createVm(
    vmid: 100,                           // First required parameter: vmid
    name: 'test-vm',                     // Named parameter for clarity
    memory: 2048,                        // Memory in MB
    cores: 2,                            // Number of CPU cores
    sockets: 1,                          // Number of CPU sockets
    cpu: 'host',                         // CPU type
    ostype: 'l26',                       // OS type
    net0: 'model=virtio,bridge=vmbr0',   // Network interface
    scsi0: 'local-lvm:32',               // Storage configuration
    scsihw: 'virtio-scsi-single',        // SCSI hardware
    boot: 'order=scsi0'                  // Boot order
);

// Or using positional parameters (less readable but also valid)
$result = $client->getNodes()->get("pve1")->getQemu()->createVm(
    100,                    // vmid
    null,                   // acpi
    null,                   // affinity
    'enabled=1',           // agent
    // ... intermediate parameters as null if not needed
    name: 'test-vm',         // finally specify name
    memory: 2048,           // memory
    cores: 2                // cores
);
```

### **Working with VMs and Containers**

```php
<?php
// Getting VM information
$vm = $client->getNodes()->get("pve1")->getQemu()->get(100);
$config = $vm->getConfig()->vmConfig();

if ($config->isSuccessStatusCode()) {
    $data = $config->getResponse()->data;
    echo "VM Name: {$data->name}\n";
    echo "Memory: {$data->memory} MB\n";
    echo "Cores: {$data->cores}\n";
}

// Working with container
$ct = $client->getNodes()->get("pve1")->getLxc()->get(101);
$ctConfig = $ct->getConfig()->vmConfig();

if ($ctConfig->isSuccessStatusCode()) {
    $data = $ctConfig->getResponse()->data;
    echo "Container Hostname: {$data->hostname}\n";
    echo "Memory: {$data->memory} MB\n";
}
```

### **Indexed Parameters (Arrays)**

Many VM/CT configuration methods use indexed parameters represented as arrays where the key is the index and the value is the configuration string.

#### Understanding Indexed Parameters

Proxmox VE uses indexed parameters for devices that can have multiple instances. In the PHP API, all indexed parameters are represented as arrays where the key is the device index (0, 1, 2...) and the value is the configuration string.

**Common Parameters:**
- **netN** - Network interfaces
- **scsiN** / **virtioN** / **sataN** / **ideN** - Disk devices
- **ipconfigN** - Cloud-init network configuration
- **hostpciN** / **usbN** - Hardware passthrough
- **mpN** - LXC mount points (containers only)

> **Note:** Proxmox VE supports many other indexed parameters. All use the same array pattern. For a complete list, refer to the [Proxmox VE API Documentation](https://pve.proxmox.com/pve-docs/api-viewer/).

#### Basic Usage

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("root@pam", "password");

// Configure network interfaces
$networks = [
    0 => 'model=virtio,bridge=vmbr0,firewall=1',
    1 => 'model=e1000,bridge=vmbr1'
];

// Configure disks
$disks = [
    0 => 'local-lvm:32,cache=writethrough',
    1 => 'local-lvm:64,iothread=1'
];

$client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->updateVmAsync(
    netN: $networks,
    scsiN: $disks
);
```

---

## Network Configuration (netN)

### Network Interface Syntax

Format: `model=<model>,bridge=<bridge>[,option=value,...]`

### Common Parameters

| Parameter | Description | Example Values |
|-----------|-------------|----------------|
| model | Network card model | virtio, e1000, rtl8139, vmxnet3 |
| bridge | Bridge to connect to | vmbr0, vmbr1 |
| firewall | Enable firewall | 0, 1 |
| link_down | Disconnect interface | 0, 1 |
| macaddr | MAC address | A2:B3:C4:D5:E6:F7 |
| mtu | MTU size | 1500, 9000 |
| queues | Number of queues | 1, 2, 4, 8 |
| rate | Rate limit (MB/s) | 10, 100 |
| tag | VLAN tag | 100, 200 |
| trunks | VLAN trunks | 10;20;30 |

### Examples

```php
<?php
// Basic VirtIO network
$networks = [
    0 => 'model=virtio,bridge=vmbr0'
];

// Network with VLAN and firewall
$networks = [
    0 => 'model=virtio,bridge=vmbr0,tag=100,firewall=1'
];

// Multiple networks with different settings
$networks = [
    0 => 'model=virtio,bridge=vmbr0,firewall=1',
    1 => 'model=e1000,bridge=vmbr1,rate=100',
    2 => 'model=virtio,bridge=vmbr0,tag=200,queues=4'
];
```

---

## Disk Configuration

### Disk Syntax

Format: `<storage>:<size>[,option=value,...]`

Or for existing volumes: `<storage>:<volume>[,option=value,...]`

### Storage Types

- **scsiN** - SCSI disks (0-30), most common, supports all features
- **virtioN** - VirtIO disks (0-15), high performance
- **sataN** - SATA disks (0-5), legacy compatibility
- **ideN** - IDE disks (0-3), legacy, often used for CD-ROM
- **efidisk0** - EFI disk for UEFI boot

### Common Disk Parameters

| Parameter | Description | Example Values |
|-----------|-------------|----------------|
| cache | Cache mode | none, writethrough, writeback, directsync, unsafe |
| discard | Enable TRIM/discard | on, ignore |
| iothread | Enable IO thread | 0, 1 |
| ssd | SSD emulation | 0, 1 |
| backup | Include in backup | 0, 1 |
| replicate | Enable replication | 0, 1 |
| media | Media type | disk, cdrom |
| size | Disk size | 32G, 100G, 1T |

### SCSI Disk Examples

```php
<?php
// Basic SCSI disk - 32GB
$disks = [
    0 => 'local-lvm:32'
];

// SCSI disk with options
$disks = [
    0 => 'local-lvm:32,cache=writethrough,iothread=1,discard=on'
];

// Multiple SCSI disks
$disks = [
    0 => 'local-lvm:32,cache=writethrough,iothread=1',  // OS disk
    1 => 'local-lvm:100,cache=none,iothread=1,discard=on',  // Data disk
    2 => 'local-lvm:200,backup=0'  // Temp disk, no backup
];
```

### VirtIO Disk Examples

```php
<?php
// VirtIO disks for maximum performance
$disks = [
    0 => 'local-lvm:32,cache=writethrough,discard=on',
    1 => 'ceph-storage:100,cache=none,iothread=1'
];
```

### SATA/IDE Examples

```php
<?php
// SATA disk
$sataDisks = [
    0 => 'local-lvm:32'
];

// IDE CD-ROM
$ideDisks = [
    2 => 'local:iso/ubuntu-22.04.iso,media=cdrom'
];
```

### EFI Disk

```php
<?php
// EFI disk for UEFI boot
$efidisk = 'local-lvm:1,efitype=4m,pre-enrolled-keys=0';

$client->getNodes()->get("pve1")->getQemu()->get(100)->getConfig()->updateVmAsync(
    bios: 'ovmf',
    efidisk0: $efidisk
);
```

---

## Cloud-Init Configuration (ipconfigN)

### IP Configuration Syntax

Format: `ip=<address>,gw=<gateway>[,option=value,...]`

### Examples

```php
<?php
// DHCP on all interfaces
$ipconfig = [
    0 => 'ip=dhcp'
];

// Static IP configuration
$ipconfig = [
    0 => 'ip=192.168.1.100/24,gw=192.168.1.1'
];

// Multiple interfaces with different configs
$ipconfig = [
    0 => 'ip=192.168.1.100/24,gw=192.168.1.1',  // Management
    1 => 'ip=10.0.0.100/24',  // Internal network
    2 => 'ip=dhcp'  // External network via DHCP
];

// IPv6 with auto-configuration
$ipconfig = [
    0 => 'ip=192.168.1.100/24,gw=192.168.1.1,ip6=auto'
];
```

---

## Complete Example

### Linux VM with VirtIO and Cloud-Init

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.example.com", 8006);
$client->login("admin@pve", "password");

// VM identifiers
$vmid = 101;
$vmName = 'ubuntu-server';
$node = 'pve1';

// Hardware resources
$memory = 4096;  // 4GB RAM
$cores = 2;
$sockets = 1;

// Configure VirtIO disks
$disks = [
    0 => 'local-lvm:32,cache=writethrough,discard=on'
];

// Configure network interfaces
$networks = [
    0 => 'model=virtio,bridge=vmbr0,firewall=1'
];

// Cloud-init IP configuration
$ipconfig = [
    0 => 'ip=192.168.1.100/24,gw=192.168.1.1'
];

// OS and boot settings
$ostype = 'l26';
$scsihw = 'virtio-scsi-single';
$boot = 'order=virtio0';
$agent = 'enabled=1';

// Cloud-init credentials and network
$ciuser = 'admin';
$cipassword = 'SecurePassword123!';
$sshkeys = 'ssh-rsa AAAAB3NzaC1yc2E...';
$nameserver = '8.8.8.8 8.8.4.4';
$searchdomain = 'example.com';

$result = $client->getNodes()->get($node)->getQemu()->createVm(
    vmid: $vmid,
    name: $vmName,
    memory: $memory,
    cores: $cores,
    sockets: $sockets,
    ostype: $ostype,
    virtioN: $disks,
    netN: $networks,
    ipconfigN: $ipconfig,
    scsihw: $scsihw,
    boot: $boot,
    agent: $agent,
    ciuser: $ciuser,
    cipassword: $cipassword,
    sshkeys: urlencode($sshkeys),
    nameserver: $nameserver,
    searchdomain: $searchdomain
);

if ($result->isSuccessStatusCode()) {
    echo "VM $vmid created successfully!\n";

    // Start the VM
    $startResult = $client->getNodes()->get($node)->getQemu()->get($vmid)->getStatus()->getStart()->vmStart();
    if ($startResult->isSuccessStatusCode()) {
        echo "VM $vmid started successfully!\n";
    }
}
```

---

## Network and Connectivity Issues

### **SSL Certificate Problems**

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("pve.local", 8006);
$client->setValidateCertificate(false);  // Only for development!

// For production with custom certificates
$client->setValidateCertificate(true);
// If you have specific issues with custom certificates, you might need to
// ensure the certificate is trusted by your system
```

### **Timeout Handling**

```php
<?php
// Configure client timeout (in seconds)
$client->setTimeout(300);  // 5 minutes for long operations

// Operations that may take longer
$result = $client->getNodes()->get("pve1")->getQemu()->get(100)->getClone()->cloneVm(
    newid: 101,
    name: 'cloned-vm'
);

// Task operations may take considerable time
// The timeout ensures the client waits appropriately
if ($result->isSuccessStatusCode()) {
    $taskId = $result->getResponse()->data;
    echo "Clone started: $taskId\n";
    
    // Monitor task completion separately
}
```

---

## Error Handling Best Practices

### **Check Response Status**

```php
<?php
// Always check if the API call succeeded
$result = $client->getNodes()->get("pve1")->getQemu()->get(100)->index();

if ($result->isSuccessStatusCode()) {
    // Process successful response
    $data = $result->getResponse()->data;
    echo "VM Status: {$data->status}\n";
} else {
    // Handle error appropriately
    echo "API Error: " . $result->getStatus() . " - " . $result->getReasonPhrase() . "\n";
    echo "Details: " . $result->getError() . "\n";
}
```

### **Specific Error Codes**

```php
<?php
$result = $client->getNodes()->get("pve1")->getQemu()->get(999)->index();

if (!$result->isSuccessStatusCode()) {
    switch ($result->getStatusCode()) {
        case 404:
            echo "VM not found\n";
            break;
        case 403:
            echo "Permission denied - check user permissions\n";
            break;
        case 500:
            echo "Internal server error - check Proxmox VE logs\n";
            break;
        default:
            echo "Other error: " . $result->getStatusCode() . " - " . $result->getReasonPhrase() . "\n";
            break;
    }
}
```

---

## PHP-Specific Issues

### **PHP Version Compatibility**

The library is compatible with PHP 5.5+ but some examples may use newer syntax:

```php
<?php
// For older PHP versions (< 7.0), avoid return type declarations
// The library handles this internally through its Result class

// Valid in all supported PHP versions:
$result = $client->getVersion()->version();
if ($result->isSuccessStatusCode()) {
    $versionData = $result->getResponse()->data;
    echo "Proxmox VE version: " . $versionData->version . "\n";
}
```

### **Large Payload Handling**

When uploading large files or handling large API responses:

```php
<?php
// Increase PHP limits for large operations
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300); // 5 minutes

// For file uploads, make sure PHP settings allow large uploads
// upload_max_filesize, post_max_size, etc.
```

---

## Common Operations Guide

### **VM Creation and Configuration**

```php
<?php
// Step 1: Create a new VM
$createResult = $client->getNodes()->get("pve1")->getQemu()->createVm(
    vmid: 200,
    name: 'my-new-vm',
    memory: 2048,
    cores: 2,
    sockets: 1,
    cpu: 'host',
    ostype: 'l26',
    net0: 'model=virtio,bridge=vmbr0',
    scsi0: 'local-lvm:32',
    scsihw: 'virtio-scsi-single',
    boot: 'order=scsi0',
    onboot: true,
    agent: 'enabled=1'
);

if ($createResult->isSuccessStatusCode()) {
    echo "VM created successfully\n";
    
    // Step 2: Start the VM
    $startResult = $client->getNodes()->get("pve1")->getQemu()->get(200)->getStatus()->getStart()->vmStart();
    if ($startResult->isSuccessStatusCode()) {
        echo "VM started successfully\n";
    }
} else {
    echo "Failed to create VM: " . $createResult->getError() . "\n";
}
```

### **Storage Operations**

```php
<?php
// List available storage
$storageResult = $client->getNodes()->get("pve1")->getStorage()->index();
if ($storageResult->isSuccessStatusCode()) {
    foreach ($storageResult->getResponse()->data as $storage) {
        echo "Storage: {$storage->storage} ({$storage->type}) - ";
        echo "Available: " . formatBytes($storage->avail) . "/" . formatBytes($storage->total) . "\n";
    }
}

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
```

### **Task Monitoring**

```php
<?php
// Many operations return task IDs
// Example: cloning a VM
$cloneResult = $client->getNodes()->get("pve1")->getQemu()->get(100)->getClone()->cloneVm(
    newid: 101,
    name: 'cloned-vm'
);

if ($cloneResult->isSuccessStatusCode()) {
    $taskId = $cloneResult->getResponse()->data;
    echo "Clone task started: $taskId\n";
    
    // Monitor the task until completion
    $timeout = 1800; // 30 minutes
    $start = time();
    
    while ((time() - $start) < $timeout) {
        $taskResult = $client->getNodes()->get("pve1")->getTasks()->get($taskId)->index();
        
        if ($taskResult->isSuccessStatusCode()) {
            $taskData = $taskResult->getResponse()->data;
            
            if ($taskData->status === 'stopped') {
                if (isset($taskData->exitstatus) && $taskData->exitstatus === 'OK') {
                    echo "Task completed successfully\n";
                    break;
                } else {
                    echo "Task failed with status: " . $taskData->exitstatus . "\n";
                    break;
                }
            }
        }
        
        sleep(5); // Check every 5 seconds
    }
}
```

---

## Environment Configuration

### **Using Environment Variables**

```php
<?php
// Store credentials securely using environment variables
$host = getenv('PROXMOX_HOST') ?: 'localhost';
$port = (int)(getenv('PROXMOX_PORT') ?: '8006');
$user = getenv('PROXMOX_USER') ?: 'root@pam';
$pass = getenv('PROXMOX_PASS');

if (empty($pass)) {
    die("PROXMOX_PASS environment variable must be set\n");
}

$client = new PveClient($host, $port);
$success = $client->login($user, $pass);

if (!$success) {
    die("Failed to authenticate with provided credentials\n");
}
```

### **Certificate Validation**

```php
<?php
$client = new PveClient("pve.example.com", 8006);

// For production environments with valid certificates
$client->setValidateCertificate(true);

// For development with self-signed certificates
$client->setValidateCertificate(false);

// The certificate validation affects SSL connections
$version = $client->getVersion()->version();
if ($version->isSuccessStatusCode()) {
    echo "Secure connection established\n";
} else {
    if (!$client->isValidateCertificate()) {
        echo "Consider using valid SSL certificates for production\n";
    }
}
```

---