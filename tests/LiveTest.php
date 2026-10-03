<?php

/*
 * SPDX-FileCopyrightText: Copyright Corsinvest Srl
 * SPDX-License-Identifier: MIT
 */

// Tests on a real Proxmox VE. Connection from the environment: PVE_HOST, PVE_PORT (default 8006),
// PVE_API_TOKEN, PVE_TEST_VMID. They only read, except on the QEMU test VM PVE_TEST_VMID, where
// they change the description and create and delete a snapshot. Without PVE_HOST and
// PVE_API_TOKEN nothing runs; without PVE_TEST_VMID the tests on the VM are skipped.
// Run with: php tests/LiveTest.php

use Corsinvest\ProxmoxVE\Api\PveClient;
use Corsinvest\ProxmoxVE\Api\PveResultException;
use Corsinvest\ProxmoxVE\Api\Result;

// a warning or a deprecation is a failure, also while the classes are loaded
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require __DIR__ . '/../src/Result.php';
require __DIR__ . '/../src/PveResultException.php';
require __DIR__ . '/../src/PveExceptionAuthentication.php';
require __DIR__ . '/../src/PveClientBase.php';
require __DIR__ . '/../src/PveClient.php';

class Skipped extends Exception
{
}

function same($expected, $actual, $what = '')
{
    if ($expected !== $actual) {
        throw new Exception(trim("{$what} expected " . var_export($expected, true) . ', got ' . var_export($actual, true)));
    }
}

function isTrue($condition, $what)
{
    if (!$condition) {
        throw new Exception($what);
    }
}

function ok(Result $result)
{
    isTrue(
        $result->isSuccessStatusCode(),
        "{$result->getStatusCode()} {$result->getReasonPhrase()} {$result->getRequestResource()}"
    );
    isTrue(!$result->responseInError(), $result->getError());
    return $result;
}

function runTask(PveClient $client, Result $result)
{
    $upid = ok($result)->getResponse()->data;
    isTrue(is_string($upid) && strpos($upid, 'UPID:') === 0, 'not a task: ' . var_export($upid, true));
    // true when the time ran out with the task still running
    same(false, $client->waitForTaskToFinish($upid, 500, 120000), "task still running: {$upid}");
    same('OK', $client->getExitStatusTask($upid));
    return $upid;
}

function hasSnapshot($snapshots, $name)
{
    foreach ($snapshots as $snapshot) {
        if ($snapshot->name === $name) {
            return true;
        }
    }
    return false;
}

$host = getenv('PVE_HOST');
$apiToken = getenv('PVE_API_TOKEN');
if (!$host || !$apiToken) {
    echo "PVE_HOST and PVE_API_TOKEN not set: nothing to run\n";
    exit(0);
}

$client = new PveClient($host, getenv('PVE_PORT') ?: 8006);
$client->setTimeout(10)->setApiToken($apiToken);

$testVmId = getenv('PVE_TEST_VMID') ?: null;
$testNode = null;
if ($testVmId !== null) {
    foreach (ok($client->getCluster()->getResources()->resources('vm'))->getResponse()->data as $vm) {
        if ((string) $vm->vmid === (string) $testVmId && $vm->type === 'qemu') {
            $testNode = $vm->node;
        }
    }
}

$firstNode = function () use ($client) {
    return ok($client->getNodes()->index())->getResponse()->data[0]->node;
};

$testVm = function () use ($client, &$testNode, $testVmId) {
    if ($testNode === null) {
        throw new Skipped('PVE_TEST_VMID not set or not a QEMU VM of the cluster');
    }
    return $client->getNodes()->get($testNode)->getQemu()->get($testVmId);
};

