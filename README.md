```text
   ______                _                      __
  / ____/___  __________(_)___ _   _____  _____/ /_
 / /   / __ \/ ___/ ___/ / __ \ | / / _ \/ ___/ __/
/ /___/ /_/ / /  (__  ) / / / / |/ /  __(__  ) /_
\____/\____/_/  /____/_/_/ /_/|___/\___/____/\__/

Proxmox VE API Client for PHP (Made in Italy)
```

[![License](https://img.shields.io/github/license/Corsinvest/cv4pve-api-php.svg?style=flat-square)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/corsinvest/cv4pve-api-php.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/corsinvest/cv4pve-api-php)
[![Packagist Downloads](https://img.shields.io/packagist/dt/corsinvest/cv4pve-api-php?style=flat-square&logo=packagist)](https://packagist.org/packages/corsinvest/cv4pve-api-php)
[![PHP Version](https://img.shields.io/packagist/php-v/corsinvest/cv4pve-api-php.svg?style=flat-square&logo=php)](https://packagist.org/packages/corsinvest/cv4pve-api-php)

---

## Quick Start

### Installation

```bash
composer require corsinvest/cv4pve-api-php
```

### Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

use Corsinvest\ProxmoxVE\Api\PveClient;

// Create client instance
$client = new PveClient("your-proxmox-host.com");

// Authenticate
if ($client->login('root@pam', 'password')) {
    // Get cluster status
    $status = $client->getCluster()->status();
    echo "Cluster: {$status->getResponse()->data[0]->name}\n";

    // List all VMs
    $vms = $client->getNodes()->get("pve1")->getQemu()->vmlist();
    foreach ($vms->getResponse()->data as $vm) {
        echo "VM {$vm->vmid}: {$vm->name} - {$vm->status}\n";
    }
}
```

---

## Key Features

### Developer Experience

- **Intuitive API Structure** - Mirrors Proxmox VE API hierarchy with fluent interface
- **Tree-Based Navigation** - Natural method chaining matching API paths
- **Flexible Response Handling** - Choose between object or array response formats
- **Comprehensive Documentation** - Detailed guides and practical examples
- **Easy Integration** - Simple Composer installation with minimal dependencies

### Core Functionality

- **Complete API Coverage** - Full implementation of Proxmox VE REST API endpoints
- **VM & Container Management** - Create, configure, start, stop, and monitor virtual machines and containers
- **Cluster Operations** - Monitor cluster status, resources, and health
- **Storage Management** - Handle storage pools, volumes, and content
- **Network Configuration** - Manage network interfaces and firewall rules

### Enterprise Ready

- **Multiple Authentication Methods** - Username/password, API tokens, and two-factor authentication
- **API Token Support** - Secure token-based authentication for automation
- **SSL Certificate Validation** - Configurable certificate verification for production environments
- **Task Management** - Monitor and wait for long-running async operations
- **Timeout Configuration** - Customizable connection and request timeouts

### Advanced Features

- **Result Object Pattern** - Structured response handling with status codes and error checking
- **Event Callbacks** - Hook into API actions for logging and monitoring
- **Zero Dependencies** - Lightweight design using only native PHP cURL
- **PHP 5.5+ Compatible** - Wide compatibility with modern and legacy environments
- **Cross-Platform** - Works on Windows, Linux, and macOS

---

## Documentation

### Getting Started

- **[Authentication](./docs/authentication.md)** - Login methods, API tokens, and security best practices
- **[Basic Examples](./docs/examples.md)** - Common operations and usage patterns
- **[Advanced Usage](./docs/advanced.md)** - Production configurations and enterprise patterns
- **[Common Issues](./docs/common-issues.md)** - Troubleshooting and configuration tips

### API Reference

- **[API Structure](./docs/apistructure.md)** - Understanding the tree-based API navigation
- **[Result Handling](./docs/results.md)** - Working with responses and data
- **[Error Handling](./docs/errorhandling.md)** - Exception management and error patterns
- **[Task Management](./docs/tasks.md)** - Monitoring async operations

---

## Examples

### VM Management

```php
<?php
// Create and configure a new VM with individual parameters (PHP 8.1+)

// Using named arguments (PHP 8.0+) for clarity
$result = $client->getNodes()->get("pve1")->getQemu()->createVm(
    vmid: 100,
    name: 'production-web-server',
    memory: 4096,
    cores: 4,
    net0: 'virtio,bridge=vmbr0',
    scsi0: 'local-lvm:32',
    acpi: true,
    balloon: 0,
    cpu: 'host',
    numa: false,
    hotplug: 'network,disk,cpu,memory',
    tags: 'production,web',
    template: false
);

if ($result->isSuccessStatusCode()) {
    echo "VM created successfully!\n";

    // Start the VM
    $startResult = $client->getNodes()->get("pve1")->getQemu()->get(100)->getStatus()->getStart()->vmStart();
    if ($startResult->isSuccessStatusCode()) {
        echo "VM started successfully!\n";
    }
} else {
    echo "Error creating VM: " . $result->getError() . "\n";
}
```

### Cluster Monitoring

```php
<?php
// Monitor cluster resources
$resources = $client->getCluster()->resources();

foreach ($resources->getResponse()->data as $resource) {
    if ($resource->type === 'vm' && $resource->status === 'running') {
        $cpuPercent = round($resource->cpu * 100, 2);
        $memPercent = round(($resource->mem / $resource->maxmem) * 100, 2);

        echo "VM {$resource->vmid}: CPU {$cpuPercent}%, RAM {$memPercent}%\n";
    }
}
```

### Automated Backup

```php
<?php
// Create backup for multiple VMs
$vmids = [100, 101, 102];

$backup = $client->getNodes()->get("pve1")->getVzdump()->create(
    vmid: implode(',', $vmids),  // Comma-separated VM IDs
    storage: 'backup-storage',   // Storage target
    mode: 'snapshot',            // Backup mode
    compress: 'zstd'             // Compression algorithm
);

$taskId = $backup->getResponse()->data;
echo "Backup started with task: {$taskId}\n";
```

---

## Support

For professional consulting and enterprise support, visit [www.corsinvest.it](https://www.corsinvest.it)

---

<div align="center">
  <sub>Part of <a href="https://www.corsinvest.it/cv4pve">cv4pve</a> suite | Made with ❤️ in Italy by <a href="https://www.corsinvest.it">Corsinvest</a></sub>
  <br>
  <sub>Copyright © Corsinvest Srl</sub>
</div>
