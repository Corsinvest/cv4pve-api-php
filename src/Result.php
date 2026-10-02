<?php

/*
 * SPDX-FileCopyrightText: Copyright Corsinvest Srl
 * SPDX-License-Identifier: MIT
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
    private $responseHeaders;

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
        $responseType,
        $responseHeaders
    ) {
        $this->statusCode = $statusCode;
        $this->reasonPhrase = $reasonPhrase;
        $this->response = $response;
        $this->resultIsObject = $resultIsObject;
        $this->requestResource = $requestResource;
        $this->requestParameters = $requestParameters;
        $this->methodType = $methodType;
        $this->responseType = $responseType;
        $this->responseHeaders = $responseHeaders;
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
     * Gets the raw HTTP headers associated with this response.
     * @return string
     */
    public function getResponseHeaders()
    {
        return $this->responseHeaders;
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
        return $this->getResponseErrors() !== null;
    }

    /**
     * Errors of the response, whatever its form (object or array); null when the response has none,
     * also when there is no response at all (the request got no answer, the body is not JSON).
     * @return array|object|null
     */
    private function getResponseErrors()
    {
        $errors = null;
        if (is_object($this->response) && isset($this->response->errors)) {
            $errors = $this->response->errors;
        } elseif (is_array($this->response) && isset($this->response['errors'])) {
            $errors = $this->response['errors'];
        }
        return is_array($errors) || is_object($errors) ? $errors : null;
    }

    /**
     * Get Error
     * @return string
     */
    public function getError()
    {
        $ret = '';
        $errors = $this->getResponseErrors();
        if ($errors !== null) {
            foreach ($errors as $key => $value) {
                if ($ret != '') {
                    $ret .= "\n";
                }
                $ret .= $key . " : " . (is_scalar($value) ? $value : json_encode($value));
            }
        }
        return $ret;
    }
}
