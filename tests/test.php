<?php

/*
 * SPDX-FileCopyrightText: 2022 Daniele Corsini <daniele.corsini@corsinvest.it>
 * SPDX-FileCopyrightText: Copyright Corsinvest Srl
 * SPDX-License-Identifier: GPL-3.0-only
 */

require_once '../vendor/autoload.php';
require_once '../src/PveClient.php';

$client = new \Corsinvest\ProxmoxVE\Api\PveClient($argv[1]);
if ($client->login($argv[2], $argv[3])) {
    $result = $client->getVersion()->version();

    var_dump($result);

    $ret1 = $client->get("/version");
    echo $ret1->getStatusCode() . "\n";
    echo $ret1->getReasonPhrase() . "\n";

    foreach ($client->getNodes()->index()->getResponse()->data as $node) {
        echo "\n" . $node->id;
    }

    foreach ($client->getNodes()->get("cv-pve01")->getQemu()->vmlist()->getResponse()->data as $vm) {
        echo "\n" . $vm->vmid . " - " . $vm->name;
    }

    //loop snapshots
    foreach ($client->getNodes()->get("cv-pve02")->getQemu()->get(100)->getSnapshot()->snapshotList()->getResponse()->data as $snap) {
        echo "\n" . $snap->name;
    }

    //return object
    var_dump($client->getVersion()->version()->getResponse());

    //disable return object
    $client->setResultIsObject(false);
    //return array
    $retArr = $client->getVersion()->version()->getResponse();
    var_dump($retArr);
    echo "\n" . $retArr['data']['release'];

    //enable return objet
    $client->setResultIsObject(true);

    //image rrd
    $client->setResponseType('png');
    echo "<img src='{$client->getNodes()->get("pve1")->getRrd()->rrd('cpu', 'day')->getResponse()}' \>";

    //reset json result
    $client->setResponseType('json');
    var_dump($client->get('/version')->getResponse());
}
