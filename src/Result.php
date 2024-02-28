<?php

/*
 * SPDX-FileCopyrightText: Copyright Corsinvest Srl
 * SPDX-License-Identifier: GPL-3.0-only
 */

namespace Corsinvest\ProxmoxVE\Api;

/**
 * Result request API
 * @package Corsinvest\ProxmoxVE\Api
 */
class Result
{
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
    public function __construct(
        $response,
        $statusCode,
        $reasonPhrase,
        $resultIsObject,
        $requestResource,
        $requestParameters,
        $methodType,
        $responseType
    ) {
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
    public function getMethodType()
    {
        return $this->methodType;
    }

    /**
     * Response type
     * @return string
     */
    public function getResponseType()
    {
        return $this->responseType;
    }

    /**
     * Resource request
     * @return string
     */
    public function getRequestResource()
    {
        return $this->requestResource;
    }

    /**
     * Request parameter
     * @return
     */
    public function getRequestParameters()
    {
        return $this->requestParameters;
    }

    /**
     * Proxmox VE response.
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Contains the values of status codes defined for HTTP.
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Gets the reason phrase which typically is sent by servers together with the status code.
     * @return string
     */
    public function getReasonPhrase()
    {
        return $this->reasonPhrase;
    }

    /**
     * Gets a value that indicates if the HTTP response was successful.
     * @return bool
     */
    public function isSuccessStatusCode()
    {
        return $this->statusCode == 200;
    }

    /**
     * Get if response Proxmox VE contain errors
     * @return bool
     */
    public function responseInError()
    {
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
    public function getError()
    {
        $ret = '';
        if ($this->responseInError()) {
            $errors = $this->resultIsObject
                ? $this->response->errors
                : $this->response->errors['errors'];

            foreach ($errors as $key => $value) {
                if ($ret != '') {
                    $ret .= '\n';
                }
                $ret .= $key . " : " . $value;
            }
        }
        return $ret;
    }
}
