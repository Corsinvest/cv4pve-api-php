<?php

require_once '../vendor/autoload.php';
require_once '../src/Client.php';

$client = new \EnterpriseVE\ProxmoxVE\Api\Client($argv[1]);
if ($client->login($argv[2], $argv[3])) {
    $result = $client->getVersion()->version();

    var_dump($result);
    
    $ret1=$client->get("/pippo");
    echo "\n" . $client->getStatusCode();
    echo "\n" . $client->getReasonPhrase();

    echo $result->data->version;

    foreach ($client->getNodes()->index()->data as $node) {
        echo "\n" . $node->id;
    }

    foreach ($client->getNodes()->get("pve1")->getQemu()->vmlist()->data as $vm) {
        echo "\n" . $vm->vmid ." - " .$vm->name;
    }

    //loop snapshots
    foreach ($client->getNodes()->get("pve1")->getQemu()->get(100)->getSnapshot()->snapshotList()->data as $snap) {
        echo "\n" . $snap->name;
    }

    var_dump($client->getVersion()->version());
    $client->setResultIsObject(false);
    $retArr = $client->getVersion()->version();
    var_dump($retArr);
    echo "\n" . $retArr['data']['release'];
    $client->setResultIsObject(true);
}
