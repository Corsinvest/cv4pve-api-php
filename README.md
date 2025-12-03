# cv4pve-api-php 🔧

<div align="center">

![cv4pve-api-php Banner](https://img.shields.io/badge/Corsinvest-Proxmox%20VE%20API%20PHP-blue?style=for-the-badge&logo=php)

**🚀 Official PHP Client Library Suite for Proxmox VE API**

[![License](https://img.shields.io/github/license/Corsinvest/cv4pve-api-php.svg)](LICENSE) 
[![Packagist Version](https://img.shields.io/packagist/v/corsinvest/cv4pve-api-php.svg)](https://packagist.org/packages/Corsinvest/cv4pve-api-php) 
![Packagist Downloads](https://img.shields.io/packagist/dt/corsinvest/cv4pve-api-php)
[![PHP Version](https://img.shields.io/packagist/php-v/corsinvest/cv4pve-api-php.svg)](https://packagist.org/packages/Corsinvest/cv4pve-api-php)

⭐ **We appreciate your star, it helps!** ⭐

```text
   ______                _                      __
  / ____/___  __________(_)___ _   _____  _____/ /_
 / /   / __ \/ ___/ ___/ / __ \ | / / _ \/ ___/ __/
/ /___/ /_/ / /  (__  ) / / / / |/ /  __(__  ) /_
\____/\____/_/  /____/_/_/ /_/|___/\___/____/\__/

Corsinvest for Proxmox VE Api Client  (Made in Italy 🇮🇹)
```

</div>

## About

**cv4pve-api-php** is a comprehensive PHP client library that provides seamless integration with Proxmox VE's REST API. Designed for developers who need to programmatically manage virtual machines, containers, storage, and cluster resources in Proxmox VE environments.

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

$client = new PveClient("your-proxmox-host.com");

if ($client->login('root', 'password', 'pam')) {
    // Get cluster status
    $status = $client->getNodes()->get("pve1")->getStatus()->current();
    echo "Node Status: " . $status->getResponse()->data->status . "\n";
    
    // List VMs
    foreach ($client->getNodes()->get("pve1")->getQemu()->vmlist()->getResponse()->data as $vm) {
        echo "VM {$vm->vmid}: {$vm->name} - Status: {$vm->status}\n";
    }
}
```

## Key Features

### Developer Experience

- **Intuitive API Structure** - Mirrors Proxmox VE API hierarchy for easy navigation
- **Comprehensive Documentation** - Detailed examples and API reference
- **Easy Integration** - Simple composer installation and minimal setup required
- **Flexible Response Handling** - Choose between object or array response formats

### Core Functionality

- **Complete API Coverage** - Full implementation of Proxmox VE REST API endpoints
- **VM & Container Management** - Create, configure, start, stop, and monitor VMs and containers
- **Storage Operations** - Manage storage pools, volumes, and backups
- **Cluster Management** - Monitor cluster status, resources, and performance

### Enterprise Ready

- **Multiple Authentication Methods** - Username/password, API tokens, and two-factor authentication
- **Security First** - Secure communication with SSL/TLS support
- **Task Management** - Built-in support for monitoring long-running operations
- **Connection Management** - Configurable timeouts and connection pooling

### Technical Excellence

- **Zero Dependencies** - Lightweight design using only native PHP cURL
- **PHP 5.5+ Compatible** - Wide compatibility with modern and legacy environments
- **Error Handling** - Comprehensive error reporting and exception management
- **Cross-Platform** - Works on Windows, Linux, and macOS

## Documentation

- **[API Reference](docs/API.md)** - Complete method documentation
- **[Configuration Guide](docs/API.md#configuration-options)** - Setup and customization options
- **[Authentication](docs/API.md#authentication-methods)** - Login methods and API tokens
- **[Usage Examples](docs/API.md#common-operations)** - Practical code examples
- **[Task Management](docs/API.md#task-management)** - Handle long-running operations
- **[Error Handling](docs/API.md#error-handling)** - Exception management

## Community & Support

### Getting Help

- **[Documentation](docs/API.md)** - Comprehensive guides and examples
- **[GitHub Issues](https://github.com/Corsinvest/cv4pve-api-php/issues)** - Bug reports and feature requests
- **[Commercial Support](https://www.corsinvest.it/cv4pve)** - Professional consulting and support

### Contributing

We welcome contributions from the community! Whether it's bug fixes, new features, or documentation improvements, your help makes this project better for everyone.

## Use Cases

Perfect for:
- **Infrastructure Automation** - Automate VM/CT deployment and configuration
- **Monitoring & Analytics** - Build custom dashboards and monitoring solutions
- **Backup Management** - Implement automated backup and disaster recovery workflows
- **Multi-tenant Environments** - Manage multiple Proxmox VE clusters and tenants
- **DevOps Integration** - Integrate with CI/CD pipelines and deployment automation

## License

**Copyright © Corsinvest Srl**

This software is part of the **cv4pve-tools** suite. For licensing details, please visit [LICENSE](LICENSE).

---

<div align="center">
  <sub>Part of <a href="https://www.corsinvest.it/cv4pve">cv4pve-tools</a> suite | Made with ❤️ in Italy by <a href="https://www.corsinvest.it">Corsinvest</a></sub>
</div>
