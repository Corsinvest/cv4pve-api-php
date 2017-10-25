<?php

namespace EnterpriseVE\ProxmoxVE\Api {
    abstract class Base
    {
        protected $client;

        /**
         * @return Client
         */
        protected function getClient()
        {
            return $this->client;
        }

        protected function addIndexedParameter(&$params, $name, $values)
        {
            if ($values == null) {
                return;
            }
            foreach ($values as $key => $value) {
                $params[$name . $key] = $value;
            }
        }
    }

    /**
     * Result request API
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class Result
    {
        private $reasonPhrase;
        private $statusCode;
        private $response;
        private $resultIsObject;

        function __construct($response, $statusCode, $reasonPhrase, $resultIsObject)
        {
            $this->statusCode = $statusCode;
            $this->reasonPhrase = $reasonPhrase;
            $this->response = $response;
            $this->resultIsObject = $resultIsObject;
        }

        /**
         * ProxmoxVE response.
         * @return mixed
         */
        function getResponse()
        {
            return $this->response;
        }

        /**
         * Contains the values of status codes defined for HTTP.
         * @return int
         */
        function getStatusCode()
        {
            return $this->statusCode;
        }

        /**
         * Gets the reason phrase which typically is sent by servers together with the status code.
         * @return string
         */
        function getReasonPhrase()
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
         * Get if response ProxmoxVE contain errors
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
         * Get error
         * @return string
         */
        public function getError()
        {
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

    /**
     * Class Client
     * @package EnterpriseVE\ProxmoxVE\Api
     *
     * ProxmoxVE Client
     */
    class Client extends Base
    {
        private $ticketCSRFPreventionToken;
        private $ticketPVEAuthCookie;
        private $hostName;
        private $port;
        private $resultIsObject = true;

        function __construct($hostName, $port = 8006)
        {
            $this->hostName = $hostName;
            $this->port = $port;
            $this->client = $this;
        }

        function getResultIsObject()
        {
            return $this->resultIsObject;
        }

        function setResultIsObject($resultIsObject)
        {
            $this->resultIsObject = $resultIsObject;
        }

        public function getHostName()
        {
            return $this->hostName;
        }

        public function getPort()
        {
            return $this->port;
        }

        /**
         * Creation ticket from login.
         * @param string $userName user name or <username>@<relam>
         * @param string $password
         * @param string $realm pam/pve or custom
         * @return bool logged
         */
        function login($userName, $password, $realm = "pam")
        {
            $uData = explode("@", $userName);
            if (count($uData) > 1) {
                $userName = $uData[0];
                $realm = $uData[1];
            }
            $oldResultIsObject = $this->getResultIsObject();
            $this->setResultIsObject(true);
            $result = $this->getAccess()
                ->getTicket()
                ->createRest($password, $userName, null, null, null, $realm);
            $this->setResultIsObject($oldResultIsObject);
            if ($result->isSuccessStatusCode()) {
                $this->ticketCSRFPreventionToken = $result->getResponse()->data->CSRFPreventionToken;
                $this->ticketPVEAuthCookie = $result->getResponse()->data->ticket;
                return true;
            }
            return false;
        }

        public function get($resource, $params = [])
        {
            return $this->executeAction($resource, 'GET', $params);
        }

        public function set($resource, $params = [])
        {
            return $this->executeAction($resource, 'PUT', $params);
        }

        public function create($resource, $params = [])
        {
            return $this->executeAction($resource, 'POST', $params);
        }

        public function delete($resource, $params = [])
        {
            return $this->executeAction($resource, 'DELETE', $params);
        }

        private function executeAction($resource, $method, $params = [])
        {
            $response = $this->requestResource($resource, $method, $params);
            return new Result($response->json(['object' => $this->resultIsObject]),
                $response->getStatusCode(),
                $response->getReasonPhrase(),
                $this->resultIsObject);
        }

        private function requestResource($resource, $method, $params = [])
        {
            $url = "https://{$this->getHostName()}:{$this->getPort()}/api2/json{$resource}";
            $cookies = [];
            $headers = [];
            if ($this->ticketPVEAuthCookie != null) {
                $cookies = ['PVEAuthCookie' => $this->ticketPVEAuthCookie];
                $headers = ['CSRFPreventionToken' => $this->ticketCSRFPreventionToken];
            }
            //remove null params
            $params = array_filter($params, function ($value) {
                return $value !== null;
            });
            $httpClient = new \GuzzleHttp\Client();
            switch ($method) {
                case 'GET':
                    return $httpClient->get($url, [
                        'verify' => false,
                        'exceptions' => false,
                        'cookies' => $cookies,
                        'query' => $params,
                    ]);
                case 'POST':
                    return $httpClient->post($url, [
                        'verify' => false,
                        'exceptions' => false,
                        'cookies' => $cookies,
                        'headers' => $headers,
                        'body' => $params,
                    ]);
                case 'PUT':
                    return $httpClient->put($url, [
                        'verify' => false,
                        'exceptions' => false,
                        'cookies' => $cookies,
                        'headers' => $headers,
                        'body' => $params,
                    ]);
                case 'DELETE':
                    return $httpClient->delete($url, [
                        'verify' => false,
                        'exceptions' => false,
                        'cookies' => $cookies,
                        'headers' => $headers,
                        'body' => $params,
                    ]);
            }
        }

        /**
         * Wait for task to finish
         * @param string $node Node identifier
         * @param string $task Task identifier
         * @param int $wait Millisecond wait next check
         * @param int $timeOut Millisecond timeout
         */
        function waitForTaskToFinish($node, $task, $wait = 500, $timeOut = 10000)
        {
            $isRunning = true;
            if ($wait <= 0) {
                $wait = 500;
            }
            if ($timeOut < $wait) {
                $timeOut = $wait + 5000;
            }
            $timeStart = time();
            $waitTime = time();
            while ($isRunning && ($timeStart - time()) < $timeOut) {
                if ((time() - $waitTime) >= $wait) {
                    $waitTime = time();
                    $isRunning = $this->getNodes()->get($node)->getTasks()->
                        get($task)->getStatus()->getRest()->getResponse()->data == "running";
                }
            }
        }

        private $cluster;

        public function getCluster()
        {
            return $this->cluster ?: ($this->cluster = new PVECluster($this->client));
        }

        private $nodes;

        public function getNodes()
        {
            return $this->nodes ?: ($this->nodes = new PVENodes($this->client));
        }

        private $storage;

        public function getStorage()
        {
            return $this->storage ?: ($this->storage = new PVEStorage($this->client));
        }

        private $access;

        public function getAccess()
        {
            return $this->access ?: ($this->access = new PVEAccess($this->client));
        }

        private $pools;

        public function getPools()
        {
            return $this->pools ?: ($this->pools = new PVEPools($this->client));
        }

        private $version;

        public function getVersion()
        {
            return $this->version ?: ($this->version = new PVEVersion($this->client));
        }
    }

    class PVECluster extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        private $replication;

        public function getReplication()
        {
            return $this->replication ?: ($this->replication = new PVEClusterReplication($this->client));
        }

        private $config;

        public function getConfig()
        {
            return $this->config ?: ($this->config = new PVEClusterConfig($this->client));
        }

        private $firewall;

        public function getFirewall()
        {
            return $this->firewall ?: ($this->firewall = new PVEClusterFirewall($this->client));
        }

        private $backup;

        public function getBackup()
        {
            return $this->backup ?: ($this->backup = new PVEClusterBackup($this->client));
        }

        private $ha;

        public function getHa()
        {
            return $this->ha ?: ($this->ha = new PVEClusterHa($this->client));
        }

        private $log;

        public function getLog()
        {
            return $this->log ?: ($this->log = new PVEClusterLog($this->client));
        }

        private $resources;

        public function getResources()
        {
            return $this->resources ?: ($this->resources = new PVEClusterResources($this->client));
        }

        private $tasks;

        public function getTasks()
        {
            return $this->tasks ?: ($this->tasks = new PVEClusterTasks($this->client));
        }

        private $options;

        public function getOptions()
        {
            return $this->options ?: ($this->options = new PVEClusterOptions($this->client));
        }

        private $status;

        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVEClusterStatus($this->client));
        }

        private $nextid;

        public function getNextid()
        {
            return $this->nextid ?: ($this->nextid = new PVEClusterNextid($this->client));
        }

        /**
         * Cluster index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster");
        }

        /**
         * Cluster index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEClusterReplication extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($id)
        {
            return new PVEItemReplicationClusterId($this->client, $id);
        }

        /**
         * List replication jobs.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/replication");
        }

        /**
         * List replication jobs.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Create a new replication job
         * @param string $id Replication Job ID. The ID is composed of a Guest ID and a job number, separated by a hyphen, i.e. '&amp;lt;GUEST&amp;gt;-&amp;lt;JOBNUM&amp;gt;'.
         * @param string $target Target node.
         * @param string $type Section type.
         *   Enum: local
         * @param string $comment Description.
         * @param bool $disable Flag to disable/deactivate the entry.
         * @param int $rate Rate limit in mbps (megabytes per second) as floating point number.
         * @param string $remove_job Mark the replication job for removal. The job will remove all local replication snapshots. When set to 'full', it also tries to remove replicated volumes on the target. The job then removes itself from the configuration file.
         *   Enum: local,full
         * @param string $schedule Storage replication schedule. The format is a subset of `systemd` calender events.
         * @return Result
         */
        public function createRest($id, $target, $type, $comment = null, $disable = null, $rate = null, $remove_job = null, $schedule = null)
        {
            $params = ['id' => $id,
                'target' => $target,
                'type' => $type,
                'comment' => $comment,
                'disable' => $disable,
                'rate' => $rate,
                'remove_job' => $remove_job,
                'schedule' => $schedule];
            return $this->getClient()->create("/cluster/replication", $params);
        }

        /**
         * Create a new replication job
         * @param string $id Replication Job ID. The ID is composed of a Guest ID and a job number, separated by a hyphen, i.e. '&amp;lt;GUEST&amp;gt;-&amp;lt;JOBNUM&amp;gt;'.
         * @param string $target Target node.
         * @param string $type Section type.
         *   Enum: local
         * @param string $comment Description.
         * @param bool $disable Flag to disable/deactivate the entry.
         * @param int $rate Rate limit in mbps (megabytes per second) as floating point number.
         * @param string $remove_job Mark the replication job for removal. The job will remove all local replication snapshots. When set to 'full', it also tries to remove replicated volumes on the target. The job then removes itself from the configuration file.
         *   Enum: local,full
         * @param string $schedule Storage replication schedule. The format is a subset of `systemd` calender events.
         * @return Result
         */
        public function create($id, $target, $type, $comment = null, $disable = null, $rate = null, $remove_job = null, $schedule = null)
        {
            return $this->createRest($id, $target, $type, $comment, $disable, $rate, $remove_job, $schedule);
        }
    }

    class PVEItemReplicationClusterId extends Base
    {
        private $id;

        function __construct($client, $id)
        {
            $this->client = $client;
            $this->id = $id;
        }

        /**
         * Mark replication job for removal.
         * @param bool $force Will remove the jobconfig entry, but will not cleanup.
         * @param bool $keep Keep replicated data at target (do not remove).
         * @return Result
         */
        public function deleteRest($force = null, $keep = null)
        {
            $params = ['force' => $force,
                'keep' => $keep];
            return $this->getClient()->delete("/cluster/replication/{$this->id}", $params);
        }

        /**
         * Mark replication job for removal.
         * @param bool $force Will remove the jobconfig entry, but will not cleanup.
         * @param bool $keep Keep replicated data at target (do not remove).
         * @return Result
         */
        public function delete($force = null, $keep = null)
        {
            return $this->deleteRest($force, $keep);
        }

        /**
         * Read replication job configuration.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/replication/{$this->id}");
        }

        /**
         * Read replication job configuration.
         * @return Result
         */
        public function read()
        {
            return $this->getRest();
        }

        /**
         * Update replication job configuration.
         * @param string $comment Description.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $disable Flag to disable/deactivate the entry.
         * @param int $rate Rate limit in mbps (megabytes per second) as floating point number.
         * @param string $remove_job Mark the replication job for removal. The job will remove all local replication snapshots. When set to 'full', it also tries to remove replicated volumes on the target. The job then removes itself from the configuration file.
         *   Enum: local,full
         * @param string $schedule Storage replication schedule. The format is a subset of `systemd` calender events.
         * @return Result
         */
        public function setRest($comment = null, $delete = null, $digest = null, $disable = null, $rate = null, $remove_job = null, $schedule = null)
        {
            $params = ['comment' => $comment,
                'delete' => $delete,
                'digest' => $digest,
                'disable' => $disable,
                'rate' => $rate,
                'remove_job' => $remove_job,
                'schedule' => $schedule];
            return $this->getClient()->set("/cluster/replication/{$this->id}", $params);
        }

        /**
         * Update replication job configuration.
         * @param string $comment Description.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $disable Flag to disable/deactivate the entry.
         * @param int $rate Rate limit in mbps (megabytes per second) as floating point number.
         * @param string $remove_job Mark the replication job for removal. The job will remove all local replication snapshots. When set to 'full', it also tries to remove replicated volumes on the target. The job then removes itself from the configuration file.
         *   Enum: local,full
         * @param string $schedule Storage replication schedule. The format is a subset of `systemd` calender events.
         * @return Result
         */
        public function update($comment = null, $delete = null, $digest = null, $disable = null, $rate = null, $remove_job = null, $schedule = null)
        {
            return $this->setRest($comment, $delete, $digest, $disable, $rate, $remove_job, $schedule);
        }
    }

    class PVEClusterConfig extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        private $nodes;

        public function getNodes()
        {
            return $this->nodes ?: ($this->nodes = new PVEConfigClusterNodes($this->client));
        }

        private $totem;

        public function getTotem()
        {
            return $this->totem ?: ($this->totem = new PVEConfigClusterTotem($this->client));
        }

        /**
         * Directory index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/config");
        }

        /**
         * Directory index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEConfigClusterNodes extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Corosync node list.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/config/nodes");
        }

        /**
         * Corosync node list.
         * @return Result
         */
        public function nodes()
        {
            return $this->getRest();
        }
    }

    class PVEConfigClusterTotem extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get corosync totem protocol settings.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/config/totem");
        }

        /**
         * Get corosync totem protocol settings.
         * @return Result
         */
        public function totem()
        {
            return $this->getRest();
        }
    }

    class PVEClusterFirewall extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        private $groups;

        public function getGroups()
        {
            return $this->groups ?: ($this->groups = new PVEFirewallClusterGroups($this->client));
        }

        private $rules;

        public function getRules()
        {
            return $this->rules ?: ($this->rules = new PVEFirewallClusterRules($this->client));
        }

        private $ipset;

        public function getIpset()
        {
            return $this->ipset ?: ($this->ipset = new PVEFirewallClusterIpset($this->client));
        }

        private $aliases;

        public function getAliases()
        {
            return $this->aliases ?: ($this->aliases = new PVEFirewallClusterAliases($this->client));
        }

        private $options;

        public function getOptions()
        {
            return $this->options ?: ($this->options = new PVEFirewallClusterOptions($this->client));
        }

        private $macros;

        public function getMacros()
        {
            return $this->macros ?: ($this->macros = new PVEFirewallClusterMacros($this->client));
        }

        private $refs;

        public function getRefs()
        {
            return $this->refs ?: ($this->refs = new PVEFirewallClusterRefs($this->client));
        }

        /**
         * Directory index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall");
        }

        /**
         * Directory index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEFirewallClusterGroups extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($group)
        {
            return new PVEItemGroupsFirewallClusterGroup($this->client, $group);
        }

        /**
         * List security groups.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall/groups");
        }

        /**
         * List security groups.
         * @return Result
         */
        public function listSecurityGroups()
        {
            return $this->getRest();
        }

        /**
         * Create new security group.
         * @param string $group Security Group name.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename/update an existing security group. You can set 'rename' to the same value as 'name' to update the 'comment' of an existing group.
         * @return Result
         */
        public function createRest($group, $comment = null, $digest = null, $rename = null)
        {
            $params = ['group' => $group,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            return $this->getClient()->create("/cluster/firewall/groups", $params);
        }

        /**
         * Create new security group.
         * @param string $group Security Group name.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename/update an existing security group. You can set 'rename' to the same value as 'name' to update the 'comment' of an existing group.
         * @return Result
         */
        public function createSecurityGroup($group, $comment = null, $digest = null, $rename = null)
        {
            return $this->createRest($group, $comment, $digest, $rename);
        }
    }

    class PVEItemGroupsFirewallClusterGroup extends Base
    {
        private $group;

        function __construct($client, $group)
        {
            $this->client = $client;
            $this->group = $group;
        }

        public function get($pos)
        {
            return new PVEItemGroupGroupsFirewallClusterPos($this->client, $this->group, $pos);
        }

        /**
         * Delete security group.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/cluster/firewall/groups/{$this->group}");
        }

        /**
         * Delete security group.
         * @return Result
         */
        public function deleteSecurityGroup()
        {
            return $this->deleteRest();
        }

        /**
         * List rules.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall/groups/{$this->group}");
        }

        /**
         * List rules.
         * @return Result
         */
        public function getRules()
        {
            return $this->getRest();
        }

        /**
         * Create new rule.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @param string $comment Descriptive comment.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $pos Update rule at position &amp;lt;pos&amp;gt;.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @return Result
         */
        public function createRest($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            $params = ['action' => $action,
                'type' => $type,
                'comment' => $comment,
                'dest' => $dest,
                'digest' => $digest,
                'dport' => $dport,
                'enable' => $enable,
                'iface' => $iface,
                'macro' => $macro,
                'pos' => $pos,
                'proto' => $proto,
                'source' => $source,
                'sport' => $sport];
            return $this->getClient()->create("/cluster/firewall/groups/{$this->group}", $params);
        }

        /**
         * Create new rule.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @param string $comment Descriptive comment.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $pos Update rule at position &amp;lt;pos&amp;gt;.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @return Result
         */
        public function createRule($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            return $this->createRest($action, $type, $comment, $dest, $digest, $dport, $enable, $iface, $macro, $pos, $proto, $source, $sport);
        }
    }

    class PVEItemGroupGroupsFirewallClusterPos extends Base
    {
        private $group;
        private $pos;

        function __construct($client, $group, $pos)
        {
            $this->client = $client;
            $this->group = $group;
            $this->pos = $pos;
        }

        /**
         * Delete rule.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRest($digest = null)
        {
            $params = ['digest' => $digest];
            return $this->getClient()->delete("/cluster/firewall/groups/{$this->group}/{$this->pos}", $params);
        }

        /**
         * Delete rule.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRule($digest = null)
        {
            return $this->deleteRest($digest);
        }

        /**
         * Get single rule data.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall/groups/{$this->group}/{$this->pos}");
        }

        /**
         * Get single rule data.
         * @return Result
         */
        public function getRule()
        {
            return $this->getRest();
        }

        /**
         * Modify rule data.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $comment Descriptive comment.
         * @param string $delete A list of settings you want to delete.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $moveto Move rule to new position &amp;lt;moveto&amp;gt;. Other arguments are ignored.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @return Result
         */
        public function setRest($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            $params = ['action' => $action,
                'comment' => $comment,
                'delete' => $delete,
                'dest' => $dest,
                'digest' => $digest,
                'dport' => $dport,
                'enable' => $enable,
                'iface' => $iface,
                'macro' => $macro,
                'moveto' => $moveto,
                'proto' => $proto,
                'source' => $source,
                'sport' => $sport,
                'type' => $type];
            return $this->getClient()->set("/cluster/firewall/groups/{$this->group}/{$this->pos}", $params);
        }

        /**
         * Modify rule data.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $comment Descriptive comment.
         * @param string $delete A list of settings you want to delete.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $moveto Move rule to new position &amp;lt;moveto&amp;gt;. Other arguments are ignored.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @return Result
         */
        public function updateRule($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            return $this->setRest($action, $comment, $delete, $dest, $digest, $dport, $enable, $iface, $macro, $moveto, $proto, $source, $sport, $type);
        }
    }

    class PVEFirewallClusterRules extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($pos)
        {
            return new PVEItemRulesFirewallClusterPos($this->client, $pos);
        }

        /**
         * List rules.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall/rules");
        }

        /**
         * List rules.
         * @return Result
         */
        public function getRules()
        {
            return $this->getRest();
        }

        /**
         * Create new rule.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @param string $comment Descriptive comment.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $pos Update rule at position &amp;lt;pos&amp;gt;.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @return Result
         */
        public function createRest($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            $params = ['action' => $action,
                'type' => $type,
                'comment' => $comment,
                'dest' => $dest,
                'digest' => $digest,
                'dport' => $dport,
                'enable' => $enable,
                'iface' => $iface,
                'macro' => $macro,
                'pos' => $pos,
                'proto' => $proto,
                'source' => $source,
                'sport' => $sport];
            return $this->getClient()->create("/cluster/firewall/rules", $params);
        }

        /**
         * Create new rule.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @param string $comment Descriptive comment.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $pos Update rule at position &amp;lt;pos&amp;gt;.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @return Result
         */
        public function createRule($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            return $this->createRest($action, $type, $comment, $dest, $digest, $dport, $enable, $iface, $macro, $pos, $proto, $source, $sport);
        }
    }

    class PVEItemRulesFirewallClusterPos extends Base
    {
        private $pos;

        function __construct($client, $pos)
        {
            $this->client = $client;
            $this->pos = $pos;
        }

        /**
         * Delete rule.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRest($digest = null)
        {
            $params = ['digest' => $digest];
            return $this->getClient()->delete("/cluster/firewall/rules/{$this->pos}", $params);
        }

        /**
         * Delete rule.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRule($digest = null)
        {
            return $this->deleteRest($digest);
        }

        /**
         * Get single rule data.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall/rules/{$this->pos}");
        }

        /**
         * Get single rule data.
         * @return Result
         */
        public function getRule()
        {
            return $this->getRest();
        }

        /**
         * Modify rule data.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $comment Descriptive comment.
         * @param string $delete A list of settings you want to delete.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $moveto Move rule to new position &amp;lt;moveto&amp;gt;. Other arguments are ignored.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @return Result
         */
        public function setRest($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            $params = ['action' => $action,
                'comment' => $comment,
                'delete' => $delete,
                'dest' => $dest,
                'digest' => $digest,
                'dport' => $dport,
                'enable' => $enable,
                'iface' => $iface,
                'macro' => $macro,
                'moveto' => $moveto,
                'proto' => $proto,
                'source' => $source,
                'sport' => $sport,
                'type' => $type];
            return $this->getClient()->set("/cluster/firewall/rules/{$this->pos}", $params);
        }

        /**
         * Modify rule data.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $comment Descriptive comment.
         * @param string $delete A list of settings you want to delete.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $moveto Move rule to new position &amp;lt;moveto&amp;gt;. Other arguments are ignored.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @return Result
         */
        public function updateRule($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            return $this->setRest($action, $comment, $delete, $dest, $digest, $dport, $enable, $iface, $macro, $moveto, $proto, $source, $sport, $type);
        }
    }

    class PVEFirewallClusterIpset extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($name)
        {
            return new PVEItemIpsetFirewallClusterName($this->client, $name);
        }

        /**
         * List IPSets
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall/ipset");
        }

        /**
         * List IPSets
         * @return Result
         */
        public function ipsetIndex()
        {
            return $this->getRest();
        }

        /**
         * Create new IPSet
         * @param string $name IP set name.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename an existing IPSet. You can set 'rename' to the same value as 'name' to update the 'comment' of an existing IPSet.
         * @return Result
         */
        public function createRest($name, $comment = null, $digest = null, $rename = null)
        {
            $params = ['name' => $name,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            return $this->getClient()->create("/cluster/firewall/ipset", $params);
        }

        /**
         * Create new IPSet
         * @param string $name IP set name.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename an existing IPSet. You can set 'rename' to the same value as 'name' to update the 'comment' of an existing IPSet.
         * @return Result
         */
        public function createIpset($name, $comment = null, $digest = null, $rename = null)
        {
            return $this->createRest($name, $comment, $digest, $rename);
        }
    }

    class PVEItemIpsetFirewallClusterName extends Base
    {
        private $name;

        function __construct($client, $name)
        {
            $this->client = $client;
            $this->name = $name;
        }

        public function get($cidr)
        {
            return new PVEItemNameIpsetFirewallClusterCidr($this->client, $this->name, $cidr);
        }

        /**
         * Delete IPSet
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/cluster/firewall/ipset/{$this->name}");
        }

        /**
         * Delete IPSet
         * @return Result
         */
        public function deleteIpset()
        {
            return $this->deleteRest();
        }

        /**
         * List IPSet content
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall/ipset/{$this->name}");
        }

        /**
         * List IPSet content
         * @return Result
         */
        public function getIpset()
        {
            return $this->getRest();
        }

        /**
         * Add IP or Network to IPSet.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $comment
         * @param bool $nomatch
         * @return Result
         */
        public function createRest($cidr, $comment = null, $nomatch = null)
        {
            $params = ['cidr' => $cidr,
                'comment' => $comment,
                'nomatch' => $nomatch];
            return $this->getClient()->create("/cluster/firewall/ipset/{$this->name}", $params);
        }

        /**
         * Add IP or Network to IPSet.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $comment
         * @param bool $nomatch
         * @return Result
         */
        public function createIp($cidr, $comment = null, $nomatch = null)
        {
            return $this->createRest($cidr, $comment, $nomatch);
        }
    }

    class PVEItemNameIpsetFirewallClusterCidr extends Base
    {
        private $name;
        private $cidr;

        function __construct($client, $name, $cidr)
        {
            $this->client = $client;
            $this->name = $name;
            $this->cidr = $cidr;
        }

        /**
         * Remove IP or Network from IPSet.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRest($digest = null)
        {
            $params = ['digest' => $digest];
            return $this->getClient()->delete("/cluster/firewall/ipset/{$this->name}/{$this->cidr}", $params);
        }

        /**
         * Remove IP or Network from IPSet.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function removeIp($digest = null)
        {
            return $this->deleteRest($digest);
        }

        /**
         * Read IP or Network settings from IPSet.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall/ipset/{$this->name}/{$this->cidr}");
        }

        /**
         * Read IP or Network settings from IPSet.
         * @return Result
         */
        public function readIp()
        {
            return $this->getRest();
        }

        /**
         * Update IP or Network settings
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $nomatch
         * @return Result
         */
        public function setRest($comment = null, $digest = null, $nomatch = null)
        {
            $params = ['comment' => $comment,
                'digest' => $digest,
                'nomatch' => $nomatch];
            return $this->getClient()->set("/cluster/firewall/ipset/{$this->name}/{$this->cidr}", $params);
        }

        /**
         * Update IP or Network settings
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $nomatch
         * @return Result
         */
        public function updateIp($comment = null, $digest = null, $nomatch = null)
        {
            return $this->setRest($comment, $digest, $nomatch);
        }
    }

    class PVEFirewallClusterAliases extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($name)
        {
            return new PVEItemAliasesFirewallClusterName($this->client, $name);
        }

        /**
         * List aliases
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall/aliases");
        }

        /**
         * List aliases
         * @return Result
         */
        public function getAliases()
        {
            return $this->getRest();
        }

        /**
         * Create IP or Network Alias.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $name Alias name.
         * @param string $comment
         * @return Result
         */
        public function createRest($cidr, $name, $comment = null)
        {
            $params = ['cidr' => $cidr,
                'name' => $name,
                'comment' => $comment];
            return $this->getClient()->create("/cluster/firewall/aliases", $params);
        }

        /**
         * Create IP or Network Alias.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $name Alias name.
         * @param string $comment
         * @return Result
         */
        public function createAlias($cidr, $name, $comment = null)
        {
            return $this->createRest($cidr, $name, $comment);
        }
    }

    class PVEItemAliasesFirewallClusterName extends Base
    {
        private $name;

        function __construct($client, $name)
        {
            $this->client = $client;
            $this->name = $name;
        }

        /**
         * Remove IP or Network alias.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRest($digest = null)
        {
            $params = ['digest' => $digest];
            return $this->getClient()->delete("/cluster/firewall/aliases/{$this->name}", $params);
        }

        /**
         * Remove IP or Network alias.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function removeAlias($digest = null)
        {
            return $this->deleteRest($digest);
        }

        /**
         * Read alias.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall/aliases/{$this->name}");
        }

        /**
         * Read alias.
         * @return Result
         */
        public function readAlias()
        {
            return $this->getRest();
        }

        /**
         * Update IP or Network alias.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename an existing alias.
         * @return Result
         */
        public function setRest($cidr, $comment = null, $digest = null, $rename = null)
        {
            $params = ['cidr' => $cidr,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            return $this->getClient()->set("/cluster/firewall/aliases/{$this->name}", $params);
        }

        /**
         * Update IP or Network alias.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename an existing alias.
         * @return Result
         */
        public function updateAlias($cidr, $comment = null, $digest = null, $rename = null)
        {
            return $this->setRest($cidr, $comment, $digest, $rename);
        }
    }

    class PVEFirewallClusterOptions extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get Firewall options.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall/options");
        }

        /**
         * Get Firewall options.
         * @return Result
         */
        public function getOptions()
        {
            return $this->getRest();
        }

        /**
         * Set Firewall options.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param int $enable Enable or disable the firewall cluster wide.
         * @param string $policy_in Input policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param string $policy_out Output policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @return Result
         */
        public function setRest($delete = null, $digest = null, $enable = null, $policy_in = null, $policy_out = null)
        {
            $params = ['delete' => $delete,
                'digest' => $digest,
                'enable' => $enable,
                'policy_in' => $policy_in,
                'policy_out' => $policy_out];
            return $this->getClient()->set("/cluster/firewall/options", $params);
        }

        /**
         * Set Firewall options.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param int $enable Enable or disable the firewall cluster wide.
         * @param string $policy_in Input policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param string $policy_out Output policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @return Result
         */
        public function setOptions($delete = null, $digest = null, $enable = null, $policy_in = null, $policy_out = null)
        {
            return $this->setRest($delete, $digest, $enable, $policy_in, $policy_out);
        }
    }

    class PVEFirewallClusterMacros extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * List available macros
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/firewall/macros");
        }

        /**
         * List available macros
         * @return Result
         */
        public function getMacros()
        {
            return $this->getRest();
        }
    }

    class PVEFirewallClusterRefs extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Lists possible IPSet/Alias reference which are allowed in source/dest properties.
         * @param string $type Only list references of specified type.
         *   Enum: alias,ipset
         * @return Result
         */
        public function getRest($type = null)
        {
            $params = ['type' => $type];
            return $this->getClient()->get("/cluster/firewall/refs", $params);
        }

        /**
         * Lists possible IPSet/Alias reference which are allowed in source/dest properties.
         * @param string $type Only list references of specified type.
         *   Enum: alias,ipset
         * @return Result
         */
        public function refs($type = null)
        {
            return $this->getRest($type);
        }
    }

    class PVEClusterBackup extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($id)
        {
            return new PVEItemBackupClusterId($this->client, $id);
        }

        /**
         * List vzdump backup schedule.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/backup");
        }

        /**
         * List vzdump backup schedule.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Create new vzdump backup job.
         * @param string $starttime Job Start time.
         * @param bool $all Backup all known guest systems on this host.
         * @param int $bwlimit Limit I/O bandwidth (KBytes per second).
         * @param string $compress Compress dump file.
         *   Enum: 0,1,gzip,lzo
         * @param string $dow Day of week selection.
         * @param string $dumpdir Store resulting files to specified directory.
         * @param bool $enabled Enable or disable the job.
         * @param string $exclude Exclude specified guest systems (assumes --all)
         * @param string $exclude_path Exclude certain files/directories (shell globs).
         * @param int $ionice Set CFQ ionice priority.
         * @param int $lockwait Maximal time to wait for the global lock (minutes).
         * @param string $mailnotification Specify when to send an email
         *   Enum: always,failure
         * @param string $mailto Comma-separated list of email addresses that should receive email notifications.
         * @param int $maxfiles Maximal number of backup files per guest system.
         * @param string $mode Backup mode.
         *   Enum: snapshot,suspend,stop
         * @param string $node Only run if executed on this node.
         * @param int $pigz Use pigz instead of gzip when N&amp;gt;0. N=1 uses half of cores, N&amp;gt;1 uses N as thread count.
         * @param bool $quiet Be quiet.
         * @param bool $remove Remove old backup files if there are more than 'maxfiles' backup files.
         * @param string $script Use specified hook script.
         * @param int $size Unused, will be removed in a future release.
         * @param bool $stdexcludes Exclude temporary files and logs.
         * @param bool $stop Stop runnig backup jobs on this host.
         * @param int $stopwait Maximal time to wait until a guest system is stopped (minutes).
         * @param string $storage Store resulting file to this storage.
         * @param string $tmpdir Store temporary files to specified directory.
         * @param string $vmid The ID of the guest system you want to backup.
         * @return Result
         */
        public function createRest($starttime, $all = null, $bwlimit = null, $compress = null, $dow = null, $dumpdir = null, $enabled = null, $exclude = null, $exclude_path = null, $ionice = null, $lockwait = null, $mailnotification = null, $mailto = null, $maxfiles = null, $mode = null, $node = null, $pigz = null, $quiet = null, $remove = null, $script = null, $size = null, $stdexcludes = null, $stop = null, $stopwait = null, $storage = null, $tmpdir = null, $vmid = null)
        {
            $params = ['starttime' => $starttime,
                'all' => $all,
                'bwlimit' => $bwlimit,
                'compress' => $compress,
                'dow' => $dow,
                'dumpdir' => $dumpdir,
                'enabled' => $enabled,
                'exclude' => $exclude,
                'exclude-path' => $exclude_path,
                'ionice' => $ionice,
                'lockwait' => $lockwait,
                'mailnotification' => $mailnotification,
                'mailto' => $mailto,
                'maxfiles' => $maxfiles,
                'mode' => $mode,
                'node' => $node,
                'pigz' => $pigz,
                'quiet' => $quiet,
                'remove' => $remove,
                'script' => $script,
                'size' => $size,
                'stdexcludes' => $stdexcludes,
                'stop' => $stop,
                'stopwait' => $stopwait,
                'storage' => $storage,
                'tmpdir' => $tmpdir,
                'vmid' => $vmid];
            return $this->getClient()->create("/cluster/backup", $params);
        }

        /**
         * Create new vzdump backup job.
         * @param string $starttime Job Start time.
         * @param bool $all Backup all known guest systems on this host.
         * @param int $bwlimit Limit I/O bandwidth (KBytes per second).
         * @param string $compress Compress dump file.
         *   Enum: 0,1,gzip,lzo
         * @param string $dow Day of week selection.
         * @param string $dumpdir Store resulting files to specified directory.
         * @param bool $enabled Enable or disable the job.
         * @param string $exclude Exclude specified guest systems (assumes --all)
         * @param string $exclude_path Exclude certain files/directories (shell globs).
         * @param int $ionice Set CFQ ionice priority.
         * @param int $lockwait Maximal time to wait for the global lock (minutes).
         * @param string $mailnotification Specify when to send an email
         *   Enum: always,failure
         * @param string $mailto Comma-separated list of email addresses that should receive email notifications.
         * @param int $maxfiles Maximal number of backup files per guest system.
         * @param string $mode Backup mode.
         *   Enum: snapshot,suspend,stop
         * @param string $node Only run if executed on this node.
         * @param int $pigz Use pigz instead of gzip when N&amp;gt;0. N=1 uses half of cores, N&amp;gt;1 uses N as thread count.
         * @param bool $quiet Be quiet.
         * @param bool $remove Remove old backup files if there are more than 'maxfiles' backup files.
         * @param string $script Use specified hook script.
         * @param int $size Unused, will be removed in a future release.
         * @param bool $stdexcludes Exclude temporary files and logs.
         * @param bool $stop Stop runnig backup jobs on this host.
         * @param int $stopwait Maximal time to wait until a guest system is stopped (minutes).
         * @param string $storage Store resulting file to this storage.
         * @param string $tmpdir Store temporary files to specified directory.
         * @param string $vmid The ID of the guest system you want to backup.
         * @return Result
         */
        public function createJob($starttime, $all = null, $bwlimit = null, $compress = null, $dow = null, $dumpdir = null, $enabled = null, $exclude = null, $exclude_path = null, $ionice = null, $lockwait = null, $mailnotification = null, $mailto = null, $maxfiles = null, $mode = null, $node = null, $pigz = null, $quiet = null, $remove = null, $script = null, $size = null, $stdexcludes = null, $stop = null, $stopwait = null, $storage = null, $tmpdir = null, $vmid = null)
        {
            return $this->createRest($starttime, $all, $bwlimit, $compress, $dow, $dumpdir, $enabled, $exclude, $exclude_path, $ionice, $lockwait, $mailnotification, $mailto, $maxfiles, $mode, $node, $pigz, $quiet, $remove, $script, $size, $stdexcludes, $stop, $stopwait, $storage, $tmpdir, $vmid);
        }
    }

    class PVEItemBackupClusterId extends Base
    {
        private $id;

        function __construct($client, $id)
        {
            $this->client = $client;
            $this->id = $id;
        }

        /**
         * Delete vzdump backup job definition.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/cluster/backup/{$this->id}");
        }

        /**
         * Delete vzdump backup job definition.
         * @return Result
         */
        public function deleteJob()
        {
            return $this->deleteRest();
        }

        /**
         * Read vzdump backup job definition.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/backup/{$this->id}");
        }

        /**
         * Read vzdump backup job definition.
         * @return Result
         */
        public function readJob()
        {
            return $this->getRest();
        }

        /**
         * Update vzdump backup job definition.
         * @param string $starttime Job Start time.
         * @param bool $all Backup all known guest systems on this host.
         * @param int $bwlimit Limit I/O bandwidth (KBytes per second).
         * @param string $compress Compress dump file.
         *   Enum: 0,1,gzip,lzo
         * @param string $delete A list of settings you want to delete.
         * @param string $dow Day of week selection.
         * @param string $dumpdir Store resulting files to specified directory.
         * @param bool $enabled Enable or disable the job.
         * @param string $exclude Exclude specified guest systems (assumes --all)
         * @param string $exclude_path Exclude certain files/directories (shell globs).
         * @param int $ionice Set CFQ ionice priority.
         * @param int $lockwait Maximal time to wait for the global lock (minutes).
         * @param string $mailnotification Specify when to send an email
         *   Enum: always,failure
         * @param string $mailto Comma-separated list of email addresses that should receive email notifications.
         * @param int $maxfiles Maximal number of backup files per guest system.
         * @param string $mode Backup mode.
         *   Enum: snapshot,suspend,stop
         * @param string $node Only run if executed on this node.
         * @param int $pigz Use pigz instead of gzip when N&amp;gt;0. N=1 uses half of cores, N&amp;gt;1 uses N as thread count.
         * @param bool $quiet Be quiet.
         * @param bool $remove Remove old backup files if there are more than 'maxfiles' backup files.
         * @param string $script Use specified hook script.
         * @param int $size Unused, will be removed in a future release.
         * @param bool $stdexcludes Exclude temporary files and logs.
         * @param bool $stop Stop runnig backup jobs on this host.
         * @param int $stopwait Maximal time to wait until a guest system is stopped (minutes).
         * @param string $storage Store resulting file to this storage.
         * @param string $tmpdir Store temporary files to specified directory.
         * @param string $vmid The ID of the guest system you want to backup.
         * @return Result
         */
        public function setRest($starttime, $all = null, $bwlimit = null, $compress = null, $delete = null, $dow = null, $dumpdir = null, $enabled = null, $exclude = null, $exclude_path = null, $ionice = null, $lockwait = null, $mailnotification = null, $mailto = null, $maxfiles = null, $mode = null, $node = null, $pigz = null, $quiet = null, $remove = null, $script = null, $size = null, $stdexcludes = null, $stop = null, $stopwait = null, $storage = null, $tmpdir = null, $vmid = null)
        {
            $params = ['starttime' => $starttime,
                'all' => $all,
                'bwlimit' => $bwlimit,
                'compress' => $compress,
                'delete' => $delete,
                'dow' => $dow,
                'dumpdir' => $dumpdir,
                'enabled' => $enabled,
                'exclude' => $exclude,
                'exclude-path' => $exclude_path,
                'ionice' => $ionice,
                'lockwait' => $lockwait,
                'mailnotification' => $mailnotification,
                'mailto' => $mailto,
                'maxfiles' => $maxfiles,
                'mode' => $mode,
                'node' => $node,
                'pigz' => $pigz,
                'quiet' => $quiet,
                'remove' => $remove,
                'script' => $script,
                'size' => $size,
                'stdexcludes' => $stdexcludes,
                'stop' => $stop,
                'stopwait' => $stopwait,
                'storage' => $storage,
                'tmpdir' => $tmpdir,
                'vmid' => $vmid];
            return $this->getClient()->set("/cluster/backup/{$this->id}", $params);
        }

        /**
         * Update vzdump backup job definition.
         * @param string $starttime Job Start time.
         * @param bool $all Backup all known guest systems on this host.
         * @param int $bwlimit Limit I/O bandwidth (KBytes per second).
         * @param string $compress Compress dump file.
         *   Enum: 0,1,gzip,lzo
         * @param string $delete A list of settings you want to delete.
         * @param string $dow Day of week selection.
         * @param string $dumpdir Store resulting files to specified directory.
         * @param bool $enabled Enable or disable the job.
         * @param string $exclude Exclude specified guest systems (assumes --all)
         * @param string $exclude_path Exclude certain files/directories (shell globs).
         * @param int $ionice Set CFQ ionice priority.
         * @param int $lockwait Maximal time to wait for the global lock (minutes).
         * @param string $mailnotification Specify when to send an email
         *   Enum: always,failure
         * @param string $mailto Comma-separated list of email addresses that should receive email notifications.
         * @param int $maxfiles Maximal number of backup files per guest system.
         * @param string $mode Backup mode.
         *   Enum: snapshot,suspend,stop
         * @param string $node Only run if executed on this node.
         * @param int $pigz Use pigz instead of gzip when N&amp;gt;0. N=1 uses half of cores, N&amp;gt;1 uses N as thread count.
         * @param bool $quiet Be quiet.
         * @param bool $remove Remove old backup files if there are more than 'maxfiles' backup files.
         * @param string $script Use specified hook script.
         * @param int $size Unused, will be removed in a future release.
         * @param bool $stdexcludes Exclude temporary files and logs.
         * @param bool $stop Stop runnig backup jobs on this host.
         * @param int $stopwait Maximal time to wait until a guest system is stopped (minutes).
         * @param string $storage Store resulting file to this storage.
         * @param string $tmpdir Store temporary files to specified directory.
         * @param string $vmid The ID of the guest system you want to backup.
         * @return Result
         */
        public function updateJob($starttime, $all = null, $bwlimit = null, $compress = null, $delete = null, $dow = null, $dumpdir = null, $enabled = null, $exclude = null, $exclude_path = null, $ionice = null, $lockwait = null, $mailnotification = null, $mailto = null, $maxfiles = null, $mode = null, $node = null, $pigz = null, $quiet = null, $remove = null, $script = null, $size = null, $stdexcludes = null, $stop = null, $stopwait = null, $storage = null, $tmpdir = null, $vmid = null)
        {
            return $this->setRest($starttime, $all, $bwlimit, $compress, $delete, $dow, $dumpdir, $enabled, $exclude, $exclude_path, $ionice, $lockwait, $mailnotification, $mailto, $maxfiles, $mode, $node, $pigz, $quiet, $remove, $script, $size, $stdexcludes, $stop, $stopwait, $storage, $tmpdir, $vmid);
        }
    }

    class PVEClusterHa extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        private $resources;

        public function getResources()
        {
            return $this->resources ?: ($this->resources = new PVEHaClusterResources($this->client));
        }

        private $groups;

        public function getGroups()
        {
            return $this->groups ?: ($this->groups = new PVEHaClusterGroups($this->client));
        }

        private $status;

        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVEHaClusterStatus($this->client));
        }

        /**
         * Directory index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/ha");
        }

        /**
         * Directory index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEHaClusterResources extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($sid)
        {
            return new PVEItemResourcesHaClusterSid($this->client, $sid);
        }

        /**
         * List HA resources.
         * @param string $type Only list resources of specific type
         *   Enum: ct,vm
         * @return Result
         */
        public function getRest($type = null)
        {
            $params = ['type' => $type];
            return $this->getClient()->get("/cluster/ha/resources", $params);
        }

        /**
         * List HA resources.
         * @param string $type Only list resources of specific type
         *   Enum: ct,vm
         * @return Result
         */
        public function index($type = null)
        {
            return $this->getRest($type);
        }

        /**
         * Create a new HA resource.
         * @param string $sid HA resource ID. This consists of a resource type followed by a resource specific name, separated with colon (example: vm:100 / ct:100). For virtual machines and containers, you can simply use the VM or CT id as a shortcut (example: 100).
         * @param string $comment Description.
         * @param string $group The HA group identifier.
         * @param int $max_relocate Maximal number of service relocate tries when a service failes to start.
         * @param int $max_restart Maximal number of tries to restart the service on a node after its start failed.
         * @param string $state Requested resource state.
         *   Enum: started,stopped,enabled,disabled,ignored
         * @param string $type Resource type.
         *   Enum: ct,vm
         * @return Result
         */
        public function createRest($sid, $comment = null, $group = null, $max_relocate = null, $max_restart = null, $state = null, $type = null)
        {
            $params = ['sid' => $sid,
                'comment' => $comment,
                'group' => $group,
                'max_relocate' => $max_relocate,
                'max_restart' => $max_restart,
                'state' => $state,
                'type' => $type];
            return $this->getClient()->create("/cluster/ha/resources", $params);
        }

        /**
         * Create a new HA resource.
         * @param string $sid HA resource ID. This consists of a resource type followed by a resource specific name, separated with colon (example: vm:100 / ct:100). For virtual machines and containers, you can simply use the VM or CT id as a shortcut (example: 100).
         * @param string $comment Description.
         * @param string $group The HA group identifier.
         * @param int $max_relocate Maximal number of service relocate tries when a service failes to start.
         * @param int $max_restart Maximal number of tries to restart the service on a node after its start failed.
         * @param string $state Requested resource state.
         *   Enum: started,stopped,enabled,disabled,ignored
         * @param string $type Resource type.
         *   Enum: ct,vm
         * @return Result
         */
        public function create($sid, $comment = null, $group = null, $max_relocate = null, $max_restart = null, $state = null, $type = null)
        {
            return $this->createRest($sid, $comment, $group, $max_relocate, $max_restart, $state, $type);
        }
    }

    class PVEItemResourcesHaClusterSid extends Base
    {
        private $sid;

        function __construct($client, $sid)
        {
            $this->client = $client;
            $this->sid = $sid;
        }

        private $migrate;

        public function getMigrate()
        {
            return $this->migrate ?: ($this->migrate = new PVESidResourcesHaClusterMigrate($this->client, $this->sid));
        }

        private $relocate;

        public function getRelocate()
        {
            return $this->relocate ?: ($this->relocate = new PVESidResourcesHaClusterRelocate($this->client, $this->sid));
        }

        /**
         * Delete resource configuration.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/cluster/ha/resources/{$this->sid}");
        }

        /**
         * Delete resource configuration.
         * @return Result
         */
        public function delete()
        {
            return $this->deleteRest();
        }

        /**
         * Read resource configuration.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/ha/resources/{$this->sid}");
        }

        /**
         * Read resource configuration.
         * @return Result
         */
        public function read()
        {
            return $this->getRest();
        }

        /**
         * Update resource configuration.
         * @param string $comment Description.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $group The HA group identifier.
         * @param int $max_relocate Maximal number of service relocate tries when a service failes to start.
         * @param int $max_restart Maximal number of tries to restart the service on a node after its start failed.
         * @param string $state Requested resource state.
         *   Enum: started,stopped,enabled,disabled,ignored
         * @return Result
         */
        public function setRest($comment = null, $delete = null, $digest = null, $group = null, $max_relocate = null, $max_restart = null, $state = null)
        {
            $params = ['comment' => $comment,
                'delete' => $delete,
                'digest' => $digest,
                'group' => $group,
                'max_relocate' => $max_relocate,
                'max_restart' => $max_restart,
                'state' => $state];
            return $this->getClient()->set("/cluster/ha/resources/{$this->sid}", $params);
        }

        /**
         * Update resource configuration.
         * @param string $comment Description.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $group The HA group identifier.
         * @param int $max_relocate Maximal number of service relocate tries when a service failes to start.
         * @param int $max_restart Maximal number of tries to restart the service on a node after its start failed.
         * @param string $state Requested resource state.
         *   Enum: started,stopped,enabled,disabled,ignored
         * @return Result
         */
        public function update($comment = null, $delete = null, $digest = null, $group = null, $max_relocate = null, $max_restart = null, $state = null)
        {
            return $this->setRest($comment, $delete, $digest, $group, $max_relocate, $max_restart, $state);
        }
    }

    class PVESidResourcesHaClusterMigrate extends Base
    {
        private $sid;

        function __construct($client, $sid)
        {
            $this->client = $client;
            $this->sid = $sid;
        }

        /**
         * Request resource migration (online) to another node.
         * @param string $node The cluster node name.
         * @return Result
         */
        public function createRest($node)
        {
            $params = ['node' => $node];
            return $this->getClient()->create("/cluster/ha/resources/{$this->sid}/migrate", $params);
        }

        /**
         * Request resource migration (online) to another node.
         * @param string $node The cluster node name.
         * @return Result
         */
        public function migrate($node)
        {
            return $this->createRest($node);
        }
    }

    class PVESidResourcesHaClusterRelocate extends Base
    {
        private $sid;

        function __construct($client, $sid)
        {
            $this->client = $client;
            $this->sid = $sid;
        }

        /**
         * Request resource relocatzion to another node. This stops the service on the old node, and restarts it on the target node.
         * @param string $node The cluster node name.
         * @return Result
         */
        public function createRest($node)
        {
            $params = ['node' => $node];
            return $this->getClient()->create("/cluster/ha/resources/{$this->sid}/relocate", $params);
        }

        /**
         * Request resource relocatzion to another node. This stops the service on the old node, and restarts it on the target node.
         * @param string $node The cluster node name.
         * @return Result
         */
        public function relocate($node)
        {
            return $this->createRest($node);
        }
    }

    class PVEHaClusterGroups extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($group)
        {
            return new PVEItemGroupsHaClusterGroup($this->client, $group);
        }

        /**
         * Get HA groups.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/ha/groups");
        }

        /**
         * Get HA groups.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Create a new HA group.
         * @param string $group The HA group identifier.
         * @param string $nodes List of cluster node names with optional priority.
         * @param string $comment Description.
         * @param bool $nofailback The CRM tries to run services on the node with the highest priority. If a node with higher priority comes online, the CRM migrates the service to that node. Enabling nofailback prevents that behavior.
         * @param bool $restricted Resources bound to restricted groups may only run on nodes defined by the group.
         * @param string $type Group type.
         *   Enum: group
         * @return Result
         */
        public function createRest($group, $nodes, $comment = null, $nofailback = null, $restricted = null, $type = null)
        {
            $params = ['group' => $group,
                'nodes' => $nodes,
                'comment' => $comment,
                'nofailback' => $nofailback,
                'restricted' => $restricted,
                'type' => $type];
            return $this->getClient()->create("/cluster/ha/groups", $params);
        }

        /**
         * Create a new HA group.
         * @param string $group The HA group identifier.
         * @param string $nodes List of cluster node names with optional priority.
         * @param string $comment Description.
         * @param bool $nofailback The CRM tries to run services on the node with the highest priority. If a node with higher priority comes online, the CRM migrates the service to that node. Enabling nofailback prevents that behavior.
         * @param bool $restricted Resources bound to restricted groups may only run on nodes defined by the group.
         * @param string $type Group type.
         *   Enum: group
         * @return Result
         */
        public function create($group, $nodes, $comment = null, $nofailback = null, $restricted = null, $type = null)
        {
            return $this->createRest($group, $nodes, $comment, $nofailback, $restricted, $type);
        }
    }

    class PVEItemGroupsHaClusterGroup extends Base
    {
        private $group;

        function __construct($client, $group)
        {
            $this->client = $client;
            $this->group = $group;
        }

        /**
         * Delete ha group configuration.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/cluster/ha/groups/{$this->group}");
        }

        /**
         * Delete ha group configuration.
         * @return Result
         */
        public function delete()
        {
            return $this->deleteRest();
        }

        /**
         * Read ha group configuration.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/ha/groups/{$this->group}");
        }

        /**
         * Read ha group configuration.
         * @return Result
         */
        public function read()
        {
            return $this->getRest();
        }

        /**
         * Update ha group configuration.
         * @param string $comment Description.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $nodes List of cluster node names with optional priority.
         * @param bool $nofailback The CRM tries to run services on the node with the highest priority. If a node with higher priority comes online, the CRM migrates the service to that node. Enabling nofailback prevents that behavior.
         * @param bool $restricted Resources bound to restricted groups may only run on nodes defined by the group.
         * @return Result
         */
        public function setRest($comment = null, $delete = null, $digest = null, $nodes = null, $nofailback = null, $restricted = null)
        {
            $params = ['comment' => $comment,
                'delete' => $delete,
                'digest' => $digest,
                'nodes' => $nodes,
                'nofailback' => $nofailback,
                'restricted' => $restricted];
            return $this->getClient()->set("/cluster/ha/groups/{$this->group}", $params);
        }

        /**
         * Update ha group configuration.
         * @param string $comment Description.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $nodes List of cluster node names with optional priority.
         * @param bool $nofailback The CRM tries to run services on the node with the highest priority. If a node with higher priority comes online, the CRM migrates the service to that node. Enabling nofailback prevents that behavior.
         * @param bool $restricted Resources bound to restricted groups may only run on nodes defined by the group.
         * @return Result
         */
        public function update($comment = null, $delete = null, $digest = null, $nodes = null, $nofailback = null, $restricted = null)
        {
            return $this->setRest($comment, $delete, $digest, $nodes, $nofailback, $restricted);
        }
    }

    class PVEHaClusterStatus extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        private $current;

        public function getCurrent()
        {
            return $this->current ?: ($this->current = new PVEStatusHaClusterCurrent($this->client));
        }

        private $managerStatus;

        public function getManagerStatus()
        {
            return $this->managerStatus ?: ($this->managerStatus = new PVEStatusHaClusterManagerStatus($this->client));
        }

        /**
         * Directory index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/ha/status");
        }

        /**
         * Directory index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEStatusHaClusterCurrent extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get HA manger status.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/ha/status/current");
        }

        /**
         * Get HA manger status.
         * @return Result
         */
        public function status()
        {
            return $this->getRest();
        }
    }

    class PVEStatusHaClusterManagerStatus extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get full HA manger status, including LRM status.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/ha/status/manager_status");
        }

        /**
         * Get full HA manger status, including LRM status.
         * @return Result
         */
        public function managerStatus()
        {
            return $this->getRest();
        }
    }

    class PVEClusterLog extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Read cluster log
         * @param int $max Maximum number of entries.
         * @return Result
         */
        public function getRest($max = null)
        {
            $params = ['max' => $max];
            return $this->getClient()->get("/cluster/log", $params);
        }

        /**
         * Read cluster log
         * @param int $max Maximum number of entries.
         * @return Result
         */
        public function log($max = null)
        {
            return $this->getRest($max);
        }
    }

    class PVEClusterResources extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Resources index (cluster wide).
         * @param string $type
         *   Enum: vm,storage,node
         * @return Result
         */
        public function getRest($type = null)
        {
            $params = ['type' => $type];
            return $this->getClient()->get("/cluster/resources", $params);
        }

        /**
         * Resources index (cluster wide).
         * @param string $type
         *   Enum: vm,storage,node
         * @return Result
         */
        public function resources($type = null)
        {
            return $this->getRest($type);
        }
    }

    class PVEClusterTasks extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * List recent tasks (cluster wide).
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/tasks");
        }

        /**
         * List recent tasks (cluster wide).
         * @return Result
         */
        public function tasks()
        {
            return $this->getRest();
        }
    }

    class PVEClusterOptions extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get datacenter options.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/options");
        }

        /**
         * Get datacenter options.
         * @return Result
         */
        public function getOptions()
        {
            return $this->getRest();
        }

        /**
         * Set datacenter options.
         * @param string $console Select the default Console viewer. You can either use the builtin java applet (VNC), an external virt-viewer comtatible application (SPICE), or an HTML5 based viewer (noVNC).
         *   Enum: applet,vv,html5
         * @param string $delete A list of settings you want to delete.
         * @param string $email_from Specify email address to send notification from (default is root@$hostname)
         * @param string $fencing Set the fencing mode of the HA cluster. Hardware mode needs a valid configuration of fence devices in /etc/pve/ha/fence.cfg. With both all two modes are used.  WARNING: 'hardware' and 'both' are EXPERIMENTAL &amp; WIP
         *   Enum: watchdog,hardware,both
         * @param string $http_proxy Specify external http proxy which is used for downloads (example: 'http://username:password@host:port/')
         * @param string $keyboard Default keybord layout for vnc server.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param string $language Default GUI language.
         *   Enum: en,de
         * @param string $mac_prefix Prefix for autogenerated MAC addresses.
         * @param int $max_workers Defines how many workers (per node) are maximal started  on actions like 'stopall VMs' or task from the ha-manager.
         * @param string $migration For cluster wide migration settings.
         * @param bool $migration_unsecure Migration is secure using SSH tunnel by default. For secure private networks you can disable it to speed up migration. Deprecated, use the 'migration' property instead!
         * @return Result
         */
        public function setRest($console = null, $delete = null, $email_from = null, $fencing = null, $http_proxy = null, $keyboard = null, $language = null, $mac_prefix = null, $max_workers = null, $migration = null, $migration_unsecure = null)
        {
            $params = ['console' => $console,
                'delete' => $delete,
                'email_from' => $email_from,
                'fencing' => $fencing,
                'http_proxy' => $http_proxy,
                'keyboard' => $keyboard,
                'language' => $language,
                'mac_prefix' => $mac_prefix,
                'max_workers' => $max_workers,
                'migration' => $migration,
                'migration_unsecure' => $migration_unsecure];
            return $this->getClient()->set("/cluster/options", $params);
        }

        /**
         * Set datacenter options.
         * @param string $console Select the default Console viewer. You can either use the builtin java applet (VNC), an external virt-viewer comtatible application (SPICE), or an HTML5 based viewer (noVNC).
         *   Enum: applet,vv,html5
         * @param string $delete A list of settings you want to delete.
         * @param string $email_from Specify email address to send notification from (default is root@$hostname)
         * @param string $fencing Set the fencing mode of the HA cluster. Hardware mode needs a valid configuration of fence devices in /etc/pve/ha/fence.cfg. With both all two modes are used.  WARNING: 'hardware' and 'both' are EXPERIMENTAL &amp; WIP
         *   Enum: watchdog,hardware,both
         * @param string $http_proxy Specify external http proxy which is used for downloads (example: 'http://username:password@host:port/')
         * @param string $keyboard Default keybord layout for vnc server.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param string $language Default GUI language.
         *   Enum: en,de
         * @param string $mac_prefix Prefix for autogenerated MAC addresses.
         * @param int $max_workers Defines how many workers (per node) are maximal started  on actions like 'stopall VMs' or task from the ha-manager.
         * @param string $migration For cluster wide migration settings.
         * @param bool $migration_unsecure Migration is secure using SSH tunnel by default. For secure private networks you can disable it to speed up migration. Deprecated, use the 'migration' property instead!
         * @return Result
         */
        public function setOptions($console = null, $delete = null, $email_from = null, $fencing = null, $http_proxy = null, $keyboard = null, $language = null, $mac_prefix = null, $max_workers = null, $migration = null, $migration_unsecure = null)
        {
            return $this->setRest($console, $delete, $email_from, $fencing, $http_proxy, $keyboard, $language, $mac_prefix, $max_workers, $migration, $migration_unsecure);
        }
    }

    class PVEClusterStatus extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get cluster status informations.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/status");
        }

        /**
         * Get cluster status informations.
         * @return Result
         */
        public function getStatus()
        {
            return $this->getRest();
        }
    }

    class PVEClusterNextid extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get next free VMID. If you pass an VMID it will raise an error if the ID is already used.
         * @param int $vmid The (unique) ID of the VM.
         * @return Result
         */
        public function getRest($vmid = null)
        {
            $params = ['vmid' => $vmid];
            return $this->getClient()->get("/cluster/nextid", $params);
        }

        /**
         * Get next free VMID. If you pass an VMID it will raise an error if the ID is already used.
         * @param int $vmid The (unique) ID of the VM.
         * @return Result
         */
        public function nextid($vmid = null)
        {
            return $this->getRest($vmid);
        }
    }

    class PVENodes extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($node)
        {
            return new PVEItemNodesNode($this->client, $node);
        }

        /**
         * Cluster node index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes");
        }

        /**
         * Cluster node index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEItemNodesNode extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        private $qemu;

        public function getQemu()
        {
            return $this->qemu ?: ($this->qemu = new PVENodeNodesQemu($this->client, $this->node));
        }

        private $lxc;

        public function getLxc()
        {
            return $this->lxc ?: ($this->lxc = new PVENodeNodesLxc($this->client, $this->node));
        }

        private $ceph;

        public function getCeph()
        {
            return $this->ceph ?: ($this->ceph = new PVENodeNodesCeph($this->client, $this->node));
        }

        private $vzdump;

        public function getVzdump()
        {
            return $this->vzdump ?: ($this->vzdump = new PVENodeNodesVzdump($this->client, $this->node));
        }

        private $services;

        public function getServices()
        {
            return $this->services ?: ($this->services = new PVENodeNodesServices($this->client, $this->node));
        }

        private $subscription;

        public function getSubscription()
        {
            return $this->subscription ?: ($this->subscription = new PVENodeNodesSubscription($this->client, $this->node));
        }

        private $network;

        public function getNetwork()
        {
            return $this->network ?: ($this->network = new PVENodeNodesNetwork($this->client, $this->node));
        }

        private $tasks;

        public function getTasks()
        {
            return $this->tasks ?: ($this->tasks = new PVENodeNodesTasks($this->client, $this->node));
        }

        private $scan;

        public function getScan()
        {
            return $this->scan ?: ($this->scan = new PVENodeNodesScan($this->client, $this->node));
        }

        private $storage;

        public function getStorage()
        {
            return $this->storage ?: ($this->storage = new PVENodeNodesStorage($this->client, $this->node));
        }

        private $disks;

        public function getDisks()
        {
            return $this->disks ?: ($this->disks = new PVENodeNodesDisks($this->client, $this->node));
        }

        private $apt;

        public function getApt()
        {
            return $this->apt ?: ($this->apt = new PVENodeNodesApt($this->client, $this->node));
        }

        private $firewall;

        public function getFirewall()
        {
            return $this->firewall ?: ($this->firewall = new PVENodeNodesFirewall($this->client, $this->node));
        }

        private $replication;

        public function getReplication()
        {
            return $this->replication ?: ($this->replication = new PVENodeNodesReplication($this->client, $this->node));
        }

        private $version;

        public function getVersion()
        {
            return $this->version ?: ($this->version = new PVENodeNodesVersion($this->client, $this->node));
        }

        private $status;

        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVENodeNodesStatus($this->client, $this->node));
        }

        private $netstat;

        public function getNetstat()
        {
            return $this->netstat ?: ($this->netstat = new PVENodeNodesNetstat($this->client, $this->node));
        }

        private $execute;

        public function getExecute()
        {
            return $this->execute ?: ($this->execute = new PVENodeNodesExecute($this->client, $this->node));
        }

        private $rrd;

        public function getRrd()
        {
            return $this->rrd ?: ($this->rrd = new PVENodeNodesRrd($this->client, $this->node));
        }

        private $rrddata;

        public function getRrddata()
        {
            return $this->rrddata ?: ($this->rrddata = new PVENodeNodesRrddata($this->client, $this->node));
        }

        private $syslog;

        public function getSyslog()
        {
            return $this->syslog ?: ($this->syslog = new PVENodeNodesSyslog($this->client, $this->node));
        }

        private $vncshell;

        public function getVncshell()
        {
            return $this->vncshell ?: ($this->vncshell = new PVENodeNodesVncshell($this->client, $this->node));
        }

        private $vncwebsocket;

        public function getVncwebsocket()
        {
            return $this->vncwebsocket ?: ($this->vncwebsocket = new PVENodeNodesVncwebsocket($this->client, $this->node));
        }

        private $spiceshell;

        public function getSpiceshell()
        {
            return $this->spiceshell ?: ($this->spiceshell = new PVENodeNodesSpiceshell($this->client, $this->node));
        }

        private $dns;

        public function getDns()
        {
            return $this->dns ?: ($this->dns = new PVENodeNodesDns($this->client, $this->node));
        }

        private $time;

        public function getTime()
        {
            return $this->time ?: ($this->time = new PVENodeNodesTime($this->client, $this->node));
        }

        private $aplinfo;

        public function getAplinfo()
        {
            return $this->aplinfo ?: ($this->aplinfo = new PVENodeNodesAplinfo($this->client, $this->node));
        }

        private $report;

        public function getReport()
        {
            return $this->report ?: ($this->report = new PVENodeNodesReport($this->client, $this->node));
        }

        private $startall;

        public function getStartall()
        {
            return $this->startall ?: ($this->startall = new PVENodeNodesStartall($this->client, $this->node));
        }

        private $stopall;

        public function getStopall()
        {
            return $this->stopall ?: ($this->stopall = new PVENodeNodesStopall($this->client, $this->node));
        }

        private $migrateall;

        public function getMigrateall()
        {
            return $this->migrateall ?: ($this->migrateall = new PVENodeNodesMigrateall($this->client, $this->node));
        }

        /**
         * Node index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}");
        }

        /**
         * Node index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVENodeNodesQemu extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($vmid)
        {
            return new PVEItemQemuNodeNodesVmid($this->client, $this->node, $vmid);
        }

        /**
         * Virtual machine index (per node).
         * @param bool $full Determine the full status of active VMs.
         * @return Result
         */
        public function getRest($full = null)
        {
            $params = ['full' => $full];
            return $this->getClient()->get("/nodes/{$this->node}/qemu", $params);
        }

        /**
         * Virtual machine index (per node).
         * @param bool $full Determine the full status of active VMs.
         * @return Result
         */
        public function vmlist($full = null)
        {
            return $this->getRest($full);
        }

        /**
         * Create or restore a virtual machine.
         * @param int $vmid The (unique) ID of the VM.
         * @param bool $acpi Enable/disable ACPI.
         * @param bool $agent Enable/disable Qemu GuestAgent.
         * @param string $archive The backup file.
         * @param string $args Arbitrary arguments passed to kvm.
         * @param bool $autostart Automatic restart after crash (currently ignored).
         * @param int $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param string $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param string $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param string $bootdisk Enable booting from specified disk.
         * @param string $cdrom This is an alias for option -ide2
         * @param int $cores The number of cores per socket.
         * @param string $cpu Emulated CPU type.
         * @param int $cpulimit Limit of CPU usage.
         * @param int $cpuunits CPU weight for a VM.
         * @param string $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param bool $force Allow to overwrite existing VM.
         * @param bool $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param array $hostpciN Map host PCI devices into guest.
         * @param string $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param string $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param array $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param string $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.conf' configuration file.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param bool $kvm Enable/disable KVM hardware virtualization.
         * @param bool $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param string $machine Specific the Qemu machine type.
         * @param int $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param int $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param int $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param string $name Set a name for the VM. Only used on the configuration web interface.
         * @param array $netN Specify network devices.
         * @param bool $numa Enable/disable NUMA.
         * @param array $numaN NUMA topology.
         * @param bool $onboot Specifies whether a VM will be started during system bootup.
         * @param string $ostype Specify guest operating system.
         *   Enum: other,wxp,w2k,w2k3,w2k8,wvista,win7,win8,win10,l24,l26,solaris
         * @param array $parallelN Map host parallel devices (n is 0 to 2).
         * @param string $pool Add the VM to the specified pool.
         * @param bool $protection Sets the protection flag of the VM. This will disable the remove VM and remove disk operations.
         * @param bool $reboot Allow reboot. If set to '0' the VM exit on reboot.
         * @param array $sataN Use volume as SATA hard disk or CD-ROM (n is 0 to 5).
         * @param array $scsiN Use volume as SCSI hard disk or CD-ROM (n is 0 to 13).
         * @param string $scsihw SCSI controller model
         *   Enum: lsi,lsi53c810,virtio-scsi-pci,virtio-scsi-single,megasas,pvscsi
         * @param array $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param int $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning
         * @param string $smbios1 Specify SMBIOS type 1 fields.
         * @param int $smp The number of CPUs. Please use option -sockets instead.
         * @param int $sockets The number of CPU sockets.
         * @param string $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param string $storage Default storage.
         * @param bool $tablet Enable/disable the USB tablet device.
         * @param bool $tdf Enable/disable time drift fix.
         * @param bool $template Enable/disable Template.
         * @param bool $unique Assign a unique random ethernet address.
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param array $usbN Configure an USB device (n is 0 to 4).
         * @param int $vcpus Number of hotplugged vcpus.
         * @param string $vga Select the VGA type.
         *   Enum: std,cirrus,vmware,qxl,serial0,serial1,serial2,serial3,qxl2,qxl3,qxl4
         * @param array $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param string $vmstatestorage Default storage for VM state volumes/files.
         * @param string $watchdog Create a virtual hardware watchdog device.
         * @return Result
         */
        public function createRest($vmid, $acpi = null, $agent = null, $archive = null, $args = null, $autostart = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $description = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $pool = null, $protection = null, $reboot = null, $sataN = null, $scsiN = null, $scsihw = null, $serialN = null, $shares = null, $smbios1 = null, $smp = null, $sockets = null, $startdate = null, $startup = null, $storage = null, $tablet = null, $tdf = null, $template = null, $unique = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $vmstatestorage = null, $watchdog = null)
        {
            $params = ['vmid' => $vmid,
                'acpi' => $acpi,
                'agent' => $agent,
                'archive' => $archive,
                'args' => $args,
                'autostart' => $autostart,
                'balloon' => $balloon,
                'bios' => $bios,
                'boot' => $boot,
                'bootdisk' => $bootdisk,
                'cdrom' => $cdrom,
                'cores' => $cores,
                'cpu' => $cpu,
                'cpulimit' => $cpulimit,
                'cpuunits' => $cpuunits,
                'description' => $description,
                'force' => $force,
                'freeze' => $freeze,
                'hotplug' => $hotplug,
                'hugepages' => $hugepages,
                'keyboard' => $keyboard,
                'kvm' => $kvm,
                'localtime' => $localtime,
                'lock' => $lock,
                'machine' => $machine,
                'memory' => $memory,
                'migrate_downtime' => $migrate_downtime,
                'migrate_speed' => $migrate_speed,
                'name' => $name,
                'numa' => $numa,
                'onboot' => $onboot,
                'ostype' => $ostype,
                'pool' => $pool,
                'protection' => $protection,
                'reboot' => $reboot,
                'scsihw' => $scsihw,
                'shares' => $shares,
                'smbios1' => $smbios1,
                'smp' => $smp,
                'sockets' => $sockets,
                'startdate' => $startdate,
                'startup' => $startup,
                'storage' => $storage,
                'tablet' => $tablet,
                'tdf' => $tdf,
                'template' => $template,
                'unique' => $unique,
                'vcpus' => $vcpus,
                'vga' => $vga,
                'vmstatestorage' => $vmstatestorage,
                'watchdog' => $watchdog];
            $this->addIndexedParameter($params, 'hostpci', $hostpciN);
            $this->addIndexedParameter($params, 'ide', $ideN);
            $this->addIndexedParameter($params, 'net', $netN);
            $this->addIndexedParameter($params, 'numa', $numaN);
            $this->addIndexedParameter($params, 'parallel', $parallelN);
            $this->addIndexedParameter($params, 'sata', $sataN);
            $this->addIndexedParameter($params, 'scsi', $scsiN);
            $this->addIndexedParameter($params, 'serial', $serialN);
            $this->addIndexedParameter($params, 'unused', $unusedN);
            $this->addIndexedParameter($params, 'usb', $usbN);
            $this->addIndexedParameter($params, 'virtio', $virtioN);
            return $this->getClient()->create("/nodes/{$this->node}/qemu", $params);
        }

        /**
         * Create or restore a virtual machine.
         * @param int $vmid The (unique) ID of the VM.
         * @param bool $acpi Enable/disable ACPI.
         * @param bool $agent Enable/disable Qemu GuestAgent.
         * @param string $archive The backup file.
         * @param string $args Arbitrary arguments passed to kvm.
         * @param bool $autostart Automatic restart after crash (currently ignored).
         * @param int $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param string $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param string $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param string $bootdisk Enable booting from specified disk.
         * @param string $cdrom This is an alias for option -ide2
         * @param int $cores The number of cores per socket.
         * @param string $cpu Emulated CPU type.
         * @param int $cpulimit Limit of CPU usage.
         * @param int $cpuunits CPU weight for a VM.
         * @param string $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param bool $force Allow to overwrite existing VM.
         * @param bool $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param array $hostpciN Map host PCI devices into guest.
         * @param string $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param string $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param array $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param string $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.conf' configuration file.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param bool $kvm Enable/disable KVM hardware virtualization.
         * @param bool $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param string $machine Specific the Qemu machine type.
         * @param int $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param int $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param int $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param string $name Set a name for the VM. Only used on the configuration web interface.
         * @param array $netN Specify network devices.
         * @param bool $numa Enable/disable NUMA.
         * @param array $numaN NUMA topology.
         * @param bool $onboot Specifies whether a VM will be started during system bootup.
         * @param string $ostype Specify guest operating system.
         *   Enum: other,wxp,w2k,w2k3,w2k8,wvista,win7,win8,win10,l24,l26,solaris
         * @param array $parallelN Map host parallel devices (n is 0 to 2).
         * @param string $pool Add the VM to the specified pool.
         * @param bool $protection Sets the protection flag of the VM. This will disable the remove VM and remove disk operations.
         * @param bool $reboot Allow reboot. If set to '0' the VM exit on reboot.
         * @param array $sataN Use volume as SATA hard disk or CD-ROM (n is 0 to 5).
         * @param array $scsiN Use volume as SCSI hard disk or CD-ROM (n is 0 to 13).
         * @param string $scsihw SCSI controller model
         *   Enum: lsi,lsi53c810,virtio-scsi-pci,virtio-scsi-single,megasas,pvscsi
         * @param array $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param int $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning
         * @param string $smbios1 Specify SMBIOS type 1 fields.
         * @param int $smp The number of CPUs. Please use option -sockets instead.
         * @param int $sockets The number of CPU sockets.
         * @param string $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param string $storage Default storage.
         * @param bool $tablet Enable/disable the USB tablet device.
         * @param bool $tdf Enable/disable time drift fix.
         * @param bool $template Enable/disable Template.
         * @param bool $unique Assign a unique random ethernet address.
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param array $usbN Configure an USB device (n is 0 to 4).
         * @param int $vcpus Number of hotplugged vcpus.
         * @param string $vga Select the VGA type.
         *   Enum: std,cirrus,vmware,qxl,serial0,serial1,serial2,serial3,qxl2,qxl3,qxl4
         * @param array $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param string $vmstatestorage Default storage for VM state volumes/files.
         * @param string $watchdog Create a virtual hardware watchdog device.
         * @return Result
         */
        public function createVm($vmid, $acpi = null, $agent = null, $archive = null, $args = null, $autostart = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $description = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $pool = null, $protection = null, $reboot = null, $sataN = null, $scsiN = null, $scsihw = null, $serialN = null, $shares = null, $smbios1 = null, $smp = null, $sockets = null, $startdate = null, $startup = null, $storage = null, $tablet = null, $tdf = null, $template = null, $unique = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $vmstatestorage = null, $watchdog = null)
        {
            return $this->createRest($vmid, $acpi, $agent, $archive, $args, $autostart, $balloon, $bios, $boot, $bootdisk, $cdrom, $cores, $cpu, $cpulimit, $cpuunits, $description, $force, $freeze, $hostpciN, $hotplug, $hugepages, $ideN, $keyboard, $kvm, $localtime, $lock, $machine, $memory, $migrate_downtime, $migrate_speed, $name, $netN, $numa, $numaN, $onboot, $ostype, $parallelN, $pool, $protection, $reboot, $sataN, $scsiN, $scsihw, $serialN, $shares, $smbios1, $smp, $sockets, $startdate, $startup, $storage, $tablet, $tdf, $template, $unique, $unusedN, $usbN, $vcpus, $vga, $virtioN, $vmstatestorage, $watchdog);
        }
    }

    class PVEItemQemuNodeNodesVmid extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        private $firewall;

        public function getFirewall()
        {
            return $this->firewall ?: ($this->firewall = new PVEVmidQemuNodeNodesFirewall($this->client, $this->node, $this->vmid));
        }

        private $rrd;

        public function getRrd()
        {
            return $this->rrd ?: ($this->rrd = new PVEVmidQemuNodeNodesRrd($this->client, $this->node, $this->vmid));
        }

        private $rrddata;

        public function getRrddata()
        {
            return $this->rrddata ?: ($this->rrddata = new PVEVmidQemuNodeNodesRrddata($this->client, $this->node, $this->vmid));
        }

        private $config;

        public function getConfig()
        {
            return $this->config ?: ($this->config = new PVEVmidQemuNodeNodesConfig($this->client, $this->node, $this->vmid));
        }

        private $pending;

        public function getPending()
        {
            return $this->pending ?: ($this->pending = new PVEVmidQemuNodeNodesPending($this->client, $this->node, $this->vmid));
        }

        private $unlink;

        public function getUnlink()
        {
            return $this->unlink ?: ($this->unlink = new PVEVmidQemuNodeNodesUnlink($this->client, $this->node, $this->vmid));
        }

        private $vncproxy;

        public function getVncproxy()
        {
            return $this->vncproxy ?: ($this->vncproxy = new PVEVmidQemuNodeNodesVncproxy($this->client, $this->node, $this->vmid));
        }

        private $vncwebsocket;

        public function getVncwebsocket()
        {
            return $this->vncwebsocket ?: ($this->vncwebsocket = new PVEVmidQemuNodeNodesVncwebsocket($this->client, $this->node, $this->vmid));
        }

        private $spiceproxy;

        public function getSpiceproxy()
        {
            return $this->spiceproxy ?: ($this->spiceproxy = new PVEVmidQemuNodeNodesSpiceproxy($this->client, $this->node, $this->vmid));
        }

        private $status;

        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVEVmidQemuNodeNodesStatus($this->client, $this->node, $this->vmid));
        }

        private $sendkey;

        public function getSendkey()
        {
            return $this->sendkey ?: ($this->sendkey = new PVEVmidQemuNodeNodesSendkey($this->client, $this->node, $this->vmid));
        }

        private $feature;

        public function getFeature()
        {
            return $this->feature ?: ($this->feature = new PVEVmidQemuNodeNodesFeature($this->client, $this->node, $this->vmid));
        }

        private $clone;

        public function getClone()
        {
            return $this->clone ?: ($this->clone = new PVEVmidQemuNodeNodesClone($this->client, $this->node, $this->vmid));
        }

        private $moveDisk;

        public function getMoveDisk()
        {
            return $this->moveDisk ?: ($this->moveDisk = new PVEVmidQemuNodeNodesMoveDisk($this->client, $this->node, $this->vmid));
        }

        private $migrate;

        public function getMigrate()
        {
            return $this->migrate ?: ($this->migrate = new PVEVmidQemuNodeNodesMigrate($this->client, $this->node, $this->vmid));
        }

        private $monitor;

        public function getMonitor()
        {
            return $this->monitor ?: ($this->monitor = new PVEVmidQemuNodeNodesMonitor($this->client, $this->node, $this->vmid));
        }

        private $agent;

        public function getAgent()
        {
            return $this->agent ?: ($this->agent = new PVEVmidQemuNodeNodesAgent($this->client, $this->node, $this->vmid));
        }

        private $resize;

        public function getResize()
        {
            return $this->resize ?: ($this->resize = new PVEVmidQemuNodeNodesResize($this->client, $this->node, $this->vmid));
        }

        private $snapshot;

        public function getSnapshot()
        {
            return $this->snapshot ?: ($this->snapshot = new PVEVmidQemuNodeNodesSnapshot($this->client, $this->node, $this->vmid));
        }

        private $template;

        public function getTemplate()
        {
            return $this->template ?: ($this->template = new PVEVmidQemuNodeNodesTemplate($this->client, $this->node, $this->vmid));
        }

        /**
         * Destroy the vm (also delete all used/owned volumes).
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function deleteRest($skiplock = null)
        {
            $params = ['skiplock' => $skiplock];
            return $this->getClient()->delete("/nodes/{$this->node}/qemu/{$this->vmid}", $params);
        }

        /**
         * Destroy the vm (also delete all used/owned volumes).
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function destroyVm($skiplock = null)
        {
            return $this->deleteRest($skiplock);
        }

        /**
         * Directory index
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}");
        }

        /**
         * Directory index
         * @return Result
         */
        public function vmdiridx()
        {
            return $this->getRest();
        }
    }

    class PVEVmidQemuNodeNodesFirewall extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        private $rules;

        public function getRules()
        {
            return $this->rules ?: ($this->rules = new PVEFirewallVmidQemuNodeNodesRules($this->client, $this->node, $this->vmid));
        }

        private $aliases;

        public function getAliases()
        {
            return $this->aliases ?: ($this->aliases = new PVEFirewallVmidQemuNodeNodesAliases($this->client, $this->node, $this->vmid));
        }

        private $ipset;

        public function getIpset()
        {
            return $this->ipset ?: ($this->ipset = new PVEFirewallVmidQemuNodeNodesIpset($this->client, $this->node, $this->vmid));
        }

        private $options;

        public function getOptions()
        {
            return $this->options ?: ($this->options = new PVEFirewallVmidQemuNodeNodesOptions($this->client, $this->node, $this->vmid));
        }

        private $log;

        public function getLog()
        {
            return $this->log ?: ($this->log = new PVEFirewallVmidQemuNodeNodesLog($this->client, $this->node, $this->vmid));
        }

        private $refs;

        public function getRefs()
        {
            return $this->refs ?: ($this->refs = new PVEFirewallVmidQemuNodeNodesRefs($this->client, $this->node, $this->vmid));
        }

        /**
         * Directory index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/firewall");
        }

        /**
         * Directory index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEFirewallVmidQemuNodeNodesRules extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        public function get($pos)
        {
            return new PVEItemRulesFirewallVmidQemuNodeNodesPos($this->client, $this->node, $this->vmid, $pos);
        }

        /**
         * List rules.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/rules");
        }

        /**
         * List rules.
         * @return Result
         */
        public function getRules()
        {
            return $this->getRest();
        }

        /**
         * Create new rule.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @param string $comment Descriptive comment.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $pos Update rule at position &amp;lt;pos&amp;gt;.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @return Result
         */
        public function createRest($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            $params = ['action' => $action,
                'type' => $type,
                'comment' => $comment,
                'dest' => $dest,
                'digest' => $digest,
                'dport' => $dport,
                'enable' => $enable,
                'iface' => $iface,
                'macro' => $macro,
                'pos' => $pos,
                'proto' => $proto,
                'source' => $source,
                'sport' => $sport];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/rules", $params);
        }

        /**
         * Create new rule.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @param string $comment Descriptive comment.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $pos Update rule at position &amp;lt;pos&amp;gt;.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @return Result
         */
        public function createRule($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            return $this->createRest($action, $type, $comment, $dest, $digest, $dport, $enable, $iface, $macro, $pos, $proto, $source, $sport);
        }
    }

    class PVEItemRulesFirewallVmidQemuNodeNodesPos extends Base
    {
        private $node;
        private $vmid;
        private $pos;

        function __construct($client, $node, $vmid, $pos)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->pos = $pos;
        }

        /**
         * Delete rule.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRest($digest = null)
        {
            $params = ['digest' => $digest];
            return $this->getClient()->delete("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/rules/{$this->pos}", $params);
        }

        /**
         * Delete rule.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRule($digest = null)
        {
            return $this->deleteRest($digest);
        }

        /**
         * Get single rule data.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/rules/{$this->pos}");
        }

        /**
         * Get single rule data.
         * @return Result
         */
        public function getRule()
        {
            return $this->getRest();
        }

        /**
         * Modify rule data.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $comment Descriptive comment.
         * @param string $delete A list of settings you want to delete.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $moveto Move rule to new position &amp;lt;moveto&amp;gt;. Other arguments are ignored.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @return Result
         */
        public function setRest($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            $params = ['action' => $action,
                'comment' => $comment,
                'delete' => $delete,
                'dest' => $dest,
                'digest' => $digest,
                'dport' => $dport,
                'enable' => $enable,
                'iface' => $iface,
                'macro' => $macro,
                'moveto' => $moveto,
                'proto' => $proto,
                'source' => $source,
                'sport' => $sport,
                'type' => $type];
            return $this->getClient()->set("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/rules/{$this->pos}", $params);
        }

        /**
         * Modify rule data.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $comment Descriptive comment.
         * @param string $delete A list of settings you want to delete.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $moveto Move rule to new position &amp;lt;moveto&amp;gt;. Other arguments are ignored.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @return Result
         */
        public function updateRule($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            return $this->setRest($action, $comment, $delete, $dest, $digest, $dport, $enable, $iface, $macro, $moveto, $proto, $source, $sport, $type);
        }
    }

    class PVEFirewallVmidQemuNodeNodesAliases extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        public function get($name)
        {
            return new PVEItemAliasesFirewallVmidQemuNodeNodesName($this->client, $this->node, $this->vmid, $name);
        }

        /**
         * List aliases
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/aliases");
        }

        /**
         * List aliases
         * @return Result
         */
        public function getAliases()
        {
            return $this->getRest();
        }

        /**
         * Create IP or Network Alias.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $name Alias name.
         * @param string $comment
         * @return Result
         */
        public function createRest($cidr, $name, $comment = null)
        {
            $params = ['cidr' => $cidr,
                'name' => $name,
                'comment' => $comment];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/aliases", $params);
        }

        /**
         * Create IP or Network Alias.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $name Alias name.
         * @param string $comment
         * @return Result
         */
        public function createAlias($cidr, $name, $comment = null)
        {
            return $this->createRest($cidr, $name, $comment);
        }
    }

    class PVEItemAliasesFirewallVmidQemuNodeNodesName extends Base
    {
        private $node;
        private $vmid;
        private $name;

        function __construct($client, $node, $vmid, $name)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->name = $name;
        }

        /**
         * Remove IP or Network alias.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRest($digest = null)
        {
            $params = ['digest' => $digest];
            return $this->getClient()->delete("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/aliases/{$this->name}", $params);
        }

        /**
         * Remove IP or Network alias.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function removeAlias($digest = null)
        {
            return $this->deleteRest($digest);
        }

        /**
         * Read alias.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/aliases/{$this->name}");
        }

        /**
         * Read alias.
         * @return Result
         */
        public function readAlias()
        {
            return $this->getRest();
        }

        /**
         * Update IP or Network alias.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename an existing alias.
         * @return Result
         */
        public function setRest($cidr, $comment = null, $digest = null, $rename = null)
        {
            $params = ['cidr' => $cidr,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            return $this->getClient()->set("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/aliases/{$this->name}", $params);
        }

        /**
         * Update IP or Network alias.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename an existing alias.
         * @return Result
         */
        public function updateAlias($cidr, $comment = null, $digest = null, $rename = null)
        {
            return $this->setRest($cidr, $comment, $digest, $rename);
        }
    }

    class PVEFirewallVmidQemuNodeNodesIpset extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        public function get($name)
        {
            return new PVEItemIpsetFirewallVmidQemuNodeNodesName($this->client, $this->node, $this->vmid, $name);
        }

        /**
         * List IPSets
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset");
        }

        /**
         * List IPSets
         * @return Result
         */
        public function ipsetIndex()
        {
            return $this->getRest();
        }

        /**
         * Create new IPSet
         * @param string $name IP set name.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename an existing IPSet. You can set 'rename' to the same value as 'name' to update the 'comment' of an existing IPSet.
         * @return Result
         */
        public function createRest($name, $comment = null, $digest = null, $rename = null)
        {
            $params = ['name' => $name,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset", $params);
        }

        /**
         * Create new IPSet
         * @param string $name IP set name.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename an existing IPSet. You can set 'rename' to the same value as 'name' to update the 'comment' of an existing IPSet.
         * @return Result
         */
        public function createIpset($name, $comment = null, $digest = null, $rename = null)
        {
            return $this->createRest($name, $comment, $digest, $rename);
        }
    }

    class PVEItemIpsetFirewallVmidQemuNodeNodesName extends Base
    {
        private $node;
        private $vmid;
        private $name;

        function __construct($client, $node, $vmid, $name)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->name = $name;
        }

        public function get($cidr)
        {
            return new PVEItemNameIpsetFirewallVmidQemuNodeNodesCidr($this->client, $this->node, $this->vmid, $this->name, $cidr);
        }

        /**
         * Delete IPSet
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset/{$this->name}");
        }

        /**
         * Delete IPSet
         * @return Result
         */
        public function deleteIpset()
        {
            return $this->deleteRest();
        }

        /**
         * List IPSet content
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset/{$this->name}");
        }

        /**
         * List IPSet content
         * @return Result
         */
        public function getIpset()
        {
            return $this->getRest();
        }

        /**
         * Add IP or Network to IPSet.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $comment
         * @param bool $nomatch
         * @return Result
         */
        public function createRest($cidr, $comment = null, $nomatch = null)
        {
            $params = ['cidr' => $cidr,
                'comment' => $comment,
                'nomatch' => $nomatch];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset/{$this->name}", $params);
        }

        /**
         * Add IP or Network to IPSet.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $comment
         * @param bool $nomatch
         * @return Result
         */
        public function createIp($cidr, $comment = null, $nomatch = null)
        {
            return $this->createRest($cidr, $comment, $nomatch);
        }
    }

    class PVEItemNameIpsetFirewallVmidQemuNodeNodesCidr extends Base
    {
        private $node;
        private $vmid;
        private $name;
        private $cidr;

        function __construct($client, $node, $vmid, $name, $cidr)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->name = $name;
            $this->cidr = $cidr;
        }

        /**
         * Remove IP or Network from IPSet.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRest($digest = null)
        {
            $params = ['digest' => $digest];
            return $this->getClient()->delete("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset/{$this->name}/{$this->cidr}", $params);
        }

        /**
         * Remove IP or Network from IPSet.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function removeIp($digest = null)
        {
            return $this->deleteRest($digest);
        }

        /**
         * Read IP or Network settings from IPSet.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset/{$this->name}/{$this->cidr}");
        }

        /**
         * Read IP or Network settings from IPSet.
         * @return Result
         */
        public function readIp()
        {
            return $this->getRest();
        }

        /**
         * Update IP or Network settings
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $nomatch
         * @return Result
         */
        public function setRest($comment = null, $digest = null, $nomatch = null)
        {
            $params = ['comment' => $comment,
                'digest' => $digest,
                'nomatch' => $nomatch];
            return $this->getClient()->set("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset/{$this->name}/{$this->cidr}", $params);
        }

        /**
         * Update IP or Network settings
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $nomatch
         * @return Result
         */
        public function updateIp($comment = null, $digest = null, $nomatch = null)
        {
            return $this->setRest($comment, $digest, $nomatch);
        }
    }

    class PVEFirewallVmidQemuNodeNodesOptions extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get VM firewall options.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/options");
        }

        /**
         * Get VM firewall options.
         * @return Result
         */
        public function getOptions()
        {
            return $this->getRest();
        }

        /**
         * Set Firewall options.
         * @param string $delete A list of settings you want to delete.
         * @param bool $dhcp Enable DHCP.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $enable Enable/disable firewall rules.
         * @param bool $ipfilter Enable default IP filters. This is equivalent to adding an empty ipfilter-net&amp;lt;id&amp;gt; ipset for every interface. Such ipsets implicitly contain sane default restrictions such as restricting IPv6 link local addresses to the one derived from the interface's MAC address. For containers the configured IP addresses will be implicitly added.
         * @param string $log_level_in Log level for incoming traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param string $log_level_out Log level for outgoing traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param bool $macfilter Enable/disable MAC address filter.
         * @param bool $ndp Enable NDP.
         * @param string $policy_in Input policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param string $policy_out Output policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param bool $radv Allow sending Router Advertisement.
         * @return Result
         */
        public function setRest($delete = null, $dhcp = null, $digest = null, $enable = null, $ipfilter = null, $log_level_in = null, $log_level_out = null, $macfilter = null, $ndp = null, $policy_in = null, $policy_out = null, $radv = null)
        {
            $params = ['delete' => $delete,
                'dhcp' => $dhcp,
                'digest' => $digest,
                'enable' => $enable,
                'ipfilter' => $ipfilter,
                'log_level_in' => $log_level_in,
                'log_level_out' => $log_level_out,
                'macfilter' => $macfilter,
                'ndp' => $ndp,
                'policy_in' => $policy_in,
                'policy_out' => $policy_out,
                'radv' => $radv];
            return $this->getClient()->set("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/options", $params);
        }

        /**
         * Set Firewall options.
         * @param string $delete A list of settings you want to delete.
         * @param bool $dhcp Enable DHCP.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $enable Enable/disable firewall rules.
         * @param bool $ipfilter Enable default IP filters. This is equivalent to adding an empty ipfilter-net&amp;lt;id&amp;gt; ipset for every interface. Such ipsets implicitly contain sane default restrictions such as restricting IPv6 link local addresses to the one derived from the interface's MAC address. For containers the configured IP addresses will be implicitly added.
         * @param string $log_level_in Log level for incoming traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param string $log_level_out Log level for outgoing traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param bool $macfilter Enable/disable MAC address filter.
         * @param bool $ndp Enable NDP.
         * @param string $policy_in Input policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param string $policy_out Output policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param bool $radv Allow sending Router Advertisement.
         * @return Result
         */
        public function setOptions($delete = null, $dhcp = null, $digest = null, $enable = null, $ipfilter = null, $log_level_in = null, $log_level_out = null, $macfilter = null, $ndp = null, $policy_in = null, $policy_out = null, $radv = null)
        {
            return $this->setRest($delete, $dhcp, $digest, $enable, $ipfilter, $log_level_in, $log_level_out, $macfilter, $ndp, $policy_in, $policy_out, $radv);
        }
    }

    class PVEFirewallVmidQemuNodeNodesLog extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Read firewall log
         * @param int $limit
         * @param int $start
         * @return Result
         */
        public function getRest($limit = null, $start = null)
        {
            $params = ['limit' => $limit,
                'start' => $start];
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/log", $params);
        }

        /**
         * Read firewall log
         * @param int $limit
         * @param int $start
         * @return Result
         */
        public function log($limit = null, $start = null)
        {
            return $this->getRest($limit, $start);
        }
    }

    class PVEFirewallVmidQemuNodeNodesRefs extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Lists possible IPSet/Alias reference which are allowed in source/dest properties.
         * @param string $type Only list references of specified type.
         *   Enum: alias,ipset
         * @return Result
         */
        public function getRest($type = null)
        {
            $params = ['type' => $type];
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/refs", $params);
        }

        /**
         * Lists possible IPSet/Alias reference which are allowed in source/dest properties.
         * @param string $type Only list references of specified type.
         *   Enum: alias,ipset
         * @return Result
         */
        public function refs($type = null)
        {
            return $this->getRest($type);
        }
    }

    class PVEVmidQemuNodeNodesRrd extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Read VM RRD statistics (returns PNG)
         * @param string $ds The list of datasources you want to display.
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function getRest($ds, $timeframe, $cf = null)
        {
            $params = ['ds' => $ds,
                'timeframe' => $timeframe,
                'cf' => $cf];
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/rrd", $params);
        }

        /**
         * Read VM RRD statistics (returns PNG)
         * @param string $ds The list of datasources you want to display.
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function rrd($ds, $timeframe, $cf = null)
        {
            return $this->getRest($ds, $timeframe, $cf);
        }
    }

    class PVEVmidQemuNodeNodesRrddata extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Read VM RRD statistics
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function getRest($timeframe, $cf = null)
        {
            $params = ['timeframe' => $timeframe,
                'cf' => $cf];
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/rrddata", $params);
        }

        /**
         * Read VM RRD statistics
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function rrddata($timeframe, $cf = null)
        {
            return $this->getRest($timeframe, $cf);
        }
    }

    class PVEVmidQemuNodeNodesConfig extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get current virtual machine configuration. This does not include pending configuration changes (see 'pending' API).
         * @param bool $current Get current values (instead of pending values).
         * @return Result
         */
        public function getRest($current = null)
        {
            $params = ['current' => $current];
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/config", $params);
        }

        /**
         * Get current virtual machine configuration. This does not include pending configuration changes (see 'pending' API).
         * @param bool $current Get current values (instead of pending values).
         * @return Result
         */
        public function vmConfig($current = null)
        {
            return $this->getRest($current);
        }

        /**
         * Set virtual machine options (asynchrounous API).
         * @param bool $acpi Enable/disable ACPI.
         * @param bool $agent Enable/disable Qemu GuestAgent.
         * @param string $args Arbitrary arguments passed to kvm.
         * @param bool $autostart Automatic restart after crash (currently ignored).
         * @param int $background_delay Time to wait for the task to finish. We return 'null' if the task finish within that time.
         * @param int $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param string $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param string $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param string $bootdisk Enable booting from specified disk.
         * @param string $cdrom This is an alias for option -ide2
         * @param int $cores The number of cores per socket.
         * @param string $cpu Emulated CPU type.
         * @param int $cpulimit Limit of CPU usage.
         * @param int $cpuunits CPU weight for a VM.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         * @param bool $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param array $hostpciN Map host PCI devices into guest.
         * @param string $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param string $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param array $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param string $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.conf' configuration file.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param bool $kvm Enable/disable KVM hardware virtualization.
         * @param bool $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param string $machine Specific the Qemu machine type.
         * @param int $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param int $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param int $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param string $name Set a name for the VM. Only used on the configuration web interface.
         * @param array $netN Specify network devices.
         * @param bool $numa Enable/disable NUMA.
         * @param array $numaN NUMA topology.
         * @param bool $onboot Specifies whether a VM will be started during system bootup.
         * @param string $ostype Specify guest operating system.
         *   Enum: other,wxp,w2k,w2k3,w2k8,wvista,win7,win8,win10,l24,l26,solaris
         * @param array $parallelN Map host parallel devices (n is 0 to 2).
         * @param bool $protection Sets the protection flag of the VM. This will disable the remove VM and remove disk operations.
         * @param bool $reboot Allow reboot. If set to '0' the VM exit on reboot.
         * @param string $revert Revert a pending change.
         * @param array $sataN Use volume as SATA hard disk or CD-ROM (n is 0 to 5).
         * @param array $scsiN Use volume as SCSI hard disk or CD-ROM (n is 0 to 13).
         * @param string $scsihw SCSI controller model
         *   Enum: lsi,lsi53c810,virtio-scsi-pci,virtio-scsi-single,megasas,pvscsi
         * @param array $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param int $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param string $smbios1 Specify SMBIOS type 1 fields.
         * @param int $smp The number of CPUs. Please use option -sockets instead.
         * @param int $sockets The number of CPU sockets.
         * @param string $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param bool $tablet Enable/disable the USB tablet device.
         * @param bool $tdf Enable/disable time drift fix.
         * @param bool $template Enable/disable Template.
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param array $usbN Configure an USB device (n is 0 to 4).
         * @param int $vcpus Number of hotplugged vcpus.
         * @param string $vga Select the VGA type.
         *   Enum: std,cirrus,vmware,qxl,serial0,serial1,serial2,serial3,qxl2,qxl3,qxl4
         * @param array $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param string $vmstatestorage Default storage for VM state volumes/files.
         * @param string $watchdog Create a virtual hardware watchdog device.
         * @return Result
         */
        public function createRest($acpi = null, $agent = null, $args = null, $autostart = null, $background_delay = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $protection = null, $reboot = null, $revert = null, $sataN = null, $scsiN = null, $scsihw = null, $serialN = null, $shares = null, $skiplock = null, $smbios1 = null, $smp = null, $sockets = null, $startdate = null, $startup = null, $tablet = null, $tdf = null, $template = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $vmstatestorage = null, $watchdog = null)
        {
            $params = ['acpi' => $acpi,
                'agent' => $agent,
                'args' => $args,
                'autostart' => $autostart,
                'background_delay' => $background_delay,
                'balloon' => $balloon,
                'bios' => $bios,
                'boot' => $boot,
                'bootdisk' => $bootdisk,
                'cdrom' => $cdrom,
                'cores' => $cores,
                'cpu' => $cpu,
                'cpulimit' => $cpulimit,
                'cpuunits' => $cpuunits,
                'delete' => $delete,
                'description' => $description,
                'digest' => $digest,
                'force' => $force,
                'freeze' => $freeze,
                'hotplug' => $hotplug,
                'hugepages' => $hugepages,
                'keyboard' => $keyboard,
                'kvm' => $kvm,
                'localtime' => $localtime,
                'lock' => $lock,
                'machine' => $machine,
                'memory' => $memory,
                'migrate_downtime' => $migrate_downtime,
                'migrate_speed' => $migrate_speed,
                'name' => $name,
                'numa' => $numa,
                'onboot' => $onboot,
                'ostype' => $ostype,
                'protection' => $protection,
                'reboot' => $reboot,
                'revert' => $revert,
                'scsihw' => $scsihw,
                'shares' => $shares,
                'skiplock' => $skiplock,
                'smbios1' => $smbios1,
                'smp' => $smp,
                'sockets' => $sockets,
                'startdate' => $startdate,
                'startup' => $startup,
                'tablet' => $tablet,
                'tdf' => $tdf,
                'template' => $template,
                'vcpus' => $vcpus,
                'vga' => $vga,
                'vmstatestorage' => $vmstatestorage,
                'watchdog' => $watchdog];
            $this->addIndexedParameter($params, 'hostpci', $hostpciN);
            $this->addIndexedParameter($params, 'ide', $ideN);
            $this->addIndexedParameter($params, 'net', $netN);
            $this->addIndexedParameter($params, 'numa', $numaN);
            $this->addIndexedParameter($params, 'parallel', $parallelN);
            $this->addIndexedParameter($params, 'sata', $sataN);
            $this->addIndexedParameter($params, 'scsi', $scsiN);
            $this->addIndexedParameter($params, 'serial', $serialN);
            $this->addIndexedParameter($params, 'unused', $unusedN);
            $this->addIndexedParameter($params, 'usb', $usbN);
            $this->addIndexedParameter($params, 'virtio', $virtioN);
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/config", $params);
        }

        /**
         * Set virtual machine options (asynchrounous API).
         * @param bool $acpi Enable/disable ACPI.
         * @param bool $agent Enable/disable Qemu GuestAgent.
         * @param string $args Arbitrary arguments passed to kvm.
         * @param bool $autostart Automatic restart after crash (currently ignored).
         * @param int $background_delay Time to wait for the task to finish. We return 'null' if the task finish within that time.
         * @param int $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param string $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param string $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param string $bootdisk Enable booting from specified disk.
         * @param string $cdrom This is an alias for option -ide2
         * @param int $cores The number of cores per socket.
         * @param string $cpu Emulated CPU type.
         * @param int $cpulimit Limit of CPU usage.
         * @param int $cpuunits CPU weight for a VM.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         * @param bool $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param array $hostpciN Map host PCI devices into guest.
         * @param string $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param string $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param array $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param string $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.conf' configuration file.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param bool $kvm Enable/disable KVM hardware virtualization.
         * @param bool $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param string $machine Specific the Qemu machine type.
         * @param int $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param int $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param int $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param string $name Set a name for the VM. Only used on the configuration web interface.
         * @param array $netN Specify network devices.
         * @param bool $numa Enable/disable NUMA.
         * @param array $numaN NUMA topology.
         * @param bool $onboot Specifies whether a VM will be started during system bootup.
         * @param string $ostype Specify guest operating system.
         *   Enum: other,wxp,w2k,w2k3,w2k8,wvista,win7,win8,win10,l24,l26,solaris
         * @param array $parallelN Map host parallel devices (n is 0 to 2).
         * @param bool $protection Sets the protection flag of the VM. This will disable the remove VM and remove disk operations.
         * @param bool $reboot Allow reboot. If set to '0' the VM exit on reboot.
         * @param string $revert Revert a pending change.
         * @param array $sataN Use volume as SATA hard disk or CD-ROM (n is 0 to 5).
         * @param array $scsiN Use volume as SCSI hard disk or CD-ROM (n is 0 to 13).
         * @param string $scsihw SCSI controller model
         *   Enum: lsi,lsi53c810,virtio-scsi-pci,virtio-scsi-single,megasas,pvscsi
         * @param array $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param int $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param string $smbios1 Specify SMBIOS type 1 fields.
         * @param int $smp The number of CPUs. Please use option -sockets instead.
         * @param int $sockets The number of CPU sockets.
         * @param string $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param bool $tablet Enable/disable the USB tablet device.
         * @param bool $tdf Enable/disable time drift fix.
         * @param bool $template Enable/disable Template.
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param array $usbN Configure an USB device (n is 0 to 4).
         * @param int $vcpus Number of hotplugged vcpus.
         * @param string $vga Select the VGA type.
         *   Enum: std,cirrus,vmware,qxl,serial0,serial1,serial2,serial3,qxl2,qxl3,qxl4
         * @param array $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param string $vmstatestorage Default storage for VM state volumes/files.
         * @param string $watchdog Create a virtual hardware watchdog device.
         * @return Result
         */
        public function updateVmAsync($acpi = null, $agent = null, $args = null, $autostart = null, $background_delay = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $protection = null, $reboot = null, $revert = null, $sataN = null, $scsiN = null, $scsihw = null, $serialN = null, $shares = null, $skiplock = null, $smbios1 = null, $smp = null, $sockets = null, $startdate = null, $startup = null, $tablet = null, $tdf = null, $template = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $vmstatestorage = null, $watchdog = null)
        {
            return $this->createRest($acpi, $agent, $args, $autostart, $background_delay, $balloon, $bios, $boot, $bootdisk, $cdrom, $cores, $cpu, $cpulimit, $cpuunits, $delete, $description, $digest, $force, $freeze, $hostpciN, $hotplug, $hugepages, $ideN, $keyboard, $kvm, $localtime, $lock, $machine, $memory, $migrate_downtime, $migrate_speed, $name, $netN, $numa, $numaN, $onboot, $ostype, $parallelN, $protection, $reboot, $revert, $sataN, $scsiN, $scsihw, $serialN, $shares, $skiplock, $smbios1, $smp, $sockets, $startdate, $startup, $tablet, $tdf, $template, $unusedN, $usbN, $vcpus, $vga, $virtioN, $vmstatestorage, $watchdog);
        }

        /**
         * Set virtual machine options (synchrounous API) - You should consider using the POST method instead for any actions involving hotplug or storage allocation.
         * @param bool $acpi Enable/disable ACPI.
         * @param bool $agent Enable/disable Qemu GuestAgent.
         * @param string $args Arbitrary arguments passed to kvm.
         * @param bool $autostart Automatic restart after crash (currently ignored).
         * @param int $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param string $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param string $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param string $bootdisk Enable booting from specified disk.
         * @param string $cdrom This is an alias for option -ide2
         * @param int $cores The number of cores per socket.
         * @param string $cpu Emulated CPU type.
         * @param int $cpulimit Limit of CPU usage.
         * @param int $cpuunits CPU weight for a VM.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         * @param bool $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param array $hostpciN Map host PCI devices into guest.
         * @param string $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param string $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param array $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param string $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.conf' configuration file.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param bool $kvm Enable/disable KVM hardware virtualization.
         * @param bool $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param string $machine Specific the Qemu machine type.
         * @param int $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param int $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param int $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param string $name Set a name for the VM. Only used on the configuration web interface.
         * @param array $netN Specify network devices.
         * @param bool $numa Enable/disable NUMA.
         * @param array $numaN NUMA topology.
         * @param bool $onboot Specifies whether a VM will be started during system bootup.
         * @param string $ostype Specify guest operating system.
         *   Enum: other,wxp,w2k,w2k3,w2k8,wvista,win7,win8,win10,l24,l26,solaris
         * @param array $parallelN Map host parallel devices (n is 0 to 2).
         * @param bool $protection Sets the protection flag of the VM. This will disable the remove VM and remove disk operations.
         * @param bool $reboot Allow reboot. If set to '0' the VM exit on reboot.
         * @param string $revert Revert a pending change.
         * @param array $sataN Use volume as SATA hard disk or CD-ROM (n is 0 to 5).
         * @param array $scsiN Use volume as SCSI hard disk or CD-ROM (n is 0 to 13).
         * @param string $scsihw SCSI controller model
         *   Enum: lsi,lsi53c810,virtio-scsi-pci,virtio-scsi-single,megasas,pvscsi
         * @param array $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param int $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param string $smbios1 Specify SMBIOS type 1 fields.
         * @param int $smp The number of CPUs. Please use option -sockets instead.
         * @param int $sockets The number of CPU sockets.
         * @param string $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param bool $tablet Enable/disable the USB tablet device.
         * @param bool $tdf Enable/disable time drift fix.
         * @param bool $template Enable/disable Template.
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param array $usbN Configure an USB device (n is 0 to 4).
         * @param int $vcpus Number of hotplugged vcpus.
         * @param string $vga Select the VGA type.
         *   Enum: std,cirrus,vmware,qxl,serial0,serial1,serial2,serial3,qxl2,qxl3,qxl4
         * @param array $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param string $vmstatestorage Default storage for VM state volumes/files.
         * @param string $watchdog Create a virtual hardware watchdog device.
         * @return Result
         */
        public function setRest($acpi = null, $agent = null, $args = null, $autostart = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $protection = null, $reboot = null, $revert = null, $sataN = null, $scsiN = null, $scsihw = null, $serialN = null, $shares = null, $skiplock = null, $smbios1 = null, $smp = null, $sockets = null, $startdate = null, $startup = null, $tablet = null, $tdf = null, $template = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $vmstatestorage = null, $watchdog = null)
        {
            $params = ['acpi' => $acpi,
                'agent' => $agent,
                'args' => $args,
                'autostart' => $autostart,
                'balloon' => $balloon,
                'bios' => $bios,
                'boot' => $boot,
                'bootdisk' => $bootdisk,
                'cdrom' => $cdrom,
                'cores' => $cores,
                'cpu' => $cpu,
                'cpulimit' => $cpulimit,
                'cpuunits' => $cpuunits,
                'delete' => $delete,
                'description' => $description,
                'digest' => $digest,
                'force' => $force,
                'freeze' => $freeze,
                'hotplug' => $hotplug,
                'hugepages' => $hugepages,
                'keyboard' => $keyboard,
                'kvm' => $kvm,
                'localtime' => $localtime,
                'lock' => $lock,
                'machine' => $machine,
                'memory' => $memory,
                'migrate_downtime' => $migrate_downtime,
                'migrate_speed' => $migrate_speed,
                'name' => $name,
                'numa' => $numa,
                'onboot' => $onboot,
                'ostype' => $ostype,
                'protection' => $protection,
                'reboot' => $reboot,
                'revert' => $revert,
                'scsihw' => $scsihw,
                'shares' => $shares,
                'skiplock' => $skiplock,
                'smbios1' => $smbios1,
                'smp' => $smp,
                'sockets' => $sockets,
                'startdate' => $startdate,
                'startup' => $startup,
                'tablet' => $tablet,
                'tdf' => $tdf,
                'template' => $template,
                'vcpus' => $vcpus,
                'vga' => $vga,
                'vmstatestorage' => $vmstatestorage,
                'watchdog' => $watchdog];
            $this->addIndexedParameter($params, 'hostpci', $hostpciN);
            $this->addIndexedParameter($params, 'ide', $ideN);
            $this->addIndexedParameter($params, 'net', $netN);
            $this->addIndexedParameter($params, 'numa', $numaN);
            $this->addIndexedParameter($params, 'parallel', $parallelN);
            $this->addIndexedParameter($params, 'sata', $sataN);
            $this->addIndexedParameter($params, 'scsi', $scsiN);
            $this->addIndexedParameter($params, 'serial', $serialN);
            $this->addIndexedParameter($params, 'unused', $unusedN);
            $this->addIndexedParameter($params, 'usb', $usbN);
            $this->addIndexedParameter($params, 'virtio', $virtioN);
            return $this->getClient()->set("/nodes/{$this->node}/qemu/{$this->vmid}/config", $params);
        }

        /**
         * Set virtual machine options (synchrounous API) - You should consider using the POST method instead for any actions involving hotplug or storage allocation.
         * @param bool $acpi Enable/disable ACPI.
         * @param bool $agent Enable/disable Qemu GuestAgent.
         * @param string $args Arbitrary arguments passed to kvm.
         * @param bool $autostart Automatic restart after crash (currently ignored).
         * @param int $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param string $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param string $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param string $bootdisk Enable booting from specified disk.
         * @param string $cdrom This is an alias for option -ide2
         * @param int $cores The number of cores per socket.
         * @param string $cpu Emulated CPU type.
         * @param int $cpulimit Limit of CPU usage.
         * @param int $cpuunits CPU weight for a VM.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         * @param bool $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param array $hostpciN Map host PCI devices into guest.
         * @param string $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param string $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param array $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param string $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.conf' configuration file.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param bool $kvm Enable/disable KVM hardware virtualization.
         * @param bool $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param string $machine Specific the Qemu machine type.
         * @param int $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param int $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param int $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param string $name Set a name for the VM. Only used on the configuration web interface.
         * @param array $netN Specify network devices.
         * @param bool $numa Enable/disable NUMA.
         * @param array $numaN NUMA topology.
         * @param bool $onboot Specifies whether a VM will be started during system bootup.
         * @param string $ostype Specify guest operating system.
         *   Enum: other,wxp,w2k,w2k3,w2k8,wvista,win7,win8,win10,l24,l26,solaris
         * @param array $parallelN Map host parallel devices (n is 0 to 2).
         * @param bool $protection Sets the protection flag of the VM. This will disable the remove VM and remove disk operations.
         * @param bool $reboot Allow reboot. If set to '0' the VM exit on reboot.
         * @param string $revert Revert a pending change.
         * @param array $sataN Use volume as SATA hard disk or CD-ROM (n is 0 to 5).
         * @param array $scsiN Use volume as SCSI hard disk or CD-ROM (n is 0 to 13).
         * @param string $scsihw SCSI controller model
         *   Enum: lsi,lsi53c810,virtio-scsi-pci,virtio-scsi-single,megasas,pvscsi
         * @param array $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param int $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param string $smbios1 Specify SMBIOS type 1 fields.
         * @param int $smp The number of CPUs. Please use option -sockets instead.
         * @param int $sockets The number of CPU sockets.
         * @param string $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param bool $tablet Enable/disable the USB tablet device.
         * @param bool $tdf Enable/disable time drift fix.
         * @param bool $template Enable/disable Template.
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param array $usbN Configure an USB device (n is 0 to 4).
         * @param int $vcpus Number of hotplugged vcpus.
         * @param string $vga Select the VGA type.
         *   Enum: std,cirrus,vmware,qxl,serial0,serial1,serial2,serial3,qxl2,qxl3,qxl4
         * @param array $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param string $vmstatestorage Default storage for VM state volumes/files.
         * @param string $watchdog Create a virtual hardware watchdog device.
         * @return Result
         */
        public function updateVm($acpi = null, $agent = null, $args = null, $autostart = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $protection = null, $reboot = null, $revert = null, $sataN = null, $scsiN = null, $scsihw = null, $serialN = null, $shares = null, $skiplock = null, $smbios1 = null, $smp = null, $sockets = null, $startdate = null, $startup = null, $tablet = null, $tdf = null, $template = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $vmstatestorage = null, $watchdog = null)
        {
            return $this->setRest($acpi, $agent, $args, $autostart, $balloon, $bios, $boot, $bootdisk, $cdrom, $cores, $cpu, $cpulimit, $cpuunits, $delete, $description, $digest, $force, $freeze, $hostpciN, $hotplug, $hugepages, $ideN, $keyboard, $kvm, $localtime, $lock, $machine, $memory, $migrate_downtime, $migrate_speed, $name, $netN, $numa, $numaN, $onboot, $ostype, $parallelN, $protection, $reboot, $revert, $sataN, $scsiN, $scsihw, $serialN, $shares, $skiplock, $smbios1, $smp, $sockets, $startdate, $startup, $tablet, $tdf, $template, $unusedN, $usbN, $vcpus, $vga, $virtioN, $vmstatestorage, $watchdog);
        }
    }

    class PVEVmidQemuNodeNodesPending extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get virtual machine configuration, including pending changes.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/pending");
        }

        /**
         * Get virtual machine configuration, including pending changes.
         * @return Result
         */
        public function vmPending()
        {
            return $this->getRest();
        }
    }

    class PVEVmidQemuNodeNodesUnlink extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Unlink/delete disk images.
         * @param string $idlist A list of disk IDs you want to delete.
         * @param bool $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         * @return Result
         */
        public function setRest($idlist, $force = null)
        {
            $params = ['idlist' => $idlist,
                'force' => $force];
            return $this->getClient()->set("/nodes/{$this->node}/qemu/{$this->vmid}/unlink", $params);
        }

        /**
         * Unlink/delete disk images.
         * @param string $idlist A list of disk IDs you want to delete.
         * @param bool $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         * @return Result
         */
        public function unlink($idlist, $force = null)
        {
            return $this->setRest($idlist, $force);
        }
    }

    class PVEVmidQemuNodeNodesVncproxy extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Creates a TCP VNC proxy connections.
         * @param bool $websocket starts websockify instead of vncproxy
         * @return Result
         */
        public function createRest($websocket = null)
        {
            $params = ['websocket' => $websocket];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/vncproxy", $params);
        }

        /**
         * Creates a TCP VNC proxy connections.
         * @param bool $websocket starts websockify instead of vncproxy
         * @return Result
         */
        public function vncproxy($websocket = null)
        {
            return $this->createRest($websocket);
        }
    }

    class PVEVmidQemuNodeNodesVncwebsocket extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Opens a weksocket for VNC traffic.
         * @param int $port Port number returned by previous vncproxy call.
         * @param string $vncticket Ticket from previous call to vncproxy.
         * @return Result
         */
        public function getRest($port, $vncticket)
        {
            $params = ['port' => $port,
                'vncticket' => $vncticket];
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/vncwebsocket", $params);
        }

        /**
         * Opens a weksocket for VNC traffic.
         * @param int $port Port number returned by previous vncproxy call.
         * @param string $vncticket Ticket from previous call to vncproxy.
         * @return Result
         */
        public function vncwebsocket($port, $vncticket)
        {
            return $this->getRest($port, $vncticket);
        }
    }

    class PVEVmidQemuNodeNodesSpiceproxy extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Returns a SPICE configuration to connect to the VM.
         * @param string $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As resonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @return Result
         */
        public function createRest($proxy = null)
        {
            $params = ['proxy' => $proxy];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/spiceproxy", $params);
        }

        /**
         * Returns a SPICE configuration to connect to the VM.
         * @param string $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As resonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @return Result
         */
        public function spiceproxy($proxy = null)
        {
            return $this->createRest($proxy);
        }
    }

    class PVEVmidQemuNodeNodesStatus extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        private $current;

        public function getCurrent()
        {
            return $this->current ?: ($this->current = new PVEStatusVmidQemuNodeNodesCurrent($this->client, $this->node, $this->vmid));
        }

        private $start;

        public function getStart()
        {
            return $this->start ?: ($this->start = new PVEStatusVmidQemuNodeNodesStart($this->client, $this->node, $this->vmid));
        }

        private $stop;

        public function getStop()
        {
            return $this->stop ?: ($this->stop = new PVEStatusVmidQemuNodeNodesStop($this->client, $this->node, $this->vmid));
        }

        private $reset;

        public function getReset()
        {
            return $this->reset ?: ($this->reset = new PVEStatusVmidQemuNodeNodesReset($this->client, $this->node, $this->vmid));
        }

        private $shutdown;

        public function getShutdown()
        {
            return $this->shutdown ?: ($this->shutdown = new PVEStatusVmidQemuNodeNodesShutdown($this->client, $this->node, $this->vmid));
        }

        private $suspend;

        public function getSuspend()
        {
            return $this->suspend ?: ($this->suspend = new PVEStatusVmidQemuNodeNodesSuspend($this->client, $this->node, $this->vmid));
        }

        private $resume;

        public function getResume()
        {
            return $this->resume ?: ($this->resume = new PVEStatusVmidQemuNodeNodesResume($this->client, $this->node, $this->vmid));
        }

        /**
         * Directory index
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/status");
        }

        /**
         * Directory index
         * @return Result
         */
        public function vmcmdidx()
        {
            return $this->getRest();
        }
    }

    class PVEStatusVmidQemuNodeNodesCurrent extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get virtual machine status.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/status/current");
        }

        /**
         * Get virtual machine status.
         * @return Result
         */
        public function vmStatus()
        {
            return $this->getRest();
        }
    }

    class PVEStatusVmidQemuNodeNodesStart extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Start virtual machine.
         * @param string $machine Specific the Qemu machine type.
         * @param string $migratedfrom The cluster node name.
         * @param string $migration_network CIDR of the (sub) network that is used for migration.
         * @param string $migration_type Migration traffic is encrypted using an SSH tunnel by default. On secure, completely private networks this can be disabled to increase performance.
         *   Enum: secure,insecure
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param string $stateuri Some command save/restore state from this location.
         * @param string $targetstorage Target storage for the migration. (Can be '1' to use the same storage id as on the source node.)
         * @return Result
         */
        public function createRest($machine = null, $migratedfrom = null, $migration_network = null, $migration_type = null, $skiplock = null, $stateuri = null, $targetstorage = null)
        {
            $params = ['machine' => $machine,
                'migratedfrom' => $migratedfrom,
                'migration_network' => $migration_network,
                'migration_type' => $migration_type,
                'skiplock' => $skiplock,
                'stateuri' => $stateuri,
                'targetstorage' => $targetstorage];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/status/start", $params);
        }

        /**
         * Start virtual machine.
         * @param string $machine Specific the Qemu machine type.
         * @param string $migratedfrom The cluster node name.
         * @param string $migration_network CIDR of the (sub) network that is used for migration.
         * @param string $migration_type Migration traffic is encrypted using an SSH tunnel by default. On secure, completely private networks this can be disabled to increase performance.
         *   Enum: secure,insecure
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param string $stateuri Some command save/restore state from this location.
         * @param string $targetstorage Target storage for the migration. (Can be '1' to use the same storage id as on the source node.)
         * @return Result
         */
        public function vmStart($machine = null, $migratedfrom = null, $migration_network = null, $migration_type = null, $skiplock = null, $stateuri = null, $targetstorage = null)
        {
            return $this->createRest($machine, $migratedfrom, $migration_network, $migration_type, $skiplock, $stateuri, $targetstorage);
        }
    }

    class PVEStatusVmidQemuNodeNodesStop extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Stop virtual machine. The qemu process will exit immediately. Thisis akin to pulling the power plug of a running computer and may damage the VM data
         * @param bool $keepActive Do not deactivate storage volumes.
         * @param string $migratedfrom The cluster node name.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param int $timeout Wait maximal timeout seconds.
         * @return Result
         */
        public function createRest($keepActive = null, $migratedfrom = null, $skiplock = null, $timeout = null)
        {
            $params = ['keepActive' => $keepActive,
                'migratedfrom' => $migratedfrom,
                'skiplock' => $skiplock,
                'timeout' => $timeout];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/status/stop", $params);
        }

        /**
         * Stop virtual machine. The qemu process will exit immediately. Thisis akin to pulling the power plug of a running computer and may damage the VM data
         * @param bool $keepActive Do not deactivate storage volumes.
         * @param string $migratedfrom The cluster node name.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param int $timeout Wait maximal timeout seconds.
         * @return Result
         */
        public function vmStop($keepActive = null, $migratedfrom = null, $skiplock = null, $timeout = null)
        {
            return $this->createRest($keepActive, $migratedfrom, $skiplock, $timeout);
        }
    }

    class PVEStatusVmidQemuNodeNodesReset extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Reset virtual machine.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function createRest($skiplock = null)
        {
            $params = ['skiplock' => $skiplock];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/status/reset", $params);
        }

        /**
         * Reset virtual machine.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function vmReset($skiplock = null)
        {
            return $this->createRest($skiplock);
        }
    }

    class PVEStatusVmidQemuNodeNodesShutdown extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Shutdown virtual machine. This is similar to pressing the power button on a physical machine.This will send an ACPI event for the guest OS, which should then proceed to a clean shutdown.
         * @param bool $forceStop Make sure the VM stops.
         * @param bool $keepActive Do not deactivate storage volumes.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param int $timeout Wait maximal timeout seconds.
         * @return Result
         */
        public function createRest($forceStop = null, $keepActive = null, $skiplock = null, $timeout = null)
        {
            $params = ['forceStop' => $forceStop,
                'keepActive' => $keepActive,
                'skiplock' => $skiplock,
                'timeout' => $timeout];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/status/shutdown", $params);
        }

        /**
         * Shutdown virtual machine. This is similar to pressing the power button on a physical machine.This will send an ACPI event for the guest OS, which should then proceed to a clean shutdown.
         * @param bool $forceStop Make sure the VM stops.
         * @param bool $keepActive Do not deactivate storage volumes.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param int $timeout Wait maximal timeout seconds.
         * @return Result
         */
        public function vmShutdown($forceStop = null, $keepActive = null, $skiplock = null, $timeout = null)
        {
            return $this->createRest($forceStop, $keepActive, $skiplock, $timeout);
        }
    }

    class PVEStatusVmidQemuNodeNodesSuspend extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Suspend virtual machine.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function createRest($skiplock = null)
        {
            $params = ['skiplock' => $skiplock];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/status/suspend", $params);
        }

        /**
         * Suspend virtual machine.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function vmSuspend($skiplock = null)
        {
            return $this->createRest($skiplock);
        }
    }

    class PVEStatusVmidQemuNodeNodesResume extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Resume virtual machine.
         * @param bool $nocheck
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function createRest($nocheck = null, $skiplock = null)
        {
            $params = ['nocheck' => $nocheck,
                'skiplock' => $skiplock];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/status/resume", $params);
        }

        /**
         * Resume virtual machine.
         * @param bool $nocheck
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function vmResume($nocheck = null, $skiplock = null)
        {
            return $this->createRest($nocheck, $skiplock);
        }
    }

    class PVEVmidQemuNodeNodesSendkey extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Send key event to virtual machine.
         * @param string $key The key (qemu monitor encoding).
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function setRest($key, $skiplock = null)
        {
            $params = ['key' => $key,
                'skiplock' => $skiplock];
            return $this->getClient()->set("/nodes/{$this->node}/qemu/{$this->vmid}/sendkey", $params);
        }

        /**
         * Send key event to virtual machine.
         * @param string $key The key (qemu monitor encoding).
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function vmSendkey($key, $skiplock = null)
        {
            return $this->setRest($key, $skiplock);
        }
    }

    class PVEVmidQemuNodeNodesFeature extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Check if feature for virtual machine is available.
         * @param string $feature Feature to check.
         *   Enum: snapshot,clone,copy
         * @param string $snapname The name of the snapshot.
         * @return Result
         */
        public function getRest($feature, $snapname = null)
        {
            $params = ['feature' => $feature,
                'snapname' => $snapname];
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/feature", $params);
        }

        /**
         * Check if feature for virtual machine is available.
         * @param string $feature Feature to check.
         *   Enum: snapshot,clone,copy
         * @param string $snapname The name of the snapshot.
         * @return Result
         */
        public function vmFeature($feature, $snapname = null)
        {
            return $this->getRest($feature, $snapname);
        }
    }

    class PVEVmidQemuNodeNodesClone extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Create a copy of virtual machine/template.
         * @param int $newid VMID for the clone.
         * @param string $description Description for the new VM.
         * @param string $format Target format for file storage.
         *   Enum: raw,qcow2,vmdk
         * @param bool $full Create a full copy of all disk. This is always done when you clone a normal VM. For VM templates, we try to create a linked clone by default.
         * @param string $name Set a name for the new VM.
         * @param string $pool Add the new VM to the specified pool.
         * @param string $snapname The name of the snapshot.
         * @param string $storage Target storage for full clone.
         * @param string $target Target node. Only allowed if the original VM is on shared storage.
         * @return Result
         */
        public function createRest($newid, $description = null, $format = null, $full = null, $name = null, $pool = null, $snapname = null, $storage = null, $target = null)
        {
            $params = ['newid' => $newid,
                'description' => $description,
                'format' => $format,
                'full' => $full,
                'name' => $name,
                'pool' => $pool,
                'snapname' => $snapname,
                'storage' => $storage,
                'target' => $target];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/clone", $params);
        }

        /**
         * Create a copy of virtual machine/template.
         * @param int $newid VMID for the clone.
         * @param string $description Description for the new VM.
         * @param string $format Target format for file storage.
         *   Enum: raw,qcow2,vmdk
         * @param bool $full Create a full copy of all disk. This is always done when you clone a normal VM. For VM templates, we try to create a linked clone by default.
         * @param string $name Set a name for the new VM.
         * @param string $pool Add the new VM to the specified pool.
         * @param string $snapname The name of the snapshot.
         * @param string $storage Target storage for full clone.
         * @param string $target Target node. Only allowed if the original VM is on shared storage.
         * @return Result
         */
        public function cloneVm($newid, $description = null, $format = null, $full = null, $name = null, $pool = null, $snapname = null, $storage = null, $target = null)
        {
            return $this->createRest($newid, $description, $format, $full, $name, $pool, $snapname, $storage, $target);
        }
    }

    class PVEVmidQemuNodeNodesMoveDisk extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Move volume to different storage.
         * @param string $disk The disk you want to move.
         *   Enum: ide0,ide1,ide2,ide3,scsi0,scsi1,scsi2,scsi3,scsi4,scsi5,scsi6,scsi7,scsi8,scsi9,scsi10,scsi11,scsi12,scsi13,virtio0,virtio1,virtio2,virtio3,virtio4,virtio5,virtio6,virtio7,virtio8,virtio9,virtio10,virtio11,virtio12,virtio13,virtio14,virtio15,sata0,sata1,sata2,sata3,sata4,sata5,efidisk0
         * @param string $storage Target storage.
         * @param bool $delete Delete the original disk after successful copy. By default the original disk is kept as unused disk.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $format Target Format.
         *   Enum: raw,qcow2,vmdk
         * @return Result
         */
        public function createRest($disk, $storage, $delete = null, $digest = null, $format = null)
        {
            $params = ['disk' => $disk,
                'storage' => $storage,
                'delete' => $delete,
                'digest' => $digest,
                'format' => $format];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/move_disk", $params);
        }

        /**
         * Move volume to different storage.
         * @param string $disk The disk you want to move.
         *   Enum: ide0,ide1,ide2,ide3,scsi0,scsi1,scsi2,scsi3,scsi4,scsi5,scsi6,scsi7,scsi8,scsi9,scsi10,scsi11,scsi12,scsi13,virtio0,virtio1,virtio2,virtio3,virtio4,virtio5,virtio6,virtio7,virtio8,virtio9,virtio10,virtio11,virtio12,virtio13,virtio14,virtio15,sata0,sata1,sata2,sata3,sata4,sata5,efidisk0
         * @param string $storage Target storage.
         * @param bool $delete Delete the original disk after successful copy. By default the original disk is kept as unused disk.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $format Target Format.
         *   Enum: raw,qcow2,vmdk
         * @return Result
         */
        public function moveVmDisk($disk, $storage, $delete = null, $digest = null, $format = null)
        {
            return $this->createRest($disk, $storage, $delete, $digest, $format);
        }
    }

    class PVEVmidQemuNodeNodesMigrate extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Migrate virtual machine. Creates a new migration task.
         * @param string $target Target node.
         * @param bool $force Allow to migrate VMs which use local devices. Only root may use this option.
         * @param string $migration_network CIDR of the (sub) network that is used for migration.
         * @param string $migration_type Migration traffic is encrypted using an SSH tunnel by default. On secure, completely private networks this can be disabled to increase performance.
         *   Enum: secure,insecure
         * @param bool $online Use online/live migration.
         * @param string $targetstorage Default target storage.
         * @param bool $with_local_disks Enable live storage migration for local disk
         * @return Result
         */
        public function createRest($target, $force = null, $migration_network = null, $migration_type = null, $online = null, $targetstorage = null, $with_local_disks = null)
        {
            $params = ['target' => $target,
                'force' => $force,
                'migration_network' => $migration_network,
                'migration_type' => $migration_type,
                'online' => $online,
                'targetstorage' => $targetstorage,
                'with-local-disks' => $with_local_disks];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/migrate", $params);
        }

        /**
         * Migrate virtual machine. Creates a new migration task.
         * @param string $target Target node.
         * @param bool $force Allow to migrate VMs which use local devices. Only root may use this option.
         * @param string $migration_network CIDR of the (sub) network that is used for migration.
         * @param string $migration_type Migration traffic is encrypted using an SSH tunnel by default. On secure, completely private networks this can be disabled to increase performance.
         *   Enum: secure,insecure
         * @param bool $online Use online/live migration.
         * @param string $targetstorage Default target storage.
         * @param bool $with_local_disks Enable live storage migration for local disk
         * @return Result
         */
        public function migrateVm($target, $force = null, $migration_network = null, $migration_type = null, $online = null, $targetstorage = null, $with_local_disks = null)
        {
            return $this->createRest($target, $force, $migration_network, $migration_type, $online, $targetstorage, $with_local_disks);
        }
    }

    class PVEVmidQemuNodeNodesMonitor extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute Qemu monitor commands.
         * @param string $command The monitor command.
         * @return Result
         */
        public function createRest($command)
        {
            $params = ['command' => $command];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/monitor", $params);
        }

        /**
         * Execute Qemu monitor commands.
         * @param string $command The monitor command.
         * @return Result
         */
        public function monitor($command)
        {
            return $this->createRest($command);
        }
    }

    class PVEVmidQemuNodeNodesAgent extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute Qemu Guest Agent commands.
         * @param string $command The QGA command.
         *   Enum: ping,get-time,info,fsfreeze-status,fsfreeze-freeze,fsfreeze-thaw,fstrim,network-get-interfaces,get-vcpus,get-fsinfo,get-memory-blocks,get-memory-block-info,suspend-hybrid,suspend-ram,suspend-disk,shutdown
         * @return Result
         */
        public function createRest($command)
        {
            $params = ['command' => $command];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent", $params);
        }

        /**
         * Execute Qemu Guest Agent commands.
         * @param string $command The QGA command.
         *   Enum: ping,get-time,info,fsfreeze-status,fsfreeze-freeze,fsfreeze-thaw,fstrim,network-get-interfaces,get-vcpus,get-fsinfo,get-memory-blocks,get-memory-block-info,suspend-hybrid,suspend-ram,suspend-disk,shutdown
         * @return Result
         */
        public function agent($command)
        {
            return $this->createRest($command);
        }
    }

    class PVEVmidQemuNodeNodesResize extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Extend volume size.
         * @param string $disk The disk you want to resize.
         *   Enum: ide0,ide1,ide2,ide3,scsi0,scsi1,scsi2,scsi3,scsi4,scsi5,scsi6,scsi7,scsi8,scsi9,scsi10,scsi11,scsi12,scsi13,virtio0,virtio1,virtio2,virtio3,virtio4,virtio5,virtio6,virtio7,virtio8,virtio9,virtio10,virtio11,virtio12,virtio13,virtio14,virtio15,sata0,sata1,sata2,sata3,sata4,sata5,efidisk0
         * @param string $size The new size. With the `+` sign the value is added to the actual size of the volume and without it, the value is taken as an absolute one. Shrinking disk size is not supported.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function setRest($disk, $size, $digest = null, $skiplock = null)
        {
            $params = ['disk' => $disk,
                'size' => $size,
                'digest' => $digest,
                'skiplock' => $skiplock];
            return $this->getClient()->set("/nodes/{$this->node}/qemu/{$this->vmid}/resize", $params);
        }

        /**
         * Extend volume size.
         * @param string $disk The disk you want to resize.
         *   Enum: ide0,ide1,ide2,ide3,scsi0,scsi1,scsi2,scsi3,scsi4,scsi5,scsi6,scsi7,scsi8,scsi9,scsi10,scsi11,scsi12,scsi13,virtio0,virtio1,virtio2,virtio3,virtio4,virtio5,virtio6,virtio7,virtio8,virtio9,virtio10,virtio11,virtio12,virtio13,virtio14,virtio15,sata0,sata1,sata2,sata3,sata4,sata5,efidisk0
         * @param string $size The new size. With the `+` sign the value is added to the actual size of the volume and without it, the value is taken as an absolute one. Shrinking disk size is not supported.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function resizeVm($disk, $size, $digest = null, $skiplock = null)
        {
            return $this->setRest($disk, $size, $digest, $skiplock);
        }
    }

    class PVEVmidQemuNodeNodesSnapshot extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        public function get($snapname)
        {
            return new PVEItemSnapshotVmidQemuNodeNodesSnapname($this->client, $this->node, $this->vmid, $snapname);
        }

        /**
         * List all snapshots.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot");
        }

        /**
         * List all snapshots.
         * @return Result
         */
        public function snapshotList()
        {
            return $this->getRest();
        }

        /**
         * Snapshot a VM.
         * @param string $snapname The name of the snapshot.
         * @param string $description A textual description or comment.
         * @param bool $vmstate Save the vmstate
         * @return Result
         */
        public function createRest($snapname, $description = null, $vmstate = null)
        {
            $params = ['snapname' => $snapname,
                'description' => $description,
                'vmstate' => $vmstate];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot", $params);
        }

        /**
         * Snapshot a VM.
         * @param string $snapname The name of the snapshot.
         * @param string $description A textual description or comment.
         * @param bool $vmstate Save the vmstate
         * @return Result
         */
        public function snapshot($snapname, $description = null, $vmstate = null)
        {
            return $this->createRest($snapname, $description, $vmstate);
        }
    }

    class PVEItemSnapshotVmidQemuNodeNodesSnapname extends Base
    {
        private $node;
        private $vmid;
        private $snapname;

        function __construct($client, $node, $vmid, $snapname)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->snapname = $snapname;
        }

        private $config;

        public function getConfig()
        {
            return $this->config ?: ($this->config = new PVESnapnameSnapshotVmidQemuNodeNodesConfig($this->client, $this->node, $this->vmid, $this->snapname));
        }

        private $rollback;

        public function getRollback()
        {
            return $this->rollback ?: ($this->rollback = new PVESnapnameSnapshotVmidQemuNodeNodesRollback($this->client, $this->node, $this->vmid, $this->snapname));
        }

        /**
         * Delete a VM snapshot.
         * @param bool $force For removal from config file, even if removing disk snapshots fails.
         * @return Result
         */
        public function deleteRest($force = null)
        {
            $params = ['force' => $force];
            return $this->getClient()->delete("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot/{$this->snapname}", $params);
        }

        /**
         * Delete a VM snapshot.
         * @param bool $force For removal from config file, even if removing disk snapshots fails.
         * @return Result
         */
        public function delsnapshot($force = null)
        {
            return $this->deleteRest($force);
        }

        /**
         *
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot/{$this->snapname}");
        }

        /**
         *
         * @return Result
         */
        public function snapshotCmdIdx()
        {
            return $this->getRest();
        }
    }

    class PVESnapnameSnapshotVmidQemuNodeNodesConfig extends Base
    {
        private $node;
        private $vmid;
        private $snapname;

        function __construct($client, $node, $vmid, $snapname)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->snapname = $snapname;
        }

        /**
         * Get snapshot configuration
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot/{$this->snapname}/config");
        }

        /**
         * Get snapshot configuration
         * @return Result
         */
        public function getSnapshotConfig()
        {
            return $this->getRest();
        }

        /**
         * Update snapshot metadata.
         * @param string $description A textual description or comment.
         * @return Result
         */
        public function setRest($description = null)
        {
            $params = ['description' => $description];
            return $this->getClient()->set("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot/{$this->snapname}/config", $params);
        }

        /**
         * Update snapshot metadata.
         * @param string $description A textual description or comment.
         * @return Result
         */
        public function updateSnapshotConfig($description = null)
        {
            return $this->setRest($description);
        }
    }

    class PVESnapnameSnapshotVmidQemuNodeNodesRollback extends Base
    {
        private $node;
        private $vmid;
        private $snapname;

        function __construct($client, $node, $vmid, $snapname)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->snapname = $snapname;
        }

        /**
         * Rollback VM state to specified snapshot.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot/{$this->snapname}/rollback");
        }

        /**
         * Rollback VM state to specified snapshot.
         * @return Result
         */
        public function rollback()
        {
            return $this->createRest();
        }
    }

    class PVEVmidQemuNodeNodesTemplate extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Create a Template.
         * @param string $disk If you want to convert only 1 disk to base image.
         *   Enum: ide0,ide1,ide2,ide3,scsi0,scsi1,scsi2,scsi3,scsi4,scsi5,scsi6,scsi7,scsi8,scsi9,scsi10,scsi11,scsi12,scsi13,virtio0,virtio1,virtio2,virtio3,virtio4,virtio5,virtio6,virtio7,virtio8,virtio9,virtio10,virtio11,virtio12,virtio13,virtio14,virtio15,sata0,sata1,sata2,sata3,sata4,sata5,efidisk0
         * @return Result
         */
        public function createRest($disk = null)
        {
            $params = ['disk' => $disk];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/template", $params);
        }

        /**
         * Create a Template.
         * @param string $disk If you want to convert only 1 disk to base image.
         *   Enum: ide0,ide1,ide2,ide3,scsi0,scsi1,scsi2,scsi3,scsi4,scsi5,scsi6,scsi7,scsi8,scsi9,scsi10,scsi11,scsi12,scsi13,virtio0,virtio1,virtio2,virtio3,virtio4,virtio5,virtio6,virtio7,virtio8,virtio9,virtio10,virtio11,virtio12,virtio13,virtio14,virtio15,sata0,sata1,sata2,sata3,sata4,sata5,efidisk0
         * @return Result
         */
        public function template($disk = null)
        {
            return $this->createRest($disk);
        }
    }

    class PVENodeNodesLxc extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($vmid)
        {
            return new PVEItemLxcNodeNodesVmid($this->client, $this->node, $vmid);
        }

        /**
         * LXC container index (per node).
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc");
        }

        /**
         * LXC container index (per node).
         * @return Result
         */
        public function vmlist()
        {
            return $this->getRest();
        }

        /**
         * Create or restore a container.
         * @param string $ostemplate The OS template or backup file.
         * @param int $vmid The (unique) ID of the VM.
         * @param string $arch OS architecture type.
         *   Enum: amd64,i386
         * @param string $cmode Console mode. By default, the console command tries to open a connection to one of the available tty devices. By setting cmode to 'console' it tries to attach to /dev/console instead. If you set cmode to 'shell', it simply invokes a shell inside the container (no login).
         *   Enum: shell,console,tty
         * @param bool $console Attach a console device (/dev/console) to the container.
         * @param int $cores The number of cores assigned to the container. A container can use all available cores by default.
         * @param int $cpulimit Limit of CPU usage.  NOTE: If the computer has 2 CPUs, it has a total of '2' CPU time. Value '0' indicates no CPU limit.
         * @param int $cpuunits CPU weight for a VM. Argument is used in the kernel fair scheduler. The larger the number is, the more CPU time this VM gets. Number is relative to the weights of all the other running VMs.  NOTE: You can disable fair-scheduler configuration by setting this to 0.
         * @param string $description Container description. Only used on the configuration web interface.
         * @param bool $force Allow to overwrite existing container.
         * @param string $hostname Set a host name for the container.
         * @param bool $ignore_unpack_errors Ignore errors when extracting the template.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param int $memory Amount of RAM for the VM in MB.
         * @param array $mpN Use volume as container mount point.
         * @param string $nameserver Sets DNS server IP address for a container. Create will automatically use the setting from the host if you neither set searchdomain nor nameserver.
         * @param array $netN Specifies network interfaces for the container.
         * @param bool $onboot Specifies whether a VM will be started during system bootup.
         * @param string $ostype OS type. This is used to setup configuration inside the container, and corresponds to lxc setup scripts in /usr/share/lxc/config/&amp;lt;ostype&amp;gt;.common.conf. Value 'unmanaged' can be used to skip and OS specific setup.
         *   Enum: debian,ubuntu,centos,fedora,opensuse,archlinux,alpine,gentoo,unmanaged
         * @param string $password Sets root password inside container.
         * @param string $pool Add the VM to the specified pool.
         * @param bool $protection Sets the protection flag of the container. This will prevent the CT or CT's disk remove/update operation.
         * @param bool $restore Mark this as restore task.
         * @param string $rootfs Use volume as container root.
         * @param string $searchdomain Sets DNS search domains for a container. Create will automatically use the setting from the host if you neither set searchdomain nor nameserver.
         * @param string $ssh_public_keys Setup public SSH keys (one key per line, OpenSSH format).
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param string $storage Default Storage.
         * @param int $swap Amount of SWAP for the VM in MB.
         * @param bool $template Enable/disable Template.
         * @param int $tty Specify the number of tty available to the container
         * @param bool $unprivileged Makes the container run as unprivileged user. (Should not be modified manually.)
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @return Result
         */
        public function createRest($ostemplate, $vmid, $arch = null, $cmode = null, $console = null, $cores = null, $cpulimit = null, $cpuunits = null, $description = null, $force = null, $hostname = null, $ignore_unpack_errors = null, $lock = null, $memory = null, $mpN = null, $nameserver = null, $netN = null, $onboot = null, $ostype = null, $password = null, $pool = null, $protection = null, $restore = null, $rootfs = null, $searchdomain = null, $ssh_public_keys = null, $startup = null, $storage = null, $swap = null, $template = null, $tty = null, $unprivileged = null, $unusedN = null)
        {
            $params = ['ostemplate' => $ostemplate,
                'vmid' => $vmid,
                'arch' => $arch,
                'cmode' => $cmode,
                'console' => $console,
                'cores' => $cores,
                'cpulimit' => $cpulimit,
                'cpuunits' => $cpuunits,
                'description' => $description,
                'force' => $force,
                'hostname' => $hostname,
                'ignore-unpack-errors' => $ignore_unpack_errors,
                'lock' => $lock,
                'memory' => $memory,
                'nameserver' => $nameserver,
                'onboot' => $onboot,
                'ostype' => $ostype,
                'password' => $password,
                'pool' => $pool,
                'protection' => $protection,
                'restore' => $restore,
                'rootfs' => $rootfs,
                'searchdomain' => $searchdomain,
                'ssh-public-keys' => $ssh_public_keys,
                'startup' => $startup,
                'storage' => $storage,
                'swap' => $swap,
                'template' => $template,
                'tty' => $tty,
                'unprivileged' => $unprivileged];
            $this->addIndexedParameter($params, 'mp', $mpN);
            $this->addIndexedParameter($params, 'net', $netN);
            $this->addIndexedParameter($params, 'unused', $unusedN);
            return $this->getClient()->create("/nodes/{$this->node}/lxc", $params);
        }

        /**
         * Create or restore a container.
         * @param string $ostemplate The OS template or backup file.
         * @param int $vmid The (unique) ID of the VM.
         * @param string $arch OS architecture type.
         *   Enum: amd64,i386
         * @param string $cmode Console mode. By default, the console command tries to open a connection to one of the available tty devices. By setting cmode to 'console' it tries to attach to /dev/console instead. If you set cmode to 'shell', it simply invokes a shell inside the container (no login).
         *   Enum: shell,console,tty
         * @param bool $console Attach a console device (/dev/console) to the container.
         * @param int $cores The number of cores assigned to the container. A container can use all available cores by default.
         * @param int $cpulimit Limit of CPU usage.  NOTE: If the computer has 2 CPUs, it has a total of '2' CPU time. Value '0' indicates no CPU limit.
         * @param int $cpuunits CPU weight for a VM. Argument is used in the kernel fair scheduler. The larger the number is, the more CPU time this VM gets. Number is relative to the weights of all the other running VMs.  NOTE: You can disable fair-scheduler configuration by setting this to 0.
         * @param string $description Container description. Only used on the configuration web interface.
         * @param bool $force Allow to overwrite existing container.
         * @param string $hostname Set a host name for the container.
         * @param bool $ignore_unpack_errors Ignore errors when extracting the template.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param int $memory Amount of RAM for the VM in MB.
         * @param array $mpN Use volume as container mount point.
         * @param string $nameserver Sets DNS server IP address for a container. Create will automatically use the setting from the host if you neither set searchdomain nor nameserver.
         * @param array $netN Specifies network interfaces for the container.
         * @param bool $onboot Specifies whether a VM will be started during system bootup.
         * @param string $ostype OS type. This is used to setup configuration inside the container, and corresponds to lxc setup scripts in /usr/share/lxc/config/&amp;lt;ostype&amp;gt;.common.conf. Value 'unmanaged' can be used to skip and OS specific setup.
         *   Enum: debian,ubuntu,centos,fedora,opensuse,archlinux,alpine,gentoo,unmanaged
         * @param string $password Sets root password inside container.
         * @param string $pool Add the VM to the specified pool.
         * @param bool $protection Sets the protection flag of the container. This will prevent the CT or CT's disk remove/update operation.
         * @param bool $restore Mark this as restore task.
         * @param string $rootfs Use volume as container root.
         * @param string $searchdomain Sets DNS search domains for a container. Create will automatically use the setting from the host if you neither set searchdomain nor nameserver.
         * @param string $ssh_public_keys Setup public SSH keys (one key per line, OpenSSH format).
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param string $storage Default Storage.
         * @param int $swap Amount of SWAP for the VM in MB.
         * @param bool $template Enable/disable Template.
         * @param int $tty Specify the number of tty available to the container
         * @param bool $unprivileged Makes the container run as unprivileged user. (Should not be modified manually.)
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @return Result
         */
        public function createVm($ostemplate, $vmid, $arch = null, $cmode = null, $console = null, $cores = null, $cpulimit = null, $cpuunits = null, $description = null, $force = null, $hostname = null, $ignore_unpack_errors = null, $lock = null, $memory = null, $mpN = null, $nameserver = null, $netN = null, $onboot = null, $ostype = null, $password = null, $pool = null, $protection = null, $restore = null, $rootfs = null, $searchdomain = null, $ssh_public_keys = null, $startup = null, $storage = null, $swap = null, $template = null, $tty = null, $unprivileged = null, $unusedN = null)
        {
            return $this->createRest($ostemplate, $vmid, $arch, $cmode, $console, $cores, $cpulimit, $cpuunits, $description, $force, $hostname, $ignore_unpack_errors, $lock, $memory, $mpN, $nameserver, $netN, $onboot, $ostype, $password, $pool, $protection, $restore, $rootfs, $searchdomain, $ssh_public_keys, $startup, $storage, $swap, $template, $tty, $unprivileged, $unusedN);
        }
    }

    class PVEItemLxcNodeNodesVmid extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        private $config;

        public function getConfig()
        {
            return $this->config ?: ($this->config = new PVEVmidLxcNodeNodesConfig($this->client, $this->node, $this->vmid));
        }

        private $status;

        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVEVmidLxcNodeNodesStatus($this->client, $this->node, $this->vmid));
        }

        private $snapshot;

        public function getSnapshot()
        {
            return $this->snapshot ?: ($this->snapshot = new PVEVmidLxcNodeNodesSnapshot($this->client, $this->node, $this->vmid));
        }

        private $firewall;

        public function getFirewall()
        {
            return $this->firewall ?: ($this->firewall = new PVEVmidLxcNodeNodesFirewall($this->client, $this->node, $this->vmid));
        }

        private $rrd;

        public function getRrd()
        {
            return $this->rrd ?: ($this->rrd = new PVEVmidLxcNodeNodesRrd($this->client, $this->node, $this->vmid));
        }

        private $rrddata;

        public function getRrddata()
        {
            return $this->rrddata ?: ($this->rrddata = new PVEVmidLxcNodeNodesRrddata($this->client, $this->node, $this->vmid));
        }

        private $vncproxy;

        public function getVncproxy()
        {
            return $this->vncproxy ?: ($this->vncproxy = new PVEVmidLxcNodeNodesVncproxy($this->client, $this->node, $this->vmid));
        }

        private $vncwebsocket;

        public function getVncwebsocket()
        {
            return $this->vncwebsocket ?: ($this->vncwebsocket = new PVEVmidLxcNodeNodesVncwebsocket($this->client, $this->node, $this->vmid));
        }

        private $spiceproxy;

        public function getSpiceproxy()
        {
            return $this->spiceproxy ?: ($this->spiceproxy = new PVEVmidLxcNodeNodesSpiceproxy($this->client, $this->node, $this->vmid));
        }

        private $migrate;

        public function getMigrate()
        {
            return $this->migrate ?: ($this->migrate = new PVEVmidLxcNodeNodesMigrate($this->client, $this->node, $this->vmid));
        }

        private $feature;

        public function getFeature()
        {
            return $this->feature ?: ($this->feature = new PVEVmidLxcNodeNodesFeature($this->client, $this->node, $this->vmid));
        }

        private $template;

        public function getTemplate()
        {
            return $this->template ?: ($this->template = new PVEVmidLxcNodeNodesTemplate($this->client, $this->node, $this->vmid));
        }

        private $clone;

        public function getClone()
        {
            return $this->clone ?: ($this->clone = new PVEVmidLxcNodeNodesClone($this->client, $this->node, $this->vmid));
        }

        private $resize;

        public function getResize()
        {
            return $this->resize ?: ($this->resize = new PVEVmidLxcNodeNodesResize($this->client, $this->node, $this->vmid));
        }

        /**
         * Destroy the container (also delete all uses files).
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/nodes/{$this->node}/lxc/{$this->vmid}");
        }

        /**
         * Destroy the container (also delete all uses files).
         * @return Result
         */
        public function destroyVm()
        {
            return $this->deleteRest();
        }

        /**
         * Directory index
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}");
        }

        /**
         * Directory index
         * @return Result
         */
        public function vmdiridx()
        {
            return $this->getRest();
        }
    }

    class PVEVmidLxcNodeNodesConfig extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get container configuration.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/config");
        }

        /**
         * Get container configuration.
         * @return Result
         */
        public function vmConfig()
        {
            return $this->getRest();
        }

        /**
         * Set container options.
         * @param string $arch OS architecture type.
         *   Enum: amd64,i386
         * @param string $cmode Console mode. By default, the console command tries to open a connection to one of the available tty devices. By setting cmode to 'console' it tries to attach to /dev/console instead. If you set cmode to 'shell', it simply invokes a shell inside the container (no login).
         *   Enum: shell,console,tty
         * @param bool $console Attach a console device (/dev/console) to the container.
         * @param int $cores The number of cores assigned to the container. A container can use all available cores by default.
         * @param int $cpulimit Limit of CPU usage.  NOTE: If the computer has 2 CPUs, it has a total of '2' CPU time. Value '0' indicates no CPU limit.
         * @param int $cpuunits CPU weight for a VM. Argument is used in the kernel fair scheduler. The larger the number is, the more CPU time this VM gets. Number is relative to the weights of all the other running VMs.  NOTE: You can disable fair-scheduler configuration by setting this to 0.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Container description. Only used on the configuration web interface.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $hostname Set a host name for the container.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param int $memory Amount of RAM for the VM in MB.
         * @param array $mpN Use volume as container mount point.
         * @param string $nameserver Sets DNS server IP address for a container. Create will automatically use the setting from the host if you neither set searchdomain nor nameserver.
         * @param array $netN Specifies network interfaces for the container.
         * @param bool $onboot Specifies whether a VM will be started during system bootup.
         * @param string $ostype OS type. This is used to setup configuration inside the container, and corresponds to lxc setup scripts in /usr/share/lxc/config/&amp;lt;ostype&amp;gt;.common.conf. Value 'unmanaged' can be used to skip and OS specific setup.
         *   Enum: debian,ubuntu,centos,fedora,opensuse,archlinux,alpine,gentoo,unmanaged
         * @param bool $protection Sets the protection flag of the container. This will prevent the CT or CT's disk remove/update operation.
         * @param string $rootfs Use volume as container root.
         * @param string $searchdomain Sets DNS search domains for a container. Create will automatically use the setting from the host if you neither set searchdomain nor nameserver.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param int $swap Amount of SWAP for the VM in MB.
         * @param bool $template Enable/disable Template.
         * @param int $tty Specify the number of tty available to the container
         * @param bool $unprivileged Makes the container run as unprivileged user. (Should not be modified manually.)
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @return Result
         */
        public function setRest($arch = null, $cmode = null, $console = null, $cores = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $hostname = null, $lock = null, $memory = null, $mpN = null, $nameserver = null, $netN = null, $onboot = null, $ostype = null, $protection = null, $rootfs = null, $searchdomain = null, $startup = null, $swap = null, $template = null, $tty = null, $unprivileged = null, $unusedN = null)
        {
            $params = ['arch' => $arch,
                'cmode' => $cmode,
                'console' => $console,
                'cores' => $cores,
                'cpulimit' => $cpulimit,
                'cpuunits' => $cpuunits,
                'delete' => $delete,
                'description' => $description,
                'digest' => $digest,
                'hostname' => $hostname,
                'lock' => $lock,
                'memory' => $memory,
                'nameserver' => $nameserver,
                'onboot' => $onboot,
                'ostype' => $ostype,
                'protection' => $protection,
                'rootfs' => $rootfs,
                'searchdomain' => $searchdomain,
                'startup' => $startup,
                'swap' => $swap,
                'template' => $template,
                'tty' => $tty,
                'unprivileged' => $unprivileged];
            $this->addIndexedParameter($params, 'mp', $mpN);
            $this->addIndexedParameter($params, 'net', $netN);
            $this->addIndexedParameter($params, 'unused', $unusedN);
            return $this->getClient()->set("/nodes/{$this->node}/lxc/{$this->vmid}/config", $params);
        }

        /**
         * Set container options.
         * @param string $arch OS architecture type.
         *   Enum: amd64,i386
         * @param string $cmode Console mode. By default, the console command tries to open a connection to one of the available tty devices. By setting cmode to 'console' it tries to attach to /dev/console instead. If you set cmode to 'shell', it simply invokes a shell inside the container (no login).
         *   Enum: shell,console,tty
         * @param bool $console Attach a console device (/dev/console) to the container.
         * @param int $cores The number of cores assigned to the container. A container can use all available cores by default.
         * @param int $cpulimit Limit of CPU usage.  NOTE: If the computer has 2 CPUs, it has a total of '2' CPU time. Value '0' indicates no CPU limit.
         * @param int $cpuunits CPU weight for a VM. Argument is used in the kernel fair scheduler. The larger the number is, the more CPU time this VM gets. Number is relative to the weights of all the other running VMs.  NOTE: You can disable fair-scheduler configuration by setting this to 0.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Container description. Only used on the configuration web interface.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $hostname Set a host name for the container.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param int $memory Amount of RAM for the VM in MB.
         * @param array $mpN Use volume as container mount point.
         * @param string $nameserver Sets DNS server IP address for a container. Create will automatically use the setting from the host if you neither set searchdomain nor nameserver.
         * @param array $netN Specifies network interfaces for the container.
         * @param bool $onboot Specifies whether a VM will be started during system bootup.
         * @param string $ostype OS type. This is used to setup configuration inside the container, and corresponds to lxc setup scripts in /usr/share/lxc/config/&amp;lt;ostype&amp;gt;.common.conf. Value 'unmanaged' can be used to skip and OS specific setup.
         *   Enum: debian,ubuntu,centos,fedora,opensuse,archlinux,alpine,gentoo,unmanaged
         * @param bool $protection Sets the protection flag of the container. This will prevent the CT or CT's disk remove/update operation.
         * @param string $rootfs Use volume as container root.
         * @param string $searchdomain Sets DNS search domains for a container. Create will automatically use the setting from the host if you neither set searchdomain nor nameserver.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param int $swap Amount of SWAP for the VM in MB.
         * @param bool $template Enable/disable Template.
         * @param int $tty Specify the number of tty available to the container
         * @param bool $unprivileged Makes the container run as unprivileged user. (Should not be modified manually.)
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @return Result
         */
        public function updateVm($arch = null, $cmode = null, $console = null, $cores = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $hostname = null, $lock = null, $memory = null, $mpN = null, $nameserver = null, $netN = null, $onboot = null, $ostype = null, $protection = null, $rootfs = null, $searchdomain = null, $startup = null, $swap = null, $template = null, $tty = null, $unprivileged = null, $unusedN = null)
        {
            return $this->setRest($arch, $cmode, $console, $cores, $cpulimit, $cpuunits, $delete, $description, $digest, $hostname, $lock, $memory, $mpN, $nameserver, $netN, $onboot, $ostype, $protection, $rootfs, $searchdomain, $startup, $swap, $template, $tty, $unprivileged, $unusedN);
        }
    }

    class PVEVmidLxcNodeNodesStatus extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        private $current;

        public function getCurrent()
        {
            return $this->current ?: ($this->current = new PVEStatusVmidLxcNodeNodesCurrent($this->client, $this->node, $this->vmid));
        }

        private $start;

        public function getStart()
        {
            return $this->start ?: ($this->start = new PVEStatusVmidLxcNodeNodesStart($this->client, $this->node, $this->vmid));
        }

        private $stop;

        public function getStop()
        {
            return $this->stop ?: ($this->stop = new PVEStatusVmidLxcNodeNodesStop($this->client, $this->node, $this->vmid));
        }

        private $shutdown;

        public function getShutdown()
        {
            return $this->shutdown ?: ($this->shutdown = new PVEStatusVmidLxcNodeNodesShutdown($this->client, $this->node, $this->vmid));
        }

        private $suspend;

        public function getSuspend()
        {
            return $this->suspend ?: ($this->suspend = new PVEStatusVmidLxcNodeNodesSuspend($this->client, $this->node, $this->vmid));
        }

        private $resume;

        public function getResume()
        {
            return $this->resume ?: ($this->resume = new PVEStatusVmidLxcNodeNodesResume($this->client, $this->node, $this->vmid));
        }

        /**
         * Directory index
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/status");
        }

        /**
         * Directory index
         * @return Result
         */
        public function vmcmdidx()
        {
            return $this->getRest();
        }
    }

    class PVEStatusVmidLxcNodeNodesCurrent extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get virtual machine status.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/status/current");
        }

        /**
         * Get virtual machine status.
         * @return Result
         */
        public function vmStatus()
        {
            return $this->getRest();
        }
    }

    class PVEStatusVmidLxcNodeNodesStart extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Start the container.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function createRest($skiplock = null)
        {
            $params = ['skiplock' => $skiplock];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/status/start", $params);
        }

        /**
         * Start the container.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function vmStart($skiplock = null)
        {
            return $this->createRest($skiplock);
        }
    }

    class PVEStatusVmidLxcNodeNodesStop extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Stop the container. This will abruptly stop all processes running in the container.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function createRest($skiplock = null)
        {
            $params = ['skiplock' => $skiplock];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/status/stop", $params);
        }

        /**
         * Stop the container. This will abruptly stop all processes running in the container.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @return Result
         */
        public function vmStop($skiplock = null)
        {
            return $this->createRest($skiplock);
        }
    }

    class PVEStatusVmidLxcNodeNodesShutdown extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Shutdown the container. This will trigger a clean shutdown of the container, see lxc-stop(1) for details.
         * @param bool $forceStop Make sure the Container stops.
         * @param int $timeout Wait maximal timeout seconds.
         * @return Result
         */
        public function createRest($forceStop = null, $timeout = null)
        {
            $params = ['forceStop' => $forceStop,
                'timeout' => $timeout];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/status/shutdown", $params);
        }

        /**
         * Shutdown the container. This will trigger a clean shutdown of the container, see lxc-stop(1) for details.
         * @param bool $forceStop Make sure the Container stops.
         * @param int $timeout Wait maximal timeout seconds.
         * @return Result
         */
        public function vmShutdown($forceStop = null, $timeout = null)
        {
            return $this->createRest($forceStop, $timeout);
        }
    }

    class PVEStatusVmidLxcNodeNodesSuspend extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Suspend the container.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/status/suspend");
        }

        /**
         * Suspend the container.
         * @return Result
         */
        public function vmSuspend()
        {
            return $this->createRest();
        }
    }

    class PVEStatusVmidLxcNodeNodesResume extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Resume the container.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/status/resume");
        }

        /**
         * Resume the container.
         * @return Result
         */
        public function vmResume()
        {
            return $this->createRest();
        }
    }

    class PVEVmidLxcNodeNodesSnapshot extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        public function get($snapname)
        {
            return new PVEItemSnapshotVmidLxcNodeNodesSnapname($this->client, $this->node, $this->vmid, $snapname);
        }

        /**
         * List all snapshots.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot");
        }

        /**
         * List all snapshots.
         * @return Result
         */
        public function list_()
        {
            return $this->getRest();
        }

        /**
         * Snapshot a container.
         * @param string $snapname The name of the snapshot.
         * @param string $description A textual description or comment.
         * @return Result
         */
        public function createRest($snapname, $description = null)
        {
            $params = ['snapname' => $snapname,
                'description' => $description];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot", $params);
        }

        /**
         * Snapshot a container.
         * @param string $snapname The name of the snapshot.
         * @param string $description A textual description or comment.
         * @return Result
         */
        public function snapshot($snapname, $description = null)
        {
            return $this->createRest($snapname, $description);
        }
    }

    class PVEItemSnapshotVmidLxcNodeNodesSnapname extends Base
    {
        private $node;
        private $vmid;
        private $snapname;

        function __construct($client, $node, $vmid, $snapname)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->snapname = $snapname;
        }

        private $rollback;

        public function getRollback()
        {
            return $this->rollback ?: ($this->rollback = new PVESnapnameSnapshotVmidLxcNodeNodesRollback($this->client, $this->node, $this->vmid, $this->snapname));
        }

        private $config;

        public function getConfig()
        {
            return $this->config ?: ($this->config = new PVESnapnameSnapshotVmidLxcNodeNodesConfig($this->client, $this->node, $this->vmid, $this->snapname));
        }

        /**
         * Delete a LXC snapshot.
         * @param bool $force For removal from config file, even if removing disk snapshots fails.
         * @return Result
         */
        public function deleteRest($force = null)
        {
            $params = ['force' => $force];
            return $this->getClient()->delete("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot/{$this->snapname}", $params);
        }

        /**
         * Delete a LXC snapshot.
         * @param bool $force For removal from config file, even if removing disk snapshots fails.
         * @return Result
         */
        public function delsnapshot($force = null)
        {
            return $this->deleteRest($force);
        }

        /**
         *
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot/{$this->snapname}");
        }

        /**
         *
         * @return Result
         */
        public function snapshotCmdIdx()
        {
            return $this->getRest();
        }
    }

    class PVESnapnameSnapshotVmidLxcNodeNodesRollback extends Base
    {
        private $node;
        private $vmid;
        private $snapname;

        function __construct($client, $node, $vmid, $snapname)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->snapname = $snapname;
        }

        /**
         * Rollback LXC state to specified snapshot.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot/{$this->snapname}/rollback");
        }

        /**
         * Rollback LXC state to specified snapshot.
         * @return Result
         */
        public function rollback()
        {
            return $this->createRest();
        }
    }

    class PVESnapnameSnapshotVmidLxcNodeNodesConfig extends Base
    {
        private $node;
        private $vmid;
        private $snapname;

        function __construct($client, $node, $vmid, $snapname)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->snapname = $snapname;
        }

        /**
         * Get snapshot configuration
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot/{$this->snapname}/config");
        }

        /**
         * Get snapshot configuration
         * @return Result
         */
        public function getSnapshotConfig()
        {
            return $this->getRest();
        }

        /**
         * Update snapshot metadata.
         * @param string $description A textual description or comment.
         * @return Result
         */
        public function setRest($description = null)
        {
            $params = ['description' => $description];
            return $this->getClient()->set("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot/{$this->snapname}/config", $params);
        }

        /**
         * Update snapshot metadata.
         * @param string $description A textual description or comment.
         * @return Result
         */
        public function updateSnapshotConfig($description = null)
        {
            return $this->setRest($description);
        }
    }

    class PVEVmidLxcNodeNodesFirewall extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        private $rules;

        public function getRules()
        {
            return $this->rules ?: ($this->rules = new PVEFirewallVmidLxcNodeNodesRules($this->client, $this->node, $this->vmid));
        }

        private $aliases;

        public function getAliases()
        {
            return $this->aliases ?: ($this->aliases = new PVEFirewallVmidLxcNodeNodesAliases($this->client, $this->node, $this->vmid));
        }

        private $ipset;

        public function getIpset()
        {
            return $this->ipset ?: ($this->ipset = new PVEFirewallVmidLxcNodeNodesIpset($this->client, $this->node, $this->vmid));
        }

        private $options;

        public function getOptions()
        {
            return $this->options ?: ($this->options = new PVEFirewallVmidLxcNodeNodesOptions($this->client, $this->node, $this->vmid));
        }

        private $log;

        public function getLog()
        {
            return $this->log ?: ($this->log = new PVEFirewallVmidLxcNodeNodesLog($this->client, $this->node, $this->vmid));
        }

        private $refs;

        public function getRefs()
        {
            return $this->refs ?: ($this->refs = new PVEFirewallVmidLxcNodeNodesRefs($this->client, $this->node, $this->vmid));
        }

        /**
         * Directory index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/firewall");
        }

        /**
         * Directory index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEFirewallVmidLxcNodeNodesRules extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        public function get($pos)
        {
            return new PVEItemRulesFirewallVmidLxcNodeNodesPos($this->client, $this->node, $this->vmid, $pos);
        }

        /**
         * List rules.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/rules");
        }

        /**
         * List rules.
         * @return Result
         */
        public function getRules()
        {
            return $this->getRest();
        }

        /**
         * Create new rule.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @param string $comment Descriptive comment.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $pos Update rule at position &amp;lt;pos&amp;gt;.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @return Result
         */
        public function createRest($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            $params = ['action' => $action,
                'type' => $type,
                'comment' => $comment,
                'dest' => $dest,
                'digest' => $digest,
                'dport' => $dport,
                'enable' => $enable,
                'iface' => $iface,
                'macro' => $macro,
                'pos' => $pos,
                'proto' => $proto,
                'source' => $source,
                'sport' => $sport];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/rules", $params);
        }

        /**
         * Create new rule.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @param string $comment Descriptive comment.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $pos Update rule at position &amp;lt;pos&amp;gt;.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @return Result
         */
        public function createRule($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            return $this->createRest($action, $type, $comment, $dest, $digest, $dport, $enable, $iface, $macro, $pos, $proto, $source, $sport);
        }
    }

    class PVEItemRulesFirewallVmidLxcNodeNodesPos extends Base
    {
        private $node;
        private $vmid;
        private $pos;

        function __construct($client, $node, $vmid, $pos)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->pos = $pos;
        }

        /**
         * Delete rule.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRest($digest = null)
        {
            $params = ['digest' => $digest];
            return $this->getClient()->delete("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/rules/{$this->pos}", $params);
        }

        /**
         * Delete rule.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRule($digest = null)
        {
            return $this->deleteRest($digest);
        }

        /**
         * Get single rule data.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/rules/{$this->pos}");
        }

        /**
         * Get single rule data.
         * @return Result
         */
        public function getRule()
        {
            return $this->getRest();
        }

        /**
         * Modify rule data.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $comment Descriptive comment.
         * @param string $delete A list of settings you want to delete.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $moveto Move rule to new position &amp;lt;moveto&amp;gt;. Other arguments are ignored.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @return Result
         */
        public function setRest($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            $params = ['action' => $action,
                'comment' => $comment,
                'delete' => $delete,
                'dest' => $dest,
                'digest' => $digest,
                'dport' => $dport,
                'enable' => $enable,
                'iface' => $iface,
                'macro' => $macro,
                'moveto' => $moveto,
                'proto' => $proto,
                'source' => $source,
                'sport' => $sport,
                'type' => $type];
            return $this->getClient()->set("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/rules/{$this->pos}", $params);
        }

        /**
         * Modify rule data.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $comment Descriptive comment.
         * @param string $delete A list of settings you want to delete.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $moveto Move rule to new position &amp;lt;moveto&amp;gt;. Other arguments are ignored.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @return Result
         */
        public function updateRule($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            return $this->setRest($action, $comment, $delete, $dest, $digest, $dport, $enable, $iface, $macro, $moveto, $proto, $source, $sport, $type);
        }
    }

    class PVEFirewallVmidLxcNodeNodesAliases extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        public function get($name)
        {
            return new PVEItemAliasesFirewallVmidLxcNodeNodesName($this->client, $this->node, $this->vmid, $name);
        }

        /**
         * List aliases
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/aliases");
        }

        /**
         * List aliases
         * @return Result
         */
        public function getAliases()
        {
            return $this->getRest();
        }

        /**
         * Create IP or Network Alias.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $name Alias name.
         * @param string $comment
         * @return Result
         */
        public function createRest($cidr, $name, $comment = null)
        {
            $params = ['cidr' => $cidr,
                'name' => $name,
                'comment' => $comment];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/aliases", $params);
        }

        /**
         * Create IP or Network Alias.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $name Alias name.
         * @param string $comment
         * @return Result
         */
        public function createAlias($cidr, $name, $comment = null)
        {
            return $this->createRest($cidr, $name, $comment);
        }
    }

    class PVEItemAliasesFirewallVmidLxcNodeNodesName extends Base
    {
        private $node;
        private $vmid;
        private $name;

        function __construct($client, $node, $vmid, $name)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->name = $name;
        }

        /**
         * Remove IP or Network alias.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRest($digest = null)
        {
            $params = ['digest' => $digest];
            return $this->getClient()->delete("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/aliases/{$this->name}", $params);
        }

        /**
         * Remove IP or Network alias.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function removeAlias($digest = null)
        {
            return $this->deleteRest($digest);
        }

        /**
         * Read alias.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/aliases/{$this->name}");
        }

        /**
         * Read alias.
         * @return Result
         */
        public function readAlias()
        {
            return $this->getRest();
        }

        /**
         * Update IP or Network alias.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename an existing alias.
         * @return Result
         */
        public function setRest($cidr, $comment = null, $digest = null, $rename = null)
        {
            $params = ['cidr' => $cidr,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            return $this->getClient()->set("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/aliases/{$this->name}", $params);
        }

        /**
         * Update IP or Network alias.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename an existing alias.
         * @return Result
         */
        public function updateAlias($cidr, $comment = null, $digest = null, $rename = null)
        {
            return $this->setRest($cidr, $comment, $digest, $rename);
        }
    }

    class PVEFirewallVmidLxcNodeNodesIpset extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        public function get($name)
        {
            return new PVEItemIpsetFirewallVmidLxcNodeNodesName($this->client, $this->node, $this->vmid, $name);
        }

        /**
         * List IPSets
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset");
        }

        /**
         * List IPSets
         * @return Result
         */
        public function ipsetIndex()
        {
            return $this->getRest();
        }

        /**
         * Create new IPSet
         * @param string $name IP set name.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename an existing IPSet. You can set 'rename' to the same value as 'name' to update the 'comment' of an existing IPSet.
         * @return Result
         */
        public function createRest($name, $comment = null, $digest = null, $rename = null)
        {
            $params = ['name' => $name,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset", $params);
        }

        /**
         * Create new IPSet
         * @param string $name IP set name.
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $rename Rename an existing IPSet. You can set 'rename' to the same value as 'name' to update the 'comment' of an existing IPSet.
         * @return Result
         */
        public function createIpset($name, $comment = null, $digest = null, $rename = null)
        {
            return $this->createRest($name, $comment, $digest, $rename);
        }
    }

    class PVEItemIpsetFirewallVmidLxcNodeNodesName extends Base
    {
        private $node;
        private $vmid;
        private $name;

        function __construct($client, $node, $vmid, $name)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->name = $name;
        }

        public function get($cidr)
        {
            return new PVEItemNameIpsetFirewallVmidLxcNodeNodesCidr($this->client, $this->node, $this->vmid, $this->name, $cidr);
        }

        /**
         * Delete IPSet
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset/{$this->name}");
        }

        /**
         * Delete IPSet
         * @return Result
         */
        public function deleteIpset()
        {
            return $this->deleteRest();
        }

        /**
         * List IPSet content
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset/{$this->name}");
        }

        /**
         * List IPSet content
         * @return Result
         */
        public function getIpset()
        {
            return $this->getRest();
        }

        /**
         * Add IP or Network to IPSet.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $comment
         * @param bool $nomatch
         * @return Result
         */
        public function createRest($cidr, $comment = null, $nomatch = null)
        {
            $params = ['cidr' => $cidr,
                'comment' => $comment,
                'nomatch' => $nomatch];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset/{$this->name}", $params);
        }

        /**
         * Add IP or Network to IPSet.
         * @param string $cidr Network/IP specification in CIDR format.
         * @param string $comment
         * @param bool $nomatch
         * @return Result
         */
        public function createIp($cidr, $comment = null, $nomatch = null)
        {
            return $this->createRest($cidr, $comment, $nomatch);
        }
    }

    class PVEItemNameIpsetFirewallVmidLxcNodeNodesCidr extends Base
    {
        private $node;
        private $vmid;
        private $name;
        private $cidr;

        function __construct($client, $node, $vmid, $name, $cidr)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->name = $name;
            $this->cidr = $cidr;
        }

        /**
         * Remove IP or Network from IPSet.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRest($digest = null)
        {
            $params = ['digest' => $digest];
            return $this->getClient()->delete("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset/{$this->name}/{$this->cidr}", $params);
        }

        /**
         * Remove IP or Network from IPSet.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function removeIp($digest = null)
        {
            return $this->deleteRest($digest);
        }

        /**
         * Read IP or Network settings from IPSet.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset/{$this->name}/{$this->cidr}");
        }

        /**
         * Read IP or Network settings from IPSet.
         * @return Result
         */
        public function readIp()
        {
            return $this->getRest();
        }

        /**
         * Update IP or Network settings
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $nomatch
         * @return Result
         */
        public function setRest($comment = null, $digest = null, $nomatch = null)
        {
            $params = ['comment' => $comment,
                'digest' => $digest,
                'nomatch' => $nomatch];
            return $this->getClient()->set("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset/{$this->name}/{$this->cidr}", $params);
        }

        /**
         * Update IP or Network settings
         * @param string $comment
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $nomatch
         * @return Result
         */
        public function updateIp($comment = null, $digest = null, $nomatch = null)
        {
            return $this->setRest($comment, $digest, $nomatch);
        }
    }

    class PVEFirewallVmidLxcNodeNodesOptions extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get VM firewall options.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/options");
        }

        /**
         * Get VM firewall options.
         * @return Result
         */
        public function getOptions()
        {
            return $this->getRest();
        }

        /**
         * Set Firewall options.
         * @param string $delete A list of settings you want to delete.
         * @param bool $dhcp Enable DHCP.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $enable Enable/disable firewall rules.
         * @param bool $ipfilter Enable default IP filters. This is equivalent to adding an empty ipfilter-net&amp;lt;id&amp;gt; ipset for every interface. Such ipsets implicitly contain sane default restrictions such as restricting IPv6 link local addresses to the one derived from the interface's MAC address. For containers the configured IP addresses will be implicitly added.
         * @param string $log_level_in Log level for incoming traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param string $log_level_out Log level for outgoing traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param bool $macfilter Enable/disable MAC address filter.
         * @param bool $ndp Enable NDP.
         * @param string $policy_in Input policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param string $policy_out Output policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param bool $radv Allow sending Router Advertisement.
         * @return Result
         */
        public function setRest($delete = null, $dhcp = null, $digest = null, $enable = null, $ipfilter = null, $log_level_in = null, $log_level_out = null, $macfilter = null, $ndp = null, $policy_in = null, $policy_out = null, $radv = null)
        {
            $params = ['delete' => $delete,
                'dhcp' => $dhcp,
                'digest' => $digest,
                'enable' => $enable,
                'ipfilter' => $ipfilter,
                'log_level_in' => $log_level_in,
                'log_level_out' => $log_level_out,
                'macfilter' => $macfilter,
                'ndp' => $ndp,
                'policy_in' => $policy_in,
                'policy_out' => $policy_out,
                'radv' => $radv];
            return $this->getClient()->set("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/options", $params);
        }

        /**
         * Set Firewall options.
         * @param string $delete A list of settings you want to delete.
         * @param bool $dhcp Enable DHCP.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $enable Enable/disable firewall rules.
         * @param bool $ipfilter Enable default IP filters. This is equivalent to adding an empty ipfilter-net&amp;lt;id&amp;gt; ipset for every interface. Such ipsets implicitly contain sane default restrictions such as restricting IPv6 link local addresses to the one derived from the interface's MAC address. For containers the configured IP addresses will be implicitly added.
         * @param string $log_level_in Log level for incoming traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param string $log_level_out Log level for outgoing traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param bool $macfilter Enable/disable MAC address filter.
         * @param bool $ndp Enable NDP.
         * @param string $policy_in Input policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param string $policy_out Output policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param bool $radv Allow sending Router Advertisement.
         * @return Result
         */
        public function setOptions($delete = null, $dhcp = null, $digest = null, $enable = null, $ipfilter = null, $log_level_in = null, $log_level_out = null, $macfilter = null, $ndp = null, $policy_in = null, $policy_out = null, $radv = null)
        {
            return $this->setRest($delete, $dhcp, $digest, $enable, $ipfilter, $log_level_in, $log_level_out, $macfilter, $ndp, $policy_in, $policy_out, $radv);
        }
    }

    class PVEFirewallVmidLxcNodeNodesLog extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Read firewall log
         * @param int $limit
         * @param int $start
         * @return Result
         */
        public function getRest($limit = null, $start = null)
        {
            $params = ['limit' => $limit,
                'start' => $start];
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/log", $params);
        }

        /**
         * Read firewall log
         * @param int $limit
         * @param int $start
         * @return Result
         */
        public function log($limit = null, $start = null)
        {
            return $this->getRest($limit, $start);
        }
    }

    class PVEFirewallVmidLxcNodeNodesRefs extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Lists possible IPSet/Alias reference which are allowed in source/dest properties.
         * @param string $type Only list references of specified type.
         *   Enum: alias,ipset
         * @return Result
         */
        public function getRest($type = null)
        {
            $params = ['type' => $type];
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/refs", $params);
        }

        /**
         * Lists possible IPSet/Alias reference which are allowed in source/dest properties.
         * @param string $type Only list references of specified type.
         *   Enum: alias,ipset
         * @return Result
         */
        public function refs($type = null)
        {
            return $this->getRest($type);
        }
    }

    class PVEVmidLxcNodeNodesRrd extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Read VM RRD statistics (returns PNG)
         * @param string $ds The list of datasources you want to display.
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function getRest($ds, $timeframe, $cf = null)
        {
            $params = ['ds' => $ds,
                'timeframe' => $timeframe,
                'cf' => $cf];
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/rrd", $params);
        }

        /**
         * Read VM RRD statistics (returns PNG)
         * @param string $ds The list of datasources you want to display.
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function rrd($ds, $timeframe, $cf = null)
        {
            return $this->getRest($ds, $timeframe, $cf);
        }
    }

    class PVEVmidLxcNodeNodesRrddata extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Read VM RRD statistics
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function getRest($timeframe, $cf = null)
        {
            $params = ['timeframe' => $timeframe,
                'cf' => $cf];
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/rrddata", $params);
        }

        /**
         * Read VM RRD statistics
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function rrddata($timeframe, $cf = null)
        {
            return $this->getRest($timeframe, $cf);
        }
    }

    class PVEVmidLxcNodeNodesVncproxy extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Creates a TCP VNC proxy connections.
         * @param int $height sets the height of the console in pixels.
         * @param bool $websocket use websocket instead of standard VNC.
         * @param int $width sets the width of the console in pixels.
         * @return Result
         */
        public function createRest($height = null, $websocket = null, $width = null)
        {
            $params = ['height' => $height,
                'websocket' => $websocket,
                'width' => $width];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/vncproxy", $params);
        }

        /**
         * Creates a TCP VNC proxy connections.
         * @param int $height sets the height of the console in pixels.
         * @param bool $websocket use websocket instead of standard VNC.
         * @param int $width sets the width of the console in pixels.
         * @return Result
         */
        public function vncproxy($height = null, $websocket = null, $width = null)
        {
            return $this->createRest($height, $websocket, $width);
        }
    }

    class PVEVmidLxcNodeNodesVncwebsocket extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Opens a weksocket for VNC traffic.
         * @param int $port Port number returned by previous vncproxy call.
         * @param string $vncticket Ticket from previous call to vncproxy.
         * @return Result
         */
        public function getRest($port, $vncticket)
        {
            $params = ['port' => $port,
                'vncticket' => $vncticket];
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/vncwebsocket", $params);
        }

        /**
         * Opens a weksocket for VNC traffic.
         * @param int $port Port number returned by previous vncproxy call.
         * @param string $vncticket Ticket from previous call to vncproxy.
         * @return Result
         */
        public function vncwebsocket($port, $vncticket)
        {
            return $this->getRest($port, $vncticket);
        }
    }

    class PVEVmidLxcNodeNodesSpiceproxy extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Returns a SPICE configuration to connect to the CT.
         * @param string $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As resonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @return Result
         */
        public function createRest($proxy = null)
        {
            $params = ['proxy' => $proxy];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/spiceproxy", $params);
        }

        /**
         * Returns a SPICE configuration to connect to the CT.
         * @param string $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As resonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @return Result
         */
        public function spiceproxy($proxy = null)
        {
            return $this->createRest($proxy);
        }
    }

    class PVEVmidLxcNodeNodesMigrate extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Migrate the container to another node. Creates a new migration task.
         * @param string $target Target node.
         * @param bool $force Force migration despite local bind / device mounts. NOTE: deprecated, use 'shared' property of mount point instead.
         * @param bool $online Use online/live migration.
         * @param bool $restart Use restart migration
         * @param int $timeout Timeout in seconds for shutdown for restart migration
         * @return Result
         */
        public function createRest($target, $force = null, $online = null, $restart = null, $timeout = null)
        {
            $params = ['target' => $target,
                'force' => $force,
                'online' => $online,
                'restart' => $restart,
                'timeout' => $timeout];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/migrate", $params);
        }

        /**
         * Migrate the container to another node. Creates a new migration task.
         * @param string $target Target node.
         * @param bool $force Force migration despite local bind / device mounts. NOTE: deprecated, use 'shared' property of mount point instead.
         * @param bool $online Use online/live migration.
         * @param bool $restart Use restart migration
         * @param int $timeout Timeout in seconds for shutdown for restart migration
         * @return Result
         */
        public function migrateVm($target, $force = null, $online = null, $restart = null, $timeout = null)
        {
            return $this->createRest($target, $force, $online, $restart, $timeout);
        }
    }

    class PVEVmidLxcNodeNodesFeature extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Check if feature for virtual machine is available.
         * @param string $feature Feature to check.
         *   Enum: snapshot
         * @param string $snapname The name of the snapshot.
         * @return Result
         */
        public function getRest($feature, $snapname = null)
        {
            $params = ['feature' => $feature,
                'snapname' => $snapname];
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/feature", $params);
        }

        /**
         * Check if feature for virtual machine is available.
         * @param string $feature Feature to check.
         *   Enum: snapshot
         * @param string $snapname The name of the snapshot.
         * @return Result
         */
        public function vmFeature($feature, $snapname = null)
        {
            return $this->getRest($feature, $snapname);
        }
    }

    class PVEVmidLxcNodeNodesTemplate extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Create a Template.
         * @param bool $experimental The template feature is experimental, set this flag if you know what you are doing.
         * @return Result
         */
        public function createRest($experimental)
        {
            $params = ['experimental' => $experimental];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/template", $params);
        }

        /**
         * Create a Template.
         * @param bool $experimental The template feature is experimental, set this flag if you know what you are doing.
         * @return Result
         */
        public function template($experimental)
        {
            return $this->createRest($experimental);
        }
    }

    class PVEVmidLxcNodeNodesClone extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Create a container clone/copy
         * @param bool $experimental The clone feature is experimental, set this flag if you know what you are doing.
         * @param int $newid VMID for the clone.
         * @param string $description Description for the new CT.
         * @param bool $full Create a full copy of all disk. This is always done when you clone a normal CT. For CT templates, we try to create a linked clone by default.
         * @param string $hostname Set a hostname for the new CT.
         * @param string $pool Add the new CT to the specified pool.
         * @param string $snapname The name of the snapshot.
         * @param string $storage Target storage for full clone.
         * @return Result
         */
        public function createRest($experimental, $newid, $description = null, $full = null, $hostname = null, $pool = null, $snapname = null, $storage = null)
        {
            $params = ['experimental' => $experimental,
                'newid' => $newid,
                'description' => $description,
                'full' => $full,
                'hostname' => $hostname,
                'pool' => $pool,
                'snapname' => $snapname,
                'storage' => $storage];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/clone", $params);
        }

        /**
         * Create a container clone/copy
         * @param bool $experimental The clone feature is experimental, set this flag if you know what you are doing.
         * @param int $newid VMID for the clone.
         * @param string $description Description for the new CT.
         * @param bool $full Create a full copy of all disk. This is always done when you clone a normal CT. For CT templates, we try to create a linked clone by default.
         * @param string $hostname Set a hostname for the new CT.
         * @param string $pool Add the new CT to the specified pool.
         * @param string $snapname The name of the snapshot.
         * @param string $storage Target storage for full clone.
         * @return Result
         */
        public function cloneVm($experimental, $newid, $description = null, $full = null, $hostname = null, $pool = null, $snapname = null, $storage = null)
        {
            return $this->createRest($experimental, $newid, $description, $full, $hostname, $pool, $snapname, $storage);
        }
    }

    class PVEVmidLxcNodeNodesResize extends Base
    {
        private $node;
        private $vmid;

        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Resize a container mount point.
         * @param string $disk The disk you want to resize.
         *   Enum: rootfs,mp0,mp1,mp2,mp3,mp4,mp5,mp6,mp7,mp8,mp9
         * @param string $size The new size. With the '+' sign the value is added to the actual size of the volume and without it, the value is taken as an absolute one. Shrinking disk size is not supported.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function setRest($disk, $size, $digest = null)
        {
            $params = ['disk' => $disk,
                'size' => $size,
                'digest' => $digest];
            return $this->getClient()->set("/nodes/{$this->node}/lxc/{$this->vmid}/resize", $params);
        }

        /**
         * Resize a container mount point.
         * @param string $disk The disk you want to resize.
         *   Enum: rootfs,mp0,mp1,mp2,mp3,mp4,mp5,mp6,mp7,mp8,mp9
         * @param string $size The new size. With the '+' sign the value is added to the actual size of the volume and without it, the value is taken as an absolute one. Shrinking disk size is not supported.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function resizeVm($disk, $size, $digest = null)
        {
            return $this->setRest($disk, $size, $digest);
        }
    }

    class PVENodeNodesCeph extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        private $osd;

        public function getOsd()
        {
            return $this->osd ?: ($this->osd = new PVECephNodeNodesOsd($this->client, $this->node));
        }

        private $disks;

        public function getDisks()
        {
            return $this->disks ?: ($this->disks = new PVECephNodeNodesDisks($this->client, $this->node));
        }

        private $config;

        public function getConfig()
        {
            return $this->config ?: ($this->config = new PVECephNodeNodesConfig($this->client, $this->node));
        }

        private $mon;

        public function getMon()
        {
            return $this->mon ?: ($this->mon = new PVECephNodeNodesMon($this->client, $this->node));
        }

        private $init;

        public function getInit()
        {
            return $this->init ?: ($this->init = new PVECephNodeNodesInit($this->client, $this->node));
        }

        private $mgr;

        public function getMgr()
        {
            return $this->mgr ?: ($this->mgr = new PVECephNodeNodesMgr($this->client, $this->node));
        }

        private $stop;

        public function getStop()
        {
            return $this->stop ?: ($this->stop = new PVECephNodeNodesStop($this->client, $this->node));
        }

        private $start;

        public function getStart()
        {
            return $this->start ?: ($this->start = new PVECephNodeNodesStart($this->client, $this->node));
        }

        private $status;

        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVECephNodeNodesStatus($this->client, $this->node));
        }

        private $pools;

        public function getPools()
        {
            return $this->pools ?: ($this->pools = new PVECephNodeNodesPools($this->client, $this->node));
        }

        private $flags;

        public function getFlags()
        {
            return $this->flags ?: ($this->flags = new PVECephNodeNodesFlags($this->client, $this->node));
        }

        private $crush;

        public function getCrush()
        {
            return $this->crush ?: ($this->crush = new PVECephNodeNodesCrush($this->client, $this->node));
        }

        private $log;

        public function getLog()
        {
            return $this->log ?: ($this->log = new PVECephNodeNodesLog($this->client, $this->node));
        }

        private $rules;

        public function getRules()
        {
            return $this->rules ?: ($this->rules = new PVECephNodeNodesRules($this->client, $this->node));
        }

        /**
         * Directory index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/ceph");
        }

        /**
         * Directory index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVECephNodeNodesOsd extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($osdid)
        {
            return new PVEItemOsdCephNodeNodesOsdid($this->client, $this->node, $osdid);
        }

        /**
         * Get Ceph osd list/tree.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/ceph/osd");
        }

        /**
         * Get Ceph osd list/tree.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Create OSD
         * @param string $dev Block device name.
         * @param bool $bluestore Use bluestore instead of filestore.
         * @param string $fstype File system type (filestore only).
         *   Enum: xfs,ext4,btrfs
         * @param string $journal_dev Block device name for journal (filestore) or block.db (bluestore).
         * @param string $wal_dev Block device name for block.wal (bluestore only).
         * @return Result
         */
        public function createRest($dev, $bluestore = null, $fstype = null, $journal_dev = null, $wal_dev = null)
        {
            $params = ['dev' => $dev,
                'bluestore' => $bluestore,
                'fstype' => $fstype,
                'journal_dev' => $journal_dev,
                'wal_dev' => $wal_dev];
            return $this->getClient()->create("/nodes/{$this->node}/ceph/osd", $params);
        }

        /**
         * Create OSD
         * @param string $dev Block device name.
         * @param bool $bluestore Use bluestore instead of filestore.
         * @param string $fstype File system type (filestore only).
         *   Enum: xfs,ext4,btrfs
         * @param string $journal_dev Block device name for journal (filestore) or block.db (bluestore).
         * @param string $wal_dev Block device name for block.wal (bluestore only).
         * @return Result
         */
        public function createosd($dev, $bluestore = null, $fstype = null, $journal_dev = null, $wal_dev = null)
        {
            return $this->createRest($dev, $bluestore, $fstype, $journal_dev, $wal_dev);
        }
    }

    class PVEItemOsdCephNodeNodesOsdid extends Base
    {
        private $node;
        private $osdid;

        function __construct($client, $node, $osdid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->osdid = $osdid;
        }

        private $in;

        public function getIn()
        {
            return $this->in ?: ($this->in = new PVEOsdidOsdCephNodeNodesIn($this->client, $this->node, $this->osdid));
        }

        private $out;

        public function getOut()
        {
            return $this->out ?: ($this->out = new PVEOsdidOsdCephNodeNodesOut($this->client, $this->node, $this->osdid));
        }

        /**
         * Destroy OSD
         * @param bool $cleanup If set, we remove partition table entries.
         * @return Result
         */
        public function deleteRest($cleanup = null)
        {
            $params = ['cleanup' => $cleanup];
            return $this->getClient()->delete("/nodes/{$this->node}/ceph/osd/{$this->osdid}", $params);
        }

        /**
         * Destroy OSD
         * @param bool $cleanup If set, we remove partition table entries.
         * @return Result
         */
        public function destroyosd($cleanup = null)
        {
            return $this->deleteRest($cleanup);
        }
    }

    class PVEOsdidOsdCephNodeNodesIn extends Base
    {
        private $node;
        private $osdid;

        function __construct($client, $node, $osdid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->osdid = $osdid;
        }

        /**
         * ceph osd in
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/ceph/osd/{$this->osdid}/in");
        }

        /**
         * ceph osd in
         * @return Result
         */
        public function in()
        {
            return $this->createRest();
        }
    }

    class PVEOsdidOsdCephNodeNodesOut extends Base
    {
        private $node;
        private $osdid;

        function __construct($client, $node, $osdid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->osdid = $osdid;
        }

        /**
         * ceph osd out
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/ceph/osd/{$this->osdid}/out");
        }

        /**
         * ceph osd out
         * @return Result
         */
        public function out()
        {
            return $this->createRest();
        }
    }

    class PVECephNodeNodesDisks extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * List local disks.
         * @param string $type Only list specific types of disks.
         *   Enum: unused,journal_disks
         * @return Result
         */
        public function getRest($type = null)
        {
            $params = ['type' => $type];
            return $this->getClient()->get("/nodes/{$this->node}/ceph/disks", $params);
        }

        /**
         * List local disks.
         * @param string $type Only list specific types of disks.
         *   Enum: unused,journal_disks
         * @return Result
         */
        public function disks($type = null)
        {
            return $this->getRest($type);
        }
    }

    class PVECephNodeNodesConfig extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get Ceph configuration.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/ceph/config");
        }

        /**
         * Get Ceph configuration.
         * @return Result
         */
        public function config()
        {
            return $this->getRest();
        }
    }

    class PVECephNodeNodesMon extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($monid)
        {
            return new PVEItemMonCephNodeNodesMonid($this->client, $this->node, $monid);
        }

        /**
         * Get Ceph monitor list.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/ceph/mon");
        }

        /**
         * Get Ceph monitor list.
         * @return Result
         */
        public function listmon()
        {
            return $this->getRest();
        }

        /**
         * Create Ceph Monitor and Manager
         * @param bool $exclude_manager When set, only a monitor will be created.
         * @param string $id The ID for the monitor, when omitted the same as the nodename
         * @return Result
         */
        public function createRest($exclude_manager = null, $id = null)
        {
            $params = ['exclude-manager' => $exclude_manager,
                'id' => $id];
            return $this->getClient()->create("/nodes/{$this->node}/ceph/mon", $params);
        }

        /**
         * Create Ceph Monitor and Manager
         * @param bool $exclude_manager When set, only a monitor will be created.
         * @param string $id The ID for the monitor, when omitted the same as the nodename
         * @return Result
         */
        public function createmon($exclude_manager = null, $id = null)
        {
            return $this->createRest($exclude_manager, $id);
        }
    }

    class PVEItemMonCephNodeNodesMonid extends Base
    {
        private $node;
        private $monid;

        function __construct($client, $node, $monid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->monid = $monid;
        }

        /**
         * Destroy Ceph Monitor and Manager.
         * @param bool $exclude_manager When set, removes only the monitor, not the manager
         * @return Result
         */
        public function deleteRest($exclude_manager = null)
        {
            $params = ['exclude-manager' => $exclude_manager];
            return $this->getClient()->delete("/nodes/{$this->node}/ceph/mon/{$this->monid}", $params);
        }

        /**
         * Destroy Ceph Monitor and Manager.
         * @param bool $exclude_manager When set, removes only the monitor, not the manager
         * @return Result
         */
        public function destroymon($exclude_manager = null)
        {
            return $this->deleteRest($exclude_manager);
        }
    }

    class PVECephNodeNodesInit extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Create initial ceph default configuration and setup symlinks.
         * @param bool $disable_cephx Disable cephx authentification.  WARNING: cephx is a security feature protecting against man-in-the-middle attacks. Only consider disabling cephx if your network is private!
         * @param int $min_size Minimum number of available replicas per object to allow I/O
         * @param string $network Use specific network for all ceph related traffic
         * @param int $pg_bits Placement group bits, used to specify the default number of placement groups.  NOTE: 'osd pool default pg num' does not work for default pools.
         * @param int $size Targeted number of replicas per object
         * @return Result
         */
        public function createRest($disable_cephx = null, $min_size = null, $network = null, $pg_bits = null, $size = null)
        {
            $params = ['disable_cephx' => $disable_cephx,
                'min_size' => $min_size,
                'network' => $network,
                'pg_bits' => $pg_bits,
                'size' => $size];
            return $this->getClient()->create("/nodes/{$this->node}/ceph/init", $params);
        }

        /**
         * Create initial ceph default configuration and setup symlinks.
         * @param bool $disable_cephx Disable cephx authentification.  WARNING: cephx is a security feature protecting against man-in-the-middle attacks. Only consider disabling cephx if your network is private!
         * @param int $min_size Minimum number of available replicas per object to allow I/O
         * @param string $network Use specific network for all ceph related traffic
         * @param int $pg_bits Placement group bits, used to specify the default number of placement groups.  NOTE: 'osd pool default pg num' does not work for default pools.
         * @param int $size Targeted number of replicas per object
         * @return Result
         */
        public function init($disable_cephx = null, $min_size = null, $network = null, $pg_bits = null, $size = null)
        {
            return $this->createRest($disable_cephx, $min_size, $network, $pg_bits, $size);
        }
    }

    class PVECephNodeNodesMgr extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($id)
        {
            return new PVEItemMgrCephNodeNodesId($this->client, $this->node, $id);
        }

        /**
         * Create Ceph Manager
         * @param string $id The ID for the manager, when omitted the same as the nodename
         * @return Result
         */
        public function createRest($id = null)
        {
            $params = ['id' => $id];
            return $this->getClient()->create("/nodes/{$this->node}/ceph/mgr", $params);
        }

        /**
         * Create Ceph Manager
         * @param string $id The ID for the manager, when omitted the same as the nodename
         * @return Result
         */
        public function createmgr($id = null)
        {
            return $this->createRest($id);
        }
    }

    class PVEItemMgrCephNodeNodesId extends Base
    {
        private $node;
        private $id;

        function __construct($client, $node, $id)
        {
            $this->client = $client;
            $this->node = $node;
            $this->id = $id;
        }

        /**
         * Destroy Ceph Manager.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/nodes/{$this->node}/ceph/mgr/{$this->id}");
        }

        /**
         * Destroy Ceph Manager.
         * @return Result
         */
        public function destroymgr()
        {
            return $this->deleteRest();
        }
    }

    class PVECephNodeNodesStop extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Stop ceph services.
         * @param string $service Ceph service name.
         * @return Result
         */
        public function createRest($service = null)
        {
            $params = ['service' => $service];
            return $this->getClient()->create("/nodes/{$this->node}/ceph/stop", $params);
        }

        /**
         * Stop ceph services.
         * @param string $service Ceph service name.
         * @return Result
         */
        public function stop($service = null)
        {
            return $this->createRest($service);
        }
    }

    class PVECephNodeNodesStart extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Start ceph services.
         * @param string $service Ceph service name.
         * @return Result
         */
        public function createRest($service = null)
        {
            $params = ['service' => $service];
            return $this->getClient()->create("/nodes/{$this->node}/ceph/start", $params);
        }

        /**
         * Start ceph services.
         * @param string $service Ceph service name.
         * @return Result
         */
        public function start($service = null)
        {
            return $this->createRest($service);
        }
    }

    class PVECephNodeNodesStatus extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ceph status.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/ceph/status");
        }

        /**
         * Get ceph status.
         * @return Result
         */
        public function status()
        {
            return $this->getRest();
        }
    }

    class PVECephNodeNodesPools extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($name)
        {
            return new PVEItemPoolsCephNodeNodesName($this->client, $this->node, $name);
        }

        /**
         * List all pools.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/ceph/pools");
        }

        /**
         * List all pools.
         * @return Result
         */
        public function lspools()
        {
            return $this->getRest();
        }

        /**
         * Create POOL
         * @param string $name The name of the pool. It must be unique.
         * @param bool $add_storages Configure VM and CT storages using the new pool.
         * @param string $application The application of the pool, 'rbd' by default.
         *   Enum: rbd,cephfs,rgw
         * @param string $crush_rule The rule to use for mapping object placement in the cluster.
         * @param int $min_size Minimum number of replicas per object
         * @param int $pg_num Number of placement groups.
         * @param int $size Number of replicas per object
         * @return Result
         */
        public function createRest($name, $add_storages = null, $application = null, $crush_rule = null, $min_size = null, $pg_num = null, $size = null)
        {
            $params = ['name' => $name,
                'add_storages' => $add_storages,
                'application' => $application,
                'crush_rule' => $crush_rule,
                'min_size' => $min_size,
                'pg_num' => $pg_num,
                'size' => $size];
            return $this->getClient()->create("/nodes/{$this->node}/ceph/pools", $params);
        }

        /**
         * Create POOL
         * @param string $name The name of the pool. It must be unique.
         * @param bool $add_storages Configure VM and CT storages using the new pool.
         * @param string $application The application of the pool, 'rbd' by default.
         *   Enum: rbd,cephfs,rgw
         * @param string $crush_rule The rule to use for mapping object placement in the cluster.
         * @param int $min_size Minimum number of replicas per object
         * @param int $pg_num Number of placement groups.
         * @param int $size Number of replicas per object
         * @return Result
         */
        public function createpool($name, $add_storages = null, $application = null, $crush_rule = null, $min_size = null, $pg_num = null, $size = null)
        {
            return $this->createRest($name, $add_storages, $application, $crush_rule, $min_size, $pg_num, $size);
        }
    }

    class PVEItemPoolsCephNodeNodesName extends Base
    {
        private $node;
        private $name;

        function __construct($client, $node, $name)
        {
            $this->client = $client;
            $this->node = $node;
            $this->name = $name;
        }

        /**
         * Destroy pool
         * @param bool $force If true, destroys pool even if in use
         * @param bool $remove_storages Remove all pveceph-managed storages configured for this pool
         * @return Result
         */
        public function deleteRest($force = null, $remove_storages = null)
        {
            $params = ['force' => $force,
                'remove_storages' => $remove_storages];
            return $this->getClient()->delete("/nodes/{$this->node}/ceph/pools/{$this->name}", $params);
        }

        /**
         * Destroy pool
         * @param bool $force If true, destroys pool even if in use
         * @param bool $remove_storages Remove all pveceph-managed storages configured for this pool
         * @return Result
         */
        public function destroypool($force = null, $remove_storages = null)
        {
            return $this->deleteRest($force, $remove_storages);
        }
    }

    class PVECephNodeNodesFlags extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($flag)
        {
            return new PVEItemFlagsCephNodeNodesFlag($this->client, $this->node, $flag);
        }

        /**
         * get all set ceph flags
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/ceph/flags");
        }

        /**
         * get all set ceph flags
         * @return Result
         */
        public function getFlags()
        {
            return $this->getRest();
        }
    }

    class PVEItemFlagsCephNodeNodesFlag extends Base
    {
        private $node;
        private $flag;

        function __construct($client, $node, $flag)
        {
            $this->client = $client;
            $this->node = $node;
            $this->flag = $flag;
        }

        /**
         * Unset a ceph flag
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/nodes/{$this->node}/ceph/flags/{$this->flag}");
        }

        /**
         * Unset a ceph flag
         * @return Result
         */
        public function unsetFlag()
        {
            return $this->deleteRest();
        }

        /**
         * Set a ceph flag
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/ceph/flags/{$this->flag}");
        }

        /**
         * Set a ceph flag
         * @return Result
         */
        public function setFlag()
        {
            return $this->createRest();
        }
    }

    class PVECephNodeNodesCrush extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get OSD crush map
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/ceph/crush");
        }

        /**
         * Get OSD crush map
         * @return Result
         */
        public function crush()
        {
            return $this->getRest();
        }
    }

    class PVECephNodeNodesLog extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Read ceph log
         * @param int $limit
         * @param int $start
         * @return Result
         */
        public function getRest($limit = null, $start = null)
        {
            $params = ['limit' => $limit,
                'start' => $start];
            return $this->getClient()->get("/nodes/{$this->node}/ceph/log", $params);
        }

        /**
         * Read ceph log
         * @param int $limit
         * @param int $start
         * @return Result
         */
        public function log($limit = null, $start = null)
        {
            return $this->getRest($limit, $start);
        }
    }

    class PVECephNodeNodesRules extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * List ceph rules.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/ceph/rules");
        }

        /**
         * List ceph rules.
         * @return Result
         */
        public function rules()
        {
            return $this->getRest();
        }
    }

    class PVENodeNodesVzdump extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        private $extractconfig;

        public function getExtractconfig()
        {
            return $this->extractconfig ?: ($this->extractconfig = new PVEVzdumpNodeNodesExtractconfig($this->client, $this->node));
        }

        /**
         * Create backup.
         * @param bool $all Backup all known guest systems on this host.
         * @param int $bwlimit Limit I/O bandwidth (KBytes per second).
         * @param string $compress Compress dump file.
         *   Enum: 0,1,gzip,lzo
         * @param string $dumpdir Store resulting files to specified directory.
         * @param string $exclude Exclude specified guest systems (assumes --all)
         * @param string $exclude_path Exclude certain files/directories (shell globs).
         * @param int $ionice Set CFQ ionice priority.
         * @param int $lockwait Maximal time to wait for the global lock (minutes).
         * @param string $mailnotification Specify when to send an email
         *   Enum: always,failure
         * @param string $mailto Comma-separated list of email addresses that should receive email notifications.
         * @param int $maxfiles Maximal number of backup files per guest system.
         * @param string $mode Backup mode.
         *   Enum: snapshot,suspend,stop
         * @param int $pigz Use pigz instead of gzip when N&amp;gt;0. N=1 uses half of cores, N&amp;gt;1 uses N as thread count.
         * @param bool $quiet Be quiet.
         * @param bool $remove Remove old backup files if there are more than 'maxfiles' backup files.
         * @param string $script Use specified hook script.
         * @param int $size Unused, will be removed in a future release.
         * @param bool $stdexcludes Exclude temporary files and logs.
         * @param bool $stdout Write tar to stdout, not to a file.
         * @param bool $stop Stop runnig backup jobs on this host.
         * @param int $stopwait Maximal time to wait until a guest system is stopped (minutes).
         * @param string $storage Store resulting file to this storage.
         * @param string $tmpdir Store temporary files to specified directory.
         * @param string $vmid The ID of the guest system you want to backup.
         * @return Result
         */
        public function createRest($all = null, $bwlimit = null, $compress = null, $dumpdir = null, $exclude = null, $exclude_path = null, $ionice = null, $lockwait = null, $mailnotification = null, $mailto = null, $maxfiles = null, $mode = null, $pigz = null, $quiet = null, $remove = null, $script = null, $size = null, $stdexcludes = null, $stdout = null, $stop = null, $stopwait = null, $storage = null, $tmpdir = null, $vmid = null)
        {
            $params = ['all' => $all,
                'bwlimit' => $bwlimit,
                'compress' => $compress,
                'dumpdir' => $dumpdir,
                'exclude' => $exclude,
                'exclude-path' => $exclude_path,
                'ionice' => $ionice,
                'lockwait' => $lockwait,
                'mailnotification' => $mailnotification,
                'mailto' => $mailto,
                'maxfiles' => $maxfiles,
                'mode' => $mode,
                'pigz' => $pigz,
                'quiet' => $quiet,
                'remove' => $remove,
                'script' => $script,
                'size' => $size,
                'stdexcludes' => $stdexcludes,
                'stdout' => $stdout,
                'stop' => $stop,
                'stopwait' => $stopwait,
                'storage' => $storage,
                'tmpdir' => $tmpdir,
                'vmid' => $vmid];
            return $this->getClient()->create("/nodes/{$this->node}/vzdump", $params);
        }

        /**
         * Create backup.
         * @param bool $all Backup all known guest systems on this host.
         * @param int $bwlimit Limit I/O bandwidth (KBytes per second).
         * @param string $compress Compress dump file.
         *   Enum: 0,1,gzip,lzo
         * @param string $dumpdir Store resulting files to specified directory.
         * @param string $exclude Exclude specified guest systems (assumes --all)
         * @param string $exclude_path Exclude certain files/directories (shell globs).
         * @param int $ionice Set CFQ ionice priority.
         * @param int $lockwait Maximal time to wait for the global lock (minutes).
         * @param string $mailnotification Specify when to send an email
         *   Enum: always,failure
         * @param string $mailto Comma-separated list of email addresses that should receive email notifications.
         * @param int $maxfiles Maximal number of backup files per guest system.
         * @param string $mode Backup mode.
         *   Enum: snapshot,suspend,stop
         * @param int $pigz Use pigz instead of gzip when N&amp;gt;0. N=1 uses half of cores, N&amp;gt;1 uses N as thread count.
         * @param bool $quiet Be quiet.
         * @param bool $remove Remove old backup files if there are more than 'maxfiles' backup files.
         * @param string $script Use specified hook script.
         * @param int $size Unused, will be removed in a future release.
         * @param bool $stdexcludes Exclude temporary files and logs.
         * @param bool $stdout Write tar to stdout, not to a file.
         * @param bool $stop Stop runnig backup jobs on this host.
         * @param int $stopwait Maximal time to wait until a guest system is stopped (minutes).
         * @param string $storage Store resulting file to this storage.
         * @param string $tmpdir Store temporary files to specified directory.
         * @param string $vmid The ID of the guest system you want to backup.
         * @return Result
         */
        public function vzdump($all = null, $bwlimit = null, $compress = null, $dumpdir = null, $exclude = null, $exclude_path = null, $ionice = null, $lockwait = null, $mailnotification = null, $mailto = null, $maxfiles = null, $mode = null, $pigz = null, $quiet = null, $remove = null, $script = null, $size = null, $stdexcludes = null, $stdout = null, $stop = null, $stopwait = null, $storage = null, $tmpdir = null, $vmid = null)
        {
            return $this->createRest($all, $bwlimit, $compress, $dumpdir, $exclude, $exclude_path, $ionice, $lockwait, $mailnotification, $mailto, $maxfiles, $mode, $pigz, $quiet, $remove, $script, $size, $stdexcludes, $stdout, $stop, $stopwait, $storage, $tmpdir, $vmid);
        }
    }

    class PVEVzdumpNodeNodesExtractconfig extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Extract configuration from vzdump backup archive.
         * @param string $volume Volume identifier
         * @return Result
         */
        public function getRest($volume)
        {
            $params = ['volume' => $volume];
            return $this->getClient()->get("/nodes/{$this->node}/vzdump/extractconfig", $params);
        }

        /**
         * Extract configuration from vzdump backup archive.
         * @param string $volume Volume identifier
         * @return Result
         */
        public function extractconfig($volume)
        {
            return $this->getRest($volume);
        }
    }

    class PVENodeNodesServices extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($service)
        {
            return new PVEItemServicesNodeNodesService($this->client, $this->node, $service);
        }

        /**
         * Service list.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/services");
        }

        /**
         * Service list.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEItemServicesNodeNodesService extends Base
    {
        private $node;
        private $service;

        function __construct($client, $node, $service)
        {
            $this->client = $client;
            $this->node = $node;
            $this->service = $service;
        }

        private $state;

        public function getState()
        {
            return $this->state ?: ($this->state = new PVEServiceServicesNodeNodesState($this->client, $this->node, $this->service));
        }

        private $start;

        public function getStart()
        {
            return $this->start ?: ($this->start = new PVEServiceServicesNodeNodesStart($this->client, $this->node, $this->service));
        }

        private $stop;

        public function getStop()
        {
            return $this->stop ?: ($this->stop = new PVEServiceServicesNodeNodesStop($this->client, $this->node, $this->service));
        }

        private $restart;

        public function getRestart()
        {
            return $this->restart ?: ($this->restart = new PVEServiceServicesNodeNodesRestart($this->client, $this->node, $this->service));
        }

        private $reload;

        public function getReload()
        {
            return $this->reload ?: ($this->reload = new PVEServiceServicesNodeNodesReload($this->client, $this->node, $this->service));
        }

        /**
         * Directory index
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/services/{$this->service}");
        }

        /**
         * Directory index
         * @return Result
         */
        public function srvcmdidx()
        {
            return $this->getRest();
        }
    }

    class PVEServiceServicesNodeNodesState extends Base
    {
        private $node;
        private $service;

        function __construct($client, $node, $service)
        {
            $this->client = $client;
            $this->node = $node;
            $this->service = $service;
        }

        /**
         * Read service properties
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/services/{$this->service}/state");
        }

        /**
         * Read service properties
         * @return Result
         */
        public function serviceState()
        {
            return $this->getRest();
        }
    }

    class PVEServiceServicesNodeNodesStart extends Base
    {
        private $node;
        private $service;

        function __construct($client, $node, $service)
        {
            $this->client = $client;
            $this->node = $node;
            $this->service = $service;
        }

        /**
         * Start service.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/services/{$this->service}/start");
        }

        /**
         * Start service.
         * @return Result
         */
        public function serviceStart()
        {
            return $this->createRest();
        }
    }

    class PVEServiceServicesNodeNodesStop extends Base
    {
        private $node;
        private $service;

        function __construct($client, $node, $service)
        {
            $this->client = $client;
            $this->node = $node;
            $this->service = $service;
        }

        /**
         * Stop service.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/services/{$this->service}/stop");
        }

        /**
         * Stop service.
         * @return Result
         */
        public function serviceStop()
        {
            return $this->createRest();
        }
    }

    class PVEServiceServicesNodeNodesRestart extends Base
    {
        private $node;
        private $service;

        function __construct($client, $node, $service)
        {
            $this->client = $client;
            $this->node = $node;
            $this->service = $service;
        }

        /**
         * Restart service.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/services/{$this->service}/restart");
        }

        /**
         * Restart service.
         * @return Result
         */
        public function serviceRestart()
        {
            return $this->createRest();
        }
    }

    class PVEServiceServicesNodeNodesReload extends Base
    {
        private $node;
        private $service;

        function __construct($client, $node, $service)
        {
            $this->client = $client;
            $this->node = $node;
            $this->service = $service;
        }

        /**
         * Reload service.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/services/{$this->service}/reload");
        }

        /**
         * Reload service.
         * @return Result
         */
        public function serviceReload()
        {
            return $this->createRest();
        }
    }

    class PVENodeNodesSubscription extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Read subscription info.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/subscription");
        }

        /**
         * Read subscription info.
         * @return Result
         */
        public function get()
        {
            return $this->getRest();
        }

        /**
         * Update subscription info.
         * @param bool $force Always connect to server, even if we have up to date info inside local cache.
         * @return Result
         */
        public function createRest($force = null)
        {
            $params = ['force' => $force];
            return $this->getClient()->create("/nodes/{$this->node}/subscription", $params);
        }

        /**
         * Update subscription info.
         * @param bool $force Always connect to server, even if we have up to date info inside local cache.
         * @return Result
         */
        public function update($force = null)
        {
            return $this->createRest($force);
        }

        /**
         * Set subscription key.
         * @param string $key Proxmox VE subscription key
         * @return Result
         */
        public function setRest($key)
        {
            $params = ['key' => $key];
            return $this->getClient()->set("/nodes/{$this->node}/subscription", $params);
        }

        /**
         * Set subscription key.
         * @param string $key Proxmox VE subscription key
         * @return Result
         */
        public function set($key)
        {
            return $this->setRest($key);
        }
    }

    class PVENodeNodesNetwork extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($iface)
        {
            return new PVEItemNetworkNodeNodesIface($this->client, $this->node, $iface);
        }

        /**
         * Revert network configuration changes.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/nodes/{$this->node}/network");
        }

        /**
         * Revert network configuration changes.
         * @return Result
         */
        public function revertNetworkChanges()
        {
            return $this->deleteRest();
        }

        /**
         * List available networks
         * @param string $type Only list specific interface types.
         *   Enum: bridge,bond,eth,alias,vlan,OVSBridge,OVSBond,OVSPort,OVSIntPort,any_bridge
         * @return Result
         */
        public function getRest($type = null)
        {
            $params = ['type' => $type];
            return $this->getClient()->get("/nodes/{$this->node}/network", $params);
        }

        /**
         * List available networks
         * @param string $type Only list specific interface types.
         *   Enum: bridge,bond,eth,alias,vlan,OVSBridge,OVSBond,OVSPort,OVSIntPort,any_bridge
         * @return Result
         */
        public function index($type = null)
        {
            return $this->getRest($type);
        }

        /**
         * Create network device configuration
         * @param string $iface Network interface name.
         * @param string $type Network interface type
         *   Enum: bridge,bond,eth,alias,vlan,OVSBridge,OVSBond,OVSPort,OVSIntPort,unknown
         * @param string $address IP address.
         * @param string $address6 IP address.
         * @param bool $autostart Automatically start interface on boot.
         * @param string $bond_mode Bonding mode.
         *   Enum: balance-rr,active-backup,balance-xor,broadcast,802.3ad,balance-tlb,balance-alb,balance-slb,lacp-balance-slb,lacp-balance-tcp
         * @param string $bond_xmit_hash_policy Selects the transmit hash policy to use for slave selection in balance-xor and 802.3ad modes.
         *   Enum: layer2,layer2+3,layer3+4
         * @param string $bridge_ports Specify the iterfaces you want to add to your bridge.
         * @param bool $bridge_vlan_aware Enable bridge vlan support.
         * @param string $comments Comments
         * @param string $comments6 Comments
         * @param string $gateway Default gateway address.
         * @param string $gateway6 Default ipv6 gateway address.
         * @param string $netmask Network mask.
         * @param int $netmask6 Network mask.
         * @param string $ovs_bonds Specify the interfaces used by the bonding device.
         * @param string $ovs_bridge The OVS bridge associated with a OVS port. This is required when you create an OVS port.
         * @param string $ovs_options OVS interface options.
         * @param string $ovs_ports Specify the iterfaces you want to add to your bridge.
         * @param int $ovs_tag Specify a VLan tag (used by OVSPort, OVSIntPort, OVSBond)
         * @param string $slaves Specify the interfaces used by the bonding device.
         * @return Result
         */
        public function createRest($iface, $type, $address = null, $address6 = null, $autostart = null, $bond_mode = null, $bond_xmit_hash_policy = null, $bridge_ports = null, $bridge_vlan_aware = null, $comments = null, $comments6 = null, $gateway = null, $gateway6 = null, $netmask = null, $netmask6 = null, $ovs_bonds = null, $ovs_bridge = null, $ovs_options = null, $ovs_ports = null, $ovs_tag = null, $slaves = null)
        {
            $params = ['iface' => $iface,
                'type' => $type,
                'address' => $address,
                'address6' => $address6,
                'autostart' => $autostart,
                'bond_mode' => $bond_mode,
                'bond_xmit_hash_policy' => $bond_xmit_hash_policy,
                'bridge_ports' => $bridge_ports,
                'bridge_vlan_aware' => $bridge_vlan_aware,
                'comments' => $comments,
                'comments6' => $comments6,
                'gateway' => $gateway,
                'gateway6' => $gateway6,
                'netmask' => $netmask,
                'netmask6' => $netmask6,
                'ovs_bonds' => $ovs_bonds,
                'ovs_bridge' => $ovs_bridge,
                'ovs_options' => $ovs_options,
                'ovs_ports' => $ovs_ports,
                'ovs_tag' => $ovs_tag,
                'slaves' => $slaves];
            return $this->getClient()->create("/nodes/{$this->node}/network", $params);
        }

        /**
         * Create network device configuration
         * @param string $iface Network interface name.
         * @param string $type Network interface type
         *   Enum: bridge,bond,eth,alias,vlan,OVSBridge,OVSBond,OVSPort,OVSIntPort,unknown
         * @param string $address IP address.
         * @param string $address6 IP address.
         * @param bool $autostart Automatically start interface on boot.
         * @param string $bond_mode Bonding mode.
         *   Enum: balance-rr,active-backup,balance-xor,broadcast,802.3ad,balance-tlb,balance-alb,balance-slb,lacp-balance-slb,lacp-balance-tcp
         * @param string $bond_xmit_hash_policy Selects the transmit hash policy to use for slave selection in balance-xor and 802.3ad modes.
         *   Enum: layer2,layer2+3,layer3+4
         * @param string $bridge_ports Specify the iterfaces you want to add to your bridge.
         * @param bool $bridge_vlan_aware Enable bridge vlan support.
         * @param string $comments Comments
         * @param string $comments6 Comments
         * @param string $gateway Default gateway address.
         * @param string $gateway6 Default ipv6 gateway address.
         * @param string $netmask Network mask.
         * @param int $netmask6 Network mask.
         * @param string $ovs_bonds Specify the interfaces used by the bonding device.
         * @param string $ovs_bridge The OVS bridge associated with a OVS port. This is required when you create an OVS port.
         * @param string $ovs_options OVS interface options.
         * @param string $ovs_ports Specify the iterfaces you want to add to your bridge.
         * @param int $ovs_tag Specify a VLan tag (used by OVSPort, OVSIntPort, OVSBond)
         * @param string $slaves Specify the interfaces used by the bonding device.
         * @return Result
         */
        public function createNetwork($iface, $type, $address = null, $address6 = null, $autostart = null, $bond_mode = null, $bond_xmit_hash_policy = null, $bridge_ports = null, $bridge_vlan_aware = null, $comments = null, $comments6 = null, $gateway = null, $gateway6 = null, $netmask = null, $netmask6 = null, $ovs_bonds = null, $ovs_bridge = null, $ovs_options = null, $ovs_ports = null, $ovs_tag = null, $slaves = null)
        {
            return $this->createRest($iface, $type, $address, $address6, $autostart, $bond_mode, $bond_xmit_hash_policy, $bridge_ports, $bridge_vlan_aware, $comments, $comments6, $gateway, $gateway6, $netmask, $netmask6, $ovs_bonds, $ovs_bridge, $ovs_options, $ovs_ports, $ovs_tag, $slaves);
        }
    }

    class PVEItemNetworkNodeNodesIface extends Base
    {
        private $node;
        private $iface;

        function __construct($client, $node, $iface)
        {
            $this->client = $client;
            $this->node = $node;
            $this->iface = $iface;
        }

        /**
         * Delete network device configuration
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/nodes/{$this->node}/network/{$this->iface}");
        }

        /**
         * Delete network device configuration
         * @return Result
         */
        public function deleteNetwork()
        {
            return $this->deleteRest();
        }

        /**
         * Read network device configuration
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/network/{$this->iface}");
        }

        /**
         * Read network device configuration
         * @return Result
         */
        public function networkConfig()
        {
            return $this->getRest();
        }

        /**
         * Update network device configuration
         * @param string $type Network interface type
         *   Enum: bridge,bond,eth,alias,vlan,OVSBridge,OVSBond,OVSPort,OVSIntPort,unknown
         * @param string $address IP address.
         * @param string $address6 IP address.
         * @param bool $autostart Automatically start interface on boot.
         * @param string $bond_mode Bonding mode.
         *   Enum: balance-rr,active-backup,balance-xor,broadcast,802.3ad,balance-tlb,balance-alb,balance-slb,lacp-balance-slb,lacp-balance-tcp
         * @param string $bond_xmit_hash_policy Selects the transmit hash policy to use for slave selection in balance-xor and 802.3ad modes.
         *   Enum: layer2,layer2+3,layer3+4
         * @param string $bridge_ports Specify the iterfaces you want to add to your bridge.
         * @param bool $bridge_vlan_aware Enable bridge vlan support.
         * @param string $comments Comments
         * @param string $comments6 Comments
         * @param string $delete A list of settings you want to delete.
         * @param string $gateway Default gateway address.
         * @param string $gateway6 Default ipv6 gateway address.
         * @param string $netmask Network mask.
         * @param int $netmask6 Network mask.
         * @param string $ovs_bonds Specify the interfaces used by the bonding device.
         * @param string $ovs_bridge The OVS bridge associated with a OVS port. This is required when you create an OVS port.
         * @param string $ovs_options OVS interface options.
         * @param string $ovs_ports Specify the iterfaces you want to add to your bridge.
         * @param int $ovs_tag Specify a VLan tag (used by OVSPort, OVSIntPort, OVSBond)
         * @param string $slaves Specify the interfaces used by the bonding device.
         * @return Result
         */
        public function setRest($type, $address = null, $address6 = null, $autostart = null, $bond_mode = null, $bond_xmit_hash_policy = null, $bridge_ports = null, $bridge_vlan_aware = null, $comments = null, $comments6 = null, $delete = null, $gateway = null, $gateway6 = null, $netmask = null, $netmask6 = null, $ovs_bonds = null, $ovs_bridge = null, $ovs_options = null, $ovs_ports = null, $ovs_tag = null, $slaves = null)
        {
            $params = ['type' => $type,
                'address' => $address,
                'address6' => $address6,
                'autostart' => $autostart,
                'bond_mode' => $bond_mode,
                'bond_xmit_hash_policy' => $bond_xmit_hash_policy,
                'bridge_ports' => $bridge_ports,
                'bridge_vlan_aware' => $bridge_vlan_aware,
                'comments' => $comments,
                'comments6' => $comments6,
                'delete' => $delete,
                'gateway' => $gateway,
                'gateway6' => $gateway6,
                'netmask' => $netmask,
                'netmask6' => $netmask6,
                'ovs_bonds' => $ovs_bonds,
                'ovs_bridge' => $ovs_bridge,
                'ovs_options' => $ovs_options,
                'ovs_ports' => $ovs_ports,
                'ovs_tag' => $ovs_tag,
                'slaves' => $slaves];
            return $this->getClient()->set("/nodes/{$this->node}/network/{$this->iface}", $params);
        }

        /**
         * Update network device configuration
         * @param string $type Network interface type
         *   Enum: bridge,bond,eth,alias,vlan,OVSBridge,OVSBond,OVSPort,OVSIntPort,unknown
         * @param string $address IP address.
         * @param string $address6 IP address.
         * @param bool $autostart Automatically start interface on boot.
         * @param string $bond_mode Bonding mode.
         *   Enum: balance-rr,active-backup,balance-xor,broadcast,802.3ad,balance-tlb,balance-alb,balance-slb,lacp-balance-slb,lacp-balance-tcp
         * @param string $bond_xmit_hash_policy Selects the transmit hash policy to use for slave selection in balance-xor and 802.3ad modes.
         *   Enum: layer2,layer2+3,layer3+4
         * @param string $bridge_ports Specify the iterfaces you want to add to your bridge.
         * @param bool $bridge_vlan_aware Enable bridge vlan support.
         * @param string $comments Comments
         * @param string $comments6 Comments
         * @param string $delete A list of settings you want to delete.
         * @param string $gateway Default gateway address.
         * @param string $gateway6 Default ipv6 gateway address.
         * @param string $netmask Network mask.
         * @param int $netmask6 Network mask.
         * @param string $ovs_bonds Specify the interfaces used by the bonding device.
         * @param string $ovs_bridge The OVS bridge associated with a OVS port. This is required when you create an OVS port.
         * @param string $ovs_options OVS interface options.
         * @param string $ovs_ports Specify the iterfaces you want to add to your bridge.
         * @param int $ovs_tag Specify a VLan tag (used by OVSPort, OVSIntPort, OVSBond)
         * @param string $slaves Specify the interfaces used by the bonding device.
         * @return Result
         */
        public function updateNetwork($type, $address = null, $address6 = null, $autostart = null, $bond_mode = null, $bond_xmit_hash_policy = null, $bridge_ports = null, $bridge_vlan_aware = null, $comments = null, $comments6 = null, $delete = null, $gateway = null, $gateway6 = null, $netmask = null, $netmask6 = null, $ovs_bonds = null, $ovs_bridge = null, $ovs_options = null, $ovs_ports = null, $ovs_tag = null, $slaves = null)
        {
            return $this->setRest($type, $address, $address6, $autostart, $bond_mode, $bond_xmit_hash_policy, $bridge_ports, $bridge_vlan_aware, $comments, $comments6, $delete, $gateway, $gateway6, $netmask, $netmask6, $ovs_bonds, $ovs_bridge, $ovs_options, $ovs_ports, $ovs_tag, $slaves);
        }
    }

    class PVENodeNodesTasks extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($upid)
        {
            return new PVEItemTasksNodeNodesUpid($this->client, $this->node, $upid);
        }

        /**
         * Read task list for one node (finished tasks).
         * @param bool $errors
         * @param int $limit
         * @param int $start
         * @param string $userfilter
         * @param int $vmid Only list tasks for this VM.
         * @return Result
         */
        public function getRest($errors = null, $limit = null, $start = null, $userfilter = null, $vmid = null)
        {
            $params = ['errors' => $errors,
                'limit' => $limit,
                'start' => $start,
                'userfilter' => $userfilter,
                'vmid' => $vmid];
            return $this->getClient()->get("/nodes/{$this->node}/tasks", $params);
        }

        /**
         * Read task list for one node (finished tasks).
         * @param bool $errors
         * @param int $limit
         * @param int $start
         * @param string $userfilter
         * @param int $vmid Only list tasks for this VM.
         * @return Result
         */
        public function nodeTasks($errors = null, $limit = null, $start = null, $userfilter = null, $vmid = null)
        {
            return $this->getRest($errors, $limit, $start, $userfilter, $vmid);
        }
    }

    class PVEItemTasksNodeNodesUpid extends Base
    {
        private $node;
        private $upid;

        function __construct($client, $node, $upid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->upid = $upid;
        }

        private $log;

        public function getLog()
        {
            return $this->log ?: ($this->log = new PVEUpidTasksNodeNodesLog($this->client, $this->node, $this->upid));
        }

        private $status;

        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVEUpidTasksNodeNodesStatus($this->client, $this->node, $this->upid));
        }

        /**
         * Stop a task.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/nodes/{$this->node}/tasks/{$this->upid}");
        }

        /**
         * Stop a task.
         * @return Result
         */
        public function stopTask()
        {
            return $this->deleteRest();
        }

        /**
         *
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/tasks/{$this->upid}");
        }

        /**
         *
         * @return Result
         */
        public function upidIndex()
        {
            return $this->getRest();
        }
    }

    class PVEUpidTasksNodeNodesLog extends Base
    {
        private $node;
        private $upid;

        function __construct($client, $node, $upid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->upid = $upid;
        }

        /**
         * Read task log.
         * @param int $limit
         * @param int $start
         * @return Result
         */
        public function getRest($limit = null, $start = null)
        {
            $params = ['limit' => $limit,
                'start' => $start];
            return $this->getClient()->get("/nodes/{$this->node}/tasks/{$this->upid}/log", $params);
        }

        /**
         * Read task log.
         * @param int $limit
         * @param int $start
         * @return Result
         */
        public function readTaskLog($limit = null, $start = null)
        {
            return $this->getRest($limit, $start);
        }
    }

    class PVEUpidTasksNodeNodesStatus extends Base
    {
        private $node;
        private $upid;

        function __construct($client, $node, $upid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->upid = $upid;
        }

        /**
         * Read task status.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/tasks/{$this->upid}/status");
        }

        /**
         * Read task status.
         * @return Result
         */
        public function readTaskStatus()
        {
            return $this->getRest();
        }
    }

    class PVENodeNodesScan extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        private $zfs;

        public function getZfs()
        {
            return $this->zfs ?: ($this->zfs = new PVEScanNodeNodesZfs($this->client, $this->node));
        }

        private $nfs;

        public function getNfs()
        {
            return $this->nfs ?: ($this->nfs = new PVEScanNodeNodesNfs($this->client, $this->node));
        }

        private $glusterfs;

        public function getGlusterfs()
        {
            return $this->glusterfs ?: ($this->glusterfs = new PVEScanNodeNodesGlusterfs($this->client, $this->node));
        }

        private $iscsi;

        public function getIscsi()
        {
            return $this->iscsi ?: ($this->iscsi = new PVEScanNodeNodesIscsi($this->client, $this->node));
        }

        private $lvm;

        public function getLvm()
        {
            return $this->lvm ?: ($this->lvm = new PVEScanNodeNodesLvm($this->client, $this->node));
        }

        private $lvmthin;

        public function getLvmthin()
        {
            return $this->lvmthin ?: ($this->lvmthin = new PVEScanNodeNodesLvmthin($this->client, $this->node));
        }

        private $usb;

        public function getUsb()
        {
            return $this->usb ?: ($this->usb = new PVEScanNodeNodesUsb($this->client, $this->node));
        }

        /**
         * Index of available scan methods
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/scan");
        }

        /**
         * Index of available scan methods
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEScanNodeNodesZfs extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Scan zfs pool list on local node.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/scan/zfs");
        }

        /**
         * Scan zfs pool list on local node.
         * @return Result
         */
        public function zfsscan()
        {
            return $this->getRest();
        }
    }

    class PVEScanNodeNodesNfs extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Scan remote NFS server.
         * @param string $server
         * @return Result
         */
        public function getRest($server)
        {
            $params = ['server' => $server];
            return $this->getClient()->get("/nodes/{$this->node}/scan/nfs", $params);
        }

        /**
         * Scan remote NFS server.
         * @param string $server
         * @return Result
         */
        public function nfsscan($server)
        {
            return $this->getRest($server);
        }
    }

    class PVEScanNodeNodesGlusterfs extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Scan remote GlusterFS server.
         * @param string $server
         * @return Result
         */
        public function getRest($server)
        {
            $params = ['server' => $server];
            return $this->getClient()->get("/nodes/{$this->node}/scan/glusterfs", $params);
        }

        /**
         * Scan remote GlusterFS server.
         * @param string $server
         * @return Result
         */
        public function glusterfsscan($server)
        {
            return $this->getRest($server);
        }
    }

    class PVEScanNodeNodesIscsi extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Scan remote iSCSI server.
         * @param string $portal
         * @return Result
         */
        public function getRest($portal)
        {
            $params = ['portal' => $portal];
            return $this->getClient()->get("/nodes/{$this->node}/scan/iscsi", $params);
        }

        /**
         * Scan remote iSCSI server.
         * @param string $portal
         * @return Result
         */
        public function iscsiscan($portal)
        {
            return $this->getRest($portal);
        }
    }

    class PVEScanNodeNodesLvm extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * List local LVM volume groups.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/scan/lvm");
        }

        /**
         * List local LVM volume groups.
         * @return Result
         */
        public function lvmscan()
        {
            return $this->getRest();
        }
    }

    class PVEScanNodeNodesLvmthin extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * List local LVM Thin Pools.
         * @param string $vg
         * @return Result
         */
        public function getRest($vg)
        {
            $params = ['vg' => $vg];
            return $this->getClient()->get("/nodes/{$this->node}/scan/lvmthin", $params);
        }

        /**
         * List local LVM Thin Pools.
         * @param string $vg
         * @return Result
         */
        public function lvmthinscan($vg)
        {
            return $this->getRest($vg);
        }
    }

    class PVEScanNodeNodesUsb extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * List local USB devices.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/scan/usb");
        }

        /**
         * List local USB devices.
         * @return Result
         */
        public function usbscan()
        {
            return $this->getRest();
        }
    }

    class PVENodeNodesStorage extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($storage)
        {
            return new PVEItemStorageNodeNodesStorage($this->client, $this->node, $storage);
        }

        /**
         * Get status for all datastores.
         * @param string $content Only list stores which support this content type.
         * @param bool $enabled Only list stores which are enabled (not disabled in config).
         * @param string $storage Only list status for  specified storage
         * @param string $target If target is different to 'node', we only lists shared storages which content is accessible on this 'node' and the specified 'target' node.
         * @return Result
         */
        public function getRest($content = null, $enabled = null, $storage = null, $target = null)
        {
            $params = ['content' => $content,
                'enabled' => $enabled,
                'storage' => $storage,
                'target' => $target];
            return $this->getClient()->get("/nodes/{$this->node}/storage", $params);
        }

        /**
         * Get status for all datastores.
         * @param string $content Only list stores which support this content type.
         * @param bool $enabled Only list stores which are enabled (not disabled in config).
         * @param string $storage Only list status for  specified storage
         * @param string $target If target is different to 'node', we only lists shared storages which content is accessible on this 'node' and the specified 'target' node.
         * @return Result
         */
        public function index($content = null, $enabled = null, $storage = null, $target = null)
        {
            return $this->getRest($content, $enabled, $storage, $target);
        }
    }

    class PVEItemStorageNodeNodesStorage extends Base
    {
        private $node;
        private $storage;

        function __construct($client, $node, $storage)
        {
            $this->client = $client;
            $this->node = $node;
            $this->storage = $storage;
        }

        private $content;

        public function getContent()
        {
            return $this->content ?: ($this->content = new PVEStorageStorageNodeNodesContent($this->client, $this->node, $this->storage));
        }

        private $status;

        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVEStorageStorageNodeNodesStatus($this->client, $this->node, $this->storage));
        }

        private $rrd;

        public function getRrd()
        {
            return $this->rrd ?: ($this->rrd = new PVEStorageStorageNodeNodesRrd($this->client, $this->node, $this->storage));
        }

        private $rrddata;

        public function getRrddata()
        {
            return $this->rrddata ?: ($this->rrddata = new PVEStorageStorageNodeNodesRrddata($this->client, $this->node, $this->storage));
        }

        private $upload;

        public function getUpload()
        {
            return $this->upload ?: ($this->upload = new PVEStorageStorageNodeNodesUpload($this->client, $this->node, $this->storage));
        }

        /**
         *
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/storage/{$this->storage}");
        }

        /**
         *
         * @return Result
         */
        public function diridx()
        {
            return $this->getRest();
        }
    }

    class PVEStorageStorageNodeNodesContent extends Base
    {
        private $node;
        private $storage;

        function __construct($client, $node, $storage)
        {
            $this->client = $client;
            $this->node = $node;
            $this->storage = $storage;
        }

        public function get($volume)
        {
            return new PVEItemContentStorageStorageNodeNodesVolume($this->client, $this->node, $this->storage, $volume);
        }

        /**
         * List storage content.
         * @param string $content Only list content of this type.
         * @param int $vmid Only list images for this VM
         * @return Result
         */
        public function getRest($content = null, $vmid = null)
        {
            $params = ['content' => $content,
                'vmid' => $vmid];
            return $this->getClient()->get("/nodes/{$this->node}/storage/{$this->storage}/content", $params);
        }

        /**
         * List storage content.
         * @param string $content Only list content of this type.
         * @param int $vmid Only list images for this VM
         * @return Result
         */
        public function index($content = null, $vmid = null)
        {
            return $this->getRest($content, $vmid);
        }

        /**
         * Allocate disk images.
         * @param string $filename The name of the file to create.
         * @param string $size Size in kilobyte (1024 bytes). Optional suffixes 'M' (megabyte, 1024K) and 'G' (gigabyte, 1024M)
         * @param int $vmid Specify owner VM
         * @param string $format
         *   Enum: raw,qcow2,subvol
         * @return Result
         */
        public function createRest($filename, $size, $vmid, $format = null)
        {
            $params = ['filename' => $filename,
                'size' => $size,
                'vmid' => $vmid,
                'format' => $format];
            return $this->getClient()->create("/nodes/{$this->node}/storage/{$this->storage}/content", $params);
        }

        /**
         * Allocate disk images.
         * @param string $filename The name of the file to create.
         * @param string $size Size in kilobyte (1024 bytes). Optional suffixes 'M' (megabyte, 1024K) and 'G' (gigabyte, 1024M)
         * @param int $vmid Specify owner VM
         * @param string $format
         *   Enum: raw,qcow2,subvol
         * @return Result
         */
        public function create($filename, $size, $vmid, $format = null)
        {
            return $this->createRest($filename, $size, $vmid, $format);
        }
    }

    class PVEItemContentStorageStorageNodeNodesVolume extends Base
    {
        private $node;
        private $storage;
        private $volume;

        function __construct($client, $node, $storage, $volume)
        {
            $this->client = $client;
            $this->node = $node;
            $this->storage = $storage;
            $this->volume = $volume;
        }

        /**
         * Delete volume
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/nodes/{$this->node}/storage/{$this->storage}/content/{$this->volume}");
        }

        /**
         * Delete volume
         * @return Result
         */
        public function delete()
        {
            return $this->deleteRest();
        }

        /**
         * Get volume attributes
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/storage/{$this->storage}/content/{$this->volume}");
        }

        /**
         * Get volume attributes
         * @return Result
         */
        public function info()
        {
            return $this->getRest();
        }

        /**
         * Copy a volume. This is experimental code - do not use.
         * @param string $target Target volume identifier
         * @param string $target_node Target node. Default is local node.
         * @return Result
         */
        public function createRest($target, $target_node = null)
        {
            $params = ['target' => $target,
                'target_node' => $target_node];
            return $this->getClient()->create("/nodes/{$this->node}/storage/{$this->storage}/content/{$this->volume}", $params);
        }

        /**
         * Copy a volume. This is experimental code - do not use.
         * @param string $target Target volume identifier
         * @param string $target_node Target node. Default is local node.
         * @return Result
         */
        public function copy($target, $target_node = null)
        {
            return $this->createRest($target, $target_node);
        }
    }

    class PVEStorageStorageNodeNodesStatus extends Base
    {
        private $node;
        private $storage;

        function __construct($client, $node, $storage)
        {
            $this->client = $client;
            $this->node = $node;
            $this->storage = $storage;
        }

        /**
         * Read storage status.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/storage/{$this->storage}/status");
        }

        /**
         * Read storage status.
         * @return Result
         */
        public function readStatus()
        {
            return $this->getRest();
        }
    }

    class PVEStorageStorageNodeNodesRrd extends Base
    {
        private $node;
        private $storage;

        function __construct($client, $node, $storage)
        {
            $this->client = $client;
            $this->node = $node;
            $this->storage = $storage;
        }

        /**
         * Read storage RRD statistics (returns PNG).
         * @param string $ds The list of datasources you want to display.
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function getRest($ds, $timeframe, $cf = null)
        {
            $params = ['ds' => $ds,
                'timeframe' => $timeframe,
                'cf' => $cf];
            return $this->getClient()->get("/nodes/{$this->node}/storage/{$this->storage}/rrd", $params);
        }

        /**
         * Read storage RRD statistics (returns PNG).
         * @param string $ds The list of datasources you want to display.
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function rrd($ds, $timeframe, $cf = null)
        {
            return $this->getRest($ds, $timeframe, $cf);
        }
    }

    class PVEStorageStorageNodeNodesRrddata extends Base
    {
        private $node;
        private $storage;

        function __construct($client, $node, $storage)
        {
            $this->client = $client;
            $this->node = $node;
            $this->storage = $storage;
        }

        /**
         * Read storage RRD statistics.
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function getRest($timeframe, $cf = null)
        {
            $params = ['timeframe' => $timeframe,
                'cf' => $cf];
            return $this->getClient()->get("/nodes/{$this->node}/storage/{$this->storage}/rrddata", $params);
        }

        /**
         * Read storage RRD statistics.
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function rrddata($timeframe, $cf = null)
        {
            return $this->getRest($timeframe, $cf);
        }
    }

    class PVEStorageStorageNodeNodesUpload extends Base
    {
        private $node;
        private $storage;

        function __construct($client, $node, $storage)
        {
            $this->client = $client;
            $this->node = $node;
            $this->storage = $storage;
        }

        /**
         * Upload templates and ISO images.
         * @param string $content Content type.
         * @param string $filename The name of the file to create.
         * @param string $tmpfilename The source file name. This parameter is usually set by the REST handler. You can only overwrite it when connecting to the trustet port on localhost.
         * @return Result
         */
        public function createRest($content, $filename, $tmpfilename = null)
        {
            $params = ['content' => $content,
                'filename' => $filename,
                'tmpfilename' => $tmpfilename];
            return $this->getClient()->create("/nodes/{$this->node}/storage/{$this->storage}/upload", $params);
        }

        /**
         * Upload templates and ISO images.
         * @param string $content Content type.
         * @param string $filename The name of the file to create.
         * @param string $tmpfilename The source file name. This parameter is usually set by the REST handler. You can only overwrite it when connecting to the trustet port on localhost.
         * @return Result
         */
        public function upload($content, $filename, $tmpfilename = null)
        {
            return $this->createRest($content, $filename, $tmpfilename);
        }
    }

    class PVENodeNodesDisks extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        private $list;

        public function getList()
        {
            return $this->list ?: ($this->list = new PVEDisksNodeNodesList($this->client, $this->node));
        }

        private $smart;

        public function getSmart()
        {
            return $this->smart ?: ($this->smart = new PVEDisksNodeNodesSmart($this->client, $this->node));
        }

        private $initgpt;

        public function getInitgpt()
        {
            return $this->initgpt ?: ($this->initgpt = new PVEDisksNodeNodesInitgpt($this->client, $this->node));
        }

        /**
         * Node index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/disks");
        }

        /**
         * Node index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEDisksNodeNodesList extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * List local disks.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/disks/list");
        }

        /**
         * List local disks.
         * @return Result
         */
        public function list_()
        {
            return $this->getRest();
        }
    }

    class PVEDisksNodeNodesSmart extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get SMART Health of a disk.
         * @param string $disk Block device name
         * @param bool $healthonly If true returns only the health status
         * @return Result
         */
        public function getRest($disk, $healthonly = null)
        {
            $params = ['disk' => $disk,
                'healthonly' => $healthonly];
            return $this->getClient()->get("/nodes/{$this->node}/disks/smart", $params);
        }

        /**
         * Get SMART Health of a disk.
         * @param string $disk Block device name
         * @param bool $healthonly If true returns only the health status
         * @return Result
         */
        public function smart($disk, $healthonly = null)
        {
            return $this->getRest($disk, $healthonly);
        }
    }

    class PVEDisksNodeNodesInitgpt extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Initialize Disk with GPT
         * @param string $disk Block device name
         * @param string $uuid UUID for the GPT table
         * @return Result
         */
        public function createRest($disk, $uuid = null)
        {
            $params = ['disk' => $disk,
                'uuid' => $uuid];
            return $this->getClient()->create("/nodes/{$this->node}/disks/initgpt", $params);
        }

        /**
         * Initialize Disk with GPT
         * @param string $disk Block device name
         * @param string $uuid UUID for the GPT table
         * @return Result
         */
        public function initgpt($disk, $uuid = null)
        {
            return $this->createRest($disk, $uuid);
        }
    }

    class PVENodeNodesApt extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        private $update;

        public function getUpdate()
        {
            return $this->update ?: ($this->update = new PVEAptNodeNodesUpdate($this->client, $this->node));
        }

        private $changelog;

        public function getChangelog()
        {
            return $this->changelog ?: ($this->changelog = new PVEAptNodeNodesChangelog($this->client, $this->node));
        }

        private $versions;

        public function getVersions()
        {
            return $this->versions ?: ($this->versions = new PVEAptNodeNodesVersions($this->client, $this->node));
        }

        /**
         * Directory index for apt (Advanced Package Tool).
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/apt");
        }

        /**
         * Directory index for apt (Advanced Package Tool).
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEAptNodeNodesUpdate extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * List available updates.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/apt/update");
        }

        /**
         * List available updates.
         * @return Result
         */
        public function listUpdates()
        {
            return $this->getRest();
        }

        /**
         * This is used to resynchronize the package index files from their sources (apt-get update).
         * @param bool $notify Send notification mail about new packages (to email address specified for user 'root@pam').
         * @param bool $quiet Only produces output suitable for logging, omitting progress indicators.
         * @return Result
         */
        public function createRest($notify = null, $quiet = null)
        {
            $params = ['notify' => $notify,
                'quiet' => $quiet];
            return $this->getClient()->create("/nodes/{$this->node}/apt/update", $params);
        }

        /**
         * This is used to resynchronize the package index files from their sources (apt-get update).
         * @param bool $notify Send notification mail about new packages (to email address specified for user 'root@pam').
         * @param bool $quiet Only produces output suitable for logging, omitting progress indicators.
         * @return Result
         */
        public function updateDatabase($notify = null, $quiet = null)
        {
            return $this->createRest($notify, $quiet);
        }
    }

    class PVEAptNodeNodesChangelog extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get package changelogs.
         * @param string $name Package name.
         * @param string $version Package version.
         * @return Result
         */
        public function getRest($name, $version = null)
        {
            $params = ['name' => $name,
                'version' => $version];
            return $this->getClient()->get("/nodes/{$this->node}/apt/changelog", $params);
        }

        /**
         * Get package changelogs.
         * @param string $name Package name.
         * @param string $version Package version.
         * @return Result
         */
        public function changelog($name, $version = null)
        {
            return $this->getRest($name, $version);
        }
    }

    class PVEAptNodeNodesVersions extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get package information for important Proxmox packages.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/apt/versions");
        }

        /**
         * Get package information for important Proxmox packages.
         * @return Result
         */
        public function versions()
        {
            return $this->getRest();
        }
    }

    class PVENodeNodesFirewall extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        private $rules;

        public function getRules()
        {
            return $this->rules ?: ($this->rules = new PVEFirewallNodeNodesRules($this->client, $this->node));
        }

        private $options;

        public function getOptions()
        {
            return $this->options ?: ($this->options = new PVEFirewallNodeNodesOptions($this->client, $this->node));
        }

        private $log;

        public function getLog()
        {
            return $this->log ?: ($this->log = new PVEFirewallNodeNodesLog($this->client, $this->node));
        }

        /**
         * Directory index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/firewall");
        }

        /**
         * Directory index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEFirewallNodeNodesRules extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($pos)
        {
            return new PVEItemRulesFirewallNodeNodesPos($this->client, $this->node, $pos);
        }

        /**
         * List rules.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/firewall/rules");
        }

        /**
         * List rules.
         * @return Result
         */
        public function getRules()
        {
            return $this->getRest();
        }

        /**
         * Create new rule.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @param string $comment Descriptive comment.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $pos Update rule at position &amp;lt;pos&amp;gt;.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @return Result
         */
        public function createRest($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            $params = ['action' => $action,
                'type' => $type,
                'comment' => $comment,
                'dest' => $dest,
                'digest' => $digest,
                'dport' => $dport,
                'enable' => $enable,
                'iface' => $iface,
                'macro' => $macro,
                'pos' => $pos,
                'proto' => $proto,
                'source' => $source,
                'sport' => $sport];
            return $this->getClient()->create("/nodes/{$this->node}/firewall/rules", $params);
        }

        /**
         * Create new rule.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @param string $comment Descriptive comment.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $pos Update rule at position &amp;lt;pos&amp;gt;.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @return Result
         */
        public function createRule($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            return $this->createRest($action, $type, $comment, $dest, $digest, $dport, $enable, $iface, $macro, $pos, $proto, $source, $sport);
        }
    }

    class PVEItemRulesFirewallNodeNodesPos extends Base
    {
        private $node;
        private $pos;

        function __construct($client, $node, $pos)
        {
            $this->client = $client;
            $this->node = $node;
            $this->pos = $pos;
        }

        /**
         * Delete rule.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRest($digest = null)
        {
            $params = ['digest' => $digest];
            return $this->getClient()->delete("/nodes/{$this->node}/firewall/rules/{$this->pos}", $params);
        }

        /**
         * Delete rule.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function deleteRule($digest = null)
        {
            return $this->deleteRest($digest);
        }

        /**
         * Get single rule data.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/firewall/rules/{$this->pos}");
        }

        /**
         * Get single rule data.
         * @return Result
         */
        public function getRule()
        {
            return $this->getRest();
        }

        /**
         * Modify rule data.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $comment Descriptive comment.
         * @param string $delete A list of settings you want to delete.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $moveto Move rule to new position &amp;lt;moveto&amp;gt;. Other arguments are ignored.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @return Result
         */
        public function setRest($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            $params = ['action' => $action,
                'comment' => $comment,
                'delete' => $delete,
                'dest' => $dest,
                'digest' => $digest,
                'dport' => $dport,
                'enable' => $enable,
                'iface' => $iface,
                'macro' => $macro,
                'moveto' => $moveto,
                'proto' => $proto,
                'source' => $source,
                'sport' => $sport,
                'type' => $type];
            return $this->getClient()->set("/nodes/{$this->node}/firewall/rules/{$this->pos}", $params);
        }

        /**
         * Modify rule data.
         * @param string $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param string $comment Descriptive comment.
         * @param string $delete A list of settings you want to delete.
         * @param string $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param int $enable Flag to enable/disable a rule.
         * @param string $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param string $macro Use predefined standard macro.
         * @param int $moveto Move rule to new position &amp;lt;moveto&amp;gt;. Other arguments are ignored.
         * @param string $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param string $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param string $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param string $type Rule type.
         *   Enum: in,out,group
         * @return Result
         */
        public function updateRule($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            return $this->setRest($action, $comment, $delete, $dest, $digest, $dport, $enable, $iface, $macro, $moveto, $proto, $source, $sport, $type);
        }
    }

    class PVEFirewallNodeNodesOptions extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get host firewall options.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/firewall/options");
        }

        /**
         * Get host firewall options.
         * @return Result
         */
        public function getOptions()
        {
            return $this->getRest();
        }

        /**
         * Set Firewall options.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $enable Enable host firewall rules.
         * @param string $log_level_in Log level for incoming traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param string $log_level_out Log level for outgoing traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param bool $ndp Enable NDP.
         * @param int $nf_conntrack_max Maximum number of tracked connections.
         * @param int $nf_conntrack_tcp_timeout_established Conntrack established timeout.
         * @param bool $nosmurfs Enable SMURFS filter.
         * @param string $smurf_log_level Log level for SMURFS filter.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param string $tcp_flags_log_level Log level for illegal tcp flags filter.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param bool $tcpflags Filter illegal combinations of TCP flags.
         * @return Result
         */
        public function setRest($delete = null, $digest = null, $enable = null, $log_level_in = null, $log_level_out = null, $ndp = null, $nf_conntrack_max = null, $nf_conntrack_tcp_timeout_established = null, $nosmurfs = null, $smurf_log_level = null, $tcp_flags_log_level = null, $tcpflags = null)
        {
            $params = ['delete' => $delete,
                'digest' => $digest,
                'enable' => $enable,
                'log_level_in' => $log_level_in,
                'log_level_out' => $log_level_out,
                'ndp' => $ndp,
                'nf_conntrack_max' => $nf_conntrack_max,
                'nf_conntrack_tcp_timeout_established' => $nf_conntrack_tcp_timeout_established,
                'nosmurfs' => $nosmurfs,
                'smurf_log_level' => $smurf_log_level,
                'tcp_flags_log_level' => $tcp_flags_log_level,
                'tcpflags' => $tcpflags];
            return $this->getClient()->set("/nodes/{$this->node}/firewall/options", $params);
        }

        /**
         * Set Firewall options.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $enable Enable host firewall rules.
         * @param string $log_level_in Log level for incoming traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param string $log_level_out Log level for outgoing traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param bool $ndp Enable NDP.
         * @param int $nf_conntrack_max Maximum number of tracked connections.
         * @param int $nf_conntrack_tcp_timeout_established Conntrack established timeout.
         * @param bool $nosmurfs Enable SMURFS filter.
         * @param string $smurf_log_level Log level for SMURFS filter.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param string $tcp_flags_log_level Log level for illegal tcp flags filter.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param bool $tcpflags Filter illegal combinations of TCP flags.
         * @return Result
         */
        public function setOptions($delete = null, $digest = null, $enable = null, $log_level_in = null, $log_level_out = null, $ndp = null, $nf_conntrack_max = null, $nf_conntrack_tcp_timeout_established = null, $nosmurfs = null, $smurf_log_level = null, $tcp_flags_log_level = null, $tcpflags = null)
        {
            return $this->setRest($delete, $digest, $enable, $log_level_in, $log_level_out, $ndp, $nf_conntrack_max, $nf_conntrack_tcp_timeout_established, $nosmurfs, $smurf_log_level, $tcp_flags_log_level, $tcpflags);
        }
    }

    class PVEFirewallNodeNodesLog extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Read firewall log
         * @param int $limit
         * @param int $start
         * @return Result
         */
        public function getRest($limit = null, $start = null)
        {
            $params = ['limit' => $limit,
                'start' => $start];
            return $this->getClient()->get("/nodes/{$this->node}/firewall/log", $params);
        }

        /**
         * Read firewall log
         * @param int $limit
         * @param int $start
         * @return Result
         */
        public function log($limit = null, $start = null)
        {
            return $this->getRest($limit, $start);
        }
    }

    class PVENodeNodesReplication extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        public function get($id)
        {
            return new PVEItemReplicationNodeNodesId($this->client, $this->node, $id);
        }

        /**
         * List status of all replication jobs on this node.
         * @param int $guest Only list replication jobs for this guest.
         * @return Result
         */
        public function getRest($guest = null)
        {
            $params = ['guest' => $guest];
            return $this->getClient()->get("/nodes/{$this->node}/replication", $params);
        }

        /**
         * List status of all replication jobs on this node.
         * @param int $guest Only list replication jobs for this guest.
         * @return Result
         */
        public function status($guest = null)
        {
            return $this->getRest($guest);
        }
    }

    class PVEItemReplicationNodeNodesId extends Base
    {
        private $node;
        private $id;

        function __construct($client, $node, $id)
        {
            $this->client = $client;
            $this->node = $node;
            $this->id = $id;
        }

        private $status;

        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVEIdReplicationNodeNodesStatus($this->client, $this->node, $this->id));
        }

        private $log;

        public function getLog()
        {
            return $this->log ?: ($this->log = new PVEIdReplicationNodeNodesLog($this->client, $this->node, $this->id));
        }

        private $scheduleNow;

        public function getScheduleNow()
        {
            return $this->scheduleNow ?: ($this->scheduleNow = new PVEIdReplicationNodeNodesScheduleNow($this->client, $this->node, $this->id));
        }

        /**
         * Directory index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/replication/{$this->id}");
        }

        /**
         * Directory index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEIdReplicationNodeNodesStatus extends Base
    {
        private $node;
        private $id;

        function __construct($client, $node, $id)
        {
            $this->client = $client;
            $this->node = $node;
            $this->id = $id;
        }

        /**
         * Get replication job status.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/replication/{$this->id}/status");
        }

        /**
         * Get replication job status.
         * @return Result
         */
        public function jobStatus()
        {
            return $this->getRest();
        }
    }

    class PVEIdReplicationNodeNodesLog extends Base
    {
        private $node;
        private $id;

        function __construct($client, $node, $id)
        {
            $this->client = $client;
            $this->node = $node;
            $this->id = $id;
        }

        /**
         * Read replication job log.
         * @param int $limit
         * @param int $start
         * @return Result
         */
        public function getRest($limit = null, $start = null)
        {
            $params = ['limit' => $limit,
                'start' => $start];
            return $this->getClient()->get("/nodes/{$this->node}/replication/{$this->id}/log", $params);
        }

        /**
         * Read replication job log.
         * @param int $limit
         * @param int $start
         * @return Result
         */
        public function readJobLog($limit = null, $start = null)
        {
            return $this->getRest($limit, $start);
        }
    }

    class PVEIdReplicationNodeNodesScheduleNow extends Base
    {
        private $node;
        private $id;

        function __construct($client, $node, $id)
        {
            $this->client = $client;
            $this->node = $node;
            $this->id = $id;
        }

        /**
         * Schedule replication job to start as soon as possible.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/replication/{$this->id}/schedule_now");
        }

        /**
         * Schedule replication job to start as soon as possible.
         * @return Result
         */
        public function scheduleNow()
        {
            return $this->createRest();
        }
    }

    class PVENodeNodesVersion extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * API version details
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/version");
        }

        /**
         * API version details
         * @return Result
         */
        public function version()
        {
            return $this->getRest();
        }
    }

    class PVENodeNodesStatus extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Read node status
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/status");
        }

        /**
         * Read node status
         * @return Result
         */
        public function status()
        {
            return $this->getRest();
        }

        /**
         * Reboot or shutdown a node.
         * @param string $command Specify the command.
         *   Enum: reboot,shutdown
         * @return Result
         */
        public function createRest($command)
        {
            $params = ['command' => $command];
            return $this->getClient()->create("/nodes/{$this->node}/status", $params);
        }

        /**
         * Reboot or shutdown a node.
         * @param string $command Specify the command.
         *   Enum: reboot,shutdown
         * @return Result
         */
        public function nodeCmd($command)
        {
            return $this->createRest($command);
        }
    }

    class PVENodeNodesNetstat extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Read tap/vm network device interface counters
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/netstat");
        }

        /**
         * Read tap/vm network device interface counters
         * @return Result
         */
        public function netstat()
        {
            return $this->getRest();
        }
    }

    class PVENodeNodesExecute extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Execute multiple commands in order.
         * @param string $commands JSON encoded array of commands.
         * @return Result
         */
        public function createRest($commands)
        {
            $params = ['commands' => $commands];
            return $this->getClient()->create("/nodes/{$this->node}/execute", $params);
        }

        /**
         * Execute multiple commands in order.
         * @param string $commands JSON encoded array of commands.
         * @return Result
         */
        public function execute($commands)
        {
            return $this->createRest($commands);
        }
    }

    class PVENodeNodesRrd extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Read node RRD statistics (returns PNG)
         * @param string $ds The list of datasources you want to display.
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function getRest($ds, $timeframe, $cf = null)
        {
            $params = ['ds' => $ds,
                'timeframe' => $timeframe,
                'cf' => $cf];
            return $this->getClient()->get("/nodes/{$this->node}/rrd", $params);
        }

        /**
         * Read node RRD statistics (returns PNG)
         * @param string $ds The list of datasources you want to display.
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function rrd($ds, $timeframe, $cf = null)
        {
            return $this->getRest($ds, $timeframe, $cf);
        }
    }

    class PVENodeNodesRrddata extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Read node RRD statistics
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function getRest($timeframe, $cf = null)
        {
            $params = ['timeframe' => $timeframe,
                'cf' => $cf];
            return $this->getClient()->get("/nodes/{$this->node}/rrddata", $params);
        }

        /**
         * Read node RRD statistics
         * @param string $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param string $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return Result
         */
        public function rrddata($timeframe, $cf = null)
        {
            return $this->getRest($timeframe, $cf);
        }
    }

    class PVENodeNodesSyslog extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Read system log
         * @param int $limit
         * @param string $since Display all log since this date-time string.
         * @param int $start
         * @param string $until Display all log until this date-time string.
         * @return Result
         */
        public function getRest($limit = null, $since = null, $start = null, $until = null)
        {
            $params = ['limit' => $limit,
                'since' => $since,
                'start' => $start,
                'until' => $until];
            return $this->getClient()->get("/nodes/{$this->node}/syslog", $params);
        }

        /**
         * Read system log
         * @param int $limit
         * @param string $since Display all log since this date-time string.
         * @param int $start
         * @param string $until Display all log until this date-time string.
         * @return Result
         */
        public function syslog($limit = null, $since = null, $start = null, $until = null)
        {
            return $this->getRest($limit, $since, $start, $until);
        }
    }

    class PVENodeNodesVncshell extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Creates a VNC Shell proxy.
         * @param int $height sets the height of the console in pixels.
         * @param bool $upgrade Run 'apt-get dist-upgrade' instead of normal shell.
         * @param bool $websocket use websocket instead of standard vnc.
         * @param int $width sets the width of the console in pixels.
         * @return Result
         */
        public function createRest($height = null, $upgrade = null, $websocket = null, $width = null)
        {
            $params = ['height' => $height,
                'upgrade' => $upgrade,
                'websocket' => $websocket,
                'width' => $width];
            return $this->getClient()->create("/nodes/{$this->node}/vncshell", $params);
        }

        /**
         * Creates a VNC Shell proxy.
         * @param int $height sets the height of the console in pixels.
         * @param bool $upgrade Run 'apt-get dist-upgrade' instead of normal shell.
         * @param bool $websocket use websocket instead of standard vnc.
         * @param int $width sets the width of the console in pixels.
         * @return Result
         */
        public function vncshell($height = null, $upgrade = null, $websocket = null, $width = null)
        {
            return $this->createRest($height, $upgrade, $websocket, $width);
        }
    }

    class PVENodeNodesVncwebsocket extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Opens a weksocket for VNC traffic.
         * @param int $port Port number returned by previous vncproxy call.
         * @param string $vncticket Ticket from previous call to vncproxy.
         * @return Result
         */
        public function getRest($port, $vncticket)
        {
            $params = ['port' => $port,
                'vncticket' => $vncticket];
            return $this->getClient()->get("/nodes/{$this->node}/vncwebsocket", $params);
        }

        /**
         * Opens a weksocket for VNC traffic.
         * @param int $port Port number returned by previous vncproxy call.
         * @param string $vncticket Ticket from previous call to vncproxy.
         * @return Result
         */
        public function vncwebsocket($port, $vncticket)
        {
            return $this->getRest($port, $vncticket);
        }
    }

    class PVENodeNodesSpiceshell extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Creates a SPICE shell.
         * @param string $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As resonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @param bool $upgrade Run 'apt-get dist-upgrade' instead of normal shell.
         * @return Result
         */
        public function createRest($proxy = null, $upgrade = null)
        {
            $params = ['proxy' => $proxy,
                'upgrade' => $upgrade];
            return $this->getClient()->create("/nodes/{$this->node}/spiceshell", $params);
        }

        /**
         * Creates a SPICE shell.
         * @param string $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As resonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @param bool $upgrade Run 'apt-get dist-upgrade' instead of normal shell.
         * @return Result
         */
        public function spiceshell($proxy = null, $upgrade = null)
        {
            return $this->createRest($proxy, $upgrade);
        }
    }

    class PVENodeNodesDns extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Read DNS settings.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/dns");
        }

        /**
         * Read DNS settings.
         * @return Result
         */
        public function dns()
        {
            return $this->getRest();
        }

        /**
         * Write DNS settings.
         * @param string $search Search domain for host-name lookup.
         * @param string $dns1 First name server IP address.
         * @param string $dns2 Second name server IP address.
         * @param string $dns3 Third name server IP address.
         * @return Result
         */
        public function setRest($search, $dns1 = null, $dns2 = null, $dns3 = null)
        {
            $params = ['search' => $search,
                'dns1' => $dns1,
                'dns2' => $dns2,
                'dns3' => $dns3];
            return $this->getClient()->set("/nodes/{$this->node}/dns", $params);
        }

        /**
         * Write DNS settings.
         * @param string $search Search domain for host-name lookup.
         * @param string $dns1 First name server IP address.
         * @param string $dns2 Second name server IP address.
         * @param string $dns3 Third name server IP address.
         * @return Result
         */
        public function updateDns($search, $dns1 = null, $dns2 = null, $dns3 = null)
        {
            return $this->setRest($search, $dns1, $dns2, $dns3);
        }
    }

    class PVENodeNodesTime extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Read server time and time zone settings.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/time");
        }

        /**
         * Read server time and time zone settings.
         * @return Result
         */
        public function time()
        {
            return $this->getRest();
        }

        /**
         * Set time zone.
         * @param string $timezone Time zone. The file '/usr/share/zoneinfo/zone.tab' contains the list of valid names.
         * @return Result
         */
        public function setRest($timezone)
        {
            $params = ['timezone' => $timezone];
            return $this->getClient()->set("/nodes/{$this->node}/time", $params);
        }

        /**
         * Set time zone.
         * @param string $timezone Time zone. The file '/usr/share/zoneinfo/zone.tab' contains the list of valid names.
         * @return Result
         */
        public function setTimezone($timezone)
        {
            return $this->setRest($timezone);
        }
    }

    class PVENodeNodesAplinfo extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get list of appliances.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/aplinfo");
        }

        /**
         * Get list of appliances.
         * @return Result
         */
        public function aplinfo()
        {
            return $this->getRest();
        }

        /**
         * Download appliance templates.
         * @param string $storage The storage where the template will be stored
         * @param string $template The template wich will downloaded
         * @return Result
         */
        public function createRest($storage, $template)
        {
            $params = ['storage' => $storage,
                'template' => $template];
            return $this->getClient()->create("/nodes/{$this->node}/aplinfo", $params);
        }

        /**
         * Download appliance templates.
         * @param string $storage The storage where the template will be stored
         * @param string $template The template wich will downloaded
         * @return Result
         */
        public function aplDownload($storage, $template)
        {
            return $this->createRest($storage, $template);
        }
    }

    class PVENodeNodesReport extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Gather various systems information about a node
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/report");
        }

        /**
         * Gather various systems information about a node
         * @return Result
         */
        public function report()
        {
            return $this->getRest();
        }
    }

    class PVENodeNodesStartall extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Start all VMs and containers (when onboot=1).
         * @param bool $force force if onboot=0.
         * @param string $vms Only consider Guests with these IDs.
         * @return Result
         */
        public function createRest($force = null, $vms = null)
        {
            $params = ['force' => $force,
                'vms' => $vms];
            return $this->getClient()->create("/nodes/{$this->node}/startall", $params);
        }

        /**
         * Start all VMs and containers (when onboot=1).
         * @param bool $force force if onboot=0.
         * @param string $vms Only consider Guests with these IDs.
         * @return Result
         */
        public function startall($force = null, $vms = null)
        {
            return $this->createRest($force, $vms);
        }
    }

    class PVENodeNodesStopall extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Stop all VMs and Containers.
         * @param string $vms Only consider Guests with these IDs.
         * @return Result
         */
        public function createRest($vms = null)
        {
            $params = ['vms' => $vms];
            return $this->getClient()->create("/nodes/{$this->node}/stopall", $params);
        }

        /**
         * Stop all VMs and Containers.
         * @param string $vms Only consider Guests with these IDs.
         * @return Result
         */
        public function stopall($vms = null)
        {
            return $this->createRest($vms);
        }
    }

    class PVENodeNodesMigrateall extends Base
    {
        private $node;

        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Migrate all VMs and Containers.
         * @param string $target Target node.
         * @param int $maxworkers Maximal number of parallel migration job. If not set use 'max_workers' from datacenter.cfg, one of both must be set!
         * @param string $vms Only consider Guests with these IDs.
         * @return Result
         */
        public function createRest($target, $maxworkers = null, $vms = null)
        {
            $params = ['target' => $target,
                'maxworkers' => $maxworkers,
                'vms' => $vms];
            return $this->getClient()->create("/nodes/{$this->node}/migrateall", $params);
        }

        /**
         * Migrate all VMs and Containers.
         * @param string $target Target node.
         * @param int $maxworkers Maximal number of parallel migration job. If not set use 'max_workers' from datacenter.cfg, one of both must be set!
         * @param string $vms Only consider Guests with these IDs.
         * @return Result
         */
        public function migrateall($target, $maxworkers = null, $vms = null)
        {
            return $this->createRest($target, $maxworkers, $vms);
        }
    }

    class PVEStorage extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($storage)
        {
            return new PVEItemStorageStorage($this->client, $storage);
        }

        /**
         * Storage index.
         * @param string $type Only list storage of specific type
         *   Enum: dir,drbd,glusterfs,iscsi,iscsidirect,lvm,lvmthin,nfs,rbd,sheepdog,zfs,zfspool
         * @return Result
         */
        public function getRest($type = null)
        {
            $params = ['type' => $type];
            return $this->getClient()->get("/storage", $params);
        }

        /**
         * Storage index.
         * @param string $type Only list storage of specific type
         *   Enum: dir,drbd,glusterfs,iscsi,iscsidirect,lvm,lvmthin,nfs,rbd,sheepdog,zfs,zfspool
         * @return Result
         */
        public function index($type = null)
        {
            return $this->getRest($type);
        }

        /**
         * Create a new storage.
         * @param string $storage The storage identifier.
         * @param string $type Storage type.
         *   Enum: dir,drbd,glusterfs,iscsi,iscsidirect,lvm,lvmthin,nfs,rbd,sheepdog,zfs,zfspool
         * @param string $authsupported Authsupported.
         * @param string $base Base volume. This volume is automatically activated.
         * @param string $blocksize block size
         * @param string $comstar_hg host group for comstar views
         * @param string $comstar_tg target group for comstar views
         * @param string $content Allowed content types.  NOTE: the value 'rootdir' is used for Containers, and value 'images' for VMs.
         * @param bool $disable Flag to disable the storage.
         * @param string $export NFS export path.
         * @param string $format Default image format.
         * @param string $is_mountpoint Assume the given path is an externally managed mountpoint and consider the storage offline if it is not mounted. Using a boolean (yes/no) value serves as a shortcut to using the target path in this field.
         * @param string $iscsiprovider iscsi provider
         * @param bool $krbd Access rbd through krbd kernel module.
         * @param int $maxfiles Maximal number of backup files per VM. Use '0' for unlimted.
         * @param bool $mkdir Create the directory if it doesn't exist.
         * @param string $monhost IP addresses of monitors (for external clusters).
         * @param string $nodes List of cluster node names.
         * @param bool $nowritecache disable write caching on the target
         * @param string $options NFS mount options (see 'man nfs')
         * @param string $path File system path.
         * @param string $pool Pool.
         * @param string $portal iSCSI portal (IP or DNS name with optional port).
         * @param int $redundancy The redundancy count specifies the number of nodes to which the resource should be deployed. It must be at least 1 and at most the number of nodes in the cluster.
         * @param bool $saferemove Zero-out data when removing LVs.
         * @param string $saferemove_throughput Wipe throughput (cstream -t parameter value).
         * @param string $server Server IP or DNS name.
         * @param string $server2 Backup volfile server IP or DNS name.
         * @param bool $shared Mark storage as shared.
         * @param bool $sparse use sparse volumes
         * @param bool $tagged_only Only use logical volumes tagged with 'pve-vm-ID'.
         * @param string $target iSCSI target.
         * @param string $thinpool LVM thin pool LV name.
         * @param string $transport Gluster transport: tcp or rdma
         *   Enum: tcp,rdma,unix
         * @param string $username RBD Id.
         * @param string $vgname Volume group name.
         * @param string $volume Glusterfs Volume.
         * @return Result
         */
        public function createRest($storage, $type, $authsupported = null, $base = null, $blocksize = null, $comstar_hg = null, $comstar_tg = null, $content = null, $disable = null, $export = null, $format = null, $is_mountpoint = null, $iscsiprovider = null, $krbd = null, $maxfiles = null, $mkdir = null, $monhost = null, $nodes = null, $nowritecache = null, $options = null, $path = null, $pool = null, $portal = null, $redundancy = null, $saferemove = null, $saferemove_throughput = null, $server = null, $server2 = null, $shared = null, $sparse = null, $tagged_only = null, $target = null, $thinpool = null, $transport = null, $username = null, $vgname = null, $volume = null)
        {
            $params = ['storage' => $storage,
                'type' => $type,
                'authsupported' => $authsupported,
                'base' => $base,
                'blocksize' => $blocksize,
                'comstar_hg' => $comstar_hg,
                'comstar_tg' => $comstar_tg,
                'content' => $content,
                'disable' => $disable,
                'export' => $export,
                'format' => $format,
                'is_mountpoint' => $is_mountpoint,
                'iscsiprovider' => $iscsiprovider,
                'krbd' => $krbd,
                'maxfiles' => $maxfiles,
                'mkdir' => $mkdir,
                'monhost' => $monhost,
                'nodes' => $nodes,
                'nowritecache' => $nowritecache,
                'options' => $options,
                'path' => $path,
                'pool' => $pool,
                'portal' => $portal,
                'redundancy' => $redundancy,
                'saferemove' => $saferemove,
                'saferemove_throughput' => $saferemove_throughput,
                'server' => $server,
                'server2' => $server2,
                'shared' => $shared,
                'sparse' => $sparse,
                'tagged_only' => $tagged_only,
                'target' => $target,
                'thinpool' => $thinpool,
                'transport' => $transport,
                'username' => $username,
                'vgname' => $vgname,
                'volume' => $volume];
            return $this->getClient()->create("/storage", $params);
        }

        /**
         * Create a new storage.
         * @param string $storage The storage identifier.
         * @param string $type Storage type.
         *   Enum: dir,drbd,glusterfs,iscsi,iscsidirect,lvm,lvmthin,nfs,rbd,sheepdog,zfs,zfspool
         * @param string $authsupported Authsupported.
         * @param string $base Base volume. This volume is automatically activated.
         * @param string $blocksize block size
         * @param string $comstar_hg host group for comstar views
         * @param string $comstar_tg target group for comstar views
         * @param string $content Allowed content types.  NOTE: the value 'rootdir' is used for Containers, and value 'images' for VMs.
         * @param bool $disable Flag to disable the storage.
         * @param string $export NFS export path.
         * @param string $format Default image format.
         * @param string $is_mountpoint Assume the given path is an externally managed mountpoint and consider the storage offline if it is not mounted. Using a boolean (yes/no) value serves as a shortcut to using the target path in this field.
         * @param string $iscsiprovider iscsi provider
         * @param bool $krbd Access rbd through krbd kernel module.
         * @param int $maxfiles Maximal number of backup files per VM. Use '0' for unlimted.
         * @param bool $mkdir Create the directory if it doesn't exist.
         * @param string $monhost IP addresses of monitors (for external clusters).
         * @param string $nodes List of cluster node names.
         * @param bool $nowritecache disable write caching on the target
         * @param string $options NFS mount options (see 'man nfs')
         * @param string $path File system path.
         * @param string $pool Pool.
         * @param string $portal iSCSI portal (IP or DNS name with optional port).
         * @param int $redundancy The redundancy count specifies the number of nodes to which the resource should be deployed. It must be at least 1 and at most the number of nodes in the cluster.
         * @param bool $saferemove Zero-out data when removing LVs.
         * @param string $saferemove_throughput Wipe throughput (cstream -t parameter value).
         * @param string $server Server IP or DNS name.
         * @param string $server2 Backup volfile server IP or DNS name.
         * @param bool $shared Mark storage as shared.
         * @param bool $sparse use sparse volumes
         * @param bool $tagged_only Only use logical volumes tagged with 'pve-vm-ID'.
         * @param string $target iSCSI target.
         * @param string $thinpool LVM thin pool LV name.
         * @param string $transport Gluster transport: tcp or rdma
         *   Enum: tcp,rdma,unix
         * @param string $username RBD Id.
         * @param string $vgname Volume group name.
         * @param string $volume Glusterfs Volume.
         * @return Result
         */
        public function create($storage, $type, $authsupported = null, $base = null, $blocksize = null, $comstar_hg = null, $comstar_tg = null, $content = null, $disable = null, $export = null, $format = null, $is_mountpoint = null, $iscsiprovider = null, $krbd = null, $maxfiles = null, $mkdir = null, $monhost = null, $nodes = null, $nowritecache = null, $options = null, $path = null, $pool = null, $portal = null, $redundancy = null, $saferemove = null, $saferemove_throughput = null, $server = null, $server2 = null, $shared = null, $sparse = null, $tagged_only = null, $target = null, $thinpool = null, $transport = null, $username = null, $vgname = null, $volume = null)
        {
            return $this->createRest($storage, $type, $authsupported, $base, $blocksize, $comstar_hg, $comstar_tg, $content, $disable, $export, $format, $is_mountpoint, $iscsiprovider, $krbd, $maxfiles, $mkdir, $monhost, $nodes, $nowritecache, $options, $path, $pool, $portal, $redundancy, $saferemove, $saferemove_throughput, $server, $server2, $shared, $sparse, $tagged_only, $target, $thinpool, $transport, $username, $vgname, $volume);
        }
    }

    class PVEItemStorageStorage extends Base
    {
        private $storage;

        function __construct($client, $storage)
        {
            $this->client = $client;
            $this->storage = $storage;
        }

        /**
         * Delete storage configuration.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/storage/{$this->storage}");
        }

        /**
         * Delete storage configuration.
         * @return Result
         */
        public function delete()
        {
            return $this->deleteRest();
        }

        /**
         * Read storage configuration.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/storage/{$this->storage}");
        }

        /**
         * Read storage configuration.
         * @return Result
         */
        public function read()
        {
            return $this->getRest();
        }

        /**
         * Update storage configuration.
         * @param string $blocksize block size
         * @param string $comstar_hg host group for comstar views
         * @param string $comstar_tg target group for comstar views
         * @param string $content Allowed content types.  NOTE: the value 'rootdir' is used for Containers, and value 'images' for VMs.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $disable Flag to disable the storage.
         * @param string $format Default image format.
         * @param string $is_mountpoint Assume the given path is an externally managed mountpoint and consider the storage offline if it is not mounted. Using a boolean (yes/no) value serves as a shortcut to using the target path in this field.
         * @param bool $krbd Access rbd through krbd kernel module.
         * @param int $maxfiles Maximal number of backup files per VM. Use '0' for unlimted.
         * @param bool $mkdir Create the directory if it doesn't exist.
         * @param string $monhost IP addresses of monitors (for external clusters).
         * @param string $nodes List of cluster node names.
         * @param bool $nowritecache disable write caching on the target
         * @param string $options NFS mount options (see 'man nfs')
         * @param string $pool Pool.
         * @param int $redundancy The redundancy count specifies the number of nodes to which the resource should be deployed. It must be at least 1 and at most the number of nodes in the cluster.
         * @param bool $saferemove Zero-out data when removing LVs.
         * @param string $saferemove_throughput Wipe throughput (cstream -t parameter value).
         * @param string $server Server IP or DNS name.
         * @param string $server2 Backup volfile server IP or DNS name.
         * @param bool $shared Mark storage as shared.
         * @param bool $sparse use sparse volumes
         * @param bool $tagged_only Only use logical volumes tagged with 'pve-vm-ID'.
         * @param string $transport Gluster transport: tcp or rdma
         *   Enum: tcp,rdma,unix
         * @param string $username RBD Id.
         * @return Result
         */
        public function setRest($blocksize = null, $comstar_hg = null, $comstar_tg = null, $content = null, $delete = null, $digest = null, $disable = null, $format = null, $is_mountpoint = null, $krbd = null, $maxfiles = null, $mkdir = null, $monhost = null, $nodes = null, $nowritecache = null, $options = null, $pool = null, $redundancy = null, $saferemove = null, $saferemove_throughput = null, $server = null, $server2 = null, $shared = null, $sparse = null, $tagged_only = null, $transport = null, $username = null)
        {
            $params = ['blocksize' => $blocksize,
                'comstar_hg' => $comstar_hg,
                'comstar_tg' => $comstar_tg,
                'content' => $content,
                'delete' => $delete,
                'digest' => $digest,
                'disable' => $disable,
                'format' => $format,
                'is_mountpoint' => $is_mountpoint,
                'krbd' => $krbd,
                'maxfiles' => $maxfiles,
                'mkdir' => $mkdir,
                'monhost' => $monhost,
                'nodes' => $nodes,
                'nowritecache' => $nowritecache,
                'options' => $options,
                'pool' => $pool,
                'redundancy' => $redundancy,
                'saferemove' => $saferemove,
                'saferemove_throughput' => $saferemove_throughput,
                'server' => $server,
                'server2' => $server2,
                'shared' => $shared,
                'sparse' => $sparse,
                'tagged_only' => $tagged_only,
                'transport' => $transport,
                'username' => $username];
            return $this->getClient()->set("/storage/{$this->storage}", $params);
        }

        /**
         * Update storage configuration.
         * @param string $blocksize block size
         * @param string $comstar_hg host group for comstar views
         * @param string $comstar_tg target group for comstar views
         * @param string $content Allowed content types.  NOTE: the value 'rootdir' is used for Containers, and value 'images' for VMs.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $disable Flag to disable the storage.
         * @param string $format Default image format.
         * @param string $is_mountpoint Assume the given path is an externally managed mountpoint and consider the storage offline if it is not mounted. Using a boolean (yes/no) value serves as a shortcut to using the target path in this field.
         * @param bool $krbd Access rbd through krbd kernel module.
         * @param int $maxfiles Maximal number of backup files per VM. Use '0' for unlimted.
         * @param bool $mkdir Create the directory if it doesn't exist.
         * @param string $monhost IP addresses of monitors (for external clusters).
         * @param string $nodes List of cluster node names.
         * @param bool $nowritecache disable write caching on the target
         * @param string $options NFS mount options (see 'man nfs')
         * @param string $pool Pool.
         * @param int $redundancy The redundancy count specifies the number of nodes to which the resource should be deployed. It must be at least 1 and at most the number of nodes in the cluster.
         * @param bool $saferemove Zero-out data when removing LVs.
         * @param string $saferemove_throughput Wipe throughput (cstream -t parameter value).
         * @param string $server Server IP or DNS name.
         * @param string $server2 Backup volfile server IP or DNS name.
         * @param bool $shared Mark storage as shared.
         * @param bool $sparse use sparse volumes
         * @param bool $tagged_only Only use logical volumes tagged with 'pve-vm-ID'.
         * @param string $transport Gluster transport: tcp or rdma
         *   Enum: tcp,rdma,unix
         * @param string $username RBD Id.
         * @return Result
         */
        public function update($blocksize = null, $comstar_hg = null, $comstar_tg = null, $content = null, $delete = null, $digest = null, $disable = null, $format = null, $is_mountpoint = null, $krbd = null, $maxfiles = null, $mkdir = null, $monhost = null, $nodes = null, $nowritecache = null, $options = null, $pool = null, $redundancy = null, $saferemove = null, $saferemove_throughput = null, $server = null, $server2 = null, $shared = null, $sparse = null, $tagged_only = null, $transport = null, $username = null)
        {
            return $this->setRest($blocksize, $comstar_hg, $comstar_tg, $content, $delete, $digest, $disable, $format, $is_mountpoint, $krbd, $maxfiles, $mkdir, $monhost, $nodes, $nowritecache, $options, $pool, $redundancy, $saferemove, $saferemove_throughput, $server, $server2, $shared, $sparse, $tagged_only, $transport, $username);
        }
    }

    class PVEAccess extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        private $users;

        public function getUsers()
        {
            return $this->users ?: ($this->users = new PVEAccessUsers($this->client));
        }

        private $groups;

        public function getGroups()
        {
            return $this->groups ?: ($this->groups = new PVEAccessGroups($this->client));
        }

        private $roles;

        public function getRoles()
        {
            return $this->roles ?: ($this->roles = new PVEAccessRoles($this->client));
        }

        private $acl;

        public function getAcl()
        {
            return $this->acl ?: ($this->acl = new PVEAccessAcl($this->client));
        }

        private $domains;

        public function getDomains()
        {
            return $this->domains ?: ($this->domains = new PVEAccessDomains($this->client));
        }

        private $ticket;

        public function getTicket()
        {
            return $this->ticket ?: ($this->ticket = new PVEAccessTicket($this->client));
        }

        private $password;

        public function getPassword()
        {
            return $this->password ?: ($this->password = new PVEAccessPassword($this->client));
        }

        /**
         * Directory index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/access");
        }

        /**
         * Directory index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    class PVEAccessUsers extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($userid)
        {
            return new PVEItemUsersAccessUserid($this->client, $userid);
        }

        /**
         * User index.
         * @param bool $enabled Optional filter for enable property.
         * @return Result
         */
        public function getRest($enabled = null)
        {
            $params = ['enabled' => $enabled];
            return $this->getClient()->get("/access/users", $params);
        }

        /**
         * User index.
         * @param bool $enabled Optional filter for enable property.
         * @return Result
         */
        public function index($enabled = null)
        {
            return $this->getRest($enabled);
        }

        /**
         * Create new user.
         * @param string $userid User ID
         * @param string $comment
         * @param string $email
         * @param bool $enable Enable the account (default). You can set this to '0' to disable the accout
         * @param int $expire Account expiration date (seconds since epoch). '0' means no expiration date.
         * @param string $firstname
         * @param string $groups
         * @param string $keys Keys for two factor auth (yubico).
         * @param string $lastname
         * @param string $password Initial password.
         * @return Result
         */
        public function createRest($userid, $comment = null, $email = null, $enable = null, $expire = null, $firstname = null, $groups = null, $keys = null, $lastname = null, $password = null)
        {
            $params = ['userid' => $userid,
                'comment' => $comment,
                'email' => $email,
                'enable' => $enable,
                'expire' => $expire,
                'firstname' => $firstname,
                'groups' => $groups,
                'keys' => $keys,
                'lastname' => $lastname,
                'password' => $password];
            return $this->getClient()->create("/access/users", $params);
        }

        /**
         * Create new user.
         * @param string $userid User ID
         * @param string $comment
         * @param string $email
         * @param bool $enable Enable the account (default). You can set this to '0' to disable the accout
         * @param int $expire Account expiration date (seconds since epoch). '0' means no expiration date.
         * @param string $firstname
         * @param string $groups
         * @param string $keys Keys for two factor auth (yubico).
         * @param string $lastname
         * @param string $password Initial password.
         * @return Result
         */
        public function createUser($userid, $comment = null, $email = null, $enable = null, $expire = null, $firstname = null, $groups = null, $keys = null, $lastname = null, $password = null)
        {
            return $this->createRest($userid, $comment, $email, $enable, $expire, $firstname, $groups, $keys, $lastname, $password);
        }
    }

    class PVEItemUsersAccessUserid extends Base
    {
        private $userid;

        function __construct($client, $userid)
        {
            $this->client = $client;
            $this->userid = $userid;
        }

        /**
         * Delete user.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/access/users/{$this->userid}");
        }

        /**
         * Delete user.
         * @return Result
         */
        public function deleteUser()
        {
            return $this->deleteRest();
        }

        /**
         * Get user configuration.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/access/users/{$this->userid}");
        }

        /**
         * Get user configuration.
         * @return Result
         */
        public function readUser()
        {
            return $this->getRest();
        }

        /**
         * Update user configuration.
         * @param bool $append
         * @param string $comment
         * @param string $email
         * @param bool $enable Enable/disable the account.
         * @param int $expire Account expiration date (seconds since epoch). '0' means no expiration date.
         * @param string $firstname
         * @param string $groups
         * @param string $keys Keys for two factor auth (yubico).
         * @param string $lastname
         * @return Result
         */
        public function setRest($append = null, $comment = null, $email = null, $enable = null, $expire = null, $firstname = null, $groups = null, $keys = null, $lastname = null)
        {
            $params = ['append' => $append,
                'comment' => $comment,
                'email' => $email,
                'enable' => $enable,
                'expire' => $expire,
                'firstname' => $firstname,
                'groups' => $groups,
                'keys' => $keys,
                'lastname' => $lastname];
            return $this->getClient()->set("/access/users/{$this->userid}", $params);
        }

        /**
         * Update user configuration.
         * @param bool $append
         * @param string $comment
         * @param string $email
         * @param bool $enable Enable/disable the account.
         * @param int $expire Account expiration date (seconds since epoch). '0' means no expiration date.
         * @param string $firstname
         * @param string $groups
         * @param string $keys Keys for two factor auth (yubico).
         * @param string $lastname
         * @return Result
         */
        public function updateUser($append = null, $comment = null, $email = null, $enable = null, $expire = null, $firstname = null, $groups = null, $keys = null, $lastname = null)
        {
            return $this->setRest($append, $comment, $email, $enable, $expire, $firstname, $groups, $keys, $lastname);
        }
    }

    class PVEAccessGroups extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($groupid)
        {
            return new PVEItemGroupsAccessGroupid($this->client, $groupid);
        }

        /**
         * Group index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/access/groups");
        }

        /**
         * Group index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Create new group.
         * @param string $groupid
         * @param string $comment
         * @return Result
         */
        public function createRest($groupid, $comment = null)
        {
            $params = ['groupid' => $groupid,
                'comment' => $comment];
            return $this->getClient()->create("/access/groups", $params);
        }

        /**
         * Create new group.
         * @param string $groupid
         * @param string $comment
         * @return Result
         */
        public function createGroup($groupid, $comment = null)
        {
            return $this->createRest($groupid, $comment);
        }
    }

    class PVEItemGroupsAccessGroupid extends Base
    {
        private $groupid;

        function __construct($client, $groupid)
        {
            $this->client = $client;
            $this->groupid = $groupid;
        }

        /**
         * Delete group.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/access/groups/{$this->groupid}");
        }

        /**
         * Delete group.
         * @return Result
         */
        public function deleteGroup()
        {
            return $this->deleteRest();
        }

        /**
         * Get group configuration.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/access/groups/{$this->groupid}");
        }

        /**
         * Get group configuration.
         * @return Result
         */
        public function readGroup()
        {
            return $this->getRest();
        }

        /**
         * Update group data.
         * @param string $comment
         * @return Result
         */
        public function setRest($comment = null)
        {
            $params = ['comment' => $comment];
            return $this->getClient()->set("/access/groups/{$this->groupid}", $params);
        }

        /**
         * Update group data.
         * @param string $comment
         * @return Result
         */
        public function updateGroup($comment = null)
        {
            return $this->setRest($comment);
        }
    }

    class PVEAccessRoles extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($roleid)
        {
            return new PVEItemRolesAccessRoleid($this->client, $roleid);
        }

        /**
         * Role index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/access/roles");
        }

        /**
         * Role index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Create new role.
         * @param string $roleid
         * @param string $privs
         * @return Result
         */
        public function createRest($roleid, $privs = null)
        {
            $params = ['roleid' => $roleid,
                'privs' => $privs];
            return $this->getClient()->create("/access/roles", $params);
        }

        /**
         * Create new role.
         * @param string $roleid
         * @param string $privs
         * @return Result
         */
        public function createRole($roleid, $privs = null)
        {
            return $this->createRest($roleid, $privs);
        }
    }

    class PVEItemRolesAccessRoleid extends Base
    {
        private $roleid;

        function __construct($client, $roleid)
        {
            $this->client = $client;
            $this->roleid = $roleid;
        }

        /**
         * Delete role.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/access/roles/{$this->roleid}");
        }

        /**
         * Delete role.
         * @return Result
         */
        public function deleteRole()
        {
            return $this->deleteRest();
        }

        /**
         * Get role configuration.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/access/roles/{$this->roleid}");
        }

        /**
         * Get role configuration.
         * @return Result
         */
        public function readRole()
        {
            return $this->getRest();
        }

        /**
         * Create new role.
         * @param string $privs
         * @param bool $append
         * @return Result
         */
        public function setRest($privs, $append = null)
        {
            $params = ['privs' => $privs,
                'append' => $append];
            return $this->getClient()->set("/access/roles/{$this->roleid}", $params);
        }

        /**
         * Create new role.
         * @param string $privs
         * @param bool $append
         * @return Result
         */
        public function updateRole($privs, $append = null)
        {
            return $this->setRest($privs, $append);
        }
    }

    class PVEAccessAcl extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get Access Control List (ACLs).
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/access/acl");
        }

        /**
         * Get Access Control List (ACLs).
         * @return Result
         */
        public function readAcl()
        {
            return $this->getRest();
        }

        /**
         * Update Access Control List (add or remove permissions).
         * @param string $path Access control path
         * @param string $roles List of roles.
         * @param bool $delete Remove permissions (instead of adding it).
         * @param string $groups List of groups.
         * @param bool $propagate Allow to propagate (inherit) permissions.
         * @param string $users List of users.
         * @return Result
         */
        public function setRest($path, $roles, $delete = null, $groups = null, $propagate = null, $users = null)
        {
            $params = ['path' => $path,
                'roles' => $roles,
                'delete' => $delete,
                'groups' => $groups,
                'propagate' => $propagate,
                'users' => $users];
            return $this->getClient()->set("/access/acl", $params);
        }

        /**
         * Update Access Control List (add or remove permissions).
         * @param string $path Access control path
         * @param string $roles List of roles.
         * @param bool $delete Remove permissions (instead of adding it).
         * @param string $groups List of groups.
         * @param bool $propagate Allow to propagate (inherit) permissions.
         * @param string $users List of users.
         * @return Result
         */
        public function updateAcl($path, $roles, $delete = null, $groups = null, $propagate = null, $users = null)
        {
            return $this->setRest($path, $roles, $delete, $groups, $propagate, $users);
        }
    }

    class PVEAccessDomains extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($realm)
        {
            return new PVEItemDomainsAccessRealm($this->client, $realm);
        }

        /**
         * Authentication domain index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/access/domains");
        }

        /**
         * Authentication domain index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Add an authentication server.
         * @param string $realm Authentication domain ID
         * @param string $type Realm type.
         *   Enum: ad,ldap,pam,pve
         * @param string $base_dn LDAP base domain name
         * @param string $bind_dn LDAP bind domain name
         * @param string $capath Path to the CA certificate store
         * @param string $cert Path to the client certificate
         * @param string $certkey Path to the client certificate key
         * @param string $comment Description.
         * @param bool $default Use this as default realm
         * @param string $domain AD domain name
         * @param int $port Server port.
         * @param bool $secure Use secure LDAPS protocol.
         * @param string $server1 Server IP address (or DNS name)
         * @param string $server2 Fallback Server IP address (or DNS name)
         * @param string $tfa Use Two-factor authentication.
         * @param string $user_attr LDAP user attribute name
         * @param bool $verify Verify the server's SSL certificate
         * @return Result
         */
        public function createRest($realm, $type, $base_dn = null, $bind_dn = null, $capath = null, $cert = null, $certkey = null, $comment = null, $default = null, $domain = null, $port = null, $secure = null, $server1 = null, $server2 = null, $tfa = null, $user_attr = null, $verify = null)
        {
            $params = ['realm' => $realm,
                'type' => $type,
                'base_dn' => $base_dn,
                'bind_dn' => $bind_dn,
                'capath' => $capath,
                'cert' => $cert,
                'certkey' => $certkey,
                'comment' => $comment,
                'default' => $default,
                'domain' => $domain,
                'port' => $port,
                'secure' => $secure,
                'server1' => $server1,
                'server2' => $server2,
                'tfa' => $tfa,
                'user_attr' => $user_attr,
                'verify' => $verify];
            return $this->getClient()->create("/access/domains", $params);
        }

        /**
         * Add an authentication server.
         * @param string $realm Authentication domain ID
         * @param string $type Realm type.
         *   Enum: ad,ldap,pam,pve
         * @param string $base_dn LDAP base domain name
         * @param string $bind_dn LDAP bind domain name
         * @param string $capath Path to the CA certificate store
         * @param string $cert Path to the client certificate
         * @param string $certkey Path to the client certificate key
         * @param string $comment Description.
         * @param bool $default Use this as default realm
         * @param string $domain AD domain name
         * @param int $port Server port.
         * @param bool $secure Use secure LDAPS protocol.
         * @param string $server1 Server IP address (or DNS name)
         * @param string $server2 Fallback Server IP address (or DNS name)
         * @param string $tfa Use Two-factor authentication.
         * @param string $user_attr LDAP user attribute name
         * @param bool $verify Verify the server's SSL certificate
         * @return Result
         */
        public function create($realm, $type, $base_dn = null, $bind_dn = null, $capath = null, $cert = null, $certkey = null, $comment = null, $default = null, $domain = null, $port = null, $secure = null, $server1 = null, $server2 = null, $tfa = null, $user_attr = null, $verify = null)
        {
            return $this->createRest($realm, $type, $base_dn, $bind_dn, $capath, $cert, $certkey, $comment, $default, $domain, $port, $secure, $server1, $server2, $tfa, $user_attr, $verify);
        }
    }

    class PVEItemDomainsAccessRealm extends Base
    {
        private $realm;

        function __construct($client, $realm)
        {
            $this->client = $client;
            $this->realm = $realm;
        }

        /**
         * Delete an authentication server.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/access/domains/{$this->realm}");
        }

        /**
         * Delete an authentication server.
         * @return Result
         */
        public function delete()
        {
            return $this->deleteRest();
        }

        /**
         * Get auth server configuration.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/access/domains/{$this->realm}");
        }

        /**
         * Get auth server configuration.
         * @return Result
         */
        public function read()
        {
            return $this->getRest();
        }

        /**
         * Update authentication server settings.
         * @param string $base_dn LDAP base domain name
         * @param string $bind_dn LDAP bind domain name
         * @param string $capath Path to the CA certificate store
         * @param string $cert Path to the client certificate
         * @param string $certkey Path to the client certificate key
         * @param string $comment Description.
         * @param bool $default Use this as default realm
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $domain AD domain name
         * @param int $port Server port.
         * @param bool $secure Use secure LDAPS protocol.
         * @param string $server1 Server IP address (or DNS name)
         * @param string $server2 Fallback Server IP address (or DNS name)
         * @param string $tfa Use Two-factor authentication.
         * @param string $user_attr LDAP user attribute name
         * @param bool $verify Verify the server's SSL certificate
         * @return Result
         */
        public function setRest($base_dn = null, $bind_dn = null, $capath = null, $cert = null, $certkey = null, $comment = null, $default = null, $delete = null, $digest = null, $domain = null, $port = null, $secure = null, $server1 = null, $server2 = null, $tfa = null, $user_attr = null, $verify = null)
        {
            $params = ['base_dn' => $base_dn,
                'bind_dn' => $bind_dn,
                'capath' => $capath,
                'cert' => $cert,
                'certkey' => $certkey,
                'comment' => $comment,
                'default' => $default,
                'delete' => $delete,
                'digest' => $digest,
                'domain' => $domain,
                'port' => $port,
                'secure' => $secure,
                'server1' => $server1,
                'server2' => $server2,
                'tfa' => $tfa,
                'user_attr' => $user_attr,
                'verify' => $verify];
            return $this->getClient()->set("/access/domains/{$this->realm}", $params);
        }

        /**
         * Update authentication server settings.
         * @param string $base_dn LDAP base domain name
         * @param string $bind_dn LDAP bind domain name
         * @param string $capath Path to the CA certificate store
         * @param string $cert Path to the client certificate
         * @param string $certkey Path to the client certificate key
         * @param string $comment Description.
         * @param bool $default Use this as default realm
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $domain AD domain name
         * @param int $port Server port.
         * @param bool $secure Use secure LDAPS protocol.
         * @param string $server1 Server IP address (or DNS name)
         * @param string $server2 Fallback Server IP address (or DNS name)
         * @param string $tfa Use Two-factor authentication.
         * @param string $user_attr LDAP user attribute name
         * @param bool $verify Verify the server's SSL certificate
         * @return Result
         */
        public function update($base_dn = null, $bind_dn = null, $capath = null, $cert = null, $certkey = null, $comment = null, $default = null, $delete = null, $digest = null, $domain = null, $port = null, $secure = null, $server1 = null, $server2 = null, $tfa = null, $user_attr = null, $verify = null)
        {
            return $this->setRest($base_dn, $bind_dn, $capath, $cert, $certkey, $comment, $default, $delete, $digest, $domain, $port, $secure, $server1, $server2, $tfa, $user_attr, $verify);
        }
    }

    class PVEAccessTicket extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Dummy. Useful for formatters which want to provide a login page.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/access/ticket");
        }

        /**
         * Dummy. Useful for formatters which want to provide a login page.
         * @return Result
         */
        public function getTicket()
        {
            return $this->getRest();
        }

        /**
         * Create or verify authentication ticket.
         * @param string $password The secret password. This can also be a valid ticket.
         * @param string $username User name
         * @param string $otp One-time password for Two-factor authentication.
         * @param string $path Verify ticket, and check if user have access 'privs' on 'path'
         * @param string $privs Verify ticket, and check if user have access 'privs' on 'path'
         * @param string $realm You can optionally pass the realm using this parameter. Normally the realm is simply added to the username &amp;lt;username&amp;gt;@&amp;lt;relam&amp;gt;.
         * @return Result
         */
        public function createRest($password, $username, $otp = null, $path = null, $privs = null, $realm = null)
        {
            $params = ['password' => $password,
                'username' => $username,
                'otp' => $otp,
                'path' => $path,
                'privs' => $privs,
                'realm' => $realm];
            return $this->getClient()->create("/access/ticket", $params);
        }

        /**
         * Create or verify authentication ticket.
         * @param string $password The secret password. This can also be a valid ticket.
         * @param string $username User name
         * @param string $otp One-time password for Two-factor authentication.
         * @param string $path Verify ticket, and check if user have access 'privs' on 'path'
         * @param string $privs Verify ticket, and check if user have access 'privs' on 'path'
         * @param string $realm You can optionally pass the realm using this parameter. Normally the realm is simply added to the username &amp;lt;username&amp;gt;@&amp;lt;relam&amp;gt;.
         * @return Result
         */
        public function createTicket($password, $username, $otp = null, $path = null, $privs = null, $realm = null)
        {
            return $this->createRest($password, $username, $otp, $path, $privs, $realm);
        }
    }

    class PVEAccessPassword extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Change user password.
         * @param string $password The new password.
         * @param string $userid User ID
         * @return Result
         */
        public function setRest($password, $userid)
        {
            $params = ['password' => $password,
                'userid' => $userid];
            return $this->getClient()->set("/access/password", $params);
        }

        /**
         * Change user password.
         * @param string $password The new password.
         * @param string $userid User ID
         * @return Result
         */
        public function changePasssword($password, $userid)
        {
            return $this->setRest($password, $userid);
        }
    }

    class PVEPools extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        public function get($poolid)
        {
            return new PVEItemPoolsPoolid($this->client, $poolid);
        }

        /**
         * Pool index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/pools");
        }

        /**
         * Pool index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Create new pool.
         * @param string $poolid
         * @param string $comment
         * @return Result
         */
        public function createRest($poolid, $comment = null)
        {
            $params = ['poolid' => $poolid,
                'comment' => $comment];
            return $this->getClient()->create("/pools", $params);
        }

        /**
         * Create new pool.
         * @param string $poolid
         * @param string $comment
         * @return Result
         */
        public function createPool($poolid, $comment = null)
        {
            return $this->createRest($poolid, $comment);
        }
    }

    class PVEItemPoolsPoolid extends Base
    {
        private $poolid;

        function __construct($client, $poolid)
        {
            $this->client = $client;
            $this->poolid = $poolid;
        }

        /**
         * Delete pool.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/pools/{$this->poolid}");
        }

        /**
         * Delete pool.
         * @return Result
         */
        public function deletePool()
        {
            return $this->deleteRest();
        }

        /**
         * Get pool configuration.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/pools/{$this->poolid}");
        }

        /**
         * Get pool configuration.
         * @return Result
         */
        public function readPool()
        {
            return $this->getRest();
        }

        /**
         * Update pool data.
         * @param string $comment
         * @param bool $delete Remove vms/storage (instead of adding it).
         * @param string $storage List of storage IDs.
         * @param string $vms List of virtual machines.
         * @return Result
         */
        public function setRest($comment = null, $delete = null, $storage = null, $vms = null)
        {
            $params = ['comment' => $comment,
                'delete' => $delete,
                'storage' => $storage,
                'vms' => $vms];
            return $this->getClient()->set("/pools/{$this->poolid}", $params);
        }

        /**
         * Update pool data.
         * @param string $comment
         * @param bool $delete Remove vms/storage (instead of adding it).
         * @param string $storage List of storage IDs.
         * @param string $vms List of virtual machines.
         * @return Result
         */
        public function updatePool($comment = null, $delete = null, $storage = null, $vms = null)
        {
            return $this->setRest($comment, $delete, $storage, $vms);
        }
    }

    class PVEVersion extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * API version details. The result also includes the global datacenter confguration.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/version");
        }

        /**
         * API version details. The result also includes the global datacenter confguration.
         * @return Result
         */
        public function version()
        {
            return $this->getRest();
        }
    }
}