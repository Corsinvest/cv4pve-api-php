<?php

/*
 * This file is part of the cv4pve-api-php https://github.com/Corsinvest/cv4pve-api-php,
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Corsinvest Enterprise License (CEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * Copyright (C) 2016 Corsinvest Srl	GPLv3 and CEL
 */

namespace Corsinvest\ProxmoxVE\Api;

/**
 * Class ClientBase
 * @package Corsinvest\ProxmoxVE\Api
 *
 * Proxmox VE Client Base
 */
class PveClientBase {

    /**
     * @ignore
     */
    private $ticketCSRFPreventionToken;

    /**
     * @ignore
     */
    private $ticketPVEAuthCookie;

    /**
     * @ignore
     */
    private $hostname;

    /**
     * @ignore
     */
    private $apiToken;

    /**
     * @ignore
     */
    private $port;

    /**
     * @ignore
     */
    private $resultIsObject = true;

    /**
     * @ignore
     */
    private $responseType = 'json';

    /**
     * @ignore
     */
    private $debugLevel = 0;

    /**
     * @ignore
     */
    private $lastResult;

    /**
     * Client constructor.
     * @param string $hostname Host Proxmox VE
     * @param int $port Port connection default 8006
     */
    function __construct($hostname, $port = 8006) {
        $this->hostname = $hostname;
        $this->port = $port;
    }

    /**
     * Return if result is object
     * @return bool
     */
    function isResultObject() {
        return $this->resultIsObject;
    }

    /**
     * Set result is object
     * @param bool $resultIsObject
     */
    function setResultIsObject($resultIsObject) {
        $this->resultIsObject = $resultIsObject;
    }

    /**
     * Gets the hostname configured.
     *
     * @return string The hostname.
     */
    public function getHostname() {
        return $this->hostname;
    }

    /**
     * Gets the port configured.
     *
     * @return int The port.
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * Sets the response type that is going to be returned when doing requests.
     *
     * @param string One of json, png.
     */
    public function setResponseType($type = 'json') {
        $this->responseType = $type;
    }

    /**
     * Returns the response type that is being used by the Proxmox API client.
     *
     * @return string Response type being used.
     */
    public function getResponseType() {
        return $this->responseType;
    }

    /**
     * Sets the debug level value 0 - nothing 1 - Url and method 2 - Url and method and result
     *
     * @param string $debugLevel One of json, png.
     */
    public function setDebugLevel($debugLevel) {
        $this->debugLevel = $debugLevel;
    }

    /**
     * Returns debug level.
     *
     * @return string Response type being used.
     */
    public function getDebugLevel() {
        return $this->debugLevel;
    }

    /**
     * Return Api Token
     *
     * @return type string
     */
    public function getApiToken() {
        return $this->apiToken;
    }

    /**
     * Set Api Token format USER@REALM!TOKENID=UUID
     *
     * @param type string $apiToken
     */
    public function setApiToken($apiToken) {
        $this->apiToken = $apiToken;
    }

    /**
     * Returns the base URL used to interact with the Proxmox VE API.
     *
     * @return string The proxmox API URL.
     */
    public function getApiUrl() {
        return "https://{$this->getHostname()}:{$this->getPort()}/api2/{$this->responseType}";
    }

    /**
     * Creation ticket from login.
     * @param string $userName user name or &lt;username&gt;@&lt;realm&gt;
     * @param string $password
     * @param string $realm pam/pve or custom
     * @return bool logged
     */
    function login($userName, $password, $realm = "pam") {
        $uData = explode("@", $userName);
        if (count($uData) > 1) {
            $userName = $uData[0];
            $realm = $uData[1];
        }

        $oldResultIsObject = $this->isResultObject();
        $this->setResultIsObject(true);

        $params = [
            'password' => $password,
            'username' => $userName,
            'realm' => $realm
        ];

        $result = $this->create("/access/ticket", $params);
        $this->setResultIsObject($oldResultIsObject);

        if ($result->isSuccessStatusCode()) {
            $this->ticketCSRFPreventionToken = $result->getResponse()->data->CSRFPreventionToken;
            $this->ticketPVEAuthCookie = $result->getResponse()->data->ticket;
            return true;
        }

        return false;
    }

    /**
     * Execute method GET
     * @param string $resource Url request
     * @param array $parameters Additional parameters
     * @return Result
     */
    public function get($resource, $parameters = []) {
        return $this->executeAction($resource, 'GET', $parameters);
    }

    /**
     * Execute method PUT
     * @param string $resource Url request
     * @param array $parameters Additional parameters
     * @return Result
     */
    public function set($resource, $parameters = []) {
        return $this->executeAction($resource, 'PUT', $parameters);
    }

    /**
     * Execute method POST
     * @param string $resource Url request
     * @param array $parameters Additional parameters
     * @return Result
     */
    public function create($resource, $parameters = []) {
        return $this->executeAction($resource, 'POST', $parameters);
    }

    /**
     * Execute method DELETE
     * @param string $resource Url request
     * @param array $parameters Additional parameters
     * @return Result
     */
    public function delete($resource, $parameters = []) {
        return $this->executeAction($resource, 'DELETE', $parameters);
    }

