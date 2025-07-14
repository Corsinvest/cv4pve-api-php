<?php

/*
 * SPDX-FileCopyrightText: Copyright Corsinvest Srl
 * SPDX-License-Identifier: GPL-3.0-only
 */

namespace Corsinvest\ProxmoxVE\Api;

/**
 * Class ClientBase
 * @package Corsinvest\ProxmoxVE\Api
 *
 * Proxmox VE Client Base
 */
class PveClientBase
{

    /**
     * Set default to 0 ... which means no timeout limit at all
     * @ignore
     */
    private $timeout = 0;

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
     * @ignore
     */
    private $validateCertificate = false;


    /** @var callable[]
     * 
     */
    public $onActionExecuted = [];

    /**
     * Client constructor.
     * @param string $hostname Host Proxmox VE
     * @param int $port Port connection default 8006
     */
    public function __construct($hostname, $port = 8006)
    {
        $this->hostname = $hostname;
        $this->port = $port;
    }

    /**
     * Set timeout in seconds
     * @param int $timeout Timeout
     * @return PveClientBase
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Get timeout in seconds.
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Return if result is object
     * @return bool
     */
    public function isResultObject()
    {
        return $this->resultIsObject;
    }

    /**
     * Set result is object
     * @param bool $resultIsObject
     * @return PveClientBase
     */
    public function setResultIsObject($resultIsObject)
    {
        $this->resultIsObject = $resultIsObject;
        return $this;
    }

    /**
     * Gets the hostname configured.
     *
     * @return string
     */
    public function getHostname()
    {
        return $this->hostname;
    }

    /**
     * Gets the port configured.
     *
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Sets the response type that is going to be returned when doing requests.
     *
     * @param string
     * @return PveClientBase
     */
    public function setResponseType($type = 'json')
    {
        $this->responseType = $type;
        return $this;
    }

    /**
     * Returns the response type that is being used by the Proxmox API client.
     *
     * @return string
     */
    public function getResponseType()
    {
        return $this->responseType;
    }

    /**
     * Sets the debug level value 0 - nothing 1 - Url and method 2 - Url and method and result
     *
     * @param int $debugLevel One of json, png.
     * @return PveClientBase
     */
    public function setDebugLevel($debugLevel)
    {
        $this->debugLevel = $debugLevel;
        return $this;
    }

    /**
     * Returns debug level.
     *
     * @return int debug level used.
     */
    public function getDebugLevel()
    {
        return $this->debugLevel;
    }

    /**
     * Sets the Validate Certificate.
     *
     * @param bool $validateCertificate
     * @return PveClientBase
     */
    public function setValidateCertificate($validateCertificate)
    {
        $this->validateCertificate = $validateCertificate;
        return $this;
    }

    /**
     * Returns Validate Certificate.
     *
     * @return bool Verify Certificate.
     */
    public function getValidateCertificate()
    {
        return $this->validateCertificate;
    }

    /**
     * Return Api Token
     *
     * @return type string
     */
    public function getApiToken()
    {
        return $this->apiToken;
    }

    /**
     * Set Api Token format USER@REALM!TOKENID=UUID
     *
     * @param type string $apiToken
     * @return PveClientBase
     */
    public function setApiToken($apiToken)
    {
        $this->apiToken = $apiToken;
        return $this;
    }

    /**
     * Returns the base URL used to interact with the Proxmox VE API.
     *
     * @return string The proxmox API URL.
     */
    public function getApiUrl()
    {
        return "https://{$this->getHostname()}:{$this->getPort()}/api2/{$this->responseType}";
    }

    /**
     * Creation ticket from login.
     * @param string $userName user name or &lt;username&gt;@&lt;realm&gt;
     * @param string $password
     * @param string $realm pam/pve or custom
     * @param string $otp One-time password for Two-factor authentication.
     * @return bool logged
     */
    public function login($userName, $password, $realm = "pam", $otp = null)
    {
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
            'realm' => $realm,
            'otp' => $otp
        ];

        $result = $this->create("/access/ticket", $params);
        $this->setResultIsObject($oldResultIsObject);

        if ($result->isSuccessStatusCode()) {
            if (isset($result->getResponse()->data->NeedTFA)) {
                throw new PveExceptionAuthentication(
                    $result,
                    "Couldn't authenticate user: missing Two Factor Authentication (TFA)"
                );
            }

            $this->ticketCSRFPreventionToken = $result->getResponse()->data->CSRFPreventionToken;
            $this->ticketPVEAuthCookie = $result->getResponse()->data->ticket;
        }

