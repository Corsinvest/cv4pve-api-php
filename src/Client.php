<?php

namespace EnterpriseVE\ProxmoxVE\Api {
    abstract class Base
    {
        protected $client;

        protected function executeAction($resource, $method, $parms = [])
        {
            $url = "https://{$this->client->getHostName()}:{$this->client->getPort()}/api2/json{$resource}";
            $cookies = [];
            $headers = [];
            if ($this->client->getPVEAuthCookie() != null) {
                $cookies = ['PVEAuthCookie' => $this->client->getPVEAuthCookie()];
                $headers = ['CSRFPreventionToken' => $this->client->getCSRFPreventionToken()];
            }
            //remove null parms
            $parms = array_filter($parms, function ($value) {
                return $value !== null;
            });
            $httpClient = new \GuzzleHttp\Client();
            switch ($method) {
                case 'GET':
                    return $httpClient->get($url, [
                        'verify' => false,
                        'exceptions' => false,
                        'cookies' => $cookies,
                        'query' => $parms,
                    ])->json(['object' => true]);
                case 'POST':
                    return $httpClient->post($url, [
                        'verify' => false,
                        'exceptions' => false,
                        'cookies' => $cookies,
                        'headers' => $headers,
                        'body' => $parms,
                    ])->json(['object' => true]);
                case 'PUT':
                    return $httpClient->put($url, [
                        'verify' => false,
                        'exceptions' => false,
                        'cookies' => $cookies,
                        'headers' => $headers,
                        'body' => $parms,
                    ])->json(['object' => true]);
                case 'DELETE':
                    return $httpClient->delete($url, [
                        'verify' => false,
                        'exceptions' => false,
                        'cookies' => $cookies,
                        'headers' => $headers,
                        'body' => $parms,
                    ])->json(['object' => true]);
            }
        }

