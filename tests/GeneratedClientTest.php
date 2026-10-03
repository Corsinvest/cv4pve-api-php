<?php

/*
 * SPDX-FileCopyrightText: Copyright Corsinvest Srl
 * SPDX-License-Identifier: MIT
 */

// Offline tests of the generated client (PveClient): the curl functions are replaced by fakes in
// the namespace of the library, so no cluster is needed. They check what each call sends: HTTP
// method, path, query string and body.
// Run with: php tests/GeneratedClientTest.php

namespace Corsinvest\ProxmoxVE\Api;

class FakeCurl
{
    public static $requests = [];
    public static $options = [];
    public static $headerSize = 0;

    /** Last request as ['method' => ..., 'path' => ..., 'query' => [...], 'body' => [...]|null] */
    public static function last()
    {
        $options = end(self::$requests);
        $url = parse_url($options[CURLOPT_URL]);
        $query = [];
        parse_str(isset($url['query']) ? $url['query'] : '', $query);
        $method = isset($options[CURLOPT_CUSTOMREQUEST]) ? $options[CURLOPT_CUSTOMREQUEST]
            : (!empty($options[CURLOPT_POST]) ? 'POST' : 'GET');
        $body = isset($options[CURLOPT_POSTFIELDS]) && $options[CURLOPT_POSTFIELDS] !== ''
            ? json_decode($options[CURLOPT_POSTFIELDS], true) : null;
        return ['method' => $method, 'path' => $url['path'], 'query' => $query, 'body' => $body];
    }
}

function curl_init()
{
    FakeCurl::$options = [];
    return new \stdClass();
}

function curl_setopt($handle, $option, $value)
{
    FakeCurl::$options[$option] = $value;
    return true;
}

function curl_exec($handle)
{
    FakeCurl::$requests[] = FakeCurl::$options;
    $headers = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n";
    FakeCurl::$headerSize = strlen($headers);
    return $headers . '{"data":null}';
}

function curl_getinfo($handle)
{
    return ['http_code' => 200, 'header_size' => FakeCurl::$headerSize];
}

function curl_error($handle)
{
    return '';
}

function curl_close($handle)
{
}

// a warning or a deprecation is a failure, also while the classes are loaded
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

require __DIR__ . '/../src/Result.php';
require __DIR__ . '/../src/PveResultException.php';
require __DIR__ . '/../src/PveExceptionAuthentication.php';
require __DIR__ . '/../src/PveClientBase.php';
require __DIR__ . '/../src/PveClient.php';

function same($expected, $actual, $what = '')
{
    if ($expected !== $actual) {
        throw new \Exception(trim("{$what} expected " . var_export($expected, true) . ', got ' . var_export($actual, true)));
    }
}

function client()
{
    FakeCurl::$requests = [];
    $client = new PveClient('pve01');
    $client->setApiToken('root@pam!test=00000000-0000-0000-0000-000000000000');
    return $client;
}

