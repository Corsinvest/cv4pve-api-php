<?php

namespace Corsinvest\ProxmoxVE\Api;

/**
 * Result request API
 * @package Corsinvest\ProxmoxVE\Api
 */
class Result {

    /**
     * @ignore
     */
    private $reasonPhrase;

    /**
     * @ignore
     */
    private $statusCode;

    /**
     * @ignore
     */
    private $response;

    /**
     * @ignore
     */
    private $resultIsObject;

    /**
     * @ignore
     */
    function __construct($response, $statusCode, $reasonPhrase, $resultIsObject) {
        $this->statusCode = $statusCode;
        $this->reasonPhrase = $reasonPhrase;
        $this->response = $response;
        $this->resultIsObject = $resultIsObject;
    }

    /**
     * Proxmox VE response.
     * @return mixed
     */
    function getResponse() {
        return $this->response;
    }

    /**
     * Contains the values of status codes defined for HTTP.
     * @return int
     */
    function getStatusCode() {
        return $this->statusCode;
    }

    /**
     * Gets the reason phrase which typically is sent by servers together with the status code.
     * @return string
     */
    function getReasonPhrase() {
        return $this->reasonPhrase;
    }

    /**
     * Gets a value that indicates if the HTTP response was successful.
     * @return bool
     */
    public function isSuccessStatusCode() {
        return $this->statusCode == 200;
    }

    /**
     * Get if response Proxmox VE contain errors
     * @return bool
     */
    public function responseInError() {
        if ($this->resultIsObject) {
            return property_exists($this->response, 'errors') && $this->response->errors != null;
        } else {
            return array_key_exists('errors', $this->response);
        }
    }

    /**
     * Get Error
     * @return string
     */
    public function getError() {
        $ret = '';
        if ($this->responseInError()) {
            if ($this->resultIsObject) {
                foreach ($this->response->errors as $key => $value) {
                    if ($ret != '') {
                        $ret .= '\n';
                    }
                    $ret .= $key . " : " . $value;
                }
            } else {
                foreach ($this->response->errors['errors'] as $key => $value) {
                    if ($ret != '') {
                        $ret .= '\n';
                    }
                    $ret .= $key . " : " . $value;
                }
            }
        }
        return $ret;
    }

}