        protected function addIndexedParmeter(&$parms, $name, $values)
        {
            if ($values == null) {
                return;
            }
            foreach ($values as $key => $value) {
                $parms[$name . $key] = $value;
            }
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

        function __construct($hostName, $port = 8006)
        {
            $this->hostName = $hostName;
            $this->port = $port;
            $this->client = $this;
        }

        function login($userName, $password, $realm = "pam")
        {
            $ticket = $this->getAccess()
                ->getTicket()
                ->createTicket($password, $userName, null, null, null, $realm);
            $this->ticketCSRFPreventionToken = $ticket->data->CSRFPreventionToken;
            $this->ticketPVEAuthCookie = $ticket->data->ticket;
        }

        public function getHostName()
        {
            return $this->hostName;
        }

        public function getPort()
        {
            return $this->port;
        }

        public function getCSRFPreventionToken()
        {
            return $this->ticketCSRFPreventionToken;
        }

        public function getPVEAuthCookie()
        {
            return $this->ticketPVEAuthCookie;
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/cluster", 'GET');
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/cluster/replication", 'GET');
        }

        /**
         * Create a new replication job
         * @param $id Replication Job ID. The ID is composed of a Guest ID and a job number, separated by a hyphen, i.e. '&amp;lt;GUEST>-&amp;lt;JOBNUM>'.
         * @param $target Target node.
         * @param $type Section type.
         *   Enum: local
         * @param $comment Description.
         * @param $disable Flag to disable/deactivate the entry.
         * @param $rate Rate limit in mbps (megabytes per second) as floating point number.
         * @param $remove_job Mark the replication job for removal. The job will remove all local replication snapshots. When set to 'full', it also tries to remove replicated volumes on the target. The job then removes itself from the configuration file.
         *   Enum: local,full
         * @param $schedule Storage replication schedule. The format is a subset of `systemd` calender events.
         */
        public function create($id, $target, $type, $comment = null, $disable = null, $rate = null, $remove_job = null, $schedule = null)
        {
            $parms = ['id' => $id,
                'target' => $target,
                'type' => $type,
                'comment' => $comment,
                'disable' => $disable,
                'rate' => $rate,
                'remove_job' => $remove_job,
                'schedule' => $schedule];
            $this->executeAction("/cluster/replication", 'POST', $parms);
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
         * @param $force Will remove the jobconfig entry, but will not cleanup.
         * @param $keep Keep replicated data at target (do not remove).
         */
        public function delete($force = null, $keep = null)
        {
            $parms = ['force' => $force,
                'keep' => $keep];
            $this->executeAction("/cluster/replication/{$this->id}", 'DELETE', $parms);
        }

        /**
         * Read replication job configuration.
         * @return mixed
         */
        public function read()
        {
            return $this->executeAction("/cluster/replication/{$this->id}", 'GET');
        }

        /**
         * Update replication job configuration.
         * @param $comment Description.
         * @param $delete A list of settings you want to delete.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $disable Flag to disable/deactivate the entry.
         * @param $rate Rate limit in mbps (megabytes per second) as floating point number.
         * @param $remove_job Mark the replication job for removal. The job will remove all local replication snapshots. When set to 'full', it also tries to remove replicated volumes on the target. The job then removes itself from the configuration file.
         *   Enum: local,full
         * @param $schedule Storage replication schedule. The format is a subset of `systemd` calender events.
         */
        public function update($comment = null, $delete = null, $digest = null, $disable = null, $rate = null, $remove_job = null, $schedule = null)
        {
            $parms = ['comment' => $comment,
                'delete' => $delete,
                'digest' => $digest,
                'disable' => $disable,
                'rate' => $rate,
                'remove_job' => $remove_job,
                'schedule' => $schedule];
            $this->executeAction("/cluster/replication/{$this->id}", 'PUT', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/cluster/config", 'GET');
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
         * @return mixed
         */
        public function nodes()
        {
            return $this->executeAction("/cluster/config/nodes", 'GET');
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
         * @return mixed
         */
        public function totem()
        {
            return $this->executeAction("/cluster/config/totem", 'GET');
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/cluster/firewall", 'GET');
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
         * @return mixed
         */
        public function listSecurityGroups()
        {
            return $this->executeAction("/cluster/firewall/groups", 'GET');
        }

        /**
         * Create new security group.
         * @param $group Security Group name.
         * @param $comment
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $rename Rename/update an existing security group. You can set 'rename' to the same value as 'name' to update the 'comment' of an existing group.
         */
        public function createSecurityGroup($group, $comment = null, $digest = null, $rename = null)
        {
            $parms = ['group' => $group,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            $this->executeAction("/cluster/firewall/groups", 'POST', $parms);
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
         */
        public function deleteSecurityGroup()
        {
            $this->executeAction("/cluster/firewall/groups/{$this->group}", 'DELETE');
        }

        /**
         * List rules.
         * @return mixed
         */
        public function getRules()
        {
            return $this->executeAction("/cluster/firewall/groups/{$this->group}", 'GET');
        }

        /**
         * Create new rule.
         * @param $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param $type Rule type.
         *   Enum: in,out,group
         * @param $comment Descriptive comment.
         * @param $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $enable Flag to enable/disable a rule.
         * @param $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param $macro Use predefined standard macro.
         * @param $pos Update rule at position &amp;lt;pos>.
         * @param $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         */
        public function createRule($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            $parms = ['action' => $action,
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
            $this->executeAction("/cluster/firewall/groups/{$this->group}", 'POST', $parms);
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
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         */
        public function deleteRule($digest = null)
        {
            $parms = ['digest' => $digest];
            $this->executeAction("/cluster/firewall/groups/{$this->group}/{$this->pos}", 'DELETE', $parms);
        }

        /**
         * Get single rule data.
         * @return mixed
         */
        public function getRule()
        {
            return $this->executeAction("/cluster/firewall/groups/{$this->group}/{$this->pos}", 'GET');
        }

        /**
         * Modify rule data.
         * @param $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param $comment Descriptive comment.
         * @param $delete A list of settings you want to delete.
         * @param $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $enable Flag to enable/disable a rule.
         * @param $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param $macro Use predefined standard macro.
         * @param $moveto Move rule to new position &amp;lt;moveto>. Other arguments are ignored.
         * @param $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $type Rule type.
         *   Enum: in,out,group
         */
        public function updateRule($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            $parms = ['action' => $action,
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
            $this->executeAction("/cluster/firewall/groups/{$this->group}/{$this->pos}", 'PUT', $parms);
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
         * @return mixed
         */
        public function getRules()
        {
            return $this->executeAction("/cluster/firewall/rules", 'GET');
        }

        /**
         * Create new rule.
         * @param $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param $type Rule type.
         *   Enum: in,out,group
         * @param $comment Descriptive comment.
         * @param $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $enable Flag to enable/disable a rule.
         * @param $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param $macro Use predefined standard macro.
         * @param $pos Update rule at position &amp;lt;pos>.
         * @param $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         */
        public function createRule($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            $parms = ['action' => $action,
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
            $this->executeAction("/cluster/firewall/rules", 'POST', $parms);
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
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         */
        public function deleteRule($digest = null)
        {
            $parms = ['digest' => $digest];
            $this->executeAction("/cluster/firewall/rules/{$this->pos}", 'DELETE', $parms);
        }

        /**
         * Get single rule data.
         * @return mixed
         */
        public function getRule()
        {
            return $this->executeAction("/cluster/firewall/rules/{$this->pos}", 'GET');
        }

        /**
         * Modify rule data.
         * @param $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param $comment Descriptive comment.
         * @param $delete A list of settings you want to delete.
         * @param $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $enable Flag to enable/disable a rule.
         * @param $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param $macro Use predefined standard macro.
         * @param $moveto Move rule to new position &amp;lt;moveto>. Other arguments are ignored.
         * @param $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $type Rule type.
         *   Enum: in,out,group
         */
        public function updateRule($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            $parms = ['action' => $action,
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
            $this->executeAction("/cluster/firewall/rules/{$this->pos}", 'PUT', $parms);
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
         * @return mixed
         */
        public function ipsetIndex()
        {
            return $this->executeAction("/cluster/firewall/ipset", 'GET');
        }

        /**
         * Create new IPSet
         * @param $name IP set name.
         * @param $comment
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $rename Rename an existing IPSet. You can set 'rename' to the same value as 'name' to update the 'comment' of an existing IPSet.
         */
        public function createIpset($name, $comment = null, $digest = null, $rename = null)
        {
            $parms = ['name' => $name,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            $this->executeAction("/cluster/firewall/ipset", 'POST', $parms);
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
         */
        public function deleteIpset()
        {
            $this->executeAction("/cluster/firewall/ipset/{$this->name}", 'DELETE');
        }

        /**
         * List IPSet content
         * @return mixed
         */
        public function getIpset()
        {
            return $this->executeAction("/cluster/firewall/ipset/{$this->name}", 'GET');
        }

        /**
         * Add IP or Network to IPSet.
         * @param $cidr Network/IP specification in CIDR format.
         * @param $comment
         * @param $nomatch
         */
        public function createIp($cidr, $comment = null, $nomatch = null)
        {
            $parms = ['cidr' => $cidr,
                'comment' => $comment,
                'nomatch' => $nomatch];
            $this->executeAction("/cluster/firewall/ipset/{$this->name}", 'POST', $parms);
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
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         */
        public function removeIp($digest = null)
        {
            $parms = ['digest' => $digest];
            $this->executeAction("/cluster/firewall/ipset/{$this->name}/{$this->cidr}", 'DELETE', $parms);
        }

        /**
         * Read IP or Network settings from IPSet.
         * @return mixed
         */
        public function readIp()
        {
            return $this->executeAction("/cluster/firewall/ipset/{$this->name}/{$this->cidr}", 'GET');
        }

        /**
         * Update IP or Network settings
         * @param $comment
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $nomatch
         */
        public function updateIp($comment = null, $digest = null, $nomatch = null)
        {
            $parms = ['comment' => $comment,
                'digest' => $digest,
                'nomatch' => $nomatch];
            $this->executeAction("/cluster/firewall/ipset/{$this->name}/{$this->cidr}", 'PUT', $parms);
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
         * @return mixed
         */
        public function getAliases()
        {
            return $this->executeAction("/cluster/firewall/aliases", 'GET');
        }

        /**
         * Create IP or Network Alias.
         * @param $cidr Network/IP specification in CIDR format.
         * @param $name Alias name.
         * @param $comment
         */
        public function createAlias($cidr, $name, $comment = null)
        {
            $parms = ['cidr' => $cidr,
                'name' => $name,
                'comment' => $comment];
            $this->executeAction("/cluster/firewall/aliases", 'POST', $parms);
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
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         */
        public function removeAlias($digest = null)
        {
            $parms = ['digest' => $digest];
            $this->executeAction("/cluster/firewall/aliases/{$this->name}", 'DELETE', $parms);
        }

        /**
         * Read alias.
         * @return mixed
         */
        public function readAlias()
        {
            return $this->executeAction("/cluster/firewall/aliases/{$this->name}", 'GET');
        }

        /**
         * Update IP or Network alias.
         * @param $cidr Network/IP specification in CIDR format.
         * @param $comment
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $rename Rename an existing alias.
         */
        public function updateAlias($cidr, $comment = null, $digest = null, $rename = null)
        {
            $parms = ['cidr' => $cidr,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            $this->executeAction("/cluster/firewall/aliases/{$this->name}", 'PUT', $parms);
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
         * @return mixed
         */
        public function getOptions()
        {
            return $this->executeAction("/cluster/firewall/options", 'GET');
        }

        /**
         * Set Firewall options.
         * @param $delete A list of settings you want to delete.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $enable Enable or disable the firewall cluster wide.
         * @param $policy_in Input policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param $policy_out Output policy.
         *   Enum: ACCEPT,REJECT,DROP
         */
        public function setOptions($delete = null, $digest = null, $enable = null, $policy_in = null, $policy_out = null)
        {
            $parms = ['delete' => $delete,
                'digest' => $digest,
                'enable' => $enable,
                'policy_in' => $policy_in,
                'policy_out' => $policy_out];
            $this->executeAction("/cluster/firewall/options", 'PUT', $parms);
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
         * @return mixed
         */
        public function getMacros()
        {
            return $this->executeAction("/cluster/firewall/macros", 'GET');
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
         * @param $type Only list references of specified type.
         *   Enum: alias,ipset
         * @return mixed
         */
        public function refs($type = null)
        {
            $parms = ['type' => $type];
            return $this->executeAction("/cluster/firewall/refs", 'GET', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/cluster/backup", 'GET');
        }

        /**
         * Create new vzdump backup job.
         * @param $starttime Job Start time.
         * @param $all Backup all known guest systems on this host.
         * @param $bwlimit Limit I/O bandwidth (KBytes per second).
         * @param $compress Compress dump file.
         *   Enum: 0,1,gzip,lzo
         * @param $dow Day of week selection.
         * @param $dumpdir Store resulting files to specified directory.
         * @param $enabled Enable or disable the job.
         * @param $exclude Exclude specified guest systems (assumes --all)
         * @param $exclude_path Exclude certain files/directories (shell globs).
         * @param $ionice Set CFQ ionice priority.
         * @param $lockwait Maximal time to wait for the global lock (minutes).
         * @param $mailnotification Specify when to send an email
         *   Enum: always,failure
         * @param $mailto Comma-separated list of email addresses that should receive email notifications.
         * @param $maxfiles Maximal number of backup files per guest system.
         * @param $mode Backup mode.
         *   Enum: snapshot,suspend,stop
         * @param $node Only run if executed on this node.
         * @param $pigz Use pigz instead of gzip when N>0. N=1 uses half of cores, N>1 uses N as thread count.
         * @param $quiet Be quiet.
         * @param $remove Remove old backup files if there are more than 'maxfiles' backup files.
         * @param $script Use specified hook script.
         * @param $size Unused, will be removed in a future release.
         * @param $stdexcludes Exclude temporary files and logs.
         * @param $stop Stop runnig backup jobs on this host.
         * @param $stopwait Maximal time to wait until a guest system is stopped (minutes).
         * @param $storage Store resulting file to this storage.
         * @param $tmpdir Store temporary files to specified directory.
         * @param $vmid The ID of the guest system you want to backup.
         */
        public function createJob($starttime, $all = null, $bwlimit = null, $compress = null, $dow = null, $dumpdir = null, $enabled = null, $exclude = null, $exclude_path = null, $ionice = null, $lockwait = null, $mailnotification = null, $mailto = null, $maxfiles = null, $mode = null, $node = null, $pigz = null, $quiet = null, $remove = null, $script = null, $size = null, $stdexcludes = null, $stop = null, $stopwait = null, $storage = null, $tmpdir = null, $vmid = null)
        {
            $parms = ['starttime' => $starttime,
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
            $this->executeAction("/cluster/backup", 'POST', $parms);
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
         */
        public function deleteJob()
        {
            $this->executeAction("/cluster/backup/{$this->id}", 'DELETE');
        }

        /**
         * Read vzdump backup job definition.
         * @return mixed
         */
        public function readJob()
        {
            return $this->executeAction("/cluster/backup/{$this->id}", 'GET');
        }

        /**
         * Update vzdump backup job definition.
         * @param $starttime Job Start time.
         * @param $all Backup all known guest systems on this host.
         * @param $bwlimit Limit I/O bandwidth (KBytes per second).
         * @param $compress Compress dump file.
         *   Enum: 0,1,gzip,lzo
         * @param $delete A list of settings you want to delete.
         * @param $dow Day of week selection.
         * @param $dumpdir Store resulting files to specified directory.
         * @param $enabled Enable or disable the job.
         * @param $exclude Exclude specified guest systems (assumes --all)
         * @param $exclude_path Exclude certain files/directories (shell globs).
         * @param $ionice Set CFQ ionice priority.
         * @param $lockwait Maximal time to wait for the global lock (minutes).
         * @param $mailnotification Specify when to send an email
         *   Enum: always,failure
         * @param $mailto Comma-separated list of email addresses that should receive email notifications.
         * @param $maxfiles Maximal number of backup files per guest system.
         * @param $mode Backup mode.
         *   Enum: snapshot,suspend,stop
         * @param $node Only run if executed on this node.
         * @param $pigz Use pigz instead of gzip when N>0. N=1 uses half of cores, N>1 uses N as thread count.
         * @param $quiet Be quiet.
         * @param $remove Remove old backup files if there are more than 'maxfiles' backup files.
         * @param $script Use specified hook script.
         * @param $size Unused, will be removed in a future release.
         * @param $stdexcludes Exclude temporary files and logs.
         * @param $stop Stop runnig backup jobs on this host.
         * @param $stopwait Maximal time to wait until a guest system is stopped (minutes).
         * @param $storage Store resulting file to this storage.
         * @param $tmpdir Store temporary files to specified directory.
         * @param $vmid The ID of the guest system you want to backup.
         */
        public function updateJob($starttime, $all = null, $bwlimit = null, $compress = null, $delete = null, $dow = null, $dumpdir = null, $enabled = null, $exclude = null, $exclude_path = null, $ionice = null, $lockwait = null, $mailnotification = null, $mailto = null, $maxfiles = null, $mode = null, $node = null, $pigz = null, $quiet = null, $remove = null, $script = null, $size = null, $stdexcludes = null, $stop = null, $stopwait = null, $storage = null, $tmpdir = null, $vmid = null)
        {
            $parms = ['starttime' => $starttime,
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
            $this->executeAction("/cluster/backup/{$this->id}", 'PUT', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/cluster/ha", 'GET');
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
         * @param $type Only list resources of specific type
         *   Enum: ct,vm
         * @return mixed
         */
        public function index($type = null)
        {
            $parms = ['type' => $type];
            return $this->executeAction("/cluster/ha/resources", 'GET', $parms);
        }

        /**
         * Create a new HA resource.
         * @param $sid HA resource ID. This consists of a resource type followed by a resource specific name, separated with colon (example: vm:100 / ct:100). For virtual machines and containers, you can simply use the VM or CT id as a shortcut (example: 100).
         * @param $comment Description.
         * @param $group The HA group identifier.
         * @param $max_relocate Maximal number of service relocate tries when a service failes to start.
         * @param $max_restart Maximal number of tries to restart the service on a node after its start failed.
         * @param $state Requested resource state.
         *   Enum: started,stopped,enabled,disabled
         * @param $type Resource type.
         *   Enum: ct,vm
         */
        public function create($sid, $comment = null, $group = null, $max_relocate = null, $max_restart = null, $state = null, $type = null)
        {
            $parms = ['sid' => $sid,
                'comment' => $comment,
                'group' => $group,
                'max_relocate' => $max_relocate,
                'max_restart' => $max_restart,
                'state' => $state,
                'type' => $type];
            $this->executeAction("/cluster/ha/resources", 'POST', $parms);
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
         */
        public function delete()
        {
            $this->executeAction("/cluster/ha/resources/{$this->sid}", 'DELETE');
        }

        /**
         * Read resource configuration.
         * @return mixed
         */
        public function read()
        {
            return $this->executeAction("/cluster/ha/resources/{$this->sid}", 'GET');
        }

        /**
         * Update resource configuration.
         * @param $comment Description.
         * @param $delete A list of settings you want to delete.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $group The HA group identifier.
         * @param $max_relocate Maximal number of service relocate tries when a service failes to start.
         * @param $max_restart Maximal number of tries to restart the service on a node after its start failed.
         * @param $state Requested resource state.
         *   Enum: started,stopped,enabled,disabled
         */
        public function update($comment = null, $delete = null, $digest = null, $group = null, $max_relocate = null, $max_restart = null, $state = null)
        {
            $parms = ['comment' => $comment,
                'delete' => $delete,
                'digest' => $digest,
                'group' => $group,
                'max_relocate' => $max_relocate,
                'max_restart' => $max_restart,
                'state' => $state];
            $this->executeAction("/cluster/ha/resources/{$this->sid}", 'PUT', $parms);
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
         * @param $node The cluster node name.
         */
        public function migrate($node)
        {
            $parms = ['node' => $node];
            $this->executeAction("/cluster/ha/resources/{$this->sid}/migrate", 'POST', $parms);
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
         * @param $node The cluster node name.
         */
        public function relocate($node)
        {
            $parms = ['node' => $node];
            $this->executeAction("/cluster/ha/resources/{$this->sid}/relocate", 'POST', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/cluster/ha/groups", 'GET');
        }

        /**
         * Create a new HA group.
         * @param $group The HA group identifier.
         * @param $nodes List of cluster node names with optional priority.
         * @param $comment Description.
         * @param $nofailback The CRM tries to run services on the node with the highest priority. If a node with higher priority comes online, the CRM migrates the service to that node. Enabling nofailback prevents that behavior.
         * @param $restricted Resources bound to restricted groups may only run on nodes defined by the group.
         * @param $type Group type.
         *   Enum: group
         */
        public function create($group, $nodes, $comment = null, $nofailback = null, $restricted = null, $type = null)
        {
            $parms = ['group' => $group,
                'nodes' => $nodes,
                'comment' => $comment,
                'nofailback' => $nofailback,
                'restricted' => $restricted,
                'type' => $type];
            $this->executeAction("/cluster/ha/groups", 'POST', $parms);
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
         */
        public function delete()
        {
            $this->executeAction("/cluster/ha/groups/{$this->group}", 'DELETE');
        }

        /**
         * Read ha group configuration.
         * @return mixed
         */
        public function read()
        {
            return $this->executeAction("/cluster/ha/groups/{$this->group}", 'GET');
        }

        /**
         * Update ha group configuration.
         * @param $comment Description.
         * @param $delete A list of settings you want to delete.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $nodes List of cluster node names with optional priority.
         * @param $nofailback The CRM tries to run services on the node with the highest priority. If a node with higher priority comes online, the CRM migrates the service to that node. Enabling nofailback prevents that behavior.
         * @param $restricted Resources bound to restricted groups may only run on nodes defined by the group.
         */
        public function update($comment = null, $delete = null, $digest = null, $nodes = null, $nofailback = null, $restricted = null)
        {
            $parms = ['comment' => $comment,
                'delete' => $delete,
                'digest' => $digest,
                'nodes' => $nodes,
                'nofailback' => $nofailback,
                'restricted' => $restricted];
            $this->executeAction("/cluster/ha/groups/{$this->group}", 'PUT', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/cluster/ha/status", 'GET');
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
         * @return mixed
         */
        public function status()
        {
            return $this->executeAction("/cluster/ha/status/current", 'GET');
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
         * @return mixed
         */
        public function managerStatus()
        {
            return $this->executeAction("/cluster/ha/status/manager_status", 'GET');
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
         * @param $max Maximum number of entries.
         * @return mixed
         */
        public function log($max = null)
        {
            $parms = ['max' => $max];
            return $this->executeAction("/cluster/log", 'GET', $parms);
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
         * @param $type
         *   Enum: vm,storage,node
         * @return mixed
         */
        public function resources($type = null)
        {
            $parms = ['type' => $type];
            return $this->executeAction("/cluster/resources", 'GET', $parms);
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
         * @return mixed
         */
        public function tasks()
        {
            return $this->executeAction("/cluster/tasks", 'GET');
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
         * @return mixed
         */
        public function getOptions()
        {
            return $this->executeAction("/cluster/options", 'GET');
        }

        /**
         * Set datacenter options.
         * @param $console Select the default Console viewer. You can either use the builtin java applet (VNC), an external virt-viewer comtatible application (SPICE), or an HTML5 based viewer (noVNC).
         *   Enum: applet,vv,html5
         * @param $delete A list of settings you want to delete.
         * @param $email_from Specify email address to send notification from (default is root@$hostname)
         * @param $fencing Set the fencing mode of the HA cluster. Hardware mode needs a valid configuration of fence devices in /etc/pve/ha/fence.cfg. With both all two modes are used.  WARNING: 'hardware' and 'both' are EXPERIMENTAL &amp; WIP
         *   Enum: watchdog,hardware,both
         * @param $http_proxy Specify external http proxy which is used for downloads (example: 'http://username:password@host:port/')
         * @param $keyboard Default keybord layout for vnc server.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param $language Default GUI language.
         *   Enum: en,de
         * @param $mac_prefix Prefix for autogenerated MAC addresses.
         * @param $max_workers Defines how many workers (per node) are maximal started  on actions like 'stopall VMs' or task from the ha-manager.
         * @param $migration For cluster wide migration settings.
         * @param $migration_unsecure Migration is secure using SSH tunnel by default. For secure private networks you can disable it to speed up migration. Deprecated, use the 'migration' property instead!
         */
        public function setOptions($console = null, $delete = null, $email_from = null, $fencing = null, $http_proxy = null, $keyboard = null, $language = null, $mac_prefix = null, $max_workers = null, $migration = null, $migration_unsecure = null)
        {
            $parms = ['console' => $console,
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
            $this->executeAction("/cluster/options", 'PUT', $parms);
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
         * @return mixed
         */
        public function getStatus()
        {
            return $this->executeAction("/cluster/status", 'GET');
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
         * @param $vmid The (unique) ID of the VM.
         * @return mixed
         */
        public function nextid($vmid = null)
        {
            $parms = ['vmid' => $vmid];
            return $this->executeAction("/cluster/nextid", 'GET', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/nodes", 'GET');
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/nodes/{$this->node}", 'GET');
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
         * @param $full Determine the full status of active VMs.
         * @return mixed
         */
        public function vmlist($full = null)
        {
            $parms = ['full' => $full];
            return $this->executeAction("/nodes/{$this->node}/qemu", 'GET', $parms);
        }

        /**
         * Create or restore a virtual machine.
         * @param $vmid The (unique) ID of the VM.
         * @param $acpi Enable/disable ACPI.
         * @param $agent Enable/disable Qemu GuestAgent.
         * @param $archive The backup file.
         * @param $args Arbitrary arguments passed to kvm.
         * @param $autostart Automatic restart after crash (currently ignored).
         * @param $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param $bootdisk Enable booting from specified disk.
         * @param $cdrom This is an alias for option -ide2
         * @param $cores The number of cores per socket.
         * @param $cpu Emulated CPU type.
         * @param $cpulimit Limit of CPU usage.
         * @param $cpuunits CPU weight for a VM.
         * @param $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param $force Allow to overwrite existing VM.
         * @param $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param $hostpciN Map host PCI devices into guest.
         * @param $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.conf' configuration file.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param $kvm Enable/disable KVM hardware virtualization.
         * @param $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param $machine Specific the Qemu machine type.
         * @param $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param $name Set a name for the VM. Only used on the configuration web interface.
         * @param $netN Specify network devices.
         * @param $numa Enable/disable NUMA.
         * @param $numaN NUMA topology.
         * @param $onboot Specifies whether a VM will be started during system bootup.
         * @param $ostype Specify guest operating system.
         *   Enum: other,wxp,w2k,w2k3,w2k8,wvista,win7,win8,win10,l24,l26,solaris
         * @param $parallelN Map host parallel devices (n is 0 to 2).
         * @param $pool Add the VM to the specified pool.
         * @param $protection Sets the protection flag of the VM. This will disable the remove VM and remove disk operations.
         * @param $reboot Allow reboot. If set to '0' the VM exit on reboot.
         * @param $sataN Use volume as SATA hard disk or CD-ROM (n is 0 to 5).
         * @param $scsiN Use volume as SCSI hard disk or CD-ROM (n is 0 to 13).
         * @param $scsihw SCSI controller model
         *   Enum: lsi,lsi53c810,virtio-scsi-pci,virtio-scsi-single,megasas,pvscsi
         * @param $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning
         * @param $smbios1 Specify SMBIOS type 1 fields.
         * @param $smp The number of CPUs. Please use option -sockets instead.
         * @param $sockets The number of CPU sockets.
         * @param $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param $storage Default storage.
         * @param $tablet Enable/disable the USB tablet device.
         * @param $tdf Enable/disable time drift fix.
         * @param $template Enable/disable Template.
         * @param $unique Assign a unique random ethernet address.
         * @param $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param $usbN Configure an USB device (n is 0 to 4).
         * @param $vcpus Number of hotplugged vcpus.
         * @param $vga Select the VGA type.
         *   Enum: std,cirrus,vmware,qxl,serial0,serial1,serial2,serial3,qxl2,qxl3,qxl4
         * @param $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param $watchdog Create a virtual hardware watchdog device.
         * @return mixed
         */
        public function createVm($vmid, $acpi = null, $agent = null, $archive = null, $args = null, $autostart = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $description = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $pool = null, $protection = null, $reboot = null, $sataN = null, $scsiN = null, $scsihw = null, $serialN = null, $shares = null, $smbios1 = null, $smp = null, $sockets = null, $startdate = null, $startup = null, $storage = null, $tablet = null, $tdf = null, $template = null, $unique = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $watchdog = null)
        {
            $parms = ['vmid' => $vmid,
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
                'watchdog' => $watchdog];
            $this->addIndexedParmeter($parms, 'hostpci', $hostpciN);
            $this->addIndexedParmeter($parms, 'ide', $ideN);
            $this->addIndexedParmeter($parms, 'net', $netN);
            $this->addIndexedParmeter($parms, 'numa', $numaN);
            $this->addIndexedParmeter($parms, 'parallel', $parallelN);
            $this->addIndexedParmeter($parms, 'sata', $sataN);
            $this->addIndexedParmeter($parms, 'scsi', $scsiN);
            $this->addIndexedParmeter($parms, 'serial', $serialN);
            $this->addIndexedParmeter($parms, 'unused', $unusedN);
            $this->addIndexedParmeter($parms, 'usb', $usbN);
            $this->addIndexedParmeter($parms, 'virtio', $virtioN);
            return $this->executeAction("/nodes/{$this->node}/qemu", 'POST', $parms);
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
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         * @return mixed
         */
        public function destroyVm($skiplock = null)
        {
            $parms = ['skiplock' => $skiplock];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}", 'DELETE', $parms);
        }

        /**
         * Directory index
         * @return mixed
         */
        public function vmdiridx()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}", 'GET');
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall", 'GET');
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
         * @return mixed
         */
        public function getRules()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/rules", 'GET');
        }

        /**
         * Create new rule.
         * @param $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param $type Rule type.
         *   Enum: in,out,group
         * @param $comment Descriptive comment.
         * @param $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $enable Flag to enable/disable a rule.
         * @param $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param $macro Use predefined standard macro.
         * @param $pos Update rule at position &amp;lt;pos>.
         * @param $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         */
        public function createRule($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            $parms = ['action' => $action,
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
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/rules", 'POST', $parms);
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
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         */
        public function deleteRule($digest = null)
        {
            $parms = ['digest' => $digest];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/rules/{$this->pos}", 'DELETE', $parms);
        }

        /**
         * Get single rule data.
         * @return mixed
         */
        public function getRule()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/rules/{$this->pos}", 'GET');
        }

        /**
         * Modify rule data.
         * @param $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param $comment Descriptive comment.
         * @param $delete A list of settings you want to delete.
         * @param $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $enable Flag to enable/disable a rule.
         * @param $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param $macro Use predefined standard macro.
         * @param $moveto Move rule to new position &amp;lt;moveto>. Other arguments are ignored.
         * @param $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $type Rule type.
         *   Enum: in,out,group
         */
        public function updateRule($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            $parms = ['action' => $action,
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
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/rules/{$this->pos}", 'PUT', $parms);
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
         * @return mixed
         */
        public function getAliases()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/aliases", 'GET');
        }

        /**
         * Create IP or Network Alias.
         * @param $cidr Network/IP specification in CIDR format.
         * @param $name Alias name.
         * @param $comment
         */
        public function createAlias($cidr, $name, $comment = null)
        {
            $parms = ['cidr' => $cidr,
                'name' => $name,
                'comment' => $comment];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/aliases", 'POST', $parms);
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
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         */
        public function removeAlias($digest = null)
        {
            $parms = ['digest' => $digest];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/aliases/{$this->name}", 'DELETE', $parms);
        }

        /**
         * Read alias.
         * @return mixed
         */
        public function readAlias()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/aliases/{$this->name}", 'GET');
        }

        /**
         * Update IP or Network alias.
         * @param $cidr Network/IP specification in CIDR format.
         * @param $comment
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $rename Rename an existing alias.
         */
        public function updateAlias($cidr, $comment = null, $digest = null, $rename = null)
        {
            $parms = ['cidr' => $cidr,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/aliases/{$this->name}", 'PUT', $parms);
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
         * @return mixed
         */
        public function ipsetIndex()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset", 'GET');
        }

        /**
         * Create new IPSet
         * @param $name IP set name.
         * @param $comment
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $rename Rename an existing IPSet. You can set 'rename' to the same value as 'name' to update the 'comment' of an existing IPSet.
         */
        public function createIpset($name, $comment = null, $digest = null, $rename = null)
        {
            $parms = ['name' => $name,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset", 'POST', $parms);
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
         */
        public function deleteIpset()
        {
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset/{$this->name}", 'DELETE');
        }

        /**
         * List IPSet content
         * @return mixed
         */
        public function getIpset()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset/{$this->name}", 'GET');
        }

        /**
         * Add IP or Network to IPSet.
         * @param $cidr Network/IP specification in CIDR format.
         * @param $comment
         * @param $nomatch
         */
        public function createIp($cidr, $comment = null, $nomatch = null)
        {
            $parms = ['cidr' => $cidr,
                'comment' => $comment,
                'nomatch' => $nomatch];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset/{$this->name}", 'POST', $parms);
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
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         */
        public function removeIp($digest = null)
        {
            $parms = ['digest' => $digest];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset/{$this->name}/{$this->cidr}", 'DELETE', $parms);
        }

        /**
         * Read IP or Network settings from IPSet.
         * @return mixed
         */
        public function readIp()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset/{$this->name}/{$this->cidr}", 'GET');
        }

        /**
         * Update IP or Network settings
         * @param $comment
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $nomatch
         */
        public function updateIp($comment = null, $digest = null, $nomatch = null)
        {
            $parms = ['comment' => $comment,
                'digest' => $digest,
                'nomatch' => $nomatch];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/ipset/{$this->name}/{$this->cidr}", 'PUT', $parms);
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
         * @return mixed
         */
        public function getOptions()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/options", 'GET');
        }

        /**
         * Set Firewall options.
         * @param $delete A list of settings you want to delete.
         * @param $dhcp Enable DHCP.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $enable Enable/disable firewall rules.
         * @param $ipfilter Enable default IP filters. This is equivalent to adding an empty ipfilter-net&amp;lt;id> ipset for every interface. Such ipsets implicitly contain sane default restrictions such as restricting IPv6 link local addresses to the one derived from the interface's MAC address. For containers the configured IP addresses will be implicitly added.
         * @param $log_level_in Log level for incoming traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param $log_level_out Log level for outgoing traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param $macfilter Enable/disable MAC address filter.
         * @param $ndp Enable NDP.
         * @param $policy_in Input policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param $policy_out Output policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param $radv Allow sending Router Advertisement.
         */
        public function setOptions($delete = null, $dhcp = null, $digest = null, $enable = null, $ipfilter = null, $log_level_in = null, $log_level_out = null, $macfilter = null, $ndp = null, $policy_in = null, $policy_out = null, $radv = null)
        {
            $parms = ['delete' => $delete,
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
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/options", 'PUT', $parms);
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
         * @param $limit
         * @param $start
         * @return mixed
         */
        public function log($limit = null, $start = null)
        {
            $parms = ['limit' => $limit,
                'start' => $start];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/log", 'GET', $parms);
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
         * @param $type Only list references of specified type.
         *   Enum: alias,ipset
         * @return mixed
         */
        public function refs($type = null)
        {
            $parms = ['type' => $type];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/firewall/refs", 'GET', $parms);
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
         * @param $ds The list of datasources you want to display.
         * @param $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return mixed
         */
        public function rrd($ds, $timeframe, $cf = null)
        {
            $parms = ['ds' => $ds,
                'timeframe' => $timeframe,
                'cf' => $cf];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/rrd", 'GET', $parms);
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
         * @param $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return mixed
         */
        public function rrddata($timeframe, $cf = null)
        {
            $parms = ['timeframe' => $timeframe,
                'cf' => $cf];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/rrddata", 'GET', $parms);
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
         * @param $current Get current values (instead of pending values).
         * @return mixed
         */
        public function vmConfig($current = null)
        {
            $parms = ['current' => $current];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/config", 'GET', $parms);
        }

        /**
         * Set virtual machine options (asynchrounous API).
         * @param $acpi Enable/disable ACPI.
         * @param $agent Enable/disable Qemu GuestAgent.
         * @param $args Arbitrary arguments passed to kvm.
         * @param $autostart Automatic restart after crash (currently ignored).
         * @param $background_delay Time to wait for the task to finish. We return 'null' if the task finish within that time.
         * @param $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param $bootdisk Enable booting from specified disk.
         * @param $cdrom This is an alias for option -ide2
         * @param $cores The number of cores per socket.
         * @param $cpu Emulated CPU type.
         * @param $cpulimit Limit of CPU usage.
         * @param $cpuunits CPU weight for a VM.
         * @param $delete A list of settings you want to delete.
         * @param $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         * @param $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param $hostpciN Map host PCI devices into guest.
         * @param $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.conf' configuration file.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param $kvm Enable/disable KVM hardware virtualization.
         * @param $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param $machine Specific the Qemu machine type.
         * @param $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param $name Set a name for the VM. Only used on the configuration web interface.
         * @param $netN Specify network devices.
         * @param $numa Enable/disable NUMA.
         * @param $numaN NUMA topology.
         * @param $onboot Specifies whether a VM will be started during system bootup.
         * @param $ostype Specify guest operating system.
         *   Enum: other,wxp,w2k,w2k3,w2k8,wvista,win7,win8,win10,l24,l26,solaris
         * @param $parallelN Map host parallel devices (n is 0 to 2).
         * @param $protection Sets the protection flag of the VM. This will disable the remove VM and remove disk operations.
         * @param $reboot Allow reboot. If set to '0' the VM exit on reboot.
         * @param $revert Revert a pending change.
         * @param $sataN Use volume as SATA hard disk or CD-ROM (n is 0 to 5).
         * @param $scsiN Use volume as SCSI hard disk or CD-ROM (n is 0 to 13).
         * @param $scsihw SCSI controller model
         *   Enum: lsi,lsi53c810,virtio-scsi-pci,virtio-scsi-single,megasas,pvscsi
         * @param $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         * @param $smbios1 Specify SMBIOS type 1 fields.
         * @param $smp The number of CPUs. Please use option -sockets instead.
         * @param $sockets The number of CPU sockets.
         * @param $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param $tablet Enable/disable the USB tablet device.
         * @param $tdf Enable/disable time drift fix.
         * @param $template Enable/disable Template.
         * @param $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param $usbN Configure an USB device (n is 0 to 4).
         * @param $vcpus Number of hotplugged vcpus.
         * @param $vga Select the VGA type.
         *   Enum: std,cirrus,vmware,qxl,serial0,serial1,serial2,serial3,qxl2,qxl3,qxl4
         * @param $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param $watchdog Create a virtual hardware watchdog device.
         * @return mixed
         */
        public function updateVmAsync($acpi = null, $agent = null, $args = null, $autostart = null, $background_delay = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $protection = null, $reboot = null, $revert = null, $sataN = null, $scsiN = null, $scsihw = null, $serialN = null, $shares = null, $skiplock = null, $smbios1 = null, $smp = null, $sockets = null, $startdate = null, $startup = null, $tablet = null, $tdf = null, $template = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $watchdog = null)
        {
            $parms = ['acpi' => $acpi,
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
                'watchdog' => $watchdog];
            $this->addIndexedParmeter($parms, 'hostpci', $hostpciN);
            $this->addIndexedParmeter($parms, 'ide', $ideN);
            $this->addIndexedParmeter($parms, 'net', $netN);
            $this->addIndexedParmeter($parms, 'numa', $numaN);
            $this->addIndexedParmeter($parms, 'parallel', $parallelN);
            $this->addIndexedParmeter($parms, 'sata', $sataN);
            $this->addIndexedParmeter($parms, 'scsi', $scsiN);
            $this->addIndexedParmeter($parms, 'serial', $serialN);
            $this->addIndexedParmeter($parms, 'unused', $unusedN);
            $this->addIndexedParmeter($parms, 'usb', $usbN);
            $this->addIndexedParmeter($parms, 'virtio', $virtioN);
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/config", 'POST', $parms);
        }

        /**
         * Set virtual machine options (synchrounous API) - You should consider using the POST method instead for any actions involving hotplug or storage allocation.
         * @param $acpi Enable/disable ACPI.
         * @param $agent Enable/disable Qemu GuestAgent.
         * @param $args Arbitrary arguments passed to kvm.
         * @param $autostart Automatic restart after crash (currently ignored).
         * @param $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param $bootdisk Enable booting from specified disk.
         * @param $cdrom This is an alias for option -ide2
         * @param $cores The number of cores per socket.
         * @param $cpu Emulated CPU type.
         * @param $cpulimit Limit of CPU usage.
         * @param $cpuunits CPU weight for a VM.
         * @param $delete A list of settings you want to delete.
         * @param $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         * @param $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param $hostpciN Map host PCI devices into guest.
         * @param $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.conf' configuration file.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param $kvm Enable/disable KVM hardware virtualization.
         * @param $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param $machine Specific the Qemu machine type.
         * @param $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param $name Set a name for the VM. Only used on the configuration web interface.
         * @param $netN Specify network devices.
         * @param $numa Enable/disable NUMA.
         * @param $numaN NUMA topology.
         * @param $onboot Specifies whether a VM will be started during system bootup.
         * @param $ostype Specify guest operating system.
         *   Enum: other,wxp,w2k,w2k3,w2k8,wvista,win7,win8,win10,l24,l26,solaris
         * @param $parallelN Map host parallel devices (n is 0 to 2).
         * @param $protection Sets the protection flag of the VM. This will disable the remove VM and remove disk operations.
         * @param $reboot Allow reboot. If set to '0' the VM exit on reboot.
         * @param $revert Revert a pending change.
         * @param $sataN Use volume as SATA hard disk or CD-ROM (n is 0 to 5).
         * @param $scsiN Use volume as SCSI hard disk or CD-ROM (n is 0 to 13).
         * @param $scsihw SCSI controller model
         *   Enum: lsi,lsi53c810,virtio-scsi-pci,virtio-scsi-single,megasas,pvscsi
         * @param $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         * @param $smbios1 Specify SMBIOS type 1 fields.
         * @param $smp The number of CPUs. Please use option -sockets instead.
         * @param $sockets The number of CPU sockets.
         * @param $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param $tablet Enable/disable the USB tablet device.
         * @param $tdf Enable/disable time drift fix.
         * @param $template Enable/disable Template.
         * @param $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param $usbN Configure an USB device (n is 0 to 4).
         * @param $vcpus Number of hotplugged vcpus.
         * @param $vga Select the VGA type.
         *   Enum: std,cirrus,vmware,qxl,serial0,serial1,serial2,serial3,qxl2,qxl3,qxl4
         * @param $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param $watchdog Create a virtual hardware watchdog device.
         */
        public function updateVm($acpi = null, $agent = null, $args = null, $autostart = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $protection = null, $reboot = null, $revert = null, $sataN = null, $scsiN = null, $scsihw = null, $serialN = null, $shares = null, $skiplock = null, $smbios1 = null, $smp = null, $sockets = null, $startdate = null, $startup = null, $tablet = null, $tdf = null, $template = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $watchdog = null)
        {
            $parms = ['acpi' => $acpi,
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
                'watchdog' => $watchdog];
            $this->addIndexedParmeter($parms, 'hostpci', $hostpciN);
            $this->addIndexedParmeter($parms, 'ide', $ideN);
            $this->addIndexedParmeter($parms, 'net', $netN);
            $this->addIndexedParmeter($parms, 'numa', $numaN);
            $this->addIndexedParmeter($parms, 'parallel', $parallelN);
            $this->addIndexedParmeter($parms, 'sata', $sataN);
            $this->addIndexedParmeter($parms, 'scsi', $scsiN);
            $this->addIndexedParmeter($parms, 'serial', $serialN);
            $this->addIndexedParmeter($parms, 'unused', $unusedN);
            $this->addIndexedParmeter($parms, 'usb', $usbN);
            $this->addIndexedParmeter($parms, 'virtio', $virtioN);
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/config", 'PUT', $parms);
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
         * @return mixed
         */
        public function vmPending()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/pending", 'GET');
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
         * @param $idlist A list of disk IDs you want to delete.
         * @param $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         */
        public function unlink($idlist, $force = null)
        {
            $parms = ['idlist' => $idlist,
                'force' => $force];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/unlink", 'PUT', $parms);
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
         * @param $websocket starts websockify instead of vncproxy
         * @return mixed
         */
        public function vncproxy($websocket = null)
        {
            $parms = ['websocket' => $websocket];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/vncproxy", 'POST', $parms);
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
         * @param $port Port number returned by previous vncproxy call.
         * @param $vncticket Ticket from previous call to vncproxy.
         * @return mixed
         */
        public function vncwebsocket($port, $vncticket)
        {
            $parms = ['port' => $port,
                'vncticket' => $vncticket];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/vncwebsocket", 'GET', $parms);
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
         * @param $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As resonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @return mixed
         */
        public function spiceproxy($proxy = null)
        {
            $parms = ['proxy' => $proxy];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/spiceproxy", 'POST', $parms);
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
         * @return mixed
         */
        public function vmcmdidx()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/status", 'GET');
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
         * @return mixed
         */
        public function vmStatus()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/status/current", 'GET');
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
         * @param $machine Specific the Qemu machine type.
         * @param $migratedfrom The cluster node name.
         * @param $migration_network CIDR of the (sub) network that is used for migration.
         * @param $migration_type Migration traffic is encrypted using an SSH tunnel by default. On secure, completely private networks this can be disabled to increase performance.
         *   Enum: secure,insecure
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         * @param $stateuri Some command save/restore state from this location.
         * @param $targetstorage Target storage for the migration. (Can be '1' to use the same storage id as on the source node.)
         * @return mixed
         */
        public function vmStart($machine = null, $migratedfrom = null, $migration_network = null, $migration_type = null, $skiplock = null, $stateuri = null, $targetstorage = null)
        {
            $parms = ['machine' => $machine,
                'migratedfrom' => $migratedfrom,
                'migration_network' => $migration_network,
                'migration_type' => $migration_type,
                'skiplock' => $skiplock,
                'stateuri' => $stateuri,
                'targetstorage' => $targetstorage];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/status/start", 'POST', $parms);
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
         * @param $keepActive Do not deactivate storage volumes.
         * @param $migratedfrom The cluster node name.
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         * @param $timeout Wait maximal timeout seconds.
         * @return mixed
         */
        public function vmStop($keepActive = null, $migratedfrom = null, $skiplock = null, $timeout = null)
        {
            $parms = ['keepActive' => $keepActive,
                'migratedfrom' => $migratedfrom,
                'skiplock' => $skiplock,
                'timeout' => $timeout];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/status/stop", 'POST', $parms);
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
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         * @return mixed
         */
        public function vmReset($skiplock = null)
        {
            $parms = ['skiplock' => $skiplock];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/status/reset", 'POST', $parms);
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
         * @param $forceStop Make sure the VM stops.
         * @param $keepActive Do not deactivate storage volumes.
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         * @param $timeout Wait maximal timeout seconds.
         * @return mixed
         */
        public function vmShutdown($forceStop = null, $keepActive = null, $skiplock = null, $timeout = null)
        {
            $parms = ['forceStop' => $forceStop,
                'keepActive' => $keepActive,
                'skiplock' => $skiplock,
                'timeout' => $timeout];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/status/shutdown", 'POST', $parms);
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
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         * @return mixed
         */
        public function vmSuspend($skiplock = null)
        {
            $parms = ['skiplock' => $skiplock];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/status/suspend", 'POST', $parms);
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
         * @param $nocheck
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         * @return mixed
         */
        public function vmResume($nocheck = null, $skiplock = null)
        {
            $parms = ['nocheck' => $nocheck,
                'skiplock' => $skiplock];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/status/resume", 'POST', $parms);
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
         * @param $key The key (qemu monitor encoding).
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         */
        public function vmSendkey($key, $skiplock = null)
        {
            $parms = ['key' => $key,
                'skiplock' => $skiplock];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/sendkey", 'PUT', $parms);
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
         * @param $feature Feature to check.
         *   Enum: snapshot,clone,copy
         * @param $snapname The name of the snapshot.
         * @return mixed
         */
        public function vmFeature($feature, $snapname = null)
        {
            $parms = ['feature' => $feature,
                'snapname' => $snapname];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/feature", 'GET', $parms);
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
         * @param $newid VMID for the clone.
         * @param $description Description for the new VM.
         * @param $format Target format for file storage.
         *   Enum: raw,qcow2,vmdk
         * @param $full Create a full copy of all disk. This is always done when you clone a normal VM. For VM templates, we try to create a linked clone by default.
         * @param $name Set a name for the new VM.
         * @param $pool Add the new VM to the specified pool.
         * @param $snapname The name of the snapshot.
         * @param $storage Target storage for full clone.
         * @param $target Target node. Only allowed if the original VM is on shared storage.
         * @return mixed
         */
        public function cloneVm($newid, $description = null, $format = null, $full = null, $name = null, $pool = null, $snapname = null, $storage = null, $target = null)
        {
            $parms = ['newid' => $newid,
                'description' => $description,
                'format' => $format,
                'full' => $full,
                'name' => $name,
                'pool' => $pool,
                'snapname' => $snapname,
                'storage' => $storage,
                'target' => $target];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/clone", 'POST', $parms);
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
         * @param $disk The disk you want to move.
         *   Enum: ide0,ide1,ide2,ide3,scsi0,scsi1,scsi2,scsi3,scsi4,scsi5,scsi6,scsi7,scsi8,scsi9,scsi10,scsi11,scsi12,scsi13,virtio0,virtio1,virtio2,virtio3,virtio4,virtio5,virtio6,virtio7,virtio8,virtio9,virtio10,virtio11,virtio12,virtio13,virtio14,virtio15,sata0,sata1,sata2,sata3,sata4,sata5,efidisk0
         * @param $storage Target storage.
         * @param $delete Delete the original disk after successful copy. By default the original disk is kept as unused disk.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $format Target Format.
         *   Enum: raw,qcow2,vmdk
         * @return mixed
         */
        public function moveVmDisk($disk, $storage, $delete = null, $digest = null, $format = null)
        {
            $parms = ['disk' => $disk,
                'storage' => $storage,
                'delete' => $delete,
                'digest' => $digest,
                'format' => $format];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/move_disk", 'POST', $parms);
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
         * @param $target Target node.
         * @param $force Allow to migrate VMs which use local devices. Only root may use this option.
         * @param $migration_network CIDR of the (sub) network that is used for migration.
         * @param $migration_type Migration traffic is encrypted using an SSH tunnel by default. On secure, completely private networks this can be disabled to increase performance.
         *   Enum: secure,insecure
         * @param $online Use online/live migration.
         * @param $targetstorage Default target storage.
         * @param $with_local_disks Enable live storage migration for local disk
         * @return mixed
         */
        public function migrateVm($target, $force = null, $migration_network = null, $migration_type = null, $online = null, $targetstorage = null, $with_local_disks = null)
        {
            $parms = ['target' => $target,
                'force' => $force,
                'migration_network' => $migration_network,
                'migration_type' => $migration_type,
                'online' => $online,
                'targetstorage' => $targetstorage,
                'with-local-disks' => $with_local_disks];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/migrate", 'POST', $parms);
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
         * @param $command The monitor command.
         * @return mixed
         */
        public function monitor($command)
        {
            $parms = ['command' => $command];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/monitor", 'POST', $parms);
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
         * @param $command The QGA command.
         *   Enum: ping,get-time,info,fsfreeze-status,fsfreeze-freeze,fsfreeze-thaw,fstrim,network-get-interfaces,get-vcpus,get-fsinfo,get-memory-blocks,get-memory-block-info,suspend-hybrid,suspend-ram,suspend-disk,shutdown
         * @return mixed
         */
        public function agent($command)
        {
            $parms = ['command' => $command];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/agent", 'POST', $parms);
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
         * @param $disk The disk you want to resize.
         *   Enum: ide0,ide1,ide2,ide3,scsi0,scsi1,scsi2,scsi3,scsi4,scsi5,scsi6,scsi7,scsi8,scsi9,scsi10,scsi11,scsi12,scsi13,virtio0,virtio1,virtio2,virtio3,virtio4,virtio5,virtio6,virtio7,virtio8,virtio9,virtio10,virtio11,virtio12,virtio13,virtio14,virtio15,sata0,sata1,sata2,sata3,sata4,sata5,efidisk0
         * @param $size The new size. With the `+` sign the value is added to the actual size of the volume and without it, the value is taken as an absolute one. Shrinking disk size is not supported.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         */
        public function resizeVm($disk, $size, $digest = null, $skiplock = null)
        {
            $parms = ['disk' => $disk,
                'size' => $size,
                'digest' => $digest,
                'skiplock' => $skiplock];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/resize", 'PUT', $parms);
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
         * @return mixed
         */
        public function snapshotList()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot", 'GET');
        }

        /**
         * Snapshot a VM.
         * @param $snapname The name of the snapshot.
         * @param $description A textual description or comment.
         * @param $vmstate Save the vmstate
         * @return mixed
         */
        public function snapshot($snapname, $description = null, $vmstate = null)
        {
            $parms = ['snapname' => $snapname,
                'description' => $description,
                'vmstate' => $vmstate];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot", 'POST', $parms);
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
         * @param $force For removal from config file, even if removing disk snapshots fails.
         * @return mixed
         */
        public function delsnapshot($force = null)
        {
            $parms = ['force' => $force];
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot/{$this->snapname}", 'DELETE', $parms);
        }

        /**
         *
         * @return mixed
         */
        public function snapshotCmdIdx()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot/{$this->snapname}", 'GET');
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
         * @return mixed
         */
        public function getSnapshotConfig()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot/{$this->snapname}/config", 'GET');
        }

        /**
         * Update snapshot metadata.
         * @param $description A textual description or comment.
         */
        public function updateSnapshotConfig($description = null)
        {
            $parms = ['description' => $description];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot/{$this->snapname}/config", 'PUT', $parms);
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
         * @return mixed
         */
        public function rollback()
        {
            return $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/snapshot/{$this->snapname}/rollback", 'POST');
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
         * @param $disk If you want to convert only 1 disk to base image.
         *   Enum: ide0,ide1,ide2,ide3,scsi0,scsi1,scsi2,scsi3,scsi4,scsi5,scsi6,scsi7,scsi8,scsi9,scsi10,scsi11,scsi12,scsi13,virtio0,virtio1,virtio2,virtio3,virtio4,virtio5,virtio6,virtio7,virtio8,virtio9,virtio10,virtio11,virtio12,virtio13,virtio14,virtio15,sata0,sata1,sata2,sata3,sata4,sata5,efidisk0
         */
        public function template($disk = null)
        {
            $parms = ['disk' => $disk];
            $this->executeAction("/nodes/{$this->node}/qemu/{$this->vmid}/template", 'POST', $parms);
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
         * @return mixed
         */
        public function vmlist()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc", 'GET');
        }

        /**
         * Create or restore a container.
         * @param $ostemplate The OS template or backup file.
         * @param $vmid The (unique) ID of the VM.
         * @param $arch OS architecture type.
         *   Enum: amd64,i386
         * @param $cmode Console mode. By default, the console command tries to open a connection to one of the available tty devices. By setting cmode to 'console' it tries to attach to /dev/console instead. If you set cmode to 'shell', it simply invokes a shell inside the container (no login).
         *   Enum: shell,console,tty
         * @param $console Attach a console device (/dev/console) to the container.
         * @param $cores The number of cores assigned to the container. A container can use all available cores by default.
         * @param $cpulimit Limit of CPU usage.  NOTE: If the computer has 2 CPUs, it has a total of '2' CPU time. Value '0' indicates no CPU limit.
         * @param $cpuunits CPU weight for a VM. Argument is used in the kernel fair scheduler. The larger the number is, the more CPU time this VM gets. Number is relative to the weights of all the other running VMs.  NOTE: You can disable fair-scheduler configuration by setting this to 0.
         * @param $description Container description. Only used on the configuration web interface.
         * @param $force Allow to overwrite existing container.
         * @param $hostname Set a host name for the container.
         * @param $ignore_unpack_errors Ignore errors when extracting the template.
         * @param $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param $memory Amount of RAM for the VM in MB.
         * @param $mpN Use volume as container mount point.
         * @param $nameserver Sets DNS server IP address for a container. Create will automatically use the setting from the host if you neither set searchdomain nor nameserver.
         * @param $netN Specifies network interfaces for the container.
         * @param $onboot Specifies whether a VM will be started during system bootup.
         * @param $ostype OS type. This is used to setup configuration inside the container, and corresponds to lxc setup scripts in /usr/share/lxc/config/&amp;lt;ostype>.common.conf. Value 'unmanaged' can be used to skip and OS specific setup.
         *   Enum: debian,ubuntu,centos,fedora,opensuse,archlinux,alpine,gentoo,unmanaged
         * @param $password Sets root password inside container.
         * @param $pool Add the VM to the specified pool.
         * @param $protection Sets the protection flag of the container. This will prevent the CT or CT's disk remove/update operation.
         * @param $restore Mark this as restore task.
         * @param $rootfs Use volume as container root.
         * @param $searchdomain Sets DNS search domains for a container. Create will automatically use the setting from the host if you neither set searchdomain nor nameserver.
         * @param $ssh_public_keys Setup public SSH keys (one key per line, OpenSSH format).
         * @param $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param $storage Default Storage.
         * @param $swap Amount of SWAP for the VM in MB.
         * @param $template Enable/disable Template.
         * @param $tty Specify the number of tty available to the container
         * @param $unprivileged Makes the container run as unprivileged user. (Should not be modified manually.)
         * @param $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @return mixed
         */
        public function createVm($ostemplate, $vmid, $arch = null, $cmode = null, $console = null, $cores = null, $cpulimit = null, $cpuunits = null, $description = null, $force = null, $hostname = null, $ignore_unpack_errors = null, $lock = null, $memory = null, $mpN = null, $nameserver = null, $netN = null, $onboot = null, $ostype = null, $password = null, $pool = null, $protection = null, $restore = null, $rootfs = null, $searchdomain = null, $ssh_public_keys = null, $startup = null, $storage = null, $swap = null, $template = null, $tty = null, $unprivileged = null, $unusedN = null)
        {
            $parms = ['ostemplate' => $ostemplate,
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
            $this->addIndexedParmeter($parms, 'mp', $mpN);
            $this->addIndexedParmeter($parms, 'net', $netN);
            $this->addIndexedParmeter($parms, 'unused', $unusedN);
            return $this->executeAction("/nodes/{$this->node}/lxc", 'POST', $parms);
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
         * @return mixed
         */
        public function destroyVm()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}", 'DELETE');
        }

        /**
         * Directory index
         * @return mixed
         */
        public function vmdiridx()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}", 'GET');
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
         * @return mixed
         */
        public function vmConfig()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/config", 'GET');
        }

        /**
         * Set container options.
         * @param $arch OS architecture type.
         *   Enum: amd64,i386
         * @param $cmode Console mode. By default, the console command tries to open a connection to one of the available tty devices. By setting cmode to 'console' it tries to attach to /dev/console instead. If you set cmode to 'shell', it simply invokes a shell inside the container (no login).
         *   Enum: shell,console,tty
         * @param $console Attach a console device (/dev/console) to the container.
         * @param $cores The number of cores assigned to the container. A container can use all available cores by default.
         * @param $cpulimit Limit of CPU usage.  NOTE: If the computer has 2 CPUs, it has a total of '2' CPU time. Value '0' indicates no CPU limit.
         * @param $cpuunits CPU weight for a VM. Argument is used in the kernel fair scheduler. The larger the number is, the more CPU time this VM gets. Number is relative to the weights of all the other running VMs.  NOTE: You can disable fair-scheduler configuration by setting this to 0.
         * @param $delete A list of settings you want to delete.
         * @param $description Container description. Only used on the configuration web interface.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $hostname Set a host name for the container.
         * @param $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param $memory Amount of RAM for the VM in MB.
         * @param $mpN Use volume as container mount point.
         * @param $nameserver Sets DNS server IP address for a container. Create will automatically use the setting from the host if you neither set searchdomain nor nameserver.
         * @param $netN Specifies network interfaces for the container.
         * @param $onboot Specifies whether a VM will be started during system bootup.
         * @param $ostype OS type. This is used to setup configuration inside the container, and corresponds to lxc setup scripts in /usr/share/lxc/config/&amp;lt;ostype>.common.conf. Value 'unmanaged' can be used to skip and OS specific setup.
         *   Enum: debian,ubuntu,centos,fedora,opensuse,archlinux,alpine,gentoo,unmanaged
         * @param $protection Sets the protection flag of the container. This will prevent the CT or CT's disk remove/update operation.
         * @param $rootfs Use volume as container root.
         * @param $searchdomain Sets DNS search domains for a container. Create will automatically use the setting from the host if you neither set searchdomain nor nameserver.
         * @param $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param $swap Amount of SWAP for the VM in MB.
         * @param $template Enable/disable Template.
         * @param $tty Specify the number of tty available to the container
         * @param $unprivileged Makes the container run as unprivileged user. (Should not be modified manually.)
         * @param $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         */
        public function updateVm($arch = null, $cmode = null, $console = null, $cores = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $hostname = null, $lock = null, $memory = null, $mpN = null, $nameserver = null, $netN = null, $onboot = null, $ostype = null, $protection = null, $rootfs = null, $searchdomain = null, $startup = null, $swap = null, $template = null, $tty = null, $unprivileged = null, $unusedN = null)
        {
            $parms = ['arch' => $arch,
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
            $this->addIndexedParmeter($parms, 'mp', $mpN);
            $this->addIndexedParmeter($parms, 'net', $netN);
            $this->addIndexedParmeter($parms, 'unused', $unusedN);
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/config", 'PUT', $parms);
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
         * @return mixed
         */
        public function vmcmdidx()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/status", 'GET');
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
         * @return mixed
         */
        public function vmStatus()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/status/current", 'GET');
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
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         * @return mixed
         */
        public function vmStart($skiplock = null)
        {
            $parms = ['skiplock' => $skiplock];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/status/start", 'POST', $parms);
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
         * @param $skiplock Ignore locks - only root is allowed to use this option.
         * @return mixed
         */
        public function vmStop($skiplock = null)
        {
            $parms = ['skiplock' => $skiplock];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/status/stop", 'POST', $parms);
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
         * @param $forceStop Make sure the Container stops.
         * @param $timeout Wait maximal timeout seconds.
         * @return mixed
         */
        public function vmShutdown($forceStop = null, $timeout = null)
        {
            $parms = ['forceStop' => $forceStop,
                'timeout' => $timeout];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/status/shutdown", 'POST', $parms);
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
         * @return mixed
         */
        public function vmSuspend()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/status/suspend", 'POST');
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
         * @return mixed
         */
        public function vmResume()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/status/resume", 'POST');
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
         * @return mixed
         */
        public function list()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot", 'GET');
        }

        /**
         * Snapshot a container.
         * @param $snapname The name of the snapshot.
         * @param $description A textual description or comment.
         * @return mixed
         */
        public function snapshot($snapname, $description = null)
        {
            $parms = ['snapname' => $snapname,
                'description' => $description];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot", 'POST', $parms);
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
         * @param $force For removal from config file, even if removing disk snapshots fails.
         * @return mixed
         */
        public function delsnapshot($force = null)
        {
            $parms = ['force' => $force];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot/{$this->snapname}", 'DELETE', $parms);
        }

        /**
         *
         * @return mixed
         */
        public function snapshotCmdIdx()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot/{$this->snapname}", 'GET');
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
         * @return mixed
         */
        public function rollback()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot/{$this->snapname}/rollback", 'POST');
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
         * @return mixed
         */
        public function getSnapshotConfig()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot/{$this->snapname}/config", 'GET');
        }

        /**
         * Update snapshot metadata.
         * @param $description A textual description or comment.
         */
        public function updateSnapshotConfig($description = null)
        {
            $parms = ['description' => $description];
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/snapshot/{$this->snapname}/config", 'PUT', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall", 'GET');
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
         * @return mixed
         */
        public function getRules()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/rules", 'GET');
        }

        /**
         * Create new rule.
         * @param $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param $type Rule type.
         *   Enum: in,out,group
         * @param $comment Descriptive comment.
         * @param $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $enable Flag to enable/disable a rule.
         * @param $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param $macro Use predefined standard macro.
         * @param $pos Update rule at position &amp;lt;pos>.
         * @param $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         */
        public function createRule($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            $parms = ['action' => $action,
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
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/rules", 'POST', $parms);
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
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         */
        public function deleteRule($digest = null)
        {
            $parms = ['digest' => $digest];
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/rules/{$this->pos}", 'DELETE', $parms);
        }

        /**
         * Get single rule data.
         * @return mixed
         */
        public function getRule()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/rules/{$this->pos}", 'GET');
        }

        /**
         * Modify rule data.
         * @param $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param $comment Descriptive comment.
         * @param $delete A list of settings you want to delete.
         * @param $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $enable Flag to enable/disable a rule.
         * @param $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param $macro Use predefined standard macro.
         * @param $moveto Move rule to new position &amp;lt;moveto>. Other arguments are ignored.
         * @param $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $type Rule type.
         *   Enum: in,out,group
         */
        public function updateRule($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            $parms = ['action' => $action,
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
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/rules/{$this->pos}", 'PUT', $parms);
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
         * @return mixed
         */
        public function getAliases()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/aliases", 'GET');
        }

        /**
         * Create IP or Network Alias.
         * @param $cidr Network/IP specification in CIDR format.
         * @param $name Alias name.
         * @param $comment
         */
        public function createAlias($cidr, $name, $comment = null)
        {
            $parms = ['cidr' => $cidr,
                'name' => $name,
                'comment' => $comment];
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/aliases", 'POST', $parms);
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
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         */
        public function removeAlias($digest = null)
        {
            $parms = ['digest' => $digest];
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/aliases/{$this->name}", 'DELETE', $parms);
        }

        /**
         * Read alias.
         * @return mixed
         */
        public function readAlias()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/aliases/{$this->name}", 'GET');
        }

        /**
         * Update IP or Network alias.
         * @param $cidr Network/IP specification in CIDR format.
         * @param $comment
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $rename Rename an existing alias.
         */
        public function updateAlias($cidr, $comment = null, $digest = null, $rename = null)
        {
            $parms = ['cidr' => $cidr,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/aliases/{$this->name}", 'PUT', $parms);
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
         * @return mixed
         */
        public function ipsetIndex()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset", 'GET');
        }

        /**
         * Create new IPSet
         * @param $name IP set name.
         * @param $comment
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $rename Rename an existing IPSet. You can set 'rename' to the same value as 'name' to update the 'comment' of an existing IPSet.
         */
        public function createIpset($name, $comment = null, $digest = null, $rename = null)
        {
            $parms = ['name' => $name,
                'comment' => $comment,
                'digest' => $digest,
                'rename' => $rename];
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset", 'POST', $parms);
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
         */
        public function deleteIpset()
        {
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset/{$this->name}", 'DELETE');
        }

        /**
         * List IPSet content
         * @return mixed
         */
        public function getIpset()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset/{$this->name}", 'GET');
        }

        /**
         * Add IP or Network to IPSet.
         * @param $cidr Network/IP specification in CIDR format.
         * @param $comment
         * @param $nomatch
         */
        public function createIp($cidr, $comment = null, $nomatch = null)
        {
            $parms = ['cidr' => $cidr,
                'comment' => $comment,
                'nomatch' => $nomatch];
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset/{$this->name}", 'POST', $parms);
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
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         */
        public function removeIp($digest = null)
        {
            $parms = ['digest' => $digest];
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset/{$this->name}/{$this->cidr}", 'DELETE', $parms);
        }

        /**
         * Read IP or Network settings from IPSet.
         * @return mixed
         */
        public function readIp()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset/{$this->name}/{$this->cidr}", 'GET');
        }

        /**
         * Update IP or Network settings
         * @param $comment
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $nomatch
         */
        public function updateIp($comment = null, $digest = null, $nomatch = null)
        {
            $parms = ['comment' => $comment,
                'digest' => $digest,
                'nomatch' => $nomatch];
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/ipset/{$this->name}/{$this->cidr}", 'PUT', $parms);
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
         * @return mixed
         */
        public function getOptions()
        {
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/options", 'GET');
        }

        /**
         * Set Firewall options.
         * @param $delete A list of settings you want to delete.
         * @param $dhcp Enable DHCP.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $enable Enable/disable firewall rules.
         * @param $ipfilter Enable default IP filters. This is equivalent to adding an empty ipfilter-net&amp;lt;id> ipset for every interface. Such ipsets implicitly contain sane default restrictions such as restricting IPv6 link local addresses to the one derived from the interface's MAC address. For containers the configured IP addresses will be implicitly added.
         * @param $log_level_in Log level for incoming traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param $log_level_out Log level for outgoing traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param $macfilter Enable/disable MAC address filter.
         * @param $ndp Enable NDP.
         * @param $policy_in Input policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param $policy_out Output policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param $radv Allow sending Router Advertisement.
         */
        public function setOptions($delete = null, $dhcp = null, $digest = null, $enable = null, $ipfilter = null, $log_level_in = null, $log_level_out = null, $macfilter = null, $ndp = null, $policy_in = null, $policy_out = null, $radv = null)
        {
            $parms = ['delete' => $delete,
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
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/options", 'PUT', $parms);
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
         * @param $limit
         * @param $start
         * @return mixed
         */
        public function log($limit = null, $start = null)
        {
            $parms = ['limit' => $limit,
                'start' => $start];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/log", 'GET', $parms);
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
         * @param $type Only list references of specified type.
         *   Enum: alias,ipset
         * @return mixed
         */
        public function refs($type = null)
        {
            $parms = ['type' => $type];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/firewall/refs", 'GET', $parms);
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
         * @param $ds The list of datasources you want to display.
         * @param $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return mixed
         */
        public function rrd($ds, $timeframe, $cf = null)
        {
            $parms = ['ds' => $ds,
                'timeframe' => $timeframe,
                'cf' => $cf];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/rrd", 'GET', $parms);
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
         * @param $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return mixed
         */
        public function rrddata($timeframe, $cf = null)
        {
            $parms = ['timeframe' => $timeframe,
                'cf' => $cf];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/rrddata", 'GET', $parms);
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
         * @param $height sets the height of the console in pixels.
         * @param $websocket use websocket instead of standard VNC.
         * @param $width sets the width of the console in pixels.
         * @return mixed
         */
        public function vncproxy($height = null, $websocket = null, $width = null)
        {
            $parms = ['height' => $height,
                'websocket' => $websocket,
                'width' => $width];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/vncproxy", 'POST', $parms);
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
         * @param $port Port number returned by previous vncproxy call.
         * @param $vncticket Ticket from previous call to vncproxy.
         * @return mixed
         */
        public function vncwebsocket($port, $vncticket)
        {
            $parms = ['port' => $port,
                'vncticket' => $vncticket];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/vncwebsocket", 'GET', $parms);
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
         * @param $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As resonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @return mixed
         */
        public function spiceproxy($proxy = null)
        {
            $parms = ['proxy' => $proxy];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/spiceproxy", 'POST', $parms);
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
         * @param $target Target node.
         * @param $force Force migration despite local bind / device mounts. NOTE: deprecated, use 'shared' property of mount point instead.
         * @param $online Use online/live migration.
         * @param $restart Use restart migration
         * @param $timeout Timeout in seconds for shutdown for restart migration
         * @return mixed
         */
        public function migrateVm($target, $force = null, $online = null, $restart = null, $timeout = null)
        {
            $parms = ['target' => $target,
                'force' => $force,
                'online' => $online,
                'restart' => $restart,
                'timeout' => $timeout];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/migrate", 'POST', $parms);
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
         * @param $feature Feature to check.
         *   Enum: snapshot
         * @param $snapname The name of the snapshot.
         * @return mixed
         */
        public function vmFeature($feature, $snapname = null)
        {
            $parms = ['feature' => $feature,
                'snapname' => $snapname];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/feature", 'GET', $parms);
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
         * @param $experimental The template feature is experimental, set this flag if you know what you are doing.
         */
        public function template($experimental)
        {
            $parms = ['experimental' => $experimental];
            $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/template", 'POST', $parms);
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
         * @param $experimental The clone feature is experimental, set this flag if you know what you are doing.
         * @param $newid VMID for the clone.
         * @param $description Description for the new CT.
         * @param $full Create a full copy of all disk. This is always done when you clone a normal CT. For CT templates, we try to create a linked clone by default.
         * @param $hostname Set a hostname for the new CT.
         * @param $pool Add the new CT to the specified pool.
         * @param $snapname The name of the snapshot.
         * @param $storage Target storage for full clone.
         * @return mixed
         */
        public function cloneVm($experimental, $newid, $description = null, $full = null, $hostname = null, $pool = null, $snapname = null, $storage = null)
        {
            $parms = ['experimental' => $experimental,
                'newid' => $newid,
                'description' => $description,
                'full' => $full,
                'hostname' => $hostname,
                'pool' => $pool,
                'snapname' => $snapname,
                'storage' => $storage];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/clone", 'POST', $parms);
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
         * @param $disk The disk you want to resize.
         *   Enum: rootfs,mp0,mp1,mp2,mp3,mp4,mp5,mp6,mp7,mp8,mp9
         * @param $size The new size. With the '+' sign the value is added to the actual size of the volume and without it, the value is taken as an absolute one. Shrinking disk size is not supported.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return mixed
         */
        public function resizeVm($disk, $size, $digest = null)
        {
            $parms = ['disk' => $disk,
                'size' => $size,
                'digest' => $digest];
            return $this->executeAction("/nodes/{$this->node}/lxc/{$this->vmid}/resize", 'PUT', $parms);
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

        /**
         * Directory index.
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/nodes/{$this->node}/ceph", 'GET');
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/nodes/{$this->node}/ceph/osd", 'GET');
        }

        /**
         * Create OSD
         * @param $dev Block device name.
         * @param $bluestore Use bluestore instead of filestore.
         * @param $fstype File system type (filestore only).
         *   Enum: xfs,ext4,btrfs
         * @param $journal_dev Block device name for journal.
         * @return mixed
         */
        public function createosd($dev, $bluestore = null, $fstype = null, $journal_dev = null)
        {
            $parms = ['dev' => $dev,
                'bluestore' => $bluestore,
                'fstype' => $fstype,
                'journal_dev' => $journal_dev];
            return $this->executeAction("/nodes/{$this->node}/ceph/osd", 'POST', $parms);
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
         * @param $cleanup If set, we remove partition table entries.
         * @return mixed
         */
        public function destroyosd($cleanup = null)
        {
            $parms = ['cleanup' => $cleanup];
            return $this->executeAction("/nodes/{$this->node}/ceph/osd/{$this->osdid}", 'DELETE', $parms);
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
         */
        public function in()
        {
            $this->executeAction("/nodes/{$this->node}/ceph/osd/{$this->osdid}/in", 'POST');
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
         */
        public function out()
        {
            $this->executeAction("/nodes/{$this->node}/ceph/osd/{$this->osdid}/out", 'POST');
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
         * @param $type Only list specific types of disks.
         *   Enum: unused,journal_disks
         * @return mixed
         */
        public function disks($type = null)
        {
            $parms = ['type' => $type];
            return $this->executeAction("/nodes/{$this->node}/ceph/disks", 'GET', $parms);
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
         * @return mixed
         */
        public function config()
        {
            return $this->executeAction("/nodes/{$this->node}/ceph/config", 'GET');
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
         * @return mixed
         */
        public function listmon()
        {
            return $this->executeAction("/nodes/{$this->node}/ceph/mon", 'GET');
        }

        /**
         * Create Ceph Monitor
         * @return mixed
         */
        public function createmon()
        {
            return $this->executeAction("/nodes/{$this->node}/ceph/mon", 'POST');
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
         * Destroy Ceph monitor.
         * @return mixed
         */
        public function destroymon()
        {
            return $this->executeAction("/nodes/{$this->node}/ceph/mon/{$this->monid}", 'DELETE');
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
         * @param $disable_cephx Disable cephx authentification.  WARNING: cephx is a security feature protecting against man-in-the-middle attacks. Only consider disabling cephx if your network is private!
         * @param $min_size Minimum number of available replicas per object to allow I/O
         * @param $network Use specific network for all ceph related traffic
         * @param $pg_bits Placement group bits, used to specify the default number of placement groups.  NOTE: 'osd pool default pg num' does not work for default pools.
         * @param $size Targeted number of replicas per object
         */
        public function init($disable_cephx = null, $min_size = null, $network = null, $pg_bits = null, $size = null)
        {
            $parms = ['disable_cephx' => $disable_cephx,
                'min_size' => $min_size,
                'network' => $network,
                'pg_bits' => $pg_bits,
                'size' => $size];
            $this->executeAction("/nodes/{$this->node}/ceph/init", 'POST', $parms);
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
         * @param $service Ceph service name.
         * @return mixed
         */
        public function stop($service = null)
        {
            $parms = ['service' => $service];
            return $this->executeAction("/nodes/{$this->node}/ceph/stop", 'POST', $parms);
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
         * @param $service Ceph service name.
         * @return mixed
         */
        public function start($service = null)
        {
            $parms = ['service' => $service];
            return $this->executeAction("/nodes/{$this->node}/ceph/start", 'POST', $parms);
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
         * @return mixed
         */
        public function status()
        {
            return $this->executeAction("/nodes/{$this->node}/ceph/status", 'GET');
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
         * @return mixed
         */
        public function lspools()
        {
            return $this->executeAction("/nodes/{$this->node}/ceph/pools", 'GET');
        }

        /**
         * Create POOL
         * @param $name The name of the pool. It must be unique.
         * @param $crush_ruleset The ruleset to use for mapping object placement in the cluster.
         * @param $min_size Minimum number of replicas per object
         * @param $pg_num Number of placement groups.
         * @param $size Number of replicas per object
         */
        public function createpool($name, $crush_ruleset = null, $min_size = null, $pg_num = null, $size = null)
        {
            $parms = ['name' => $name,
                'crush_ruleset' => $crush_ruleset,
                'min_size' => $min_size,
                'pg_num' => $pg_num,
                'size' => $size];
            $this->executeAction("/nodes/{$this->node}/ceph/pools", 'POST', $parms);
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
         * @param $force If true, destroys pool even if in use
         */
        public function destroypool($force = null)
        {
            $parms = ['force' => $force];
            $this->executeAction("/nodes/{$this->node}/ceph/pools/{$this->name}", 'DELETE', $parms);
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
         * @return mixed
         */
        public function getFlags()
        {
            return $this->executeAction("/nodes/{$this->node}/ceph/flags", 'GET');
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
         */
        public function unsetFlag()
        {
            $this->executeAction("/nodes/{$this->node}/ceph/flags/{$this->flag}", 'DELETE');
        }

        /**
         * Set a ceph flag
         */
        public function setFlag()
        {
            $this->executeAction("/nodes/{$this->node}/ceph/flags/{$this->flag}", 'POST');
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
         * @return mixed
         */
        public function crush()
        {
            return $this->executeAction("/nodes/{$this->node}/ceph/crush", 'GET');
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
         * @param $limit
         * @param $start
         * @return mixed
         */
        public function log($limit = null, $start = null)
        {
            $parms = ['limit' => $limit,
                'start' => $start];
            return $this->executeAction("/nodes/{$this->node}/ceph/log", 'GET', $parms);
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
         * @param $all Backup all known guest systems on this host.
         * @param $bwlimit Limit I/O bandwidth (KBytes per second).
         * @param $compress Compress dump file.
         *   Enum: 0,1,gzip,lzo
         * @param $dumpdir Store resulting files to specified directory.
         * @param $exclude Exclude specified guest systems (assumes --all)
         * @param $exclude_path Exclude certain files/directories (shell globs).
         * @param $ionice Set CFQ ionice priority.
         * @param $lockwait Maximal time to wait for the global lock (minutes).
         * @param $mailnotification Specify when to send an email
         *   Enum: always,failure
         * @param $mailto Comma-separated list of email addresses that should receive email notifications.
         * @param $maxfiles Maximal number of backup files per guest system.
         * @param $mode Backup mode.
         *   Enum: snapshot,suspend,stop
         * @param $pigz Use pigz instead of gzip when N>0. N=1 uses half of cores, N>1 uses N as thread count.
         * @param $quiet Be quiet.
         * @param $remove Remove old backup files if there are more than 'maxfiles' backup files.
         * @param $script Use specified hook script.
         * @param $size Unused, will be removed in a future release.
         * @param $stdexcludes Exclude temporary files and logs.
         * @param $stdout Write tar to stdout, not to a file.
         * @param $stop Stop runnig backup jobs on this host.
         * @param $stopwait Maximal time to wait until a guest system is stopped (minutes).
         * @param $storage Store resulting file to this storage.
         * @param $tmpdir Store temporary files to specified directory.
         * @param $vmid The ID of the guest system you want to backup.
         * @return mixed
         */
        public function vzdump($all = null, $bwlimit = null, $compress = null, $dumpdir = null, $exclude = null, $exclude_path = null, $ionice = null, $lockwait = null, $mailnotification = null, $mailto = null, $maxfiles = null, $mode = null, $pigz = null, $quiet = null, $remove = null, $script = null, $size = null, $stdexcludes = null, $stdout = null, $stop = null, $stopwait = null, $storage = null, $tmpdir = null, $vmid = null)
        {
            $parms = ['all' => $all,
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
            return $this->executeAction("/nodes/{$this->node}/vzdump", 'POST', $parms);
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
         * @param $volume Volume identifier
         * @return mixed
         */
        public function extractconfig($volume)
        {
            $parms = ['volume' => $volume];
            return $this->executeAction("/nodes/{$this->node}/vzdump/extractconfig", 'GET', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/nodes/{$this->node}/services", 'GET');
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
         * @return mixed
         */
        public function srvcmdidx()
        {
            return $this->executeAction("/nodes/{$this->node}/services/{$this->service}", 'GET');
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
         * @return mixed
         */
        public function serviceState()
        {
            return $this->executeAction("/nodes/{$this->node}/services/{$this->service}/state", 'GET');
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
         * @return mixed
         */
        public function serviceStart()
        {
            return $this->executeAction("/nodes/{$this->node}/services/{$this->service}/start", 'POST');
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
         * @return mixed
         */
        public function serviceStop()
        {
            return $this->executeAction("/nodes/{$this->node}/services/{$this->service}/stop", 'POST');
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
         * @return mixed
         */
        public function serviceRestart()
        {
            return $this->executeAction("/nodes/{$this->node}/services/{$this->service}/restart", 'POST');
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
         * @return mixed
         */
        public function serviceReload()
        {
            return $this->executeAction("/nodes/{$this->node}/services/{$this->service}/reload", 'POST');
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
         * @return mixed
         */
        public function get()
        {
            return $this->executeAction("/nodes/{$this->node}/subscription", 'GET');
        }

        /**
         * Update subscription info.
         * @param $force Always connect to server, even if we have up to date info inside local cache.
         */
        public function update($force = null)
        {
            $parms = ['force' => $force];
            $this->executeAction("/nodes/{$this->node}/subscription", 'POST', $parms);
        }

        /**
         * Set subscription key.
         * @param $key Proxmox VE subscription key
         */
        public function set($key)
        {
            $parms = ['key' => $key];
            $this->executeAction("/nodes/{$this->node}/subscription", 'PUT', $parms);
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
         */
        public function revertNetworkChanges()
        {
            $this->executeAction("/nodes/{$this->node}/network", 'DELETE');
        }

        /**
         * List available networks
         * @param $type Only list specific interface types.
         *   Enum: bridge,bond,eth,alias,vlan,OVSBridge,OVSBond,OVSPort,OVSIntPort,any_bridge
         * @return mixed
         */
        public function index($type = null)
        {
            $parms = ['type' => $type];
            return $this->executeAction("/nodes/{$this->node}/network", 'GET', $parms);
        }

        /**
         * Create network device configuration
         * @param $iface Network interface name.
         * @param $type Network interface type
         *   Enum: bridge,bond,eth,alias,vlan,OVSBridge,OVSBond,OVSPort,OVSIntPort,unknown
         * @param $address IP address.
         * @param $address6 IP address.
         * @param $autostart Automatically start interface on boot.
         * @param $bond_mode Bonding mode.
         *   Enum: balance-rr,active-backup,balance-xor,broadcast,802.3ad,balance-tlb,balance-alb,balance-slb,lacp-balance-slb,lacp-balance-tcp
         * @param $bond_xmit_hash_policy Selects the transmit hash policy to use for slave selection in balance-xor and 802.3ad modes.
         *   Enum: layer2,layer2+3,layer3+4
         * @param $bridge_ports Specify the iterfaces you want to add to your bridge.
         * @param $bridge_vlan_aware Enable bridge vlan support.
         * @param $comments Comments
         * @param $comments6 Comments
         * @param $gateway Default gateway address.
         * @param $gateway6 Default ipv6 gateway address.
         * @param $netmask Network mask.
         * @param $netmask6 Network mask.
         * @param $ovs_bonds Specify the interfaces used by the bonding device.
         * @param $ovs_bridge The OVS bridge associated with a OVS port. This is required when you create an OVS port.
         * @param $ovs_options OVS interface options.
         * @param $ovs_ports Specify the iterfaces you want to add to your bridge.
         * @param $ovs_tag Specify a VLan tag (used by OVSPort, OVSIntPort, OVSBond)
         * @param $slaves Specify the interfaces used by the bonding device.
         */
        public function createNetwork($iface, $type, $address = null, $address6 = null, $autostart = null, $bond_mode = null, $bond_xmit_hash_policy = null, $bridge_ports = null, $bridge_vlan_aware = null, $comments = null, $comments6 = null, $gateway = null, $gateway6 = null, $netmask = null, $netmask6 = null, $ovs_bonds = null, $ovs_bridge = null, $ovs_options = null, $ovs_ports = null, $ovs_tag = null, $slaves = null)
        {
            $parms = ['iface' => $iface,
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
            $this->executeAction("/nodes/{$this->node}/network", 'POST', $parms);
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
         */
        public function deleteNetwork()
        {
            $this->executeAction("/nodes/{$this->node}/network/{$this->iface}", 'DELETE');
        }

        /**
         * Read network device configuration
         * @return mixed
         */
        public function networkConfig()
        {
            return $this->executeAction("/nodes/{$this->node}/network/{$this->iface}", 'GET');
        }

        /**
         * Update network device configuration
         * @param $type Network interface type
         *   Enum: bridge,bond,eth,alias,vlan,OVSBridge,OVSBond,OVSPort,OVSIntPort,unknown
         * @param $address IP address.
         * @param $address6 IP address.
         * @param $autostart Automatically start interface on boot.
         * @param $bond_mode Bonding mode.
         *   Enum: balance-rr,active-backup,balance-xor,broadcast,802.3ad,balance-tlb,balance-alb,balance-slb,lacp-balance-slb,lacp-balance-tcp
         * @param $bond_xmit_hash_policy Selects the transmit hash policy to use for slave selection in balance-xor and 802.3ad modes.
         *   Enum: layer2,layer2+3,layer3+4
         * @param $bridge_ports Specify the iterfaces you want to add to your bridge.
         * @param $bridge_vlan_aware Enable bridge vlan support.
         * @param $comments Comments
         * @param $comments6 Comments
         * @param $delete A list of settings you want to delete.
         * @param $gateway Default gateway address.
         * @param $gateway6 Default ipv6 gateway address.
         * @param $netmask Network mask.
         * @param $netmask6 Network mask.
         * @param $ovs_bonds Specify the interfaces used by the bonding device.
         * @param $ovs_bridge The OVS bridge associated with a OVS port. This is required when you create an OVS port.
         * @param $ovs_options OVS interface options.
         * @param $ovs_ports Specify the iterfaces you want to add to your bridge.
         * @param $ovs_tag Specify a VLan tag (used by OVSPort, OVSIntPort, OVSBond)
         * @param $slaves Specify the interfaces used by the bonding device.
         */
        public function updateNetwork($type, $address = null, $address6 = null, $autostart = null, $bond_mode = null, $bond_xmit_hash_policy = null, $bridge_ports = null, $bridge_vlan_aware = null, $comments = null, $comments6 = null, $delete = null, $gateway = null, $gateway6 = null, $netmask = null, $netmask6 = null, $ovs_bonds = null, $ovs_bridge = null, $ovs_options = null, $ovs_ports = null, $ovs_tag = null, $slaves = null)
        {
            $parms = ['type' => $type,
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
            $this->executeAction("/nodes/{$this->node}/network/{$this->iface}", 'PUT', $parms);
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
         * @param $errors
         * @param $limit
         * @param $start
         * @param $userfilter
         * @param $vmid Only list tasks for this VM.
         * @return mixed
         */
        public function nodeTasks($errors = null, $limit = null, $start = null, $userfilter = null, $vmid = null)
        {
            $parms = ['errors' => $errors,
                'limit' => $limit,
                'start' => $start,
                'userfilter' => $userfilter,
                'vmid' => $vmid];
            return $this->executeAction("/nodes/{$this->node}/tasks", 'GET', $parms);
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
         */
        public function stopTask()
        {
            $this->executeAction("/nodes/{$this->node}/tasks/{$this->upid}", 'DELETE');
        }

        /**
         *
         * @return mixed
         */
        public function upidIndex()
        {
            return $this->executeAction("/nodes/{$this->node}/tasks/{$this->upid}", 'GET');
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
         * @param $limit
         * @param $start
         * @return mixed
         */
        public function readTaskLog($limit = null, $start = null)
        {
            $parms = ['limit' => $limit,
                'start' => $start];
            return $this->executeAction("/nodes/{$this->node}/tasks/{$this->upid}/log", 'GET', $parms);
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
         * @return mixed
         */
        public function readTaskStatus()
        {
            return $this->executeAction("/nodes/{$this->node}/tasks/{$this->upid}/status", 'GET');
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/nodes/{$this->node}/scan", 'GET');
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
         * @return mixed
         */
        public function zfsscan()
        {
            return $this->executeAction("/nodes/{$this->node}/scan/zfs", 'GET');
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
         * @param $server
         * @return mixed
         */
        public function nfsscan($server)
        {
            $parms = ['server' => $server];
            return $this->executeAction("/nodes/{$this->node}/scan/nfs", 'GET', $parms);
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
         * @param $server
         * @return mixed
         */
        public function glusterfsscan($server)
        {
            $parms = ['server' => $server];
            return $this->executeAction("/nodes/{$this->node}/scan/glusterfs", 'GET', $parms);
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
         * @param $portal
         * @return mixed
         */
        public function iscsiscan($portal)
        {
            $parms = ['portal' => $portal];
            return $this->executeAction("/nodes/{$this->node}/scan/iscsi", 'GET', $parms);
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
         * @return mixed
         */
        public function lvmscan()
        {
            return $this->executeAction("/nodes/{$this->node}/scan/lvm", 'GET');
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
         * @param $vg
         * @return mixed
         */
        public function lvmthinscan($vg)
        {
            $parms = ['vg' => $vg];
            return $this->executeAction("/nodes/{$this->node}/scan/lvmthin", 'GET', $parms);
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
         * @return mixed
         */
        public function usbscan()
        {
            return $this->executeAction("/nodes/{$this->node}/scan/usb", 'GET');
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
         * @param $content Only list stores which support this content type.
         * @param $enabled Only list stores which are enabled (not disabled in config).
         * @param $storage Only list status for  specified storage
         * @param $target If target is different to 'node', we only lists shared storages which content is accessible on this 'node' and the specified 'target' node.
         * @return mixed
         */
        public function index($content = null, $enabled = null, $storage = null, $target = null)
        {
            $parms = ['content' => $content,
                'enabled' => $enabled,
                'storage' => $storage,
                'target' => $target];
            return $this->executeAction("/nodes/{$this->node}/storage", 'GET', $parms);
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
         * @return mixed
         */
        public function diridx()
        {
            return $this->executeAction("/nodes/{$this->node}/storage/{$this->storage}", 'GET');
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
         * @param $content Only list content of this type.
         * @param $vmid Only list images for this VM
         * @return mixed
         */
        public function index($content = null, $vmid = null)
        {
            $parms = ['content' => $content,
                'vmid' => $vmid];
            return $this->executeAction("/nodes/{$this->node}/storage/{$this->storage}/content", 'GET', $parms);
        }

        /**
         * Allocate disk images.
         * @param $filename The name of the file to create.
         * @param $size Size in kilobyte (1024 bytes). Optional suffixes 'M' (megabyte, 1024K) and 'G' (gigabyte, 1024M)
         * @param $vmid Specify owner VM
         * @param $format
         *   Enum: raw,qcow2,subvol
         * @return mixed
         */
        public function create($filename, $size, $vmid, $format = null)
        {
            $parms = ['filename' => $filename,
                'size' => $size,
                'vmid' => $vmid,
                'format' => $format];
            return $this->executeAction("/nodes/{$this->node}/storage/{$this->storage}/content", 'POST', $parms);
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
         */
        public function delete()
        {
            $this->executeAction("/nodes/{$this->node}/storage/{$this->storage}/content/{$this->volume}", 'DELETE');
        }

        /**
         * Get volume attributes
         * @return mixed
         */
        public function info()
        {
            return $this->executeAction("/nodes/{$this->node}/storage/{$this->storage}/content/{$this->volume}", 'GET');
        }

        /**
         * Copy a volume. This is experimental code - do not use.
         * @param $target Target volume identifier
         * @param $target_node Target node. Default is local node.
         * @return mixed
         */
        public function copy($target, $target_node = null)
        {
            $parms = ['target' => $target,
                'target_node' => $target_node];
            return $this->executeAction("/nodes/{$this->node}/storage/{$this->storage}/content/{$this->volume}", 'POST', $parms);
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
         * @return mixed
         */
        public function readStatus()
        {
            return $this->executeAction("/nodes/{$this->node}/storage/{$this->storage}/status", 'GET');
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
         * @param $ds The list of datasources you want to display.
         * @param $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return mixed
         */
        public function rrd($ds, $timeframe, $cf = null)
        {
            $parms = ['ds' => $ds,
                'timeframe' => $timeframe,
                'cf' => $cf];
            return $this->executeAction("/nodes/{$this->node}/storage/{$this->storage}/rrd", 'GET', $parms);
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
         * @param $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return mixed
         */
        public function rrddata($timeframe, $cf = null)
        {
            $parms = ['timeframe' => $timeframe,
                'cf' => $cf];
            return $this->executeAction("/nodes/{$this->node}/storage/{$this->storage}/rrddata", 'GET', $parms);
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
         * @param $content Content type.
         * @param $filename The name of the file to create.
         * @param $tmpfilename The source file name. This parameter is usually set by the REST handler. You can only overwrite it when connecting to the trustet port on localhost.
         * @return mixed
         */
        public function upload($content, $filename, $tmpfilename = null)
        {
            $parms = ['content' => $content,
                'filename' => $filename,
                'tmpfilename' => $tmpfilename];
            return $this->executeAction("/nodes/{$this->node}/storage/{$this->storage}/upload", 'POST', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/nodes/{$this->node}/disks", 'GET');
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
         * @return mixed
         */
        public function list()
        {
            return $this->executeAction("/nodes/{$this->node}/disks/list", 'GET');
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
         * @param $disk Block device name
         * @param $healthonly If true returns only the health status
         * @return mixed
         */
        public function smart($disk, $healthonly = null)
        {
            $parms = ['disk' => $disk,
                'healthonly' => $healthonly];
            return $this->executeAction("/nodes/{$this->node}/disks/smart", 'GET', $parms);
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
         * @param $disk Block device name
         * @param $uuid UUID for the GPT table
         * @return mixed
         */
        public function initgpt($disk, $uuid = null)
        {
            $parms = ['disk' => $disk,
                'uuid' => $uuid];
            return $this->executeAction("/nodes/{$this->node}/disks/initgpt", 'POST', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/nodes/{$this->node}/apt", 'GET');
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
         * @return mixed
         */
        public function listUpdates()
        {
            return $this->executeAction("/nodes/{$this->node}/apt/update", 'GET');
        }

        /**
         * This is used to resynchronize the package index files from their sources (apt-get update).
         * @param $notify Send notification mail about new packages (to email address specified for user 'root@pam').
         * @param $quiet Only produces output suitable for logging, omitting progress indicators.
         * @return mixed
         */
        public function updateDatabase($notify = null, $quiet = null)
        {
            $parms = ['notify' => $notify,
                'quiet' => $quiet];
            return $this->executeAction("/nodes/{$this->node}/apt/update", 'POST', $parms);
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
         * @param $name Package name.
         * @param $version Package version.
         * @return mixed
         */
        public function changelog($name, $version = null)
        {
            $parms = ['name' => $name,
                'version' => $version];
            return $this->executeAction("/nodes/{$this->node}/apt/changelog", 'GET', $parms);
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
         * @return mixed
         */
        public function versions()
        {
            return $this->executeAction("/nodes/{$this->node}/apt/versions", 'GET');
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/nodes/{$this->node}/firewall", 'GET');
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
         * @return mixed
         */
        public function getRules()
        {
            return $this->executeAction("/nodes/{$this->node}/firewall/rules", 'GET');
        }

        /**
         * Create new rule.
         * @param $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param $type Rule type.
         *   Enum: in,out,group
         * @param $comment Descriptive comment.
         * @param $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $enable Flag to enable/disable a rule.
         * @param $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param $macro Use predefined standard macro.
         * @param $pos Update rule at position &amp;lt;pos>.
         * @param $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         */
        public function createRule($action, $type, $comment = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $pos = null, $proto = null, $source = null, $sport = null)
        {
            $parms = ['action' => $action,
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
            $this->executeAction("/nodes/{$this->node}/firewall/rules", 'POST', $parms);
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
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         */
        public function deleteRule($digest = null)
        {
            $parms = ['digest' => $digest];
            $this->executeAction("/nodes/{$this->node}/firewall/rules/{$this->pos}", 'DELETE', $parms);
        }

        /**
         * Get single rule data.
         * @return mixed
         */
        public function getRule()
        {
            return $this->executeAction("/nodes/{$this->node}/firewall/rules/{$this->pos}", 'GET');
        }

        /**
         * Modify rule data.
         * @param $action Rule action ('ACCEPT', 'DROP', 'REJECT') or security group name.
         * @param $comment Descriptive comment.
         * @param $delete A list of settings you want to delete.
         * @param $dest Restrict packet destination address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $dport Restrict TCP/UDP destination port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $enable Flag to enable/disable a rule.
         * @param $iface Network interface name. You have to use network configuration key names for VMs and containers ('net\d+'). Host related rules can use arbitrary strings.
         * @param $macro Use predefined standard macro.
         * @param $moveto Move rule to new position &amp;lt;moveto>. Other arguments are ignored.
         * @param $proto IP protocol. You can use protocol names ('tcp'/'udp') or simple numbers, as defined in '/etc/protocols'.
         * @param $source Restrict packet source address. This can refer to a single IP address, an IP set ('+ipsetname') or an IP alias definition. You can also specify an address range like '20.34.101.207-201.3.9.99', or a list of IP addresses and networks (entries are separated by comma). Please do not mix IPv4 and IPv6 addresses inside such lists.
         * @param $sport Restrict TCP/UDP source port. You can use service names or simple numbers (0-65535), as defined in '/etc/services'. Port ranges can be specified with '\d+:\d+', for example '80:85', and you can use comma separated list to match several ports or ranges.
         * @param $type Rule type.
         *   Enum: in,out,group
         */
        public function updateRule($action = null, $comment = null, $delete = null, $dest = null, $digest = null, $dport = null, $enable = null, $iface = null, $macro = null, $moveto = null, $proto = null, $source = null, $sport = null, $type = null)
        {
            $parms = ['action' => $action,
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
            $this->executeAction("/nodes/{$this->node}/firewall/rules/{$this->pos}", 'PUT', $parms);
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
         * @return mixed
         */
        public function getOptions()
        {
            return $this->executeAction("/nodes/{$this->node}/firewall/options", 'GET');
        }

        /**
         * Set Firewall options.
         * @param $delete A list of settings you want to delete.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $enable Enable host firewall rules.
         * @param $log_level_in Log level for incoming traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param $log_level_out Log level for outgoing traffic.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param $ndp Enable NDP.
         * @param $nf_conntrack_max Maximum number of tracked connections.
         * @param $nf_conntrack_tcp_timeout_established Conntrack established timeout.
         * @param $nosmurfs Enable SMURFS filter.
         * @param $smurf_log_level Log level for SMURFS filter.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param $tcp_flags_log_level Log level for illegal tcp flags filter.
         *   Enum: emerg,alert,crit,err,warning,notice,info,debug,nolog
         * @param $tcpflags Filter illegal combinations of TCP flags.
         */
        public function setOptions($delete = null, $digest = null, $enable = null, $log_level_in = null, $log_level_out = null, $ndp = null, $nf_conntrack_max = null, $nf_conntrack_tcp_timeout_established = null, $nosmurfs = null, $smurf_log_level = null, $tcp_flags_log_level = null, $tcpflags = null)
        {
            $parms = ['delete' => $delete,
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
            $this->executeAction("/nodes/{$this->node}/firewall/options", 'PUT', $parms);
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
         * @param $limit
         * @param $start
         * @return mixed
         */
        public function log($limit = null, $start = null)
        {
            $parms = ['limit' => $limit,
                'start' => $start];
            return $this->executeAction("/nodes/{$this->node}/firewall/log", 'GET', $parms);
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
         * @param $guest Only list replication jobs for this guest.
         * @return mixed
         */
        public function status($guest = null)
        {
            $parms = ['guest' => $guest];
            return $this->executeAction("/nodes/{$this->node}/replication", 'GET', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/nodes/{$this->node}/replication/{$this->id}", 'GET');
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
         * @return mixed
         */
        public function jobStatus()
        {
            return $this->executeAction("/nodes/{$this->node}/replication/{$this->id}/status", 'GET');
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
         * @param $limit
         * @param $start
         * @return mixed
         */
        public function readJobLog($limit = null, $start = null)
        {
            $parms = ['limit' => $limit,
                'start' => $start];
            return $this->executeAction("/nodes/{$this->node}/replication/{$this->id}/log", 'GET', $parms);
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
         * @return mixed
         */
        public function scheduleNow()
        {
            return $this->executeAction("/nodes/{$this->node}/replication/{$this->id}/schedule_now", 'POST');
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
         * @return mixed
         */
        public function version()
        {
            return $this->executeAction("/nodes/{$this->node}/version", 'GET');
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
         * @return mixed
         */
        public function status()
        {
            return $this->executeAction("/nodes/{$this->node}/status", 'GET');
        }

        /**
         * Reboot or shutdown a node.
         * @param $command Specify the command.
         *   Enum: reboot,shutdown
         */
        public function nodeCmd($command)
        {
            $parms = ['command' => $command];
            $this->executeAction("/nodes/{$this->node}/status", 'POST', $parms);
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
         * @return mixed
         */
        public function netstat()
        {
            return $this->executeAction("/nodes/{$this->node}/netstat", 'GET');
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
         * @param $commands JSON encoded array of commands.
         * @return mixed
         */
        public function execute($commands)
        {
            $parms = ['commands' => $commands];
            return $this->executeAction("/nodes/{$this->node}/execute", 'POST', $parms);
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
         * @param $ds The list of datasources you want to display.
         * @param $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return mixed
         */
        public function rrd($ds, $timeframe, $cf = null)
        {
            $parms = ['ds' => $ds,
                'timeframe' => $timeframe,
                'cf' => $cf];
            return $this->executeAction("/nodes/{$this->node}/rrd", 'GET', $parms);
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
         * @param $timeframe Specify the time frame you are interested in.
         *   Enum: hour,day,week,month,year
         * @param $cf The RRD consolidation function
         *   Enum: AVERAGE,MAX
         * @return mixed
         */
        public function rrddata($timeframe, $cf = null)
        {
            $parms = ['timeframe' => $timeframe,
                'cf' => $cf];
            return $this->executeAction("/nodes/{$this->node}/rrddata", 'GET', $parms);
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
         * @param $limit
         * @param $since Display all log since this date-time string.
         * @param $start
         * @param $until Display all log until this date-time string.
         * @return mixed
         */
        public function syslog($limit = null, $since = null, $start = null, $until = null)
        {
            $parms = ['limit' => $limit,
                'since' => $since,
                'start' => $start,
                'until' => $until];
            return $this->executeAction("/nodes/{$this->node}/syslog", 'GET', $parms);
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
         * @param $height sets the height of the console in pixels.
         * @param $upgrade Run 'apt-get dist-upgrade' instead of normal shell.
         * @param $websocket use websocket instead of standard vnc.
         * @param $width sets the width of the console in pixels.
         * @return mixed
         */
        public function vncshell($height = null, $upgrade = null, $websocket = null, $width = null)
        {
            $parms = ['height' => $height,
                'upgrade' => $upgrade,
                'websocket' => $websocket,
                'width' => $width];
            return $this->executeAction("/nodes/{$this->node}/vncshell", 'POST', $parms);
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
         * @param $port Port number returned by previous vncproxy call.
         * @param $vncticket Ticket from previous call to vncproxy.
         * @return mixed
         */
        public function vncwebsocket($port, $vncticket)
        {
            $parms = ['port' => $port,
                'vncticket' => $vncticket];
            return $this->executeAction("/nodes/{$this->node}/vncwebsocket", 'GET', $parms);
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
         * @param $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As resonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @param $upgrade Run 'apt-get dist-upgrade' instead of normal shell.
         * @return mixed
         */
        public function spiceshell($proxy = null, $upgrade = null)
        {
            $parms = ['proxy' => $proxy,
                'upgrade' => $upgrade];
            return $this->executeAction("/nodes/{$this->node}/spiceshell", 'POST', $parms);
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
         * @return mixed
         */
        public function dns()
        {
            return $this->executeAction("/nodes/{$this->node}/dns", 'GET');
        }

        /**
         * Write DNS settings.
         * @param $search Search domain for host-name lookup.
         * @param $dns1 First name server IP address.
         * @param $dns2 Second name server IP address.
         * @param $dns3 Third name server IP address.
         */
        public function updateDns($search, $dns1 = null, $dns2 = null, $dns3 = null)
        {
            $parms = ['search' => $search,
                'dns1' => $dns1,
                'dns2' => $dns2,
                'dns3' => $dns3];
            $this->executeAction("/nodes/{$this->node}/dns", 'PUT', $parms);
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
         * @return mixed
         */
        public function time()
        {
            return $this->executeAction("/nodes/{$this->node}/time", 'GET');
        }

        /**
         * Set time zone.
         * @param $timezone Time zone. The file '/usr/share/zoneinfo/zone.tab' contains the list of valid names.
         */
        public function setTimezone($timezone)
        {
            $parms = ['timezone' => $timezone];
            $this->executeAction("/nodes/{$this->node}/time", 'PUT', $parms);
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
         * @return mixed
         */
        public function aplinfo()
        {
            return $this->executeAction("/nodes/{$this->node}/aplinfo", 'GET');
        }

        /**
         * Download appliance templates.
         * @param $storage The storage where the template will be stored
         * @param $template The template wich will downloaded
         * @return mixed
         */
        public function aplDownload($storage, $template)
        {
            $parms = ['storage' => $storage,
                'template' => $template];
            return $this->executeAction("/nodes/{$this->node}/aplinfo", 'POST', $parms);
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
         * @return mixed
         */
        public function report()
        {
            return $this->executeAction("/nodes/{$this->node}/report", 'GET');
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
         * @param $force force if onboot=0.
         * @param $vms Only consider Guests with these IDs.
         * @return mixed
         */
        public function startall($force = null, $vms = null)
        {
            $parms = ['force' => $force,
                'vms' => $vms];
            return $this->executeAction("/nodes/{$this->node}/startall", 'POST', $parms);
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
         * @param $vms Only consider Guests with these IDs.
         * @return mixed
         */
        public function stopall($vms = null)
        {
            $parms = ['vms' => $vms];
            return $this->executeAction("/nodes/{$this->node}/stopall", 'POST', $parms);
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
         * @param $target Target node.
         * @param $maxworkers Maximal number of parallel migration job. If not set use 'max_workers' from datacenter.cfg, one of both must be set!
         * @param $vms Only consider Guests with these IDs.
         * @return mixed
         */
        public function migrateall($target, $maxworkers = null, $vms = null)
        {
            $parms = ['target' => $target,
                'maxworkers' => $maxworkers,
                'vms' => $vms];
            return $this->executeAction("/nodes/{$this->node}/migrateall", 'POST', $parms);
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
         * @param $type Only list storage of specific type
         *   Enum: dir,drbd,glusterfs,iscsi,iscsidirect,lvm,lvmthin,nfs,rbd,sheepdog,zfs,zfspool
         * @return mixed
         */
        public function index($type = null)
        {
            $parms = ['type' => $type];
            return $this->executeAction("/storage", 'GET', $parms);
        }

        /**
         * Create a new storage.
         * @param $storage The storage identifier.
         * @param $type Storage type.
         *   Enum: dir,drbd,glusterfs,iscsi,iscsidirect,lvm,lvmthin,nfs,rbd,sheepdog,zfs,zfspool
         * @param $authsupported Authsupported.
         * @param $base Base volume. This volume is automatically activated.
         * @param $blocksize block size
         * @param $comstar_hg host group for comstar views
         * @param $comstar_tg target group for comstar views
         * @param $content Allowed content types.  NOTE: the value 'rootdir' is used for Containers, and value 'images' for VMs.
         * @param $disable Flag to disable the storage.
         * @param $export NFS export path.
         * @param $format Default image format.
         * @param $is_mountpoint Assume the directory is an externally managed mountpoint. If nothing is mounted the storage will be considered offline.
         * @param $iscsiprovider iscsi provider
         * @param $krbd Access rbd through krbd kernel module.
         * @param $maxfiles Maximal number of backup files per VM. Use '0' for unlimted.
         * @param $mkdir Create the directory if it doesn't exist.
         * @param $monhost Monitors daemon ips.
         * @param $nodes List of cluster node names.
         * @param $nowritecache disable write caching on the target
         * @param $options NFS mount options (see 'man nfs')
         * @param $path File system path.
         * @param $pool Pool.
         * @param $portal iSCSI portal (IP or DNS name with optional port).
         * @param $redundancy The redundancy count specifies the number of nodes to which the resource should be deployed. It must be at least 1 and at most the number of nodes in the cluster.
         * @param $saferemove Zero-out data when removing LVs.
         * @param $saferemove_throughput Wipe throughput (cstream -t parameter value).
         * @param $server Server IP or DNS name.
         * @param $server2 Backup volfile server IP or DNS name.
         * @param $shared Mark storage as shared.
         * @param $sparse use sparse volumes
         * @param $tagged_only Only use logical volumes tagged with 'pve-vm-ID'.
         * @param $target iSCSI target.
         * @param $thinpool LVM thin pool LV name.
         * @param $transport Gluster transport: tcp or rdma
         *   Enum: tcp,rdma,unix
         * @param $username RBD Id.
         * @param $vgname Volume group name.
         * @param $volume Glusterfs Volume.
         */
        public function create($storage, $type, $authsupported = null, $base = null, $blocksize = null, $comstar_hg = null, $comstar_tg = null, $content = null, $disable = null, $export = null, $format = null, $is_mountpoint = null, $iscsiprovider = null, $krbd = null, $maxfiles = null, $mkdir = null, $monhost = null, $nodes = null, $nowritecache = null, $options = null, $path = null, $pool = null, $portal = null, $redundancy = null, $saferemove = null, $saferemove_throughput = null, $server = null, $server2 = null, $shared = null, $sparse = null, $tagged_only = null, $target = null, $thinpool = null, $transport = null, $username = null, $vgname = null, $volume = null)
        {
            $parms = ['storage' => $storage,
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
            $this->executeAction("/storage", 'POST', $parms);
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
         */
        public function delete()
        {
            $this->executeAction("/storage/{$this->storage}", 'DELETE');
        }

        /**
         * Read storage configuration.
         * @return mixed
         */
        public function read()
        {
            return $this->executeAction("/storage/{$this->storage}", 'GET');
        }

        /**
         * Update storage configuration.
         * @param $blocksize block size
         * @param $comstar_hg host group for comstar views
         * @param $comstar_tg target group for comstar views
         * @param $content Allowed content types.  NOTE: the value 'rootdir' is used for Containers, and value 'images' for VMs.
         * @param $delete A list of settings you want to delete.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $disable Flag to disable the storage.
         * @param $format Default image format.
         * @param $is_mountpoint Assume the directory is an externally managed mountpoint. If nothing is mounted the storage will be considered offline.
         * @param $krbd Access rbd through krbd kernel module.
         * @param $maxfiles Maximal number of backup files per VM. Use '0' for unlimted.
         * @param $mkdir Create the directory if it doesn't exist.
         * @param $nodes List of cluster node names.
         * @param $nowritecache disable write caching on the target
         * @param $options NFS mount options (see 'man nfs')
         * @param $pool Pool.
         * @param $redundancy The redundancy count specifies the number of nodes to which the resource should be deployed. It must be at least 1 and at most the number of nodes in the cluster.
         * @param $saferemove Zero-out data when removing LVs.
         * @param $saferemove_throughput Wipe throughput (cstream -t parameter value).
         * @param $server Server IP or DNS name.
         * @param $server2 Backup volfile server IP or DNS name.
         * @param $shared Mark storage as shared.
         * @param $sparse use sparse volumes
         * @param $tagged_only Only use logical volumes tagged with 'pve-vm-ID'.
         * @param $transport Gluster transport: tcp or rdma
         *   Enum: tcp,rdma,unix
         * @param $username RBD Id.
         */
        public function update($blocksize = null, $comstar_hg = null, $comstar_tg = null, $content = null, $delete = null, $digest = null, $disable = null, $format = null, $is_mountpoint = null, $krbd = null, $maxfiles = null, $mkdir = null, $nodes = null, $nowritecache = null, $options = null, $pool = null, $redundancy = null, $saferemove = null, $saferemove_throughput = null, $server = null, $server2 = null, $shared = null, $sparse = null, $tagged_only = null, $transport = null, $username = null)
        {
            $parms = ['blocksize' => $blocksize,
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
            $this->executeAction("/storage/{$this->storage}", 'PUT', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/access", 'GET');
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
         * @param $enabled Optional filter for enable property.
         * @return mixed
         */
        public function index($enabled = null)
        {
            $parms = ['enabled' => $enabled];
            return $this->executeAction("/access/users", 'GET', $parms);
        }

        /**
         * Create new user.
         * @param $userid User ID
         * @param $comment
         * @param $email
         * @param $enable Enable the account (default). You can set this to '0' to disable the accout
         * @param $expire Account expiration date (seconds since epoch). '0' means no expiration date.
         * @param $firstname
         * @param $groups
         * @param $keys Keys for two factor auth (yubico).
         * @param $lastname
         * @param $password Initial password.
         */
        public function createUser($userid, $comment = null, $email = null, $enable = null, $expire = null, $firstname = null, $groups = null, $keys = null, $lastname = null, $password = null)
        {
            $parms = ['userid' => $userid,
                'comment' => $comment,
                'email' => $email,
                'enable' => $enable,
                'expire' => $expire,
                'firstname' => $firstname,
                'groups' => $groups,
                'keys' => $keys,
                'lastname' => $lastname,
                'password' => $password];
            $this->executeAction("/access/users", 'POST', $parms);
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
         */
        public function deleteUser()
        {
            $this->executeAction("/access/users/{$this->userid}", 'DELETE');
        }

        /**
         * Get user configuration.
         * @return mixed
         */
        public function readUser()
        {
            return $this->executeAction("/access/users/{$this->userid}", 'GET');
        }

        /**
         * Update user configuration.
         * @param $append
         * @param $comment
         * @param $email
         * @param $enable Enable/disable the account.
         * @param $expire Account expiration date (seconds since epoch). '0' means no expiration date.
         * @param $firstname
         * @param $groups
         * @param $keys Keys for two factor auth (yubico).
         * @param $lastname
         */
        public function updateUser($append = null, $comment = null, $email = null, $enable = null, $expire = null, $firstname = null, $groups = null, $keys = null, $lastname = null)
        {
            $parms = ['append' => $append,
                'comment' => $comment,
                'email' => $email,
                'enable' => $enable,
                'expire' => $expire,
                'firstname' => $firstname,
                'groups' => $groups,
                'keys' => $keys,
                'lastname' => $lastname];
            $this->executeAction("/access/users/{$this->userid}", 'PUT', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/access/groups", 'GET');
        }

        /**
         * Create new group.
         * @param $groupid
         * @param $comment
         */
        public function createGroup($groupid, $comment = null)
        {
            $parms = ['groupid' => $groupid,
                'comment' => $comment];
            $this->executeAction("/access/groups", 'POST', $parms);
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
         */
        public function deleteGroup()
        {
            $this->executeAction("/access/groups/{$this->groupid}", 'DELETE');
        }

        /**
         * Get group configuration.
         * @return mixed
         */
        public function readGroup()
        {
            return $this->executeAction("/access/groups/{$this->groupid}", 'GET');
        }

        /**
         * Update group data.
         * @param $comment
         */
        public function updateGroup($comment = null)
        {
            $parms = ['comment' => $comment];
            $this->executeAction("/access/groups/{$this->groupid}", 'PUT', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/access/roles", 'GET');
        }

        /**
         * Create new role.
         * @param $roleid
         * @param $privs
         */
        public function createRole($roleid, $privs = null)
        {
            $parms = ['roleid' => $roleid,
                'privs' => $privs];
            $this->executeAction("/access/roles", 'POST', $parms);
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
         */
        public function deleteRole()
        {
            $this->executeAction("/access/roles/{$this->roleid}", 'DELETE');
        }

        /**
         * Get role configuration.
         * @return mixed
         */
        public function readRole()
        {
            return $this->executeAction("/access/roles/{$this->roleid}", 'GET');
        }

        /**
         * Create new role.
         * @param $privs
         * @param $append
         */
        public function updateRole($privs, $append = null)
        {
            $parms = ['privs' => $privs,
                'append' => $append];
            $this->executeAction("/access/roles/{$this->roleid}", 'PUT', $parms);
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
         * @return mixed
         */
        public function readAcl()
        {
            return $this->executeAction("/access/acl", 'GET');
        }

        /**
         * Update Access Control List (add or remove permissions).
         * @param $path Access control path
         * @param $roles List of roles.
         * @param $delete Remove permissions (instead of adding it).
         * @param $groups List of groups.
         * @param $propagate Allow to propagate (inherit) permissions.
         * @param $users List of users.
         */
        public function updateAcl($path, $roles, $delete = null, $groups = null, $propagate = null, $users = null)
        {
            $parms = ['path' => $path,
                'roles' => $roles,
                'delete' => $delete,
                'groups' => $groups,
                'propagate' => $propagate,
                'users' => $users];
            $this->executeAction("/access/acl", 'PUT', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/access/domains", 'GET');
        }

        /**
         * Add an authentication server.
         * @param $realm Authentication domain ID
         * @param $type Realm type.
         *   Enum: ad,ldap,pam,pve
         * @param $base_dn LDAP base domain name
         * @param $bind_dn LDAP bind domain name
         * @param $comment Description.
         * @param $default Use this as default realm
         * @param $domain AD domain name
         * @param $port Server port.
         * @param $secure Use secure LDAPS protocol.
         * @param $server1 Server IP address (or DNS name)
         * @param $server2 Fallback Server IP address (or DNS name)
         * @param $tfa Use Two-factor authentication.
         * @param $user_attr LDAP user attribute name
         */
        public function create($realm, $type, $base_dn = null, $bind_dn = null, $comment = null, $default = null, $domain = null, $port = null, $secure = null, $server1 = null, $server2 = null, $tfa = null, $user_attr = null)
        {
            $parms = ['realm' => $realm,
                'type' => $type,
                'base_dn' => $base_dn,
                'bind_dn' => $bind_dn,
                'comment' => $comment,
                'default' => $default,
                'domain' => $domain,
                'port' => $port,
                'secure' => $secure,
                'server1' => $server1,
                'server2' => $server2,
                'tfa' => $tfa,
                'user_attr' => $user_attr];
            $this->executeAction("/access/domains", 'POST', $parms);
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
         */
        public function delete()
        {
            $this->executeAction("/access/domains/{$this->realm}", 'DELETE');
        }

        /**
         * Get auth server configuration.
         * @return mixed
         */
        public function read()
        {
            return $this->executeAction("/access/domains/{$this->realm}", 'GET');
        }

        /**
         * Update authentication server settings.
         * @param $base_dn LDAP base domain name
         * @param $bind_dn LDAP bind domain name
         * @param $comment Description.
         * @param $default Use this as default realm
         * @param $delete A list of settings you want to delete.
         * @param $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param $domain AD domain name
         * @param $port Server port.
         * @param $secure Use secure LDAPS protocol.
         * @param $server1 Server IP address (or DNS name)
         * @param $server2 Fallback Server IP address (or DNS name)
         * @param $tfa Use Two-factor authentication.
         * @param $user_attr LDAP user attribute name
         */
        public function update($base_dn = null, $bind_dn = null, $comment = null, $default = null, $delete = null, $digest = null, $domain = null, $port = null, $secure = null, $server1 = null, $server2 = null, $tfa = null, $user_attr = null)
        {
            $parms = ['base_dn' => $base_dn,
                'bind_dn' => $bind_dn,
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
                'user_attr' => $user_attr];
            $this->executeAction("/access/domains/{$this->realm}", 'PUT', $parms);
        }
    }

    class PVEAccessTicket extends Base
    {
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Dummy. Useful for formaters which want to priovde a login page.
         */
        public function getTicket()
        {
            $this->executeAction("/access/ticket", 'GET');
        }

        /**
         * Create or verify authentication ticket.
         * @param $password The secret password. This can also be a valid ticket.
         * @param $username User name
         * @param $otp One-time password for Two-factor authentication.
         * @param $path Verify ticket, and check if user have access 'privs' on 'path'
         * @param $privs Verify ticket, and check if user have access 'privs' on 'path'
         * @param $realm You can optionally pass the realm using this parameter. Normally the realm is simply added to the username &amp;lt;username>@&amp;lt;relam>.
         * @return mixed
         */
        public function createTicket($password, $username, $otp = null, $path = null, $privs = null, $realm = null)
        {
            $parms = ['password' => $password,
                'username' => $username,
                'otp' => $otp,
                'path' => $path,
                'privs' => $privs,
                'realm' => $realm];
            return $this->executeAction("/access/ticket", 'POST', $parms);
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
         * @param $password The new password.
         * @param $userid User ID
         */
        public function changePasssword($password, $userid)
        {
            $parms = ['password' => $password,
                'userid' => $userid];
            $this->executeAction("/access/password", 'PUT', $parms);
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
         * @return mixed
         */
        public function index()
        {
            return $this->executeAction("/pools", 'GET');
        }

        /**
         * Create new pool.
         * @param $poolid
         * @param $comment
         */
        public function createPool($poolid, $comment = null)
        {
            $parms = ['poolid' => $poolid,
                'comment' => $comment];
            $this->executeAction("/pools", 'POST', $parms);
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
         */
        public function deletePool()
        {
            $this->executeAction("/pools/{$this->poolid}", 'DELETE');
        }

        /**
         * Get pool configuration.
         * @return mixed
         */
        public function readPool()
        {
            return $this->executeAction("/pools/{$this->poolid}", 'GET');
        }

        /**
         * Update pool data.
         * @param $comment
         * @param $delete Remove vms/storage (instead of adding it).
         * @param $storage List of storage IDs.
         * @param $vms List of virtual machines.
         */
        public function updatePool($comment = null, $delete = null, $storage = null, $vms = null)
        {
            $parms = ['comment' => $comment,
                'delete' => $delete,
                'storage' => $storage,
                'vms' => $vms];
            $this->executeAction("/pools/{$this->poolid}", 'PUT', $parms);
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
         * @return mixed
         */
        public function version()
        {
            return $this->executeAction("/version", 'GET');
        }
    }
}