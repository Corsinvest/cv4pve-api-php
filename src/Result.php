<?php

/*
 * This file is part of the cv4pve-api-php https://github.com/Corsinvest/cv4pve-api-php,
 * Copyright (C) 2016 Corsinvest Srl
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

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
    private $requestResource;

    /**
     * @ignore
     */
    private $requestParameters;

    /**
     * @ignore
     */
    private $methodType;

    /**
     * @ignore
     */
    private $responseType;

    /**
     * @ignore
     */
    function __construct($response,
            $statusCode,
            $reasonPhrase,
            $resultIsObject,
            $requestResource,
            $requestParameters,
            $methodType,
            $responseType) {
        $this->statusCode = $statusCode;
        $this->reasonPhrase = $reasonPhrase;
        $this->response = $response;
        $this->resultIsObject = $resultIsObject;
        $this->requestResource = $requestResource;
        $this->requestParameters = $requestParameters;
        $this->methodType = $methodType;
        $this->responseType = $responseType;
    }

    /**
     * Request method type
     * @return string
     */
    function getMethodType() {
        return $this->methodType;
    }

    /**
     * Response type
     * @return string
     */
    function getResponseType() {
        return $this->responseType;
    }

    /**
     * Resource request
     * @return string
     */
    function getRequestResource() {
        return $this->requestResource;
    }

    /**
     * Request parameter
     * @return 
     */
    function getRequestParameters() {
        return $this->requestParameters;
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