    /**
     * @ignore
     */
    private function executeAction($resource, $method, $parameters = []) {
        //url resource
        $url = "{$this->getApiUrl()}{$resource}";

        //remove null params
        $params = array_filter($parameters, function ($value) {
            return null !== $value;
        });

        if ($this->getDebugLevel() >= 1) {
            echo "Method: " . method . " , Url: " . $url . "\n";
            if (method != 'GET') {
                echo "Parameters:\n";
                var_dump($params);
            }
        }

        $methodType = "";
        $prox_ch = curl_init();
        switch ($method) {
            case "GET":
                $action_postfields = http_build_query($params);
                $url .= '?' . $action_postfields;
                unset($action_postfields);
                $methodType = "GET";
                break;

            case "PUT":
                curl_setopt($prox_ch, CURLOPT_CUSTOMREQUEST, "PUT");
                $action_postfields = http_build_query($params);
                curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $action_postfields);
                unset($action_postfields);
                $methodType = "SET";
                break;

            case "POST":
                curl_setopt($prox_ch, CURLOPT_POST, true);
                $action_postfields = http_build_query($params);
                curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $action_postfields);
                unset($action_postfields);
                $methodType = "CREATE";
                break;

            case "DELETE":
                curl_setopt($prox_ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                $methodType = "DELETE";
                break;
        }

        curl_setopt($prox_ch, CURLOPT_URL, $url);
        curl_setopt($prox_ch, CURLOPT_HEADER, true);
        curl_setopt($prox_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($prox_ch, CURLOPT_COOKIE, "PVEAuthCookie=" . $this->ticketPVEAuthCookie);
        curl_setopt($prox_ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($prox_ch, CURLOPT_SSL_VERIFYHOST, false);

        $headers = [];
        if (isSet($this->ticketPVEAuthCookie)) {
            array_push($headers, "CSRFPreventionToken: {$this->ticketCSRFPreventionToken}");
        }

        if (isSet($this->apiToken)) {
            array_push($headers, "Authorization: PVEAPIToken {$this->apiToken}");
        }

        curl_setopt($prox_ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($prox_ch);
        $curlInfo = curl_getinfo($prox_ch);
        $reasonPhrase = curl_error($prox_ch);
        $reasonCode = $curlInfo["http_code"];
        curl_close($prox_ch);
        unset($prox_ch);

        $body = substr($response, $curlInfo["header_size"]);
        unset($response);
        unset($curlInfo);

        $obj = null;
        switch ($this->responseType) {
            case 'json':
                $obj = json_decode($body, !$this->isResultObject());
                break;

            case 'png':
                $obj = 'data:image/png;base64,' . base64_encode($body);
                break;
        }
        unset($body);

        $lastResult = new Result($obj,
                $reasonCode,
                $reasonPhrase,
                $this->resultIsObject,
                $resource,
                $parameters,
                $methodType,
                $this->responseType);

        if ($this->getDebugLevel() >= 2) {
            echo $obj . "\n";
            echo "StatusCode:          " . $lastResult->getStatusCode() . "\n";
            echo "ReasonPhrase:        " . $lastResult->getReasonPhrase() . "\n";
            echo "IsSuccessStatusCode: " . $lastResult->isSuccessStatusCode() . "\n";
        }

        if ($this->getDebugLevel() > 0) {
            echo "=============================";
        }
        return $lastResult;
    }

    /**
     * Gets the last result action
     * @return Result
     */
    public function getLastResult() {
        return $this->lastResult;
    }

    /**
     * Wait for task to finish
     * @param string $node Node identifier
     * @param string $task Task identifier
     * @param int $wait Millisecond wait next check
     * @param int $timeOut Millisecond timeout
     * @return bool 
     */
    public function waitForTaskToFinish($node, $task, $wait = 500, $timeOut = 10000) {
        $isRunning = true;
        if ($wait <= 0) {
            $wait = 500;
        }
        if ($timeOut < $wait) {
            $timeOut = $wait + 5000;
        }
        $timeStart = time();
        $waitTime = time();
        while ($isRunning && (time() - $timeStart) < $timeOut) {
            if ((time() - $waitTime) >= $wait) {
                $waitTime = time();
                $isRunning = taskIsRunning($node, $task);
            }
        }

        return (time() - $timeStart ) < $timeOut;
    }

    /**
     * Wait for task to finish
     * @param string $task Task identifier
     * @param int $wait Millisecond wait next check
     * @param int $timeOut Millisecond timeout
     * @return bool 
     */
    function waitForTaskToFinish1($task, $wait = 500, $timeOut = 10000) {
        return waitForTaskToFinish(split(':', $task)[1], $task, $wait, $timeOut);
    }

    /**
     * Check task is running
     *
     * @param string $node Node identifier
     * @param string $task Task identifier
     * @return bool Is running
     */
    function taskIsRunning($node, $task) {
        return $this->readTaskStatus($node, $task)->getResponse()->data == "running";
    }

    /**
     * Return exit status code task
     *
     * @param string $node Node identifier
     * @param string $task Task identifier
     * @return string Message status
     */
    function getExitStatusTask($node, $task) {
        return $this->readTaskStatus($node, $task)->getResponse()->data->exitstatus;
    }

    /**
     * Read task status.
     * @return Result
     */
    private function readTaskStatus($node, $task) {
        return $this->get("/nodes/{$node}/tasks/{$task}/status");
    }

}