$tests = [

    'version' => function () {
        $result = client()->getVersion()->version();

        same(true, $result instanceof Result);
        same(['method' => 'GET', 'path' => '/api2/json/version', 'query' => [], 'body' => null], FakeCurl::last());
    },

    'a node of the tree makes no request until a method is called' => function () {
        $vm = client()->getNodes()->get('pve01')->getQemu()->get(100);
        $vm->getSnapshot();

        same(0, count(FakeCurl::$requests));
    },

    'an optional parameter goes in the query string' => function () {
        client()->getCluster()->getResources()->resources('vm');

        same('/api2/json/cluster/resources', FakeCurl::last()['path']);
        same(['type' => 'vm'], FakeCurl::last()['query']);
    },

    'a parameter left as null is not sent' => function () {
        client()->getCluster()->getResources()->resources();

        same([], FakeCurl::last()['query']);
    },

    'the indexers build the path' => function () {
        client()->getNodes()->get('pve01')->getQemu()->get(100)->getStatus()->getCurrent()->vmStatus();

        same('GET', FakeCurl::last()['method']);
        same('/api2/json/nodes/pve01/qemu/100/status/current', FakeCurl::last()['path']);
    },

    'create a snapshot: parameters in a JSON body, a boolean as 1 or 0' => function () {
        $snapshot = client()->getNodes()->get('pve01')->getQemu()->get(100)->getSnapshot();

        $snapshot->snapshot('snap1', 'before the update', true);
        same('POST', FakeCurl::last()['method']);
        same('/api2/json/nodes/pve01/qemu/100/snapshot', FakeCurl::last()['path']);
        same(['snapname' => 'snap1', 'description' => 'before the update', 'vmstate' => 1], FakeCurl::last()['body']);

        $snapshot->snapshot('snap2', null, false);
        same(['snapname' => 'snap2', 'vmstate' => 0], FakeCurl::last()['body']);
    },

    'delete a snapshot sends force in the query string' => function () {
        client()->getNodes()->get('pve01')->getQemu()->get(100)->getSnapshot()->get('snap1')->delsnapshot(true);

        same('DELETE', FakeCurl::last()['method']);
        same('/api2/json/nodes/pve01/qemu/100/snapshot/snap1', FakeCurl::last()['path']);
        same(['force' => '1'], FakeCurl::last()['query']);
    },

    'delete a snapshot without parameters' => function () {
        client()->getNodes()->get('pve01')->getQemu()->get(100)->getSnapshot()->get('snap1')->delsnapshot();

        same('DELETE', FakeCurl::last()['method']);
        same([], FakeCurl::last()['query']);
    },

    'a parameter name with a dash is sent with the dash' => function () {
        client()->getNodes()->get('pve01')->getQemu()->get(100)->getStatus()->getStart()->vmStart('host');

        same('/api2/json/nodes/pve01/qemu/100/status/start', FakeCurl::last()['path']);
        same(['force-cpu' => 'host'], FakeCurl::last()['body']);
    },

    'indexed parameters are sent one per index' => function () {
        $qemu = client()->getNodes()->get('pve01')->getQemu();

        // createVm has about a hundred parameters: vmid and netN are placed by their names
        $arguments = [];
        foreach ((new \ReflectionMethod($qemu, 'createVm'))->getParameters() as $parameter) {
            $values = ['vmid' => 100, 'netN' => [0 => 'model=virtio,bridge=vmbr0', 1 => 'model=virtio,bridge=vmbr1']];
            $arguments[] = isset($values[$parameter->getName()]) ? $values[$parameter->getName()] : null;
        }
        call_user_func_array([$qemu, 'createVm'], $arguments);

        same('POST', FakeCurl::last()['method']);
        same('/api2/json/nodes/pve01/qemu', FakeCurl::last()['path']);
        same(
            ['vmid' => 100, 'net0' => 'model=virtio,bridge=vmbr0', 'net1' => 'model=virtio,bridge=vmbr1'],
            FakeCurl::last()['body']
        );
    },

    'addIndexedParameter adds the values under the names of the API' => function () {
        $parameters = ['cores' => 4];
        client()->addIndexedParameter($parameters, 'net', [0 => 'a', 2 => 'c']);
        same(['cores' => 4, 'net0' => 'a', 'net2' => 'c'], $parameters);

        client()->addIndexedParameter($parameters, 'scsi', null);
        same(['cores' => 4, 'net0' => 'a', 'net2' => 'c'], $parameters);
    },

    'HA rule: the parameters are sent by name' => function () {
        client()->getCluster()->getHa()->getRules()->createRule('rule1', 'node-affinity', 'vm:100,vm:101');

        same('POST', FakeCurl::last()['method']);
        same('/api2/json/cluster/ha/rules', FakeCurl::last()['path']);
        same(['rule' => 'rule1', 'type' => 'node-affinity', 'resources' => 'vm:100,vm:101'], FakeCurl::last()['body']);

        client()->getCluster()->getHa()->getRules()->get('rule1')->updateRule('node-affinity', 'comment', 'abc');
        same('PUT', FakeCurl::last()['method']);
        same('/api2/json/cluster/ha/rules/rule1', FakeCurl::last()['path']);
        same(['type' => 'node-affinity', 'delete' => 'comment', 'digest' => 'abc'], FakeCurl::last()['body']);
    },

    'Ceph health mute' => function () {
        client()->getCluster()->getCeph()->getHealthMute()->healthMuteIndex();
        same('GET', FakeCurl::last()['method']);
        same('/api2/json/cluster/ceph/health-mute', FakeCurl::last()['path']);

        client()->getCluster()->getCeph()->getHealthMute()->get('OSD_DOWN')->healthMute(true, null, '1h');
        same('PUT', FakeCurl::last()['method']);
        same('/api2/json/cluster/ceph/health-mute/OSD_DOWN', FakeCurl::last()['path']);
        same(['value' => 1, 'ttl' => '1h'], FakeCurl::last()['body']);
    },

    'a route map entry takes the path values from the indexers' => function () {
        client()->getCluster()->getSdn()->getRouteMaps()->getEntries()->get('map1')->getEntry()->get(10)
            ->deleteRouteMapEntry('lock-123');

        same('DELETE', FakeCurl::last()['method']);
        same('/api2/json/cluster/sdn/route-maps/entries/map1/entry/10', FakeCurl::last()['path']);
        same(['lock-token' => 'lock-123'], FakeCurl::last()['query']);
    },

    'the journal of a node sends its filters by name' => function () {
        client()->getNodes()->get('pve01')->getJournal()->journal(null, null, null, 100, null, 'pveproxy');

        same('/api2/json/nodes/pve01/journal', FakeCurl::last()['path']);
        same(['lastentries' => '100', 'service' => 'pveproxy'], FakeCurl::last()['query']);
    },
];

$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "ok      {$name}\n";
    } catch (\Throwable $e) {
        $failed++;
        echo "FAILED  {$name}\n        " . get_class($e) . ': ' . $e->getMessage()
            . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
    }
}

echo "\n" . count($tests) . ' tests, ' . (count($tests) - $failed) . " passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
