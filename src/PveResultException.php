<?php

/*
 * SPDX-FileCopyrightText: Copyright Corsinvest Srl
 * SPDX-License-Identifier: MIT
 */

namespace Corsinvest\ProxmoxVE\Api;

use Exception;
use Throwable;

/**
 * Call to the Proxmox VE API that did not return the expected result, e.g. the status of a task
 * that cannot be read.
 */
class PveResultException extends Exception
{
    private $result;

    /**
     * Construction
     * @param Result|null $result
     * @param string $message
     * @param int $code
     * @param Throwable $previous
     */
    public function __construct($result, $message, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->result = $result;
    }

    /**
     * Gets result
     *
     * @return Result result.
     */
    public function getResult()
    {
        return $this->result;
    }
}
