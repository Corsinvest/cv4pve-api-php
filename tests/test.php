<?php

require_once '../vendor/autoload.php';
require_once '../src/Client.php';

$client = new \EnterpriseVE\ProxmoxVE\Api\Client($argv[1]);
$client->login($argv[2], $argv[3]);


$result = $client->getVersion()->Version();

//var_dump($result);

//echo $result->data->version;

foreach ($client->getNodes()->Index()->data as $node) {
    echo "\n" . $node->id;
}

foreach ($client->getNodes()->get("pve1")->getQemu()->Vmlist()->data as $vm) {
    echo "\n" . $vm->vmid ." - " .$vm->name;
}


//var_dump($nodes->data[0]);
