<?php

/*
 * SPDX-FileCopyrightText: Copyright Corsinvest Srl
 * SPDX-License-Identifier: MIT
 */

// Offline tests of the hand-written classes (PveClientBase, Result, exceptions): the curl functions
// are replaced by fakes in the namespace of the library, so no cluster is needed.
// Run with: php tests/BaseTest.php

namespace Corsinvest\ProxmoxVE\Api;

class FakeCurl
{
    /** @var callable function(array $options): array answer of {@see http()} or {@see failure()} */
    public static $respond;
    public static $requests = [];
    public static $options = [];
    public static $last;

    public static function http($status, $reason, $body, $statusLine = null)
    {
        $headers = ($statusLine !== null ? $statusLine : "HTTP/1.1 {$status} {$reason}")
            . "\r\nContent-Type: application/json\r\n\r\n";
        return ['raw' => $headers . $body, 'http_code' => $status, 'header_size' => strlen($headers), 'error' => ''];
    }

    public static function failure($error)
    {
        return ['raw' => false, 'http_code' => 0, 'header_size' => 0, 'error' => $error];
    }

    public static function answer($respond)
    {
        self::$respond = $respond;
        self::$requests = [];
    }
}

function curl_init()
{
    FakeCurl::$options = [];
    return new \stdClass();
}

function curl_setopt($handle, $option, $value)
{
    FakeCurl::$options[$option] = $value;
    return true;
}

function curl_exec($handle)
{
    FakeCurl::$requests[] = FakeCurl::$options;
    FakeCurl::$last = call_user_func(FakeCurl::$respond, FakeCurl::$options);
    return FakeCurl::$last['raw'];
}

function curl_getinfo($handle)
{
    return ['http_code' => FakeCurl::$last['http_code'], 'header_size' => FakeCurl::$last['header_size']];
}

function curl_error($handle)
{
    return FakeCurl::$last['error'];
}

function curl_close($handle)
{
}

// a warning or a deprecation is a failure, also while the classes are loaded
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

require __DIR__ . '/../src/Result.php';
require __DIR__ . '/../src/PveResultException.php';
require __DIR__ . '/../src/PveExceptionAuthentication.php';
require __DIR__ . '/../src/PveClientBase.php';

const UPID = 'UPID:pve01:0012A3F4:05C1B2D3:6720F1A0:qmsnapshot:100:root@pam:';

function same($expected, $actual, $what = '')
{
    if ($expected !== $actual) {
        throw new \Exception(trim("{$what} expected " . var_export($expected, true) . ', got ' . var_export($actual, true)));
    }
}

function throws($class, $call, $messagePart = null)
{
    try {
        $call();
    } catch (\Throwable $e) {
        if (!($e instanceof $class)) {
            throw new \Exception("expected {$class}, got " . get_class($e) . ': ' . $e->getMessage());
        }
        if ($messagePart !== null && strpos($e->getMessage(), $messagePart) === false) {
            throw new \Exception("message '{$e->getMessage()}' does not contain '{$messagePart}'");
        }
        return;
    }
    throw new \Exception("expected {$class}, nothing was thrown");
}

function url($request)
{
    return $request[CURLOPT_URL];
}

function ticketAnswer()
{
    return FakeCurl::http(200, 'OK', '{"data":{"ticket":"PVE:root@pam:SECRETTICKET","CSRFPreventionToken":"SECRETCSRF","username":"root@pam"}}');
}

