<?php

/*
 * SPDX-FileCopyrightText: Copyright Corsinvest Srl
 * SPDX-License-Identifier: GPL-3.0-only
 */

namespace Corsinvest\ProxmoxVE\Api;

use Exception;
use Throwable;

/**
 *
 */
class PveExceptionAuthentication extends Exception
{
    private $result;

    /**
     * Construction
     * @param Result $result
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
