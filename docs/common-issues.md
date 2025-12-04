# Common Issues and Examples

This guide provides practical examples for common operations using cv4pve-api-php with Proxmox VE, focusing on how to properly pass array parameters for disks, network interfaces, and other VM configurations.

## Table of Contents

- [Array Parameters (Disks, Network, etc.)](#array-parameters-disks-network-etc)
- [Disk Configuration Syntax](#disk-configuration-syntax)
- [Creating VMs with Disks and Network](#creating-vms-with-disks-and-network)

---

## Array Parameters (Disks, Network, etc.)

In PHP, when configuring VMs with multiple devices like network interfaces, disks, or PCI devices, you pass parameters as **PHP array** with **comma-separated string values**.

### Key Concepts

- **Parameter names** use suffix numbers: `net0`, `net1`, `scsi0`, `scsi1`, `virtio0`, `ide2`, etc.
- **Configuration values** are comma-separated strings: `'key=value,key=value'`
- **No spaces** around commas in configuration strings
- Pass all parameters as a **PHP associative array**

### Two Ways to Create VMs

There are two approaches to create VMs with this library:

#### 1. Direct API Call (Recommended for Complex Configurations)

Use `$client->create()` with an array of parameters. This is the simplest approach for VMs with multiple disks, networks, etc.

```php
<?php
$params = [
    'vmid' => 100,
    'name' => 'my-vm',
    'memory' => 2048,
    'cores' => 2,
    'net0' => 'virtio,bridge=vmbr0,firewall=1',
    'net1' => 'virtio,bridge=vmbr1,firewall=1',
    'scsi0' => 'local-lvm:100',
    'scsi1' => 'local-lvm:200'
];
$result = $client->create('/nodes/pve/qemu', $params);
```

#### 2. Fluent API (Advanced Usage)

Use the fluent API method `createVm()` - this method accepts **arrays** for indexed parameters like `$netN`, `$scsiN`, etc.:

```php
<?php
// Creates VM with multiple network interfaces and disks
$result = $client->getNodes()
    ->get('pve')
    ->getQemu()
    ->createVm(
        vmid: 100,
        name: 'my-vm',
        memory: 2048,
        cores: 2,
        netN: [
            0 => 'virtio,bridge=vmbr0,firewall=1',  // net0
            1 => 'virtio,bridge=vmbr1,firewall=1'   // net1
        ],
        scsiN: [
            0 => 'local-lvm:100',  // scsi0
            1 => 'local-lvm:200'   // scsi1
        ]
    );
```

**Note:** This approach requires PHP 8.0+ for named parameters. For PHP 5.5-7.x compatibility, use the direct API call approach (#1).

### Network Configuration

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("proxmox.example.com");
$client->login('root@pam', 'password');

// Single network interface using array parameters
$params = [
    'vmid' => 100,
    'name' => 'test-vm',
    'memory' => 2048,
    'cores' => 2,
    'net0' => 'virtio,bridge=vmbr0,firewall=1'
];
$result = $client->create('/nodes/pve/qemu', $params);

// Multiple network interfaces
$params = [
    'vmid' => 101,
    'name' => 'multi-net-vm',
    'memory' => 2048,
    'cores' => 2,
    'net0' => 'virtio,bridge=vmbr0,firewall=1',
    'net1' => 'virtio,bridge=vmbr1,firewall=1'
];
$result = $client->create('/nodes/pve/qemu', $params);
```

### SCSI Disk Configuration

```php
<?php
// Single SCSI disk (100GB on local-lvm storage)
$params = [
    'vmid' => 100,
    'name' => 'scsi-vm',
    'memory' => 2048,
    'scsi0' => 'local-lvm:100'
];
$result = $client->create('/nodes/pve/qemu', $params);

// Multiple SCSI disks with performance options
$params = [
    'vmid' => 100,
    'name' => 'multi-disk-vm',
    'memory' => 4096,
    'scsi0' => 'local-lvm:100,cache=writethrough,iothread=1',
    'scsi1' => 'local-lvm:50,cache=writeback,discard=on'
];
$result = $client->create('/nodes/pve/qemu', $params);
```

### SATA Disk Configuration

```php
<?php
$params = [
    'vmid' => 100,
    'name' => 'sata-vm',
    'memory' => 2048,
    'sata0' => 'local-lvm:100',
    'sata1' => 'local-lvm:50'
];
$result = $client->create('/nodes/pve/qemu', $params);
```

### IDE Configuration (CD-ROM)

```php
<?php
// IDE disk for CD-ROM (typically ide2)
$params = [
    'vmid' => 100,
    'name' => 'vm-with-iso',
    'memory' => 2048,
    'ide2' => 'local:iso/ubuntu-22.04.iso,media=cdrom'
];
$result = $client->create('/nodes/pve/qemu', $params);
```

### VirtIO Disk Configuration

```php
<?php
// VirtIO disks (best performance for Linux VMs)
$params = [
    'vmid' => 100,
    'name' => 'linux-vm',
    'memory' => 2048,
    'virtio0' => 'local-lvm:100,cache=writeback,discard=on',
    'virtio1' => 'local-lvm:200,cache=writeback,discard=on'
];
$result = $client->create('/nodes/pve/qemu', $params);
```

### LXC Container Network

```php
<?php
// LXC with DHCP
$params = [
    'vmid' => 200,
    'hostname' => 'container-dhcp',
    'ostemplate' => 'local:vztmpl/ubuntu-22.04-standard_amd64.tar.zst',
    'storage' => 'local-lvm',
    'memory' => 1024,
    'net0' => 'name=eth0,bridge=vmbr0,ip=dhcp'
];
$result = $client->create('/nodes/pve/lxc', $params);

// LXC with static IP
$params = [
    'vmid' => 201,
    'hostname' => 'container-static',
    'ostemplate' => 'local:vztmpl/ubuntu-22.04-standard_amd64.tar.zst',
    'storage' => 'local-lvm',
    'memory' => 1024,
    'net0' => 'name=eth0,bridge=vmbr0,ip=192.168.1.100/24,gw=192.168.1.1'
];
$result = $client->create('/nodes/pve/lxc', $params);
```

---

## Disk Configuration Syntax

Disk parameters use the format: `STORAGE:SIZE[,option=value,...]`

### Basic Disk Syntax

```php
<?php
// Format: "storage:size_in_gb"
'scsi0' => 'local-lvm:100'      // 100GB disk
'virtio0' => 'ceph-storage:50'  // 50GB on ceph
```

### EFI Disk Configuration

```php
<?php
// EFI disk for UEFI boot (typically 4M)
$params = [
    'vmid' => 100,
    'bios' => 'ovmf',
    'efidisk0' => 'local-lvm:4,efitype=4m'
];
$result = $client->create('/nodes/pve/qemu', $params);

// Alternative: pre-TPM2 format
$params = [
    'efidisk0' => 'local-lvm:1,format=raw,efitype=4m,pre-enrolled-keys=1'
];
```

### Disk with Cache and Performance Options

```php
<?php
// SCSI disk with cache mode
$params = [
    'scsi0' => 'local-lvm:100,cache=writethrough,ssd=1,discard=on'
];

// VirtIO with backup disabled
$params = [
    'virtio0' => 'local-lvm:100,backup=0,cache=writeback'
];

// Multiple options
$params = [
    'scsi0' => 'local-lvm:100,cache=writethrough,iothread=1,ssd=1,discard=on,backup=1'
];
```

### Import Existing Disk

```php
<?php
// Import disk from existing image
$params = [
    'scsi0' => 'local-lvm:0,import-from=/mnt/pve/nfs/images/100/vm-100-disk-0.raw'
];
$result = $client->set('/nodes/pve/qemu/100/config', $params);
```

### Common Disk Options

| Option | Values | Description |
|--------|--------|-------------|
| `cache` | `none`, `writethrough`, `writeback`, `directsync`, `unsafe` | Cache mode |
| `ssd` | `0`, `1` | Emulate SSD |
| `discard` | `on`, `ignore` | Enable TRIM/discard |
| `iothread` | `0`, `1` | Enable IO thread (SCSI/VirtIO only) |
| `backup` | `0`, `1` | Include in backup |
| `replicate` | `0`, `1` | Enable replication |
| `size` | `100G`, `1T` | Disk size |

---

## Creating VMs with Disks and Network

Complete examples for creating VMs with proper configuration in PHP.

### Example 1: Windows VM with EFI, Disks, and Network

```php
<?php
use Corsinvest\ProxmoxVE\Api\PveClient;

$client = new PveClient("proxmox.example.com");
$client->login('root@pam', 'password');

// Create Windows VM with EFI boot
$params = [
    'vmid' => 100,
    'name' => 'windows-server-2022',
    'memory' => 4096,
    'cores' => 4,
    'sockets' => 1,
    'cpu' => 'host',
    'bios' => 'ovmf',
    'machine' => 'q35',
    'ostype' => 'win11',
    'scsihw' => 'virtio-scsi-pci',

    // EFI disk
    'efidisk0' => 'local-lvm:4,efitype=4m,pre-enrolled-keys=1',

    // OS disk (100GB)
    'scsi0' => 'local-lvm:100,cache=writeback,discard=on',

    // Data disk (200GB)
    'scsi1' => 'local-lvm:200,cache=writeback,discard=on',

    // Network interface
    'net0' => 'virtio,bridge=vmbr0,firewall=1',

    // CD-ROM with Windows ISO
    'ide2' => 'local:iso/windows-server-2022.iso,media=cdrom',

    // Display
    'vga' => 'qxl',
    'balloon' => 2048
];

$result = $client->create('/nodes/pve/qemu', $params);

if ($result->isSuccessStatusCode()) {
    echo "VM {$params['vmid']} created successfully\n";

    // Start VM
    $startResult = $client->create("/nodes/pve/qemu/{$params['vmid']}/status/start");

    if ($startResult->isSuccessStatusCode()) {
        echo "VM started\n";
    }
} else {
    echo "Error: " . $result->getReasonPhrase() . "\n";
}
```

### Example 2: Linux VM with VirtIO Disks

```php
<?php
$client = new PveClient("proxmox.example.com");
$client->login('root@pam', 'password');

// Create Ubuntu VM with VirtIO (best performance)
$params = [
    'vmid' => 101,
    'name' => 'ubuntu-server',
    'memory' => 2048,
    'cores' => 2,
    'cpu' => 'host',
    'ostype' => 'l26',
    'scsihw' => 'virtio-scsi-pci',

    // VirtIO disks
    'virtio0' => 'local-lvm:50,cache=writeback,discard=on',  // OS
    'virtio1' => 'local-lvm:100,cache=writeback,discard=on', // Data

    // Network
    'net0' => 'virtio,bridge=vmbr0',

    // CD-ROM
    'ide2' => 'local:iso/ubuntu-22.04-server.iso,media=cdrom',

    // Display
    'vga' => 'virtio',
    'agent' => 'enabled=1'
];

$result = $client->create('/nodes/pve/qemu', $params);

if ($result->isSuccessStatusCode()) {
    echo "Linux VM created successfully\n";
}
```

### Example 3: LXC Container with Network

```php
<?php
$client = new PveClient("proxmox.example.com");
$client->login('root@pam', 'password');

// Create LXC container with static IP
$params = [
    'vmid' => 200,
    'hostname' => 'ubuntu-container',
    'ostemplate' => 'local:vztmpl/ubuntu-22.04-standard_amd64.tar.zst',
    'storage' => 'local-lvm',
    'memory' => 1024,
    'swap' => 512,
    'cores' => 2,
    'rootfs' => 'local-lvm:8',

    // Network with static IP
    'net0' => 'name=eth0,bridge=vmbr0,ip=192.168.1.100/24,gw=192.168.1.1',

    // DNS
    'nameserver' => '8.8.8.8',
    'searchdomain' => 'example.com',

    // Features
    'features' => 'nesting=1',
    'unprivileged' => 1,
    'start' => 1
];

$result = $client->create('/nodes/pve/lxc', $params);

if ($result->isSuccessStatusCode()) {
    echo "Container {$params['vmid']} created and started\n";
} else {
    echo "Error: " . $result->getReasonPhrase() . "\n";
}
```

### Example 4: VM Template Creation

```php
<?php
$client = new PveClient("proxmox.example.com");
$client->login('root@pam', 'password');

// 1. Create base VM
$params = [
    'vmid' => 9000,
    'name' => 'ubuntu-template',
    'memory' => 2048,
    'cores' => 2,
    'cpu' => 'host',
    'ostype' => 'l26',
    'scsihw' => 'virtio-scsi-pci',
    'virtio0' => 'local-lvm:32,cache=writeback,discard=on',
    'net0' => 'virtio,bridge=vmbr0',
    'agent' => 'enabled=1',
    'serial0' => 'socket',
    'vga' => 'serial0'
];

$result = $client->create('/nodes/pve/qemu', $params);

if ($result->isSuccessStatusCode()) {
    echo "Base VM created\n";

    // 2. Configure and install OS (manual or with cloud-init)
    // ... installation steps ...

    // 3. Convert to template
    $templateResult = $client->create('/nodes/pve/qemu/9000/template');

    if ($templateResult->isSuccessStatusCode()) {
        echo "VM converted to template\n";
    }
}
```

### Example 5: Clone VM from Template

```php
<?php
$client = new PveClient("proxmox.example.com");
$client->login('root@pam', 'password');

// Clone from template
$params = [
    'newid' => 102,
    'name' => 'webserver-01',
    'full' => 1,  // Full clone (not linked)
    'target' => 'pve',  // Target node
    'storage' => 'local-lvm'  // Storage for cloned disks
];

$result = $client->create('/nodes/pve/qemu/9000/clone', $params);

if ($result->isSuccessStatusCode()) {
    $taskId = $result->getResponse()->data;
    echo "Cloning started, task: $taskId\n";

    // Wait for clone to complete
    $client->waitForTaskToFinish($taskId);

    echo "VM cloned successfully\n";

    // Customize cloned VM
    $customParams = [
        'memory' => 4096,
        'cores' => 4,
        'ipconfig0' => 'ip=192.168.1.102/24,gw=192.168.1.1'
    ];

    $client->set('/nodes/pve/qemu/102/config', $customParams);

    // Start VM
    $client->create('/nodes/pve/qemu/102/status/start');
}
```