$tests = [

    'the reason of an HTTP error is read from the status line' => function () {
        FakeCurl::answer(function () {
            return FakeCurl::http(403, 'Permission check failed (/vms/100, VM.Snapshot)', '{"data":null}');
        });
        $result = (new PveClientBase('pve01'))->get('/nodes/pve01/qemu/100/snapshot');

        same(403, $result->getStatusCode());
        same('Permission check failed (/vms/100, VM.Snapshot)', $result->getReasonPhrase());
        same(false, $result->isSuccessStatusCode());
    },

    'the reason is read from the last status line, also without text (HTTP/2)' => function () {
        FakeCurl::answer(function () {
            $headers = "HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 500 no such VM ('999')\r\nContent-Type: application/json\r\n\r\n";
            return ['raw' => $headers . '{"data":null}', 'http_code' => 500, 'header_size' => strlen($headers), 'error' => ''];
        });
        same("no such VM ('999')", (new PveClientBase('pve01'))->get('/x')->getReasonPhrase());

        FakeCurl::answer(function () {
            return FakeCurl::http(200, '', '{"data":{}}', 'HTTP/2 200');
        });
        $result = (new PveClientBase('pve01'))->get('/version');
        same('', $result->getReasonPhrase());
        same(true, $result->isSuccessStatusCode());
    },

    'a request that gets no answer has status 0 and the reason of curl' => function () {
        FakeCurl::answer(function () {
            return FakeCurl::failure('Could not resolve host: pve01');
        });
        $result = (new PveClientBase('pve01'))->get('/version');

        same(0, $result->getStatusCode());
        same('Could not resolve host: pve01', $result->getReasonPhrase());
        same(false, $result->isSuccessStatusCode());
        same(false, $result->responseInError(), 'responseInError');
        same('', $result->getError(), 'getError');
    },

    'the same in array mode' => function () {
        FakeCurl::answer(function () {
            return FakeCurl::failure('Connection refused');
        });
        $client = new PveClientBase('pve01');
        $client->setResultIsObject(false);
        $result = $client->get('/version');

        same(false, $result->responseInError(), 'responseInError');
        same('', $result->getError(), 'getError');
    },

    'refused parameters are listed by getError, one per line' => function () {
        $respond = function () {
            return FakeCurl::http(400, 'Parameter verification failed.', '{"data":null,"errors":{"vmid":"invalid format","name":"too long"}}');
        };
        foreach ([true, false] as $asObject) {
            FakeCurl::answer($respond);
            $client = new PveClientBase('pve01');
            $client->setResultIsObject($asObject);
            $result = $client->set('/nodes/pve01/qemu/abc/config', ['name' => 'x']);

            same(true, $result->responseInError(), 'responseInError');
            same("vmid : invalid format\nname : too long", $result->getError(), $asObject ? 'object mode' : 'array mode');
        }
    },

    'an error answer without errors is not in error for responseInError' => function () {
        foreach ([true, false] as $asObject) {
            FakeCurl::answer(function () {
                return FakeCurl::http(500, 'no such VM', '{"data":null}');
            });
            $client = new PveClientBase('pve01');
            $client->setResultIsObject($asObject);
            same(false, $client->get('/x')->responseInError());
        }
    },

    'login stores the ticket and sends it with the next request' => function () {
        FakeCurl::answer(function ($options) {
            return strpos(url($options), '/access/ticket') !== false ? ticketAnswer() : FakeCurl::http(200, 'OK', '{"data":{}}');
        });
        $client = new PveClientBase('pve01');

        same(true, $client->login('root@pam', 'pw'));
        $client->get('/version');
        same('PVEAuthCookie=PVE:root@pam:SECRETTICKET', FakeCurl::$requests[1][CURLOPT_COOKIE]);
        same(true, in_array('CSRFPreventionToken: SECRETCSRF', FakeCurl::$requests[1][CURLOPT_HTTPHEADER]));
    },

    'a login answered without a ticket is not a login' => function () {
        foreach (['<html>proxy login</html>', '', '{"data":null}', '{"data":{}}'] as $body) {
            FakeCurl::answer(function () use ($body) {
                return FakeCurl::http(200, 'OK', $body);
            });
            same(false, (new PveClientBase('pve01'))->login('root@pam', 'pw'), "body '{$body}':");
        }
    },

    'login reads the realm after the last @' => function () {
        $sent = function ($user, $realm = null) {
            FakeCurl::answer(function () {
                return ticketAnswer();
            });
            $client = new PveClientBase('pve01');
            $realm === null ? $client->login($user, 'pw') : $client->login($user, 'pw', $realm);
            $body = json_decode(FakeCurl::$requests[0][CURLOPT_POSTFIELDS], true);
            return $body['username'] . ' ' . $body['realm'];
        };

        same('root pam', $sent('root'));
        same('admin pve', $sent('admin@pve'));
        same('admin pve', $sent('admin', 'pve'));
        same('john@example.com ldap', $sent('john@example.com@ldap'));
    },

    'login keeps the result mode of the caller' => function () {
        FakeCurl::answer(function () {
            return FakeCurl::http(200, 'OK', '{"data":{"NeedTFA":1,"ticket":"PVE:!tfa!x"}}');
        });
        $client = new PveClientBase('pve01');
        $client->setResultIsObject(false);

        throws(PveExceptionAuthentication::class, function () use ($client) {
            $client->login('root@pam', 'pw');
        });
        same(false, $client->isResultObject());
    },

    'the host name of the certificate is checked when the certificate is validated' => function () {
        FakeCurl::answer(function () {
            return FakeCurl::http(200, 'OK', '{"data":{}}');
        });
        $client = new PveClientBase('pve01');

        $client->get('/version');
        same(false, (bool) FakeCurl::$requests[0][CURLOPT_SSL_VERIFYPEER]);
        same(0, (int) FakeCurl::$requests[0][CURLOPT_SSL_VERIFYHOST]);

        $client->setValidateCertificate(true);
        $client->get('/version');
        same(true, (bool) FakeCurl::$requests[1][CURLOPT_SSL_VERIFYPEER]);
        same(2, (int) FakeCurl::$requests[1][CURLOPT_SSL_VERIFYHOST]);
    },

    'the debug output does not show the ticket and the CSRF token' => function () {
        FakeCurl::answer(function () {
            return ticketAnswer();
        });
        $client = new PveClientBase('pve01');
        $client->setDebugLevel(2);

        ob_start();
        try {
            $client->login('root@pam', 'SECRETPASSWORD');
        } finally {
            $output = ob_get_clean();
        }

        foreach (['SECRETTICKET', 'SECRETCSRF', 'SECRETPASSWORD'] as $secret) {
            same(false, strpos($output, $secret), "the output shows {$secret}:");
        }
        same(true, strpos($output, 'root@pam') !== false, 'values that are not secret are still shown');
    },

    'the debug output does not show the value of a new API token' => function () {
        foreach ([true, false] as $resultIsObject) {
            $client = new PveClientBase('pve01');
            $client->setDebugLevel(2)->setResultIsObject($resultIsObject);
            $show = function ($body) use ($client) {
                FakeCurl::answer(function () use ($body) {
                    return FakeCurl::http(200, 'OK', $body);
                });
                ob_start();
                try {
                    $client->create('/access/users/automation@pve/token/app');
                } finally {
                    return ob_get_clean();
                }
            };

            $output = $show('{"data":{"full-tokenid":"automation@pve!app","info":{"privsep":1},"value":"SECRETVALUE"}}');
            same(false, strpos($output, 'SECRETVALUE'), 'the output shows the value of the token:');
            same(true, strpos($output, 'privsep') !== false, 'values that are not secret are still shown');

            //a member named value of any other answer is not a secret
            $output = $show('{"data":{"key":"keyboard","value":"PLAINVALUE"}}');
            same(true, strpos($output, 'PLAINVALUE') !== false, 'a value that is not a token is shown');
        }
    },

    'debug level 1 prints a parameter that is an array' => function () {
        FakeCurl::answer(function () {
            return FakeCurl::http(200, 'OK', '{"data":null}');
        });
        $client = new PveClientBase('pve01');
        $client->setDebugLevel(1);

        ob_start();
        try {
            $client->set('/nodes/pve01/qemu/100/config', ['tags' => ['a', 'b']]);
        } finally {
            $output = ob_get_clean();
        }
        same(true, strpos($output, 'tags : ["a","b"]') !== false);
    },

    'parameters that cannot be encoded are refused, not sent as an empty body' => function () {
        FakeCurl::answer(function () {
            return FakeCurl::http(200, 'OK', '{"data":null}');
        });
        $client = new PveClientBase('pve01');

        foreach (['set', 'create'] as $method) {
            throws(\InvalidArgumentException::class, function () use ($client, $method) {
                $client->$method('/nodes/pve01/qemu/100/config', ['description' => "caff\xe8"]);
            }, 'JSON');
        }
        same(0, count(FakeCurl::$requests), 'requests sent:');
    },

    'the timeout is also the connection timeout' => function () {
        FakeCurl::answer(function () {
            return FakeCurl::http(200, 'OK', '{"data":{}}');
        });
        $client = new PveClientBase('pve01');

        $client->get('/version');
        same(false, isset(FakeCurl::$requests[0][CURLOPT_TIMEOUT]), 'no timeout by default:');
        same(false, isset(FakeCurl::$requests[0][CURLOPT_CONNECTTIMEOUT]), 'no connection timeout by default:');

        $client->setTimeout(5);
        $client->get('/version');
        same(5, FakeCurl::$requests[1][CURLOPT_TIMEOUT]);
        same(5, FakeCurl::$requests[1][CURLOPT_CONNECTTIMEOUT]);
    },

    'a task id that is not a UPID is refused before any request' => function () {
        FakeCurl::answer(function () {
            return FakeCurl::http(200, 'OK', '{"data":{"status":"stopped"}}');
        });
        $client = new PveClientBase('pve01');

        foreach ([null, '', 'abc', 100] as $task) {
            foreach (['taskIsRunning', 'getExitStatusTask', 'waitForTaskToFinish'] as $method) {
                throws(PveResultException::class, function () use ($client, $method, $task) {
                    $client->$method($task);
                }, 'not a valid task');
            }
        }
        same(0, count(FakeCurl::$requests), 'requests sent:');
    },

    'task status: running, exit status, wait' => function () {
        $status = '{"data":{"status":"running"}}';
        FakeCurl::answer(function () use (&$status) {
            return FakeCurl::http(200, 'OK', $status);
        });
        $client = new PveClientBase('pve01');

        same(true, $client->taskIsRunning(UPID));
        same(null, $client->getExitStatusTask(UPID));
        same(true, $client->waitForTaskToFinish(UPID, 5, 20), 'still running at the timeout:');

        $status = '{"data":{"status":"stopped","exitstatus":"OK"}}';
        same(false, $client->taskIsRunning(UPID));
        same('OK', $client->getExitStatusTask(UPID));
        same(false, $client->waitForTaskToFinish(UPID, 5, 20), 'finished:');
    },

    'a task status that cannot be read is reported with its reason' => function () {
        FakeCurl::answer(function () {
            return FakeCurl::http(403, 'Permission check failed (/nodes/pve01, Sys.Audit)', '{"data":null}');
        });
        throws(PveResultException::class, function () {
            (new PveClientBase('pve01'))->taskIsRunning(UPID);
        }, '(403 Permission check failed (/nodes/pve01, Sys.Audit))');
    },

    'png: a chart is returned as a data URI, an error keeps status, reason and errors' => function () {
        $client = new PveClientBase('pve01');
        $client->setResponseType('png');

        FakeCurl::answer(function () {
            return FakeCurl::http(200, 'OK', "\x89PNG\r\n\x1a\n\xff\x00");
        });
        $result = $client->get('/nodes/pve01/rrd', ['ds' => 'cpu', 'timeframe' => 'day']);
        same(true, strpos(url(FakeCurl::$requests[0]), 'https://pve01:8006/api2/png/nodes/pve01/rrd?') === 0);
        same('data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\n\xff\x00"), $result->getResponse());
        same(false, $result->responseInError());

        FakeCurl::answer(function () {
            return FakeCurl::http(400, 'Parameter verification failed.', '{"data":null,"errors":{"ds":"invalid"}}');
        });
        $result = $client->get('/nodes/pve01/rrd', ['ds' => 'x', 'timeframe' => 'day']);
        same(400, $result->getStatusCode());
        same('Parameter verification failed.', $result->getReasonPhrase());
        same('ds : invalid', $result->getError());
    },

    'png: login and task status are still read as json' => function () {
        FakeCurl::answer(function ($options) {
            return strpos(url($options), '/access/ticket') !== false
                ? ticketAnswer()
                : FakeCurl::http(200, 'OK', '{"data":{"status":"stopped","exitstatus":"OK"}}');
        });
        $client = new PveClientBase('pve01');
        $client->setResponseType('png');

        same(true, $client->login('root@pam', 'pw'));
        same('OK', $client->getExitStatusTask(UPID));
        foreach (FakeCurl::$requests as $request) {
            same(true, strpos(url($request), 'https://pve01:8006/api2/json/') === 0, url($request));
        }
        same('png', $client->getResponseType());
    },

    'the exceptions can be created on every supported PHP version' => function () {
        $inner = new \Exception('inner');
        same($inner, (new PveResultException(null, 'message', 0, $inner))->getPrevious());
        same(null, (new PveExceptionAuthentication(null, 'message'))->getPrevious());
    },
];

$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "ok      {$name}\n";
    } catch (\Throwable $e) {
        $failed++;
        echo "FAILED  {$name}\n        " . get_class($e) . ': ' . $e->getMessage()
            . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
    }
}

echo "\n" . count($tests) . ' tests, ' . (count($tests) - $failed) . " passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