$tests = [

    'version' => function () use ($client) {
        $data = ok($client->getVersion()->version())->getResponse()->data;

        same(1, preg_match('/^\d+\.\d+/', $data->version), $data->version);
        isTrue(isset($data->release), 'release is missing');
    },

    'nodes and their status' => function () use ($client) {
        $nodes = ok($client->getNodes()->index())->getResponse()->data;
        isTrue(count($nodes) > 0, 'no nodes');

        foreach ($nodes as $node) {
            if ($node->status === 'online') {
                $status = ok($client->getNodes()->get($node->node)->getStatus()->status())->getResponse()->data;
                isTrue($status->uptime > 0, "uptime of {$node->node}");
            }
        }
    },

    'cluster resources filtered by type' => function () use ($client) {
        foreach (ok($client->getCluster()->getResources()->resources('vm'))->getResponse()->data as $resource) {
            isTrue(in_array($resource->type, ['qemu', 'lxc']), $resource->type);
        }
    },

    'qemu list of every node' => function () use ($client) {
        foreach (ok($client->getNodes()->index())->getResponse()->data as $node) {
            if ($node->status === 'online') {
                foreach (ok($client->getNodes()->get($node->node)->getQemu()->vmlist())->getResponse()->data as $vm) {
                    isTrue($vm->vmid > 0, 'vmid');
                }
            }
        }
    },

    'the same answer as arrays' => function () use ($client) {
        $client->setResultIsObject(false);
        try {
            $response = ok($client->getVersion()->version())->getResponse();
        } finally {
            $client->setResultIsObject(true);
        }

        isTrue(is_array($response) && is_string($response['data']['version']), 'not an array');
    },

    'a resource that does not exist is an error' => function () use ($client) {
        $result = $client->getNodes()->get('node-that-does-not-exist')->getQemu()->vmlist();

        same(false, $result->isSuccessStatusCode());
        isTrue($result->getStatusCode() >= 400, (string) $result->getStatusCode());
    },

    'a wrong API token is rejected' => function () use ($client) {
        $other = new PveClient($client->getHostname(), $client->getPort());
        $other->setTimeout(10)->setApiToken('root@pam!none=00000000-0000-0000-0000-000000000000');

        same(401, $other->getNodes()->index()->getStatusCode());
    },

    'the reason of an error is the message of Proxmox VE' => function () use ($client, $firstNode) {
        $result = $client->get("/nodes/{$firstNode()}/qemu/999999/config");

        same(false, $result->isSuccessStatusCode());
        isTrue(strpos($result->getReasonPhrase(), 'does not exist') !== false, $result->getReasonPhrase());
        same(false, $result->responseInError());
    },

    'a refused parameter is listed by getError' => function () use ($client, $testVm, &$testNode, $testVmId) {
        $testVm();
        $result = $client->set("/nodes/{$testNode}/qemu/{$testVmId}/config", ['memory' => 'abc']);

        same(400, $result->getStatusCode());
        same(true, $result->responseInError());
        isTrue(strpos($result->getError(), 'memory : ') === 0, $result->getError());
    },

    'the chart of a node is a PNG image' => function () use ($client, $firstNode) {
        $node = $firstNode();

        $client->setResponseType('png');
        try {
            $result = $client->getNodes()->get($node)->getRrd()->rrd('cpu', 'hour');
        } finally {
            $client->setResponseType('json');
        }

        isTrue($result->isSuccessStatusCode(), "{$result->getStatusCode()} {$result->getReasonPhrase()}");
        $prefix = 'data:image/png;base64,';
        same(0, strpos($result->getResponse(), $prefix));
        // signature of a PNG file
        same("\x89PNG", substr(base64_decode(substr($result->getResponse(), strlen($prefix))), 0, 4));
    },

    'a self-signed certificate is refused when validated' => function () use ($client) {
        $strict = new PveClient($client->getHostname(), $client->getPort());
        $strict->setTimeout(10)->setValidateCertificate(true)->setApiToken($client->getApiToken());

        $result = $strict->getVersion()->version();

        if ($result->isSuccessStatusCode()) {
            throw new Skipped('the node has a trusted certificate');
        }
        same(0, $result->getStatusCode());
        isTrue($result->getReasonPhrase() !== '', 'the reason is empty');
        same('', $result->getError());
    },

    'test VM: configuration and status' => function () use ($testVm, $testVmId) {
        $vm = $testVm();

        isTrue(isset(ok($vm->getConfig()->vmConfig())->getResponse()->data->digest), 'digest is missing');
        same((string) $testVmId, (string) ok($vm->getStatus()->getCurrent()->vmStatus())->getResponse()->data->vmid);
    },

    'test VM: the description is changed and restored' => function () use ($client, $testVm, &$testNode, $testVmId) {
        $vm = $testVm();
        $resource = "/nodes/{$testNode}/qemu/{$testVmId}/config";
        $read = function () use ($vm) {
            $config = ok($vm->getConfig()->vmConfig())->getResponse()->data;
            return isset($config->description) ? $config->description : '';
        };
        $before = $read();
        $value = 'cv4pve-api-php live test ' . time() . ' àèì';

        try {
            ok($client->set($resource, ['description' => $value]));
            same($value, trim($read()));
        } finally {
            ok($before === ''
                ? $client->set($resource, ['delete' => 'description'])
                : $client->set($resource, ['description' => $before]));
        }

        same($before, $read());
    },

    'test VM: a snapshot is created, updated and deleted' => function () use ($client, $testVm) {
        $vm = $testVm();
        $name = 'livetest' . time();

        $upid = runTask($client, $vm->getSnapshot()->snapshot($name, 'created by cv4pve-api-php', false));
        try {
            same(false, $client->taskIsRunning($upid));
            isTrue(hasSnapshot(ok($vm->getSnapshot()->snapshotList())->getResponse()->data, $name), 'snapshot not listed');

            ok($vm->getSnapshot()->get($name)->getConfig()->updateSnapshotConfig('updated by cv4pve-api-php'));
            $config = ok($vm->getSnapshot()->get($name)->getConfig()->getSnapshotConfig())->getResponse()->data;
            same('updated by cv4pve-api-php', trim($config->description));
        } finally {
            // DELETE with a parameter in the query string
            runTask($client, $vm->getSnapshot()->get($name)->delsnapshot(false));
        }

        same(false, hasSnapshot(ok($vm->getSnapshot()->snapshotList())->getResponse()->data, $name));
    },

    'a task that does not exist cannot be read' => function () use ($client, $firstNode) {
        $upid = "UPID:{$firstNode()}:00000001:00000001:00000001:qmstart:999999:root@pam:";

        try {
            $client->taskIsRunning($upid);
        } catch (PveResultException $e) {
            isTrue($e->getResult() instanceof Result, 'the exception has no result');
            isTrue(strpos($e->getMessage(), "Read status of task '{$upid}' failed") === 0, $e->getMessage());
            return;
        }
        throw new Exception('PveResultException expected, nothing was thrown');
    },
];

$failed = 0;
$skipped = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "ok      {$name}\n";
    } catch (Skipped $e) {
        $skipped++;
        echo "skipped {$name}\n        {$e->getMessage()}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAILED  {$name}\n        " . get_class($e) . ': ' . $e->getMessage()
            . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
    }
}

echo "\n" . count($tests) . ' tests, ' . (count($tests) - $failed - $skipped) . " passed, {$skipped} skipped, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
