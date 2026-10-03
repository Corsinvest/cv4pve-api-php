# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
The major and minor version follow Proxmox VE (9.2.x targets Proxmox VE 9.2); the patch number can include breaking changes, listed under "Changed (breaking)".

## [9.2.4] - 2026-10-03

### Added
- Documentation site at [corsinvest.github.io/cv4pve-api-php](https://corsinvest.github.io/cv4pve-api-php/), built with Astro Starlight and published by the new `Docs` workflow; the pages are rewritten on the current client and replace the Markdown files of `docs/` ([#53](https://github.com/Corsinvest/cv4pve-api-php/pull/53))
- Icon of the project (`icon.svg`) ([#53](https://github.com/Corsinvest/cv4pve-api-php/pull/53))
- Tests: offline tests of the generated client in `tests/GeneratedClientTest.php`, run by the `Test` workflow; tests on a real Proxmox VE in `tests/LiveTest.php` (`PVE_HOST`, `PVE_API_TOKEN`, `PVE_TEST_VMID`). The old `tests/test.php` is removed ([#54](https://github.com/Corsinvest/cv4pve-api-php/pull/54))

### Changed
- `README.md` rewritten, with links to the documentation site ([#53](https://github.com/Corsinvest/cv4pve-api-php/pull/53))
- The Composer package no longer contains `docs/` ([#53](https://github.com/Corsinvest/cv4pve-api-php/pull/53))

### Fixed
- Debug level 2 printed the value of a new API token, the answer of `POST /access/users/{userid}/token/{tokenid}`, unmasked ([#55](https://github.com/Corsinvest/cv4pve-api-php/pull/55))

## [9.2.3] - 2026-10-03

### Added
- Api: Ceph health mute, `getCluster()->getCeph()->getHealthMute()`: `healthMuteIndex()` and `get($code)->healthMute($value, $sticky, $ttl)` ([#51](https://github.com/Corsinvest/cv4pve-api-php/pull/51))
- Api: Ceph rolling restart, `restartBulk` on `/cluster/ceph/restart-bulk` and `/nodes/{node}/ceph/restart-bulk` ([#51](https://github.com/Corsinvest/cv4pve-api-php/pull/51))
- Api: Ceph releases, `releases()` on `/nodes/{node}/ceph/releases` ([#51](https://github.com/Corsinvest/cv4pve-api-php/pull/51))
- Api: `/nodes/{node}/journal` `journal` has the new filters `identifiers`, `kernel`, `priority`, `service`, `structured`, `unit`, `units` ([#51](https://github.com/Corsinvest/cv4pve-api-php/pull/51))
- Tests: offline tests of the hand-written classes in `tests/BaseTest.php` (the `curl_*` functions replaced by fakes, no cluster and no dependency needed), run by the new `Test` workflow on PHP 7.4, 8.3 and 8.4 ([#50](https://github.com/Corsinvest/cv4pve-api-php/pull/50))

### Changed (breaking)
- Api: path parameters no longer repeated as method arguments: drop `$route_map_id` from `getRouteMapEntry`, `deleteRouteMapEntry`, `updateRouteMapEntry`, `listRouteMapEntriesForRouteMap` and `$pci_id_or_mapping` from `pciIndex`, `mdevscan`. The value comes from `get(...)` ([#51](https://github.com/Corsinvest/cv4pve-api-php/pull/51))
- Api: `/cluster/ha/rules` parameter order changed, so old calls with positional arguments send the wrong values: `createRule($rule, $type, $resources, ...)` was `createRule($resources, $rule, $type, ...)`; `updateRule($type, $delete, $digest, $affinity, $comment, ...)` was `updateRule($type, $affinity, $comment, $delete, $digest, ...)` ([#51](https://github.com/Corsinvest/cv4pve-api-php/pull/51))
- Api: `journal` has the new filters between the old parameters, so the position of `$lastentries`, `$since`, `$startcursor`, `$until` changed: `journal($endcursor, $identifiers, $kernel, $lastentries, $priority, $service, $since, $startcursor, $structured, $unit, $units, $until)` was `journal($endcursor, $lastentries, $since, $startcursor, $until)` ([#51](https://github.com/Corsinvest/cv4pve-api-php/pull/51))

### Changed
- The timeout, when set, is also the connection timeout (`CURLOPT_CONNECTTIMEOUT`). Without a timeout nothing changes ([#50](https://github.com/Corsinvest/cv4pve-api-php/pull/50))
- Api: the `<` and `>` characters in the comments of the methods are escaped once (`&lt;`), they were escaped twice (`&amp;lt;`) ([#51](https://github.com/Corsinvest/cv4pve-api-php/pull/51))
- CI: the GitHub release body is the section of `CHANGELOG.md` for the tag version ([#52](https://github.com/Corsinvest/cv4pve-api-php/pull/52))
- CI: workflow permissions and job timeouts declared ([#48](https://github.com/Corsinvest/cv4pve-api-php/pull/48))

### Fixed
- Login with a second factor never worked on Proxmox VE 7+ (TOTP, WebAuthn, recovery keys): the answer to the challenge is now sent in a second call with `tfa-challenge`; a code without a type is sent as `totp:<code>`. `tfa-challenge` is masked in the debug output ([#49](https://github.com/Corsinvest/cv4pve-api-php/pull/49))
- `taskIsRunning`, `getExitStatusTask`: a status that cannot be read (node down, missing privilege) throws `PveResultException` with the HTTP status and the Proxmox VE error, instead of being taken for a finished task. They also work with `setResultIsObject(false)` ([#49](https://github.com/Corsinvest/cv4pve-api-php/pull/49))
- `waitForTaskToFinish` no longer reads the status and waits once more after the task is finished ([#49](https://github.com/Corsinvest/cv4pve-api-php/pull/49))
- `getReasonPhrase()` was always empty for an HTTP answer: it is now read from the status line of the response (for example `Permission check failed (/vms/100, VM.Snapshot)`); without an answer it is the error of curl, as before ([#50](https://github.com/Corsinvest/cv4pve-api-php/pull/50))
- `responseInError()` and `getError()` threw `TypeError` on PHP 8 when there was no response; in array mode `getError()` always returned an empty string; the lines were joined with the two characters `\n` instead of a new line ([#50](https://github.com/Corsinvest/cv4pve-api-php/pull/50))
- `login()` returned `true` on a 200 answer without a ticket (the page of a proxy, an empty body); it now returns `false`. The realm is read after the last `@`. The result mode of the caller is restored on every path ([#50](https://github.com/Corsinvest/cv4pve-api-php/pull/50))
- The host name of the certificate was never checked, also with `setValidateCertificate(true)`: `CURLOPT_SSL_VERIFYHOST` now follows the setting ([#50](https://github.com/Corsinvest/cv4pve-api-php/pull/50))
- Debug level 2 printed the ticket and the CSRF token of the login answer unmasked; debug level 1 with a parameter that is an array raised "Array to string conversion" ([#50](https://github.com/Corsinvest/cv4pve-api-php/pull/50))
- Parameters that cannot be encoded as JSON (text that is not UTF-8) were lost and the request was sent with an empty body; it is now an `InvalidArgumentException` before anything is sent ([#50](https://github.com/Corsinvest/cv4pve-api-php/pull/50))
- A task id that is not valid (null, empty, not a UPID) gave warnings and a useless request; it is now a `PveResultException` before any request ([#50](https://github.com/Corsinvest/cv4pve-api-php/pull/50))
- PNG response type: `login()` and the task functions failed and an error answer was wrapped as an image. Login and task status are always read as JSON and an error answer keeps status, reason and errors ([#50](https://github.com/Corsinvest/cv4pve-api-php/pull/50))
- The exceptions raised a deprecation on PHP 8.4 when loaded (implicitly nullable parameter) ([#50](https://github.com/Corsinvest/cv4pve-api-php/pull/50))

## [9.2.2] and earlier

See [GitHub releases](https://github.com/Corsinvest/cv4pve-api-php/releases).