        return $result->isSuccessStatusCode();
    }

    /**
     * Execute method GET
     * @param string $resource Url request
     * @param array $parameters Additional parameters
     * @return Result
     */
    public function get($resource, $parameters = [])
    {
        return $this->executeAction($resource, 'GET', $parameters);
    }

    /**
     * Execute method PUT
     * @param string $resource Url request
     * @param array $parameters Additional parameters
     * @return Result
     */
    public function set($resource, $parameters = [])
    {
        return $this->executeAction($resource, 'PUT', $parameters);
    }

    /**
     * Execute method POST
     * @param string $resource Url request
     * @param array $parameters Additional parameters
     * @return Result
     */
    public function create($resource, $parameters = [])
    {
        return $this->executeAction($resource, 'POST', $parameters);
    }

    /**
     * Execute method DELETE
     * @param string $resource Url request
     * @param array $parameters Additional parameters
     * @return Result
     */
    public function delete($resource, $parameters = [])
    {
        return $this->executeAction($resource, 'DELETE', $parameters);
    }

    /**
     * @ignore
     */
    protected function executeAction($resource, $method, $parameters = [])
    {
        //url resource
        $url = "{$this->getApiUrl()}{$resource}";

        //remove null params
        $params = array_filter($parameters, function ($value) {
            return null !== $value;
        });

        //fix bool value
        $params = array_map(function ($value) {
            return is_bool($value) ? ($value ? 1 : 0) : $value;
        }, $params);

        if ($this->getDebugLevel() >= 1) {
            echo "Method: " . $method . " , Url: " . $url . "\n";
            if ($method != 'GET') {
                echo "Parameters:\n";
                var_dump($params);
            }
        }

        $headers = [];
        $methodType = "";
        $data = ""; // default POSTFIELDS value as defined in https://curl.se/libcurl/c/CURLOPT_POSTFIELDS.html
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

                // data from params only if there are any
                if (count($params)) {
                    $data = json_encode($params);
                    array_push($headers, 'Content-Type: application/json');
                    array_push($headers, 'Content-Length: ' . strlen($data));
                }

                curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $data);
                $methodType = "SET";
                break;

            case "POST":
                curl_setopt($prox_ch, CURLOPT_POST, true);

                // data from params only if there are any
                if (count($params)) {
                    $data = json_encode($params);
                    array_push($headers, 'Content-Type: application/json');
                    array_push($headers, 'Content-Length: ' . strlen($data));
                }

                curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $data);
                $methodType = "CREATE";
                break;

            case "DELETE":
                curl_setopt($prox_ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                $methodType = "DELETE";

                // do not forget to pass query from params if there are any
                if (count($params) > 0) {
                    $action_postfields = http_build_query($params);
                    $url .= '?' . $action_postfields;
                    unset($action_postfields);
                }

                break;

            default:
                break;
        }

        curl_setopt($prox_ch, CURLOPT_URL, $url);
        curl_setopt($prox_ch, CURLOPT_HEADER, true);
        curl_setopt($prox_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($prox_ch, CURLOPT_COOKIE, "PVEAuthCookie=" . $this->ticketPVEAuthCookie);
        curl_setopt($prox_ch, CURLOPT_SSL_VERIFYPEER, $this->validateCertificate);
        curl_setopt($prox_ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($this->timeout != 0) {
            curl_setopt($prox_ch, CURLOPT_TIMEOUT, $this->timeout);
        }

        if (isset($this->ticketPVEAuthCookie)) {
            array_push($headers, "CSRFPreventionToken: {$this->ticketCSRFPreventionToken}");
        }

        if (isset($this->apiToken)) {
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
        $responseHeaders = substr($response, 0, $curlInfo["header_size"]);
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

            default:
                break;
        }
        unset($body);

        $this->lastResult = new Result(
            $obj,
            $reasonCode,
            $reasonPhrase,
            $this->resultIsObject,
            $resource,
            $parameters,
            $methodType,
            $this->responseType,
            $responseHeaders,
        );

        if (is_array($this->onActionExecuted) && count($this->onActionExecuted)) {
            foreach ($this->onActionExecuted as $call) {
                if (is_callable($call)) {
                    call_user_func_array($call, [
                        $this->lastResult, [
                            'url' => $url, 
                            'method' => $method, 
                            'parameters' => $parameters, 
                            'headers' => $headers
                        ]
                    ]);
                }
            }
        }

        if ($this->getDebugLevel() >= 2) {
            if (is_array($obj)) {
                echo '<pre>';
                print_r($obj);
                echo '</pre>';
            } else {
                echo var_dump($obj) . PHP_EOL;
            }
            echo "StatusCode:          " . $this->lastResult->getStatusCode() . PHP_EOL;
            echo "ReasonPhrase:        " . $this->lastResult->getReasonPhrase() . PHP_EOL;
            echo "IsSuccessStatusCode: " . $this->lastResult->isSuccessStatusCode() . PHP_EOL;
        }

        if ($this->getDebugLevel() > 0) {
            echo "=============================";
        }
        return $this->lastResult;
    }

    /**
     * Gets the last result action
     * @return Result
     */
    public function getLastResult()
    {
        return $this->lastResult;
    }

    /**
     * Wait for task to finish
     * @param string $task Task identifier
     * @param int $wait Millisecond wait next check
     * @param int $timeOut Millisecond timeout
     * @return bool Function timed out
     */
    public function waitForTaskToFinish($task, $wait = 500, $timeOut = 10000)
    {
        $isRunning = $this->taskIsRunning($task);
        if ($wait <= 0) {
            $wait = 500;
        }
        if ($timeOut < $wait) {
            $timeOut = $wait + 5000;
        }
        $timeStart = floor(microtime(true) * 1000);
        $waitTime = floor(microtime(true) * 1000);

        while ($isRunning && ((floor(microtime(true) * 1000) - $timeStart) < $timeOut)) {
            if ((floor(microtime(true) * 1000) - $waitTime) >= $wait) {
                $waitTime = floor(microtime(true) * 1000);
                $isRunning = $this->taskIsRunning($task);
            }
        }

        return $isRunning;
    }

    /**
     * Check task is running
     *
     * @param string $task Task identifier
     * @return bool Is running
     */
    public function taskIsRunning($task)
    {
        return $this->readTaskStatus($task)->getResponse()->data->status == "running";
    }

    /**
     * Return exit status code task
     *
     * @param string $task Task identifier
     * @return string Message status
     */
    public function getExitStatusTask($task)
    {
        return $this->readTaskStatus($task)->getResponse()->data->exitstatus;
    }

    /**
     * Get node from task
     * @param string $task
     * @return type
     */
    public function getNodeFromTask($task)
    {
        return explode(":", $task)[1];
    }

    /**
     * Read task status.
     * @return Result
     */
    private function readTaskStatus($task)
    {
        return $this->get("/nodes/{$this->getNodeFromTask($task)}/tasks/{$task}/status");
    }
}
