<?php
/**
 * Proxmox VE Client Api
 */

namespace EnterpriseVE\ProxmoxVE\Api {
    /**
     * Class Base
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    abstract class Base
    {
        /**
         * @ignore
         */
        protected $client;

        /**
         * Client
         * @return Client
         */
        protected function getClient()
        {
            return $this->client;
        }

        /**
         * Add index parameter to parameters
         * @param array $params Parameters
         * @param string $name name
         * @param array $values values
         */
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
        function __construct($response, $statusCode, $reasonPhrase, $resultIsObject)
        {
            $this->statusCode = $statusCode;
            $this->reasonPhrase = $reasonPhrase;
            $this->response = $response;
            $this->resultIsObject = $resultIsObject;
        }

        /**
         * Proxmox VE response.
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
     * Proxmox VE Client
     */
    class Client extends Base
    {
        /**
         * @ignore
         */
        private $ticketCSRFPreventionToken;
        /**
         * @ignore
         */
        private $ticketPVEAuthCookie;
        /**
         * @ignore
         */
        private $hostname;
        /**
         * @ignore
         */
        private $port;
        /**
         * @ignore
         */
        private $resultIsObject = true;
        /**
         * @ignore
         */
        private $responseType = 'json';

        /**
         * @ignore
         */
        function __construct($hostname, $port = 8006)
        {
            $this->hostname = $hostname;
            $this->port = $port;
            $this->client = $this;
        }

        /**
         * Return if result is object
         * @return bool
         */
        function getResultIsObject()
        {
            return $this->resultIsObject;
        }

        /**
         * Set result is object
         * @param bool $resultIsObject
         */
        function setResultIsObject($resultIsObject)
        {
            $this->resultIsObject = $resultIsObject;
        }

        /**
         * Gets the hostname configured.
         *
         * @return string The hostname.
         */
        public function getHostname()
        {
            return $this->hostname;
        }

        /**
         * Gets the port configured.
         *
         * @return int The port.
         */
        public function getPort()
        {
            return $this->port;
        }

        /**
         * Sets the response type that is going to be returned when doing requests.
         *
         * @param string One of json, png.
         */
        public function setResponseType($type = 'json')
        {
            $this->responseType = $type;
        }

        /**
         * Returns the response type that is being used by the Proxmox API client.
         *
         * @return string Response type being used.
         */
        public function getResponseType()
        {
            return $this->responseType;
        }

        /**
         * Returns the base URL used to interact with the Proxmox VE API.
         *
         * @return string The proxmox API URL.
         */
        public function getApiUrl()
        {
            return "https://{$this->getHostname()}:{$this->getPort()}/api2/{$this->responseType}";
        }

        /**
         * Creation ticket from login.
         * @param string $userName user name or &lt;username&gt;@&lt;realm&gt;
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

        /**
         * Execute method GET
         * @param string $resource Url request
         * @param array $params Additional parameters
         * @return Result
         */
        public function get($resource, $params = [])
        {
            return $this->executeAction($resource, 'GET', $params);
        }

        /**
         * Execute method PUT
         * @param string $resource Url request
         * @param array $params Additional parameters
         * @return Result
         */
        public function set($resource, $params = [])
        {
            return $this->executeAction($resource, 'PUT', $params);
        }

        /**
         * Execute method POST
         * @param string $resource Url request
         * @param array $params Additional parameters
         * @return Result
         */
        public function create($resource, $params = [])
        {
            return $this->executeAction($resource, 'POST', $params);
        }

        /**
         * Execute method DELETE
         * @param string $resource Url request
         * @param array $params Additional parameters
         * @return Result
         */
        public function delete($resource, $params = [])
        {
            return $this->executeAction($resource, 'DELETE', $params);
        }

        /**
         * @ignore
         */
        private function executeAction($resource, $method, $params = [])
        {
            $response = $this->requestResource($resource, $method, $params);
            $obj = null;
            switch ($this->responseType) {
                case 'json':
                    $obj = $response->json(['object' => $this->resultIsObject]);
                    break;
                case 'png':
                    $obj = 'data:image/png;base64,' . base64_encode($response->getBody());
                    break;
            }
            return new Result($obj,
                $response->getStatusCode(),
                $response->getReasonPhrase(),
                $this->resultIsObject);
        }

        /**
         * @ignore
         */
        private function requestResource($resource, $method, $params = [])
        {
            //url resource
            $url = "{$this->getApiUrl()}{$resource}";
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

        /**
         * @ignore
         */
        private $cluster;

        /**
         * Get Cluster
         * @return PVECluster
         */
        public function getCluster()
        {
            return $this->cluster ?: ($this->cluster = new PVECluster($this->client));
        }

        /**
         * @ignore
         */
        private $nodes;

        /**
         * Get Nodes
         * @return PVENodes
         */
        public function getNodes()
        {
            return $this->nodes ?: ($this->nodes = new PVENodes($this->client));
        }

        /**
         * @ignore
         */
        private $storage;

        /**
         * Get Storage
         * @return PVEStorage
         */
        public function getStorage()
        {
            return $this->storage ?: ($this->storage = new PVEStorage($this->client));
        }

        /**
         * @ignore
         */
        private $access;

        /**
         * Get Access
         * @return PVEAccess
         */
        public function getAccess()
        {
            return $this->access ?: ($this->access = new PVEAccess($this->client));
        }

        /**
         * @ignore
         */
        private $pools;

        /**
         * Get Pools
         * @return PVEPools
         */
        public function getPools()
        {
            return $this->pools ?: ($this->pools = new PVEPools($this->client));
        }

        /**
         * @ignore
         */
        private $version;

        /**
         * Get Version
         * @return PVEVersion
         */
        public function getVersion()
        {
            return $this->version ?: ($this->version = new PVEVersion($this->client));
        }
    }

    /**
     * Class PVECluster
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECluster extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * @ignore
         */
        private $replication;

        /**
         * Get ClusterReplication
         * @return PVEClusterReplication
         */
        public function getReplication()
        {
            return $this->replication ?: ($this->replication = new PVEClusterReplication($this->client));
        }

        /**
         * @ignore
         */
        private $config;

        /**
         * Get ClusterConfig
         * @return PVEClusterConfig
         */
        public function getConfig()
        {
            return $this->config ?: ($this->config = new PVEClusterConfig($this->client));
        }

        /**
         * @ignore
         */
        private $firewall;

        /**
         * Get ClusterFirewall
         * @return PVEClusterFirewall
         */
        public function getFirewall()
        {
            return $this->firewall ?: ($this->firewall = new PVEClusterFirewall($this->client));
        }

        /**
         * @ignore
         */
        private $backup;

        /**
         * Get ClusterBackup
         * @return PVEClusterBackup
         */
        public function getBackup()
        {
            return $this->backup ?: ($this->backup = new PVEClusterBackup($this->client));
        }

        /**
         * @ignore
         */
        private $ha;

        /**
         * Get ClusterHa
         * @return PVEClusterHa
         */
        public function getHa()
        {
            return $this->ha ?: ($this->ha = new PVEClusterHa($this->client));
        }

        /**
         * @ignore
         */
        private $acme;

        /**
         * Get ClusterAcme
         * @return PVEClusterAcme
         */
        public function getAcme()
        {
            return $this->acme ?: ($this->acme = new PVEClusterAcme($this->client));
        }

        /**
         * @ignore
         */
        private $log;

        /**
         * Get ClusterLog
         * @return PVEClusterLog
         */
        public function getLog()
        {
            return $this->log ?: ($this->log = new PVEClusterLog($this->client));
        }

        /**
         * @ignore
         */
        private $resources;

        /**
         * Get ClusterResources
         * @return PVEClusterResources
         */
        public function getResources()
        {
            return $this->resources ?: ($this->resources = new PVEClusterResources($this->client));
        }

        /**
         * @ignore
         */
        private $tasks;

        /**
         * Get ClusterTasks
         * @return PVEClusterTasks
         */
        public function getTasks()
        {
            return $this->tasks ?: ($this->tasks = new PVEClusterTasks($this->client));
        }

        /**
         * @ignore
         */
        private $options;

        /**
         * Get ClusterOptions
         * @return PVEClusterOptions
         */
        public function getOptions()
        {
            return $this->options ?: ($this->options = new PVEClusterOptions($this->client));
        }

        /**
         * @ignore
         */
        private $status;

        /**
         * Get ClusterStatus
         * @return PVEClusterStatus
         */
        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVEClusterStatus($this->client));
        }

        /**
         * @ignore
         */
        private $nextid;

        /**
         * Get ClusterNextid
         * @return PVEClusterNextid
         */
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

    /**
     * Class PVEClusterReplication
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEClusterReplication extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemReplicationClusterId
         * @param id
         * @return PVEItemReplicationClusterId
         */
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
         * @param string $source Source of the replication.
         * @return Result
         */
        public function createRest($id, $target, $type, $comment = null, $disable = null, $rate = null, $remove_job = null, $schedule = null, $source = null)
        {
            $params = ['id' => $id,
                'target' => $target,
                'type' => $type,
                'comment' => $comment,
                'disable' => $disable,
                'rate' => $rate,
                'remove_job' => $remove_job,
                'schedule' => $schedule,
                'source' => $source];
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
         * @param string $source Source of the replication.
         * @return Result
         */
        public function create($id, $target, $type, $comment = null, $disable = null, $rate = null, $remove_job = null, $schedule = null, $source = null)
        {
            return $this->createRest($id, $target, $type, $comment, $disable, $rate, $remove_job, $schedule, $source);
        }
    }

    /**
     * Class PVEItemReplicationClusterId
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemReplicationClusterId extends Base
    {
        /**
         * @ignore
         */
        private $id;

        /**
         * @ignore
         */
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
         * @param string $source Source of the replication.
         * @return Result
         */
        public function setRest($comment = null, $delete = null, $digest = null, $disable = null, $rate = null, $remove_job = null, $schedule = null, $source = null)
        {
            $params = ['comment' => $comment,
                'delete' => $delete,
                'digest' => $digest,
                'disable' => $disable,
                'rate' => $rate,
                'remove_job' => $remove_job,
                'schedule' => $schedule,
                'source' => $source];
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
         * @param string $source Source of the replication.
         * @return Result
         */
        public function update($comment = null, $delete = null, $digest = null, $disable = null, $rate = null, $remove_job = null, $schedule = null, $source = null)
        {
            return $this->setRest($comment, $delete, $digest, $disable, $rate, $remove_job, $schedule, $source);
        }
    }

    /**
     * Class PVEClusterConfig
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEClusterConfig extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * @ignore
         */
        private $nodes;

        /**
         * Get ConfigClusterNodes
         * @return PVEConfigClusterNodes
         */
        public function getNodes()
        {
            return $this->nodes ?: ($this->nodes = new PVEConfigClusterNodes($this->client));
        }

        /**
         * @ignore
         */
        private $join;

        /**
         * Get ConfigClusterJoin
         * @return PVEConfigClusterJoin
         */
        public function getJoin()
        {
            return $this->join ?: ($this->join = new PVEConfigClusterJoin($this->client));
        }

        /**
         * @ignore
         */
        private $totem;

        /**
         * Get ConfigClusterTotem
         * @return PVEConfigClusterTotem
         */
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

        /**
         * Generate new cluster configuration.
         * @param string $clustername The name of the cluster.
         * @param string $bindnet0_addr This specifies the network address the corosync ring 0 executive should bind to and defaults to the local IP address of the node.
         * @param string $bindnet1_addr This specifies the network address the corosync ring 1 executive should bind to and is optional.
         * @param int $nodeid Node id for this node.
         * @param string $ring0_addr Hostname (or IP) of the corosync ring0 address of this node.
         * @param string $ring1_addr Hostname (or IP) of the corosync ring1 address of this node. Requires a valid configured ring 1 (bindnet1_addr) in the cluster.
         * @param int $votes Number of votes for this node.
         * @return Result
         */
        public function createRest($clustername, $bindnet0_addr = null, $bindnet1_addr = null, $nodeid = null, $ring0_addr = null, $ring1_addr = null, $votes = null)
        {
            $params = ['clustername' => $clustername,
                'bindnet0_addr' => $bindnet0_addr,
                'bindnet1_addr' => $bindnet1_addr,
                'nodeid' => $nodeid,
                'ring0_addr' => $ring0_addr,
                'ring1_addr' => $ring1_addr,
                'votes' => $votes];
            return $this->getClient()->create("/cluster/config", $params);
        }

        /**
         * Generate new cluster configuration.
         * @param string $clustername The name of the cluster.
         * @param string $bindnet0_addr This specifies the network address the corosync ring 0 executive should bind to and defaults to the local IP address of the node.
         * @param string $bindnet1_addr This specifies the network address the corosync ring 1 executive should bind to and is optional.
         * @param int $nodeid Node id for this node.
         * @param string $ring0_addr Hostname (or IP) of the corosync ring0 address of this node.
         * @param string $ring1_addr Hostname (or IP) of the corosync ring1 address of this node. Requires a valid configured ring 1 (bindnet1_addr) in the cluster.
         * @param int $votes Number of votes for this node.
         * @return Result
         */
        public function create($clustername, $bindnet0_addr = null, $bindnet1_addr = null, $nodeid = null, $ring0_addr = null, $ring1_addr = null, $votes = null)
        {
            return $this->createRest($clustername, $bindnet0_addr, $bindnet1_addr, $nodeid, $ring0_addr, $ring1_addr, $votes);
        }
    }

    /**
     * Class PVEConfigClusterNodes
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEConfigClusterNodes extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemNodesConfigClusterNode
         * @param node
         * @return PVEItemNodesConfigClusterNode
         */
        public function get($node)
        {
            return new PVEItemNodesConfigClusterNode($this->client, $node);
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

    /**
     * Class PVEItemNodesConfigClusterNode
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemNodesConfigClusterNode extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Removes a node from the cluster configuration.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/cluster/config/nodes/{$this->node}");
        }

        /**
         * Removes a node from the cluster configuration.
         * @return Result
         */
        public function delnode()
        {
            return $this->deleteRest();
        }

        /**
         * Adds a node to the cluster configuration. This call is for internal use.
         * @param bool $force Do not throw error if node already exists.
         * @param int $nodeid Node id for this node.
         * @param string $ring0_addr Hostname (or IP) of the corosync ring0 address of this node.
         * @param string $ring1_addr Hostname (or IP) of the corosync ring1 address of this node. Requires a valid configured ring 1 (bindnet1_addr) in the cluster.
         * @param int $votes Number of votes for this node
         * @return Result
         */
        public function createRest($force = null, $nodeid = null, $ring0_addr = null, $ring1_addr = null, $votes = null)
        {
            $params = ['force' => $force,
                'nodeid' => $nodeid,
                'ring0_addr' => $ring0_addr,
                'ring1_addr' => $ring1_addr,
                'votes' => $votes];
            return $this->getClient()->create("/cluster/config/nodes/{$this->node}", $params);
        }

        /**
         * Adds a node to the cluster configuration. This call is for internal use.
         * @param bool $force Do not throw error if node already exists.
         * @param int $nodeid Node id for this node.
         * @param string $ring0_addr Hostname (or IP) of the corosync ring0 address of this node.
         * @param string $ring1_addr Hostname (or IP) of the corosync ring1 address of this node. Requires a valid configured ring 1 (bindnet1_addr) in the cluster.
         * @param int $votes Number of votes for this node
         * @return Result
         */
        public function addnode($force = null, $nodeid = null, $ring0_addr = null, $ring1_addr = null, $votes = null)
        {
            return $this->createRest($force, $nodeid, $ring0_addr, $ring1_addr, $votes);
        }
    }

    /**
     * Class PVEConfigClusterJoin
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEConfigClusterJoin extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get information needed to join this cluster over the connected node.
         * @param string $node The node for which the joinee gets the nodeinfo.
         * @return Result
         */
        public function getRest($node = null)
        {
            $params = ['node' => $node];
            return $this->getClient()->get("/cluster/config/join", $params);
        }

        /**
         * Get information needed to join this cluster over the connected node.
         * @param string $node The node for which the joinee gets the nodeinfo.
         * @return Result
         */
        public function joinInfo($node = null)
        {
            return $this->getRest($node);
        }

        /**
         * Joins this node into an existing cluster.
         * @param string $fingerprint Certificate SHA 256 fingerprint.
         * @param string $hostname Hostname (or IP) of an existing cluster member.
         * @param string $password Superuser (root) password of peer node.
         * @param bool $force Do not throw error if node already exists.
         * @param int $nodeid Node id for this node.
         * @param string $ring0_addr Hostname (or IP) of the corosync ring0 address of this node.
         * @param string $ring1_addr Hostname (or IP) of the corosync ring1 address of this node. Requires a valid configured ring 1 (bindnet1_addr) in the cluster.
         * @param int $votes Number of votes for this node
         * @return Result
         */
        public function createRest($fingerprint, $hostname, $password, $force = null, $nodeid = null, $ring0_addr = null, $ring1_addr = null, $votes = null)
        {
            $params = ['fingerprint' => $fingerprint,
                'hostname' => $hostname,
                'password' => $password,
                'force' => $force,
                'nodeid' => $nodeid,
                'ring0_addr' => $ring0_addr,
                'ring1_addr' => $ring1_addr,
                'votes' => $votes];
            return $this->getClient()->create("/cluster/config/join", $params);
        }

        /**
         * Joins this node into an existing cluster.
         * @param string $fingerprint Certificate SHA 256 fingerprint.
         * @param string $hostname Hostname (or IP) of an existing cluster member.
         * @param string $password Superuser (root) password of peer node.
         * @param bool $force Do not throw error if node already exists.
         * @param int $nodeid Node id for this node.
         * @param string $ring0_addr Hostname (or IP) of the corosync ring0 address of this node.
         * @param string $ring1_addr Hostname (or IP) of the corosync ring1 address of this node. Requires a valid configured ring 1 (bindnet1_addr) in the cluster.
         * @param int $votes Number of votes for this node
         * @return Result
         */
        public function join($fingerprint, $hostname, $password, $force = null, $nodeid = null, $ring0_addr = null, $ring1_addr = null, $votes = null)
        {
            return $this->createRest($fingerprint, $hostname, $password, $force, $nodeid, $ring0_addr, $ring1_addr, $votes);
        }
    }

    /**
     * Class PVEConfigClusterTotem
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEConfigClusterTotem extends Base
    {
        /**
         * @ignore
         */
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

    /**
     * Class PVEClusterFirewall
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEClusterFirewall extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * @ignore
         */
        private $groups;

        /**
         * Get FirewallClusterGroups
         * @return PVEFirewallClusterGroups
         */
        public function getGroups()
        {
            return $this->groups ?: ($this->groups = new PVEFirewallClusterGroups($this->client));
        }

        /**
         * @ignore
         */
        private $rules;

        /**
         * Get FirewallClusterRules
         * @return PVEFirewallClusterRules
         */
        public function getRules()
        {
            return $this->rules ?: ($this->rules = new PVEFirewallClusterRules($this->client));
        }

        /**
         * @ignore
         */
        private $ipset;

        /**
         * Get FirewallClusterIpset
         * @return PVEFirewallClusterIpset
         */
        public function getIpset()
        {
            return $this->ipset ?: ($this->ipset = new PVEFirewallClusterIpset($this->client));
        }

        /**
         * @ignore
         */
        private $aliases;

        /**
         * Get FirewallClusterAliases
         * @return PVEFirewallClusterAliases
         */
        public function getAliases()
        {
            return $this->aliases ?: ($this->aliases = new PVEFirewallClusterAliases($this->client));
        }

        /**
         * @ignore
         */
        private $options;

        /**
         * Get FirewallClusterOptions
         * @return PVEFirewallClusterOptions
         */
        public function getOptions()
        {
            return $this->options ?: ($this->options = new PVEFirewallClusterOptions($this->client));
        }

        /**
         * @ignore
         */
        private $macros;

        /**
         * Get FirewallClusterMacros
         * @return PVEFirewallClusterMacros
         */
        public function getMacros()
        {
            return $this->macros ?: ($this->macros = new PVEFirewallClusterMacros($this->client));
        }

        /**
         * @ignore
         */
        private $refs;

        /**
         * Get FirewallClusterRefs
         * @return PVEFirewallClusterRefs
         */
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

    /**
     * Class PVEFirewallClusterGroups
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallClusterGroups extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemGroupsFirewallClusterGroup
         * @param group
         * @return PVEItemGroupsFirewallClusterGroup
         */
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

    /**
     * Class PVEItemGroupsFirewallClusterGroup
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemGroupsFirewallClusterGroup extends Base
    {
        /**
         * @ignore
         */
        private $group;

        /**
         * @ignore
         */
        function __construct($client, $group)
        {
            $this->client = $client;
            $this->group = $group;
        }

        /**
         * Get ItemGroupGroupsFirewallClusterPos
         * @param pos
         * @return PVEItemGroupGroupsFirewallClusterPos
         */
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

    /**
     * Class PVEItemGroupGroupsFirewallClusterPos
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemGroupGroupsFirewallClusterPos extends Base
    {
        /**
         * @ignore
         */
        private $group;
        /**
         * @ignore
         */
        private $pos;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallClusterRules
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallClusterRules extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemRulesFirewallClusterPos
         * @param pos
         * @return PVEItemRulesFirewallClusterPos
         */
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

    /**
     * Class PVEItemRulesFirewallClusterPos
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemRulesFirewallClusterPos extends Base
    {
        /**
         * @ignore
         */
        private $pos;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallClusterIpset
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallClusterIpset extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemIpsetFirewallClusterName
         * @param name
         * @return PVEItemIpsetFirewallClusterName
         */
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

    /**
     * Class PVEItemIpsetFirewallClusterName
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemIpsetFirewallClusterName extends Base
    {
        /**
         * @ignore
         */
        private $name;

        /**
         * @ignore
         */
        function __construct($client, $name)
        {
            $this->client = $client;
            $this->name = $name;
        }

        /**
         * Get ItemNameIpsetFirewallClusterCidr
         * @param cidr
         * @return PVEItemNameIpsetFirewallClusterCidr
         */
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

    /**
     * Class PVEItemNameIpsetFirewallClusterCidr
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemNameIpsetFirewallClusterCidr extends Base
    {
        /**
         * @ignore
         */
        private $name;
        /**
         * @ignore
         */
        private $cidr;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallClusterAliases
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallClusterAliases extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemAliasesFirewallClusterName
         * @param name
         * @return PVEItemAliasesFirewallClusterName
         */
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

    /**
     * Class PVEItemAliasesFirewallClusterName
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemAliasesFirewallClusterName extends Base
    {
        /**
         * @ignore
         */
        private $name;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallClusterOptions
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallClusterOptions extends Base
    {
        /**
         * @ignore
         */
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
         * @param bool $ebtables Enable ebtables rules cluster wide.
         * @param int $enable Enable or disable the firewall cluster wide.
         * @param string $policy_in Input policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param string $policy_out Output policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @return Result
         */
        public function setRest($delete = null, $digest = null, $ebtables = null, $enable = null, $policy_in = null, $policy_out = null)
        {
            $params = ['delete' => $delete,
                'digest' => $digest,
                'ebtables' => $ebtables,
                'enable' => $enable,
                'policy_in' => $policy_in,
                'policy_out' => $policy_out];
            return $this->getClient()->set("/cluster/firewall/options", $params);
        }

        /**
         * Set Firewall options.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $ebtables Enable ebtables rules cluster wide.
         * @param int $enable Enable or disable the firewall cluster wide.
         * @param string $policy_in Input policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @param string $policy_out Output policy.
         *   Enum: ACCEPT,REJECT,DROP
         * @return Result
         */
        public function setOptions($delete = null, $digest = null, $ebtables = null, $enable = null, $policy_in = null, $policy_out = null)
        {
            return $this->setRest($delete, $digest, $ebtables, $enable, $policy_in, $policy_out);
        }
    }

    /**
     * Class PVEFirewallClusterMacros
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallClusterMacros extends Base
    {
        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallClusterRefs
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallClusterRefs extends Base
    {
        /**
         * @ignore
         */
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

    /**
     * Class PVEClusterBackup
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEClusterBackup extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemBackupClusterId
         * @param id
         * @return PVEItemBackupClusterId
         */
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

    /**
     * Class PVEItemBackupClusterId
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemBackupClusterId extends Base
    {
        /**
         * @ignore
         */
        private $id;

        /**
         * @ignore
         */
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

    /**
     * Class PVEClusterHa
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEClusterHa extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * @ignore
         */
        private $resources;

        /**
         * Get HaClusterResources
         * @return PVEHaClusterResources
         */
        public function getResources()
        {
            return $this->resources ?: ($this->resources = new PVEHaClusterResources($this->client));
        }

        /**
         * @ignore
         */
        private $groups;

        /**
         * Get HaClusterGroups
         * @return PVEHaClusterGroups
         */
        public function getGroups()
        {
            return $this->groups ?: ($this->groups = new PVEHaClusterGroups($this->client));
        }

        /**
         * @ignore
         */
        private $status;

        /**
         * Get HaClusterStatus
         * @return PVEHaClusterStatus
         */
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

    /**
     * Class PVEHaClusterResources
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEHaClusterResources extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemResourcesHaClusterSid
         * @param sid
         * @return PVEItemResourcesHaClusterSid
         */
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

    /**
     * Class PVEItemResourcesHaClusterSid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemResourcesHaClusterSid extends Base
    {
        /**
         * @ignore
         */
        private $sid;

        /**
         * @ignore
         */
        function __construct($client, $sid)
        {
            $this->client = $client;
            $this->sid = $sid;
        }

        /**
         * @ignore
         */
        private $migrate;

        /**
         * Get SidResourcesHaClusterMigrate
         * @return PVESidResourcesHaClusterMigrate
         */
        public function getMigrate()
        {
            return $this->migrate ?: ($this->migrate = new PVESidResourcesHaClusterMigrate($this->client, $this->sid));
        }

        /**
         * @ignore
         */
        private $relocate;

        /**
         * Get SidResourcesHaClusterRelocate
         * @return PVESidResourcesHaClusterRelocate
         */
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

    /**
     * Class PVESidResourcesHaClusterMigrate
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVESidResourcesHaClusterMigrate extends Base
    {
        /**
         * @ignore
         */
        private $sid;

        /**
         * @ignore
         */
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

    /**
     * Class PVESidResourcesHaClusterRelocate
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVESidResourcesHaClusterRelocate extends Base
    {
        /**
         * @ignore
         */
        private $sid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEHaClusterGroups
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEHaClusterGroups extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemGroupsHaClusterGroup
         * @param group
         * @return PVEItemGroupsHaClusterGroup
         */
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

    /**
     * Class PVEItemGroupsHaClusterGroup
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemGroupsHaClusterGroup extends Base
    {
        /**
         * @ignore
         */
        private $group;

        /**
         * @ignore
         */
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

    /**
     * Class PVEHaClusterStatus
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEHaClusterStatus extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * @ignore
         */
        private $current;

        /**
         * Get StatusHaClusterCurrent
         * @return PVEStatusHaClusterCurrent
         */
        public function getCurrent()
        {
            return $this->current ?: ($this->current = new PVEStatusHaClusterCurrent($this->client));
        }

        /**
         * @ignore
         */
        private $managerStatus;

        /**
         * Get StatusHaClusterManagerStatus
         * @return PVEStatusHaClusterManagerStatus
         */
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

    /**
     * Class PVEStatusHaClusterCurrent
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusHaClusterCurrent extends Base
    {
        /**
         * @ignore
         */
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

    /**
     * Class PVEStatusHaClusterManagerStatus
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusHaClusterManagerStatus extends Base
    {
        /**
         * @ignore
         */
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

    /**
     * Class PVEClusterAcme
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEClusterAcme extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * @ignore
         */
        private $account;

        /**
         * Get AcmeClusterAccount
         * @return PVEAcmeClusterAccount
         */
        public function getAccount()
        {
            return $this->account ?: ($this->account = new PVEAcmeClusterAccount($this->client));
        }

        /**
         * @ignore
         */
        private $tos;

        /**
         * Get AcmeClusterTos
         * @return PVEAcmeClusterTos
         */
        public function getTos()
        {
            return $this->tos ?: ($this->tos = new PVEAcmeClusterTos($this->client));
        }

        /**
         * @ignore
         */
        private $directories;

        /**
         * Get AcmeClusterDirectories
         * @return PVEAcmeClusterDirectories
         */
        public function getDirectories()
        {
            return $this->directories ?: ($this->directories = new PVEAcmeClusterDirectories($this->client));
        }

        /**
         * ACMEAccount index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/acme");
        }

        /**
         * ACMEAccount index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAcmeClusterAccount
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAcmeClusterAccount extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemAccountAcmeClusterName
         * @param name
         * @return PVEItemAccountAcmeClusterName
         */
        public function get($name)
        {
            return new PVEItemAccountAcmeClusterName($this->client, $name);
        }

        /**
         * ACMEAccount index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/acme/account");
        }

        /**
         * ACMEAccount index.
         * @return Result
         */
        public function accountIndex()
        {
            return $this->getRest();
        }

        /**
         * Register a new ACME account with CA.
         * @param string $contact Contact email addresses.
         * @param string $directory URL of ACME CA directory endpoint.
         * @param string $name ACME account config file name.
         * @param string $tos_url URL of CA TermsOfService - setting this indicates agreement.
         * @return Result
         */
        public function createRest($contact, $directory = null, $name = null, $tos_url = null)
        {
            $params = ['contact' => $contact,
                'directory' => $directory,
                'name' => $name,
                'tos_url' => $tos_url];
            return $this->getClient()->create("/cluster/acme/account", $params);
        }

        /**
         * Register a new ACME account with CA.
         * @param string $contact Contact email addresses.
         * @param string $directory URL of ACME CA directory endpoint.
         * @param string $name ACME account config file name.
         * @param string $tos_url URL of CA TermsOfService - setting this indicates agreement.
         * @return Result
         */
        public function registerAccount($contact, $directory = null, $name = null, $tos_url = null)
        {
            return $this->createRest($contact, $directory, $name, $tos_url);
        }
    }

    /**
     * Class PVEItemAccountAcmeClusterName
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemAccountAcmeClusterName extends Base
    {
        /**
         * @ignore
         */
        private $name;

        /**
         * @ignore
         */
        function __construct($client, $name)
        {
            $this->client = $client;
            $this->name = $name;
        }

        /**
         * Deactivate existing ACME account at CA.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/cluster/acme/account/{$this->name}");
        }

        /**
         * Deactivate existing ACME account at CA.
         * @return Result
         */
        public function deactivateAccount()
        {
            return $this->deleteRest();
        }

        /**
         * Return existing ACME account information.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/acme/account/{$this->name}");
        }

        /**
         * Return existing ACME account information.
         * @return Result
         */
        public function getAccount()
        {
            return $this->getRest();
        }

        /**
         * Update existing ACME account information with CA. Note: not specifying any new account information triggers a refresh.
         * @param string $contact Contact email addresses.
         * @return Result
         */
        public function setRest($contact = null)
        {
            $params = ['contact' => $contact];
            return $this->getClient()->set("/cluster/acme/account/{$this->name}", $params);
        }

        /**
         * Update existing ACME account information with CA. Note: not specifying any new account information triggers a refresh.
         * @param string $contact Contact email addresses.
         * @return Result
         */
        public function updateAccount($contact = null)
        {
            return $this->setRest($contact);
        }
    }

    /**
     * Class PVEAcmeClusterTos
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAcmeClusterTos extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Retrieve ACME TermsOfService URL from CA.
         * @param string $directory URL of ACME CA directory endpoint.
         * @return Result
         */
        public function getRest($directory = null)
        {
            $params = ['directory' => $directory];
            return $this->getClient()->get("/cluster/acme/tos", $params);
        }

        /**
         * Retrieve ACME TermsOfService URL from CA.
         * @param string $directory URL of ACME CA directory endpoint.
         * @return Result
         */
        public function getTos($directory = null)
        {
            return $this->getRest($directory);
        }
    }

    /**
     * Class PVEAcmeClusterDirectories
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAcmeClusterDirectories extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get named known ACME directory endpoints.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/cluster/acme/directories");
        }

        /**
         * Get named known ACME directory endpoints.
         * @return Result
         */
        public function getDirectories()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEClusterLog
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEClusterLog extends Base
    {
        /**
         * @ignore
         */
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

    /**
     * Class PVEClusterResources
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEClusterResources extends Base
    {
        /**
         * @ignore
         */
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

    /**
     * Class PVEClusterTasks
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEClusterTasks extends Base
    {
        /**
         * @ignore
         */
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

    /**
     * Class PVEClusterOptions
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEClusterOptions extends Base
    {
        /**
         * @ignore
         */
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
         * @param string $bwlimit Set bandwidth/io limits various operations.
         * @param string $console Select the default Console viewer. You can either use the builtin java applet (VNC; deprecated and maps to html5), an external virt-viewer comtatible application (SPICE), an HTML5 based vnc viewer (noVNC), or an HTML5 based console client (xtermjs). If the selected viewer is not available (e.g. SPICE not activated for the VM), the fallback is noVNC.
         *   Enum: applet,vv,html5,xtermjs
         * @param string $delete A list of settings you want to delete.
         * @param string $email_from Specify email address to send notification from (default is root@$hostname)
         * @param string $fencing Set the fencing mode of the HA cluster. Hardware mode needs a valid configuration of fence devices in /etc/pve/ha/fence.cfg. With both all two modes are used.  WARNING: 'hardware' and 'both' are EXPERIMENTAL &amp; WIP
         *   Enum: watchdog,hardware,both
         * @param string $http_proxy Specify external http proxy which is used for downloads (example: 'http://username:password@host:port/')
         * @param string $keyboard Default keybord layout for vnc server.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param string $language Default GUI language.
         *   Enum: zh_CN,zh_TW,ca,en,eu,fr,de,it,es,ja,nb,nn,fa,pl,pt_BR,ru,sl,sv,tr
         * @param string $mac_prefix Prefix for autogenerated MAC addresses.
         * @param int $max_workers Defines how many workers (per node) are maximal started  on actions like 'stopall VMs' or task from the ha-manager.
         * @param string $migration For cluster wide migration settings.
         * @param bool $migration_unsecure Migration is secure using SSH tunnel by default. For secure private networks you can disable it to speed up migration. Deprecated, use the 'migration' property instead!
         * @return Result
         */
        public function setRest($bwlimit = null, $console = null, $delete = null, $email_from = null, $fencing = null, $http_proxy = null, $keyboard = null, $language = null, $mac_prefix = null, $max_workers = null, $migration = null, $migration_unsecure = null)
        {
            $params = ['bwlimit' => $bwlimit,
                'console' => $console,
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
         * @param string $bwlimit Set bandwidth/io limits various operations.
         * @param string $console Select the default Console viewer. You can either use the builtin java applet (VNC; deprecated and maps to html5), an external virt-viewer comtatible application (SPICE), an HTML5 based vnc viewer (noVNC), or an HTML5 based console client (xtermjs). If the selected viewer is not available (e.g. SPICE not activated for the VM), the fallback is noVNC.
         *   Enum: applet,vv,html5,xtermjs
         * @param string $delete A list of settings you want to delete.
         * @param string $email_from Specify email address to send notification from (default is root@$hostname)
         * @param string $fencing Set the fencing mode of the HA cluster. Hardware mode needs a valid configuration of fence devices in /etc/pve/ha/fence.cfg. With both all two modes are used.  WARNING: 'hardware' and 'both' are EXPERIMENTAL &amp; WIP
         *   Enum: watchdog,hardware,both
         * @param string $http_proxy Specify external http proxy which is used for downloads (example: 'http://username:password@host:port/')
         * @param string $keyboard Default keybord layout for vnc server.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param string $language Default GUI language.
         *   Enum: zh_CN,zh_TW,ca,en,eu,fr,de,it,es,ja,nb,nn,fa,pl,pt_BR,ru,sl,sv,tr
         * @param string $mac_prefix Prefix for autogenerated MAC addresses.
         * @param int $max_workers Defines how many workers (per node) are maximal started  on actions like 'stopall VMs' or task from the ha-manager.
         * @param string $migration For cluster wide migration settings.
         * @param bool $migration_unsecure Migration is secure using SSH tunnel by default. For secure private networks you can disable it to speed up migration. Deprecated, use the 'migration' property instead!
         * @return Result
         */
        public function setOptions($bwlimit = null, $console = null, $delete = null, $email_from = null, $fencing = null, $http_proxy = null, $keyboard = null, $language = null, $mac_prefix = null, $max_workers = null, $migration = null, $migration_unsecure = null)
        {
            return $this->setRest($bwlimit, $console, $delete, $email_from, $fencing, $http_proxy, $keyboard, $language, $mac_prefix, $max_workers, $migration, $migration_unsecure);
        }
    }

    /**
     * Class PVEClusterStatus
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEClusterStatus extends Base
    {
        /**
         * @ignore
         */
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

    /**
     * Class PVEClusterNextid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEClusterNextid extends Base
    {
        /**
         * @ignore
         */
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

    /**
     * Class PVENodes
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodes extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemNodesNode
         * @param node
         * @return PVEItemNodesNode
         */
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

    /**
     * Class PVEItemNodesNode
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemNodesNode extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * @ignore
         */
        private $qemu;

        /**
         * Get NodeNodesQemu
         * @return PVENodeNodesQemu
         */
        public function getQemu()
        {
            return $this->qemu ?: ($this->qemu = new PVENodeNodesQemu($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $lxc;

        /**
         * Get NodeNodesLxc
         * @return PVENodeNodesLxc
         */
        public function getLxc()
        {
            return $this->lxc ?: ($this->lxc = new PVENodeNodesLxc($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $ceph;

        /**
         * Get NodeNodesCeph
         * @return PVENodeNodesCeph
         */
        public function getCeph()
        {
            return $this->ceph ?: ($this->ceph = new PVENodeNodesCeph($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $vzdump;

        /**
         * Get NodeNodesVzdump
         * @return PVENodeNodesVzdump
         */
        public function getVzdump()
        {
            return $this->vzdump ?: ($this->vzdump = new PVENodeNodesVzdump($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $services;

        /**
         * Get NodeNodesServices
         * @return PVENodeNodesServices
         */
        public function getServices()
        {
            return $this->services ?: ($this->services = new PVENodeNodesServices($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $subscription;

        /**
         * Get NodeNodesSubscription
         * @return PVENodeNodesSubscription
         */
        public function getSubscription()
        {
            return $this->subscription ?: ($this->subscription = new PVENodeNodesSubscription($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $network;

        /**
         * Get NodeNodesNetwork
         * @return PVENodeNodesNetwork
         */
        public function getNetwork()
        {
            return $this->network ?: ($this->network = new PVENodeNodesNetwork($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $tasks;

        /**
         * Get NodeNodesTasks
         * @return PVENodeNodesTasks
         */
        public function getTasks()
        {
            return $this->tasks ?: ($this->tasks = new PVENodeNodesTasks($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $scan;

        /**
         * Get NodeNodesScan
         * @return PVENodeNodesScan
         */
        public function getScan()
        {
            return $this->scan ?: ($this->scan = new PVENodeNodesScan($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $hardware;

        /**
         * Get NodeNodesHardware
         * @return PVENodeNodesHardware
         */
        public function getHardware()
        {
            return $this->hardware ?: ($this->hardware = new PVENodeNodesHardware($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $storage;

        /**
         * Get NodeNodesStorage
         * @return PVENodeNodesStorage
         */
        public function getStorage()
        {
            return $this->storage ?: ($this->storage = new PVENodeNodesStorage($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $disks;

        /**
         * Get NodeNodesDisks
         * @return PVENodeNodesDisks
         */
        public function getDisks()
        {
            return $this->disks ?: ($this->disks = new PVENodeNodesDisks($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $apt;

        /**
         * Get NodeNodesApt
         * @return PVENodeNodesApt
         */
        public function getApt()
        {
            return $this->apt ?: ($this->apt = new PVENodeNodesApt($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $firewall;

        /**
         * Get NodeNodesFirewall
         * @return PVENodeNodesFirewall
         */
        public function getFirewall()
        {
            return $this->firewall ?: ($this->firewall = new PVENodeNodesFirewall($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $replication;

        /**
         * Get NodeNodesReplication
         * @return PVENodeNodesReplication
         */
        public function getReplication()
        {
            return $this->replication ?: ($this->replication = new PVENodeNodesReplication($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $certificates;

        /**
         * Get NodeNodesCertificates
         * @return PVENodeNodesCertificates
         */
        public function getCertificates()
        {
            return $this->certificates ?: ($this->certificates = new PVENodeNodesCertificates($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $config;

        /**
         * Get NodeNodesConfig
         * @return PVENodeNodesConfig
         */
        public function getConfig()
        {
            return $this->config ?: ($this->config = new PVENodeNodesConfig($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $version;

        /**
         * Get NodeNodesVersion
         * @return PVENodeNodesVersion
         */
        public function getVersion()
        {
            return $this->version ?: ($this->version = new PVENodeNodesVersion($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $status;

        /**
         * Get NodeNodesStatus
         * @return PVENodeNodesStatus
         */
        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVENodeNodesStatus($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $netstat;

        /**
         * Get NodeNodesNetstat
         * @return PVENodeNodesNetstat
         */
        public function getNetstat()
        {
            return $this->netstat ?: ($this->netstat = new PVENodeNodesNetstat($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $execute;

        /**
         * Get NodeNodesExecute
         * @return PVENodeNodesExecute
         */
        public function getExecute()
        {
            return $this->execute ?: ($this->execute = new PVENodeNodesExecute($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $rrd;

        /**
         * Get NodeNodesRrd
         * @return PVENodeNodesRrd
         */
        public function getRrd()
        {
            return $this->rrd ?: ($this->rrd = new PVENodeNodesRrd($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $rrddata;

        /**
         * Get NodeNodesRrddata
         * @return PVENodeNodesRrddata
         */
        public function getRrddata()
        {
            return $this->rrddata ?: ($this->rrddata = new PVENodeNodesRrddata($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $syslog;

        /**
         * Get NodeNodesSyslog
         * @return PVENodeNodesSyslog
         */
        public function getSyslog()
        {
            return $this->syslog ?: ($this->syslog = new PVENodeNodesSyslog($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $vncshell;

        /**
         * Get NodeNodesVncshell
         * @return PVENodeNodesVncshell
         */
        public function getVncshell()
        {
            return $this->vncshell ?: ($this->vncshell = new PVENodeNodesVncshell($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $termproxy;

        /**
         * Get NodeNodesTermproxy
         * @return PVENodeNodesTermproxy
         */
        public function getTermproxy()
        {
            return $this->termproxy ?: ($this->termproxy = new PVENodeNodesTermproxy($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $vncwebsocket;

        /**
         * Get NodeNodesVncwebsocket
         * @return PVENodeNodesVncwebsocket
         */
        public function getVncwebsocket()
        {
            return $this->vncwebsocket ?: ($this->vncwebsocket = new PVENodeNodesVncwebsocket($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $spiceshell;

        /**
         * Get NodeNodesSpiceshell
         * @return PVENodeNodesSpiceshell
         */
        public function getSpiceshell()
        {
            return $this->spiceshell ?: ($this->spiceshell = new PVENodeNodesSpiceshell($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $dns;

        /**
         * Get NodeNodesDns
         * @return PVENodeNodesDns
         */
        public function getDns()
        {
            return $this->dns ?: ($this->dns = new PVENodeNodesDns($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $time;

        /**
         * Get NodeNodesTime
         * @return PVENodeNodesTime
         */
        public function getTime()
        {
            return $this->time ?: ($this->time = new PVENodeNodesTime($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $aplinfo;

        /**
         * Get NodeNodesAplinfo
         * @return PVENodeNodesAplinfo
         */
        public function getAplinfo()
        {
            return $this->aplinfo ?: ($this->aplinfo = new PVENodeNodesAplinfo($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $report;

        /**
         * Get NodeNodesReport
         * @return PVENodeNodesReport
         */
        public function getReport()
        {
            return $this->report ?: ($this->report = new PVENodeNodesReport($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $startall;

        /**
         * Get NodeNodesStartall
         * @return PVENodeNodesStartall
         */
        public function getStartall()
        {
            return $this->startall ?: ($this->startall = new PVENodeNodesStartall($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $stopall;

        /**
         * Get NodeNodesStopall
         * @return PVENodeNodesStopall
         */
        public function getStopall()
        {
            return $this->stopall ?: ($this->stopall = new PVENodeNodesStopall($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $migrateall;

        /**
         * Get NodeNodesMigrateall
         * @return PVENodeNodesMigrateall
         */
        public function getMigrateall()
        {
            return $this->migrateall ?: ($this->migrateall = new PVENodeNodesMigrateall($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $hosts;

        /**
         * Get NodeNodesHosts
         * @return PVENodeNodesHosts
         */
        public function getHosts()
        {
            return $this->hosts ?: ($this->hosts = new PVENodeNodesHosts($this->client, $this->node));
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

    /**
     * Class PVENodeNodesQemu
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesQemu extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemQemuNodeNodesVmid
         * @param vmid
         * @return PVEItemQemuNodeNodesVmid
         */
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
         * @param string $agent Enable/disable Qemu GuestAgent and its properties.
         * @param string $arch Virtual processor architecture. Defaults to the host.
         *   Enum: x86_64,aarch64
         * @param string $archive The backup file.
         * @param string $args Arbitrary arguments passed to kvm.
         * @param bool $autostart Automatic restart after crash (currently ignored).
         * @param int $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param string $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param string $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param string $bootdisk Enable booting from specified disk.
         * @param int $bwlimit Override i/o bandwidth limit (in KiB/s).
         * @param string $cdrom This is an alias for option -ide2
         * @param string $cipassword cloud-init: Password to assign the user. Using this is generally not recommended. Use ssh keys instead. Also note that older cloud-init versions do not support hashed passwords.
         * @param string $citype Specifies the cloud-init configuration format. The default depends on the configured operating system type (`ostype`. We use the `nocloud` format for Linux, and `configdrive2` for windows.
         *   Enum: configdrive2,nocloud
         * @param string $ciuser cloud-init: User name to change ssh keys and password for instead of the image's configured default user.
         * @param int $cores The number of cores per socket.
         * @param string $cpu Emulated CPU type.
         * @param int $cpulimit Limit of CPU usage.
         * @param int $cpuunits CPU weight for a VM.
         * @param string $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param string $efidisk0 Configure a Disk for storing EFI vars
         * @param bool $force Allow to overwrite existing VM.
         * @param bool $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param array $hostpciN Map host PCI devices into guest.
         * @param string $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param string $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param array $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param array $ipconfigN cloud-init: Specify IP addresses and gateways for the corresponding interface.  IP addresses use CIDR notation, gateways are optional but need an IP of the same type specified.  The special string 'dhcp' can be used for IP addresses to use DHCP, in which case no explicit gateway should be provided. For IPv6 the special string 'auto' can be used to use stateless autoconfiguration.  If cloud-init is enabled and neither an IPv4 nor an IPv6 address is specified, it defaults to using dhcp on IPv4.
         * @param string $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.cfg' configuration file.It should not be necessary to set it.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param bool $kvm Enable/disable KVM hardware virtualization.
         * @param bool $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param string $machine Specifies the Qemu machine type.
         * @param int $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param int $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param int $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param string $name Set a name for the VM. Only used on the configuration web interface.
         * @param string $nameserver cloud-init: Sets DNS server IP address for a container. Create will automatically use the setting from the host if neither searchdomain nor nameserver are set.
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
         * @param string $searchdomain cloud-init: Sets DNS search domains for a container. Create will automatically use the setting from the host if neither searchdomain nor nameserver are set.
         * @param array $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param int $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning. Auto-ballooning is done by pvestatd.
         * @param string $smbios1 Specify SMBIOS type 1 fields.
         * @param int $smp The number of CPUs. Please use option -sockets instead.
         * @param int $sockets The number of CPU sockets.
         * @param string $sshkeys cloud-init: Setup public SSH keys (one key per line, OpenSSH format).
         * @param bool $start Start VM after it was created successfully.
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
         * @param string $vga Configure the VGA hardware.
         * @param array $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param string $vmgenid Set VM Generation ID. Use '1' to autogenerate on create or update, pass '0' to disable explicitly.
         * @param string $vmstatestorage Default storage for VM state volumes/files.
         * @param string $watchdog Create a virtual hardware watchdog device.
         * @return Result
         */
        public function createRest($vmid, $acpi = null, $agent = null, $arch = null, $archive = null, $args = null, $autostart = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $bwlimit = null, $cdrom = null, $cipassword = null, $citype = null, $ciuser = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $description = null, $efidisk0 = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $ipconfigN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $nameserver = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $pool = null, $protection = null, $reboot = null, $sataN = null, $scsiN = null, $scsihw = null, $searchdomain = null, $serialN = null, $shares = null, $smbios1 = null, $smp = null, $sockets = null, $sshkeys = null, $start = null, $startdate = null, $startup = null, $storage = null, $tablet = null, $tdf = null, $template = null, $unique = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $vmgenid = null, $vmstatestorage = null, $watchdog = null)
        {
            $params = ['vmid' => $vmid,
                'acpi' => $acpi,
                'agent' => $agent,
                'arch' => $arch,
                'archive' => $archive,
                'args' => $args,
                'autostart' => $autostart,
                'balloon' => $balloon,
                'bios' => $bios,
                'boot' => $boot,
                'bootdisk' => $bootdisk,
                'bwlimit' => $bwlimit,
                'cdrom' => $cdrom,
                'cipassword' => $cipassword,
                'citype' => $citype,
                'ciuser' => $ciuser,
                'cores' => $cores,
                'cpu' => $cpu,
                'cpulimit' => $cpulimit,
                'cpuunits' => $cpuunits,
                'description' => $description,
                'efidisk0' => $efidisk0,
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
                'nameserver' => $nameserver,
                'numa' => $numa,
                'onboot' => $onboot,
                'ostype' => $ostype,
                'pool' => $pool,
                'protection' => $protection,
                'reboot' => $reboot,
                'scsihw' => $scsihw,
                'searchdomain' => $searchdomain,
                'shares' => $shares,
                'smbios1' => $smbios1,
                'smp' => $smp,
                'sockets' => $sockets,
                'sshkeys' => $sshkeys,
                'start' => $start,
                'startdate' => $startdate,
                'startup' => $startup,
                'storage' => $storage,
                'tablet' => $tablet,
                'tdf' => $tdf,
                'template' => $template,
                'unique' => $unique,
                'vcpus' => $vcpus,
                'vga' => $vga,
                'vmgenid' => $vmgenid,
                'vmstatestorage' => $vmstatestorage,
                'watchdog' => $watchdog];
            $this->addIndexedParameter($params, 'hostpci', $hostpciN);
            $this->addIndexedParameter($params, 'ide', $ideN);
            $this->addIndexedParameter($params, 'ipconfig', $ipconfigN);
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
         * @param string $agent Enable/disable Qemu GuestAgent and its properties.
         * @param string $arch Virtual processor architecture. Defaults to the host.
         *   Enum: x86_64,aarch64
         * @param string $archive The backup file.
         * @param string $args Arbitrary arguments passed to kvm.
         * @param bool $autostart Automatic restart after crash (currently ignored).
         * @param int $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param string $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param string $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param string $bootdisk Enable booting from specified disk.
         * @param int $bwlimit Override i/o bandwidth limit (in KiB/s).
         * @param string $cdrom This is an alias for option -ide2
         * @param string $cipassword cloud-init: Password to assign the user. Using this is generally not recommended. Use ssh keys instead. Also note that older cloud-init versions do not support hashed passwords.
         * @param string $citype Specifies the cloud-init configuration format. The default depends on the configured operating system type (`ostype`. We use the `nocloud` format for Linux, and `configdrive2` for windows.
         *   Enum: configdrive2,nocloud
         * @param string $ciuser cloud-init: User name to change ssh keys and password for instead of the image's configured default user.
         * @param int $cores The number of cores per socket.
         * @param string $cpu Emulated CPU type.
         * @param int $cpulimit Limit of CPU usage.
         * @param int $cpuunits CPU weight for a VM.
         * @param string $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param string $efidisk0 Configure a Disk for storing EFI vars
         * @param bool $force Allow to overwrite existing VM.
         * @param bool $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param array $hostpciN Map host PCI devices into guest.
         * @param string $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param string $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param array $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param array $ipconfigN cloud-init: Specify IP addresses and gateways for the corresponding interface.  IP addresses use CIDR notation, gateways are optional but need an IP of the same type specified.  The special string 'dhcp' can be used for IP addresses to use DHCP, in which case no explicit gateway should be provided. For IPv6 the special string 'auto' can be used to use stateless autoconfiguration.  If cloud-init is enabled and neither an IPv4 nor an IPv6 address is specified, it defaults to using dhcp on IPv4.
         * @param string $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.cfg' configuration file.It should not be necessary to set it.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param bool $kvm Enable/disable KVM hardware virtualization.
         * @param bool $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param string $machine Specifies the Qemu machine type.
         * @param int $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param int $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param int $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param string $name Set a name for the VM. Only used on the configuration web interface.
         * @param string $nameserver cloud-init: Sets DNS server IP address for a container. Create will automatically use the setting from the host if neither searchdomain nor nameserver are set.
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
         * @param string $searchdomain cloud-init: Sets DNS search domains for a container. Create will automatically use the setting from the host if neither searchdomain nor nameserver are set.
         * @param array $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param int $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning. Auto-ballooning is done by pvestatd.
         * @param string $smbios1 Specify SMBIOS type 1 fields.
         * @param int $smp The number of CPUs. Please use option -sockets instead.
         * @param int $sockets The number of CPU sockets.
         * @param string $sshkeys cloud-init: Setup public SSH keys (one key per line, OpenSSH format).
         * @param bool $start Start VM after it was created successfully.
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
         * @param string $vga Configure the VGA hardware.
         * @param array $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param string $vmgenid Set VM Generation ID. Use '1' to autogenerate on create or update, pass '0' to disable explicitly.
         * @param string $vmstatestorage Default storage for VM state volumes/files.
         * @param string $watchdog Create a virtual hardware watchdog device.
         * @return Result
         */
        public function createVm($vmid, $acpi = null, $agent = null, $arch = null, $archive = null, $args = null, $autostart = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $bwlimit = null, $cdrom = null, $cipassword = null, $citype = null, $ciuser = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $description = null, $efidisk0 = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $ipconfigN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $nameserver = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $pool = null, $protection = null, $reboot = null, $sataN = null, $scsiN = null, $scsihw = null, $searchdomain = null, $serialN = null, $shares = null, $smbios1 = null, $smp = null, $sockets = null, $sshkeys = null, $start = null, $startdate = null, $startup = null, $storage = null, $tablet = null, $tdf = null, $template = null, $unique = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $vmgenid = null, $vmstatestorage = null, $watchdog = null)
        {
            return $this->createRest($vmid, $acpi, $agent, $arch, $archive, $args, $autostart, $balloon, $bios, $boot, $bootdisk, $bwlimit, $cdrom, $cipassword, $citype, $ciuser, $cores, $cpu, $cpulimit, $cpuunits, $description, $efidisk0, $force, $freeze, $hostpciN, $hotplug, $hugepages, $ideN, $ipconfigN, $keyboard, $kvm, $localtime, $lock, $machine, $memory, $migrate_downtime, $migrate_speed, $name, $nameserver, $netN, $numa, $numaN, $onboot, $ostype, $parallelN, $pool, $protection, $reboot, $sataN, $scsiN, $scsihw, $searchdomain, $serialN, $shares, $smbios1, $smp, $sockets, $sshkeys, $start, $startdate, $startup, $storage, $tablet, $tdf, $template, $unique, $unusedN, $usbN, $vcpus, $vga, $virtioN, $vmgenid, $vmstatestorage, $watchdog);
        }
    }

    /**
     * Class PVEItemQemuNodeNodesVmid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemQemuNodeNodesVmid extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * @ignore
         */
        private $firewall;

        /**
         * Get VmidQemuNodeNodesFirewall
         * @return PVEVmidQemuNodeNodesFirewall
         */
        public function getFirewall()
        {
            return $this->firewall ?: ($this->firewall = new PVEVmidQemuNodeNodesFirewall($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $agent;

        /**
         * Get VmidQemuNodeNodesAgent
         * @return PVEVmidQemuNodeNodesAgent
         */
        public function getAgent()
        {
            return $this->agent ?: ($this->agent = new PVEVmidQemuNodeNodesAgent($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $rrd;

        /**
         * Get VmidQemuNodeNodesRrd
         * @return PVEVmidQemuNodeNodesRrd
         */
        public function getRrd()
        {
            return $this->rrd ?: ($this->rrd = new PVEVmidQemuNodeNodesRrd($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $rrddata;

        /**
         * Get VmidQemuNodeNodesRrddata
         * @return PVEVmidQemuNodeNodesRrddata
         */
        public function getRrddata()
        {
            return $this->rrddata ?: ($this->rrddata = new PVEVmidQemuNodeNodesRrddata($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $config;

        /**
         * Get VmidQemuNodeNodesConfig
         * @return PVEVmidQemuNodeNodesConfig
         */
        public function getConfig()
        {
            return $this->config ?: ($this->config = new PVEVmidQemuNodeNodesConfig($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $pending;

        /**
         * Get VmidQemuNodeNodesPending
         * @return PVEVmidQemuNodeNodesPending
         */
        public function getPending()
        {
            return $this->pending ?: ($this->pending = new PVEVmidQemuNodeNodesPending($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $unlink;

        /**
         * Get VmidQemuNodeNodesUnlink
         * @return PVEVmidQemuNodeNodesUnlink
         */
        public function getUnlink()
        {
            return $this->unlink ?: ($this->unlink = new PVEVmidQemuNodeNodesUnlink($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $vncproxy;

        /**
         * Get VmidQemuNodeNodesVncproxy
         * @return PVEVmidQemuNodeNodesVncproxy
         */
        public function getVncproxy()
        {
            return $this->vncproxy ?: ($this->vncproxy = new PVEVmidQemuNodeNodesVncproxy($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $termproxy;

        /**
         * Get VmidQemuNodeNodesTermproxy
         * @return PVEVmidQemuNodeNodesTermproxy
         */
        public function getTermproxy()
        {
            return $this->termproxy ?: ($this->termproxy = new PVEVmidQemuNodeNodesTermproxy($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $vncwebsocket;

        /**
         * Get VmidQemuNodeNodesVncwebsocket
         * @return PVEVmidQemuNodeNodesVncwebsocket
         */
        public function getVncwebsocket()
        {
            return $this->vncwebsocket ?: ($this->vncwebsocket = new PVEVmidQemuNodeNodesVncwebsocket($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $spiceproxy;

        /**
         * Get VmidQemuNodeNodesSpiceproxy
         * @return PVEVmidQemuNodeNodesSpiceproxy
         */
        public function getSpiceproxy()
        {
            return $this->spiceproxy ?: ($this->spiceproxy = new PVEVmidQemuNodeNodesSpiceproxy($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $status;

        /**
         * Get VmidQemuNodeNodesStatus
         * @return PVEVmidQemuNodeNodesStatus
         */
        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVEVmidQemuNodeNodesStatus($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $sendkey;

        /**
         * Get VmidQemuNodeNodesSendkey
         * @return PVEVmidQemuNodeNodesSendkey
         */
        public function getSendkey()
        {
            return $this->sendkey ?: ($this->sendkey = new PVEVmidQemuNodeNodesSendkey($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $feature;

        /**
         * Get VmidQemuNodeNodesFeature
         * @return PVEVmidQemuNodeNodesFeature
         */
        public function getFeature()
        {
            return $this->feature ?: ($this->feature = new PVEVmidQemuNodeNodesFeature($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $clone;

        /**
         * Get VmidQemuNodeNodesClone
         * @return PVEVmidQemuNodeNodesClone
         */
        public function getClone()
        {
            return $this->clone ?: ($this->clone = new PVEVmidQemuNodeNodesClone($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $moveDisk;

        /**
         * Get VmidQemuNodeNodesMoveDisk
         * @return PVEVmidQemuNodeNodesMoveDisk
         */
        public function getMoveDisk()
        {
            return $this->moveDisk ?: ($this->moveDisk = new PVEVmidQemuNodeNodesMoveDisk($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $migrate;

        /**
         * Get VmidQemuNodeNodesMigrate
         * @return PVEVmidQemuNodeNodesMigrate
         */
        public function getMigrate()
        {
            return $this->migrate ?: ($this->migrate = new PVEVmidQemuNodeNodesMigrate($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $monitor;

        /**
         * Get VmidQemuNodeNodesMonitor
         * @return PVEVmidQemuNodeNodesMonitor
         */
        public function getMonitor()
        {
            return $this->monitor ?: ($this->monitor = new PVEVmidQemuNodeNodesMonitor($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $resize;

        /**
         * Get VmidQemuNodeNodesResize
         * @return PVEVmidQemuNodeNodesResize
         */
        public function getResize()
        {
            return $this->resize ?: ($this->resize = new PVEVmidQemuNodeNodesResize($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $snapshot;

        /**
         * Get VmidQemuNodeNodesSnapshot
         * @return PVEVmidQemuNodeNodesSnapshot
         */
        public function getSnapshot()
        {
            return $this->snapshot ?: ($this->snapshot = new PVEVmidQemuNodeNodesSnapshot($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $template;

        /**
         * Get VmidQemuNodeNodesTemplate
         * @return PVEVmidQemuNodeNodesTemplate
         */
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

    /**
     * Class PVEVmidQemuNodeNodesFirewall
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesFirewall extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * @ignore
         */
        private $rules;

        /**
         * Get FirewallVmidQemuNodeNodesRules
         * @return PVEFirewallVmidQemuNodeNodesRules
         */
        public function getRules()
        {
            return $this->rules ?: ($this->rules = new PVEFirewallVmidQemuNodeNodesRules($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $aliases;

        /**
         * Get FirewallVmidQemuNodeNodesAliases
         * @return PVEFirewallVmidQemuNodeNodesAliases
         */
        public function getAliases()
        {
            return $this->aliases ?: ($this->aliases = new PVEFirewallVmidQemuNodeNodesAliases($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $ipset;

        /**
         * Get FirewallVmidQemuNodeNodesIpset
         * @return PVEFirewallVmidQemuNodeNodesIpset
         */
        public function getIpset()
        {
            return $this->ipset ?: ($this->ipset = new PVEFirewallVmidQemuNodeNodesIpset($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $options;

        /**
         * Get FirewallVmidQemuNodeNodesOptions
         * @return PVEFirewallVmidQemuNodeNodesOptions
         */
        public function getOptions()
        {
            return $this->options ?: ($this->options = new PVEFirewallVmidQemuNodeNodesOptions($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $log;

        /**
         * Get FirewallVmidQemuNodeNodesLog
         * @return PVEFirewallVmidQemuNodeNodesLog
         */
        public function getLog()
        {
            return $this->log ?: ($this->log = new PVEFirewallVmidQemuNodeNodesLog($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $refs;

        /**
         * Get FirewallVmidQemuNodeNodesRefs
         * @return PVEFirewallVmidQemuNodeNodesRefs
         */
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

    /**
     * Class PVEFirewallVmidQemuNodeNodesRules
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallVmidQemuNodeNodesRules extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get ItemRulesFirewallVmidQemuNodeNodesPos
         * @param pos
         * @return PVEItemRulesFirewallVmidQemuNodeNodesPos
         */
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

    /**
     * Class PVEItemRulesFirewallVmidQemuNodeNodesPos
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemRulesFirewallVmidQemuNodeNodesPos extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $pos;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallVmidQemuNodeNodesAliases
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallVmidQemuNodeNodesAliases extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get ItemAliasesFirewallVmidQemuNodeNodesName
         * @param name
         * @return PVEItemAliasesFirewallVmidQemuNodeNodesName
         */
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

    /**
     * Class PVEItemAliasesFirewallVmidQemuNodeNodesName
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemAliasesFirewallVmidQemuNodeNodesName extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $name;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallVmidQemuNodeNodesIpset
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallVmidQemuNodeNodesIpset extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get ItemIpsetFirewallVmidQemuNodeNodesName
         * @param name
         * @return PVEItemIpsetFirewallVmidQemuNodeNodesName
         */
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

    /**
     * Class PVEItemIpsetFirewallVmidQemuNodeNodesName
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemIpsetFirewallVmidQemuNodeNodesName extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $name;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid, $name)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->name = $name;
        }

        /**
         * Get ItemNameIpsetFirewallVmidQemuNodeNodesCidr
         * @param cidr
         * @return PVEItemNameIpsetFirewallVmidQemuNodeNodesCidr
         */
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

    /**
     * Class PVEItemNameIpsetFirewallVmidQemuNodeNodesCidr
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemNameIpsetFirewallVmidQemuNodeNodesCidr extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $name;
        /**
         * @ignore
         */
        private $cidr;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallVmidQemuNodeNodesOptions
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallVmidQemuNodeNodesOptions extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallVmidQemuNodeNodesLog
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallVmidQemuNodeNodesLog extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallVmidQemuNodeNodesRefs
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallVmidQemuNodeNodesRefs extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesAgent
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesAgent extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * @ignore
         */
        private $fsfreeze_Freeze;

        /**
         * Get AgentVmidQemuNodeNodesFsfreeze_Freeze
         * @return PVEAgentVmidQemuNodeNodesFsfreeze_Freeze
         */
        public function getFsfreeze_Freeze()
        {
            return $this->fsfreeze_Freeze ?: ($this->fsfreeze_Freeze = new PVEAgentVmidQemuNodeNodesFsfreeze_Freeze($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $fsfreeze_Status;

        /**
         * Get AgentVmidQemuNodeNodesFsfreeze_Status
         * @return PVEAgentVmidQemuNodeNodesFsfreeze_Status
         */
        public function getFsfreeze_Status()
        {
            return $this->fsfreeze_Status ?: ($this->fsfreeze_Status = new PVEAgentVmidQemuNodeNodesFsfreeze_Status($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $fsfreeze_Thaw;

        /**
         * Get AgentVmidQemuNodeNodesFsfreeze_Thaw
         * @return PVEAgentVmidQemuNodeNodesFsfreeze_Thaw
         */
        public function getFsfreeze_Thaw()
        {
            return $this->fsfreeze_Thaw ?: ($this->fsfreeze_Thaw = new PVEAgentVmidQemuNodeNodesFsfreeze_Thaw($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $fstrim;

        /**
         * Get AgentVmidQemuNodeNodesFstrim
         * @return PVEAgentVmidQemuNodeNodesFstrim
         */
        public function getFstrim()
        {
            return $this->fstrim ?: ($this->fstrim = new PVEAgentVmidQemuNodeNodesFstrim($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $get_Fsinfo;

        /**
         * Get AgentVmidQemuNodeNodesGet_Fsinfo
         * @return PVEAgentVmidQemuNodeNodesGet_Fsinfo
         */
        public function getGet_Fsinfo()
        {
            return $this->get_Fsinfo ?: ($this->get_Fsinfo = new PVEAgentVmidQemuNodeNodesGet_Fsinfo($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $get_Host_Name;

        /**
         * Get AgentVmidQemuNodeNodesGet_Host_Name
         * @return PVEAgentVmidQemuNodeNodesGet_Host_Name
         */
        public function getGet_Host_Name()
        {
            return $this->get_Host_Name ?: ($this->get_Host_Name = new PVEAgentVmidQemuNodeNodesGet_Host_Name($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $get_Memory_Block_Info;

        /**
         * Get AgentVmidQemuNodeNodesGet_Memory_Block_Info
         * @return PVEAgentVmidQemuNodeNodesGet_Memory_Block_Info
         */
        public function getGet_Memory_Block_Info()
        {
            return $this->get_Memory_Block_Info ?: ($this->get_Memory_Block_Info = new PVEAgentVmidQemuNodeNodesGet_Memory_Block_Info($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $get_Memory_Blocks;

        /**
         * Get AgentVmidQemuNodeNodesGet_Memory_Blocks
         * @return PVEAgentVmidQemuNodeNodesGet_Memory_Blocks
         */
        public function getGet_Memory_Blocks()
        {
            return $this->get_Memory_Blocks ?: ($this->get_Memory_Blocks = new PVEAgentVmidQemuNodeNodesGet_Memory_Blocks($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $get_Osinfo;

        /**
         * Get AgentVmidQemuNodeNodesGet_Osinfo
         * @return PVEAgentVmidQemuNodeNodesGet_Osinfo
         */
        public function getGet_Osinfo()
        {
            return $this->get_Osinfo ?: ($this->get_Osinfo = new PVEAgentVmidQemuNodeNodesGet_Osinfo($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $get_Time;

        /**
         * Get AgentVmidQemuNodeNodesGet_Time
         * @return PVEAgentVmidQemuNodeNodesGet_Time
         */
        public function getGet_Time()
        {
            return $this->get_Time ?: ($this->get_Time = new PVEAgentVmidQemuNodeNodesGet_Time($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $get_Timezone;

        /**
         * Get AgentVmidQemuNodeNodesGet_Timezone
         * @return PVEAgentVmidQemuNodeNodesGet_Timezone
         */
        public function getGet_Timezone()
        {
            return $this->get_Timezone ?: ($this->get_Timezone = new PVEAgentVmidQemuNodeNodesGet_Timezone($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $get_Users;

        /**
         * Get AgentVmidQemuNodeNodesGet_Users
         * @return PVEAgentVmidQemuNodeNodesGet_Users
         */
        public function getGet_Users()
        {
            return $this->get_Users ?: ($this->get_Users = new PVEAgentVmidQemuNodeNodesGet_Users($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $get_Vcpus;

        /**
         * Get AgentVmidQemuNodeNodesGet_Vcpus
         * @return PVEAgentVmidQemuNodeNodesGet_Vcpus
         */
        public function getGet_Vcpus()
        {
            return $this->get_Vcpus ?: ($this->get_Vcpus = new PVEAgentVmidQemuNodeNodesGet_Vcpus($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $info;

        /**
         * Get AgentVmidQemuNodeNodesInfo
         * @return PVEAgentVmidQemuNodeNodesInfo
         */
        public function getInfo()
        {
            return $this->info ?: ($this->info = new PVEAgentVmidQemuNodeNodesInfo($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $network_Get_Interfaces;

        /**
         * Get AgentVmidQemuNodeNodesNetwork_Get_Interfaces
         * @return PVEAgentVmidQemuNodeNodesNetwork_Get_Interfaces
         */
        public function getNetwork_Get_Interfaces()
        {
            return $this->network_Get_Interfaces ?: ($this->network_Get_Interfaces = new PVEAgentVmidQemuNodeNodesNetwork_Get_Interfaces($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $ping;

        /**
         * Get AgentVmidQemuNodeNodesPing
         * @return PVEAgentVmidQemuNodeNodesPing
         */
        public function getPing()
        {
            return $this->ping ?: ($this->ping = new PVEAgentVmidQemuNodeNodesPing($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $shutdown;

        /**
         * Get AgentVmidQemuNodeNodesShutdown
         * @return PVEAgentVmidQemuNodeNodesShutdown
         */
        public function getShutdown()
        {
            return $this->shutdown ?: ($this->shutdown = new PVEAgentVmidQemuNodeNodesShutdown($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $suspend_Disk;

        /**
         * Get AgentVmidQemuNodeNodesSuspend_Disk
         * @return PVEAgentVmidQemuNodeNodesSuspend_Disk
         */
        public function getSuspend_Disk()
        {
            return $this->suspend_Disk ?: ($this->suspend_Disk = new PVEAgentVmidQemuNodeNodesSuspend_Disk($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $suspend_Hybrid;

        /**
         * Get AgentVmidQemuNodeNodesSuspend_Hybrid
         * @return PVEAgentVmidQemuNodeNodesSuspend_Hybrid
         */
        public function getSuspend_Hybrid()
        {
            return $this->suspend_Hybrid ?: ($this->suspend_Hybrid = new PVEAgentVmidQemuNodeNodesSuspend_Hybrid($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $suspend_Ram;

        /**
         * Get AgentVmidQemuNodeNodesSuspend_Ram
         * @return PVEAgentVmidQemuNodeNodesSuspend_Ram
         */
        public function getSuspend_Ram()
        {
            return $this->suspend_Ram ?: ($this->suspend_Ram = new PVEAgentVmidQemuNodeNodesSuspend_Ram($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $set_User_Password;

        /**
         * Get AgentVmidQemuNodeNodesSet_User_Password
         * @return PVEAgentVmidQemuNodeNodesSet_User_Password
         */
        public function getSet_User_Password()
        {
            return $this->set_User_Password ?: ($this->set_User_Password = new PVEAgentVmidQemuNodeNodesSet_User_Password($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $exec;

        /**
         * Get AgentVmidQemuNodeNodesExec
         * @return PVEAgentVmidQemuNodeNodesExec
         */
        public function getExec()
        {
            return $this->exec ?: ($this->exec = new PVEAgentVmidQemuNodeNodesExec($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $exec_Status;

        /**
         * Get AgentVmidQemuNodeNodesExec_Status
         * @return PVEAgentVmidQemuNodeNodesExec_Status
         */
        public function getExec_Status()
        {
            return $this->exec_Status ?: ($this->exec_Status = new PVEAgentVmidQemuNodeNodesExec_Status($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $file_Read;

        /**
         * Get AgentVmidQemuNodeNodesFile_Read
         * @return PVEAgentVmidQemuNodeNodesFile_Read
         */
        public function getFile_Read()
        {
            return $this->file_Read ?: ($this->file_Read = new PVEAgentVmidQemuNodeNodesFile_Read($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $file_Write;

        /**
         * Get AgentVmidQemuNodeNodesFile_Write
         * @return PVEAgentVmidQemuNodeNodesFile_Write
         */
        public function getFile_Write()
        {
            return $this->file_Write ?: ($this->file_Write = new PVEAgentVmidQemuNodeNodesFile_Write($this->client, $this->node, $this->vmid));
        }

        /**
         * Qemu Agent command index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent");
        }

        /**
         * Qemu Agent command index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Execute Qemu Guest Agent commands.
         * @param string $command The QGA command.
         *   Enum: fsfreeze-freeze,fsfreeze-status,fsfreeze-thaw,fstrim,get-fsinfo,get-host-name,get-memory-block-info,get-memory-blocks,get-osinfo,get-time,get-timezone,get-users,get-vcpus,info,network-get-interfaces,ping,shutdown,suspend-disk,suspend-hybrid,suspend-ram
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
         *   Enum: fsfreeze-freeze,fsfreeze-status,fsfreeze-thaw,fstrim,get-fsinfo,get-host-name,get-memory-block-info,get-memory-blocks,get-osinfo,get-time,get-timezone,get-users,get-vcpus,info,network-get-interfaces,ping,shutdown,suspend-disk,suspend-hybrid,suspend-ram
         * @return Result
         */
        public function agent($command)
        {
            return $this->createRest($command);
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesFsfreeze_Freeze
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesFsfreeze_Freeze extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute fsfreeze-freeze.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent/fsfreeze-freeze");
        }

        /**
         * Execute fsfreeze-freeze.
         * @return Result
         */
        public function fsfreeze_Freeze()
        {
            return $this->createRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesFsfreeze_Status
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesFsfreeze_Status extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute fsfreeze-status.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent/fsfreeze-status");
        }

        /**
         * Execute fsfreeze-status.
         * @return Result
         */
        public function fsfreeze_Status()
        {
            return $this->createRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesFsfreeze_Thaw
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesFsfreeze_Thaw extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute fsfreeze-thaw.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent/fsfreeze-thaw");
        }

        /**
         * Execute fsfreeze-thaw.
         * @return Result
         */
        public function fsfreeze_Thaw()
        {
            return $this->createRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesFstrim
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesFstrim extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute fstrim.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent/fstrim");
        }

        /**
         * Execute fstrim.
         * @return Result
         */
        public function fstrim()
        {
            return $this->createRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesGet_Fsinfo
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesGet_Fsinfo extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute get-fsinfo.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/get-fsinfo");
        }

        /**
         * Execute get-fsinfo.
         * @return Result
         */
        public function get_Fsinfo()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesGet_Host_Name
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesGet_Host_Name extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute get-host-name.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/get-host-name");
        }

        /**
         * Execute get-host-name.
         * @return Result
         */
        public function get_Host_Name()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesGet_Memory_Block_Info
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesGet_Memory_Block_Info extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute get-memory-block-info.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/get-memory-block-info");
        }

        /**
         * Execute get-memory-block-info.
         * @return Result
         */
        public function get_Memory_Block_Info()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesGet_Memory_Blocks
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesGet_Memory_Blocks extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute get-memory-blocks.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/get-memory-blocks");
        }

        /**
         * Execute get-memory-blocks.
         * @return Result
         */
        public function get_Memory_Blocks()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesGet_Osinfo
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesGet_Osinfo extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute get-osinfo.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/get-osinfo");
        }

        /**
         * Execute get-osinfo.
         * @return Result
         */
        public function get_Osinfo()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesGet_Time
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesGet_Time extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute get-time.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/get-time");
        }

        /**
         * Execute get-time.
         * @return Result
         */
        public function get_Time()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesGet_Timezone
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesGet_Timezone extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute get-timezone.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/get-timezone");
        }

        /**
         * Execute get-timezone.
         * @return Result
         */
        public function get_Timezone()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesGet_Users
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesGet_Users extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute get-users.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/get-users");
        }

        /**
         * Execute get-users.
         * @return Result
         */
        public function get_Users()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesGet_Vcpus
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesGet_Vcpus extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute get-vcpus.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/get-vcpus");
        }

        /**
         * Execute get-vcpus.
         * @return Result
         */
        public function get_Vcpus()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesInfo
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesInfo extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute info.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/info");
        }

        /**
         * Execute info.
         * @return Result
         */
        public function info()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesNetwork_Get_Interfaces
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesNetwork_Get_Interfaces extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute network-get-interfaces.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/network-get-interfaces");
        }

        /**
         * Execute network-get-interfaces.
         * @return Result
         */
        public function network_Get_Interfaces()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesPing
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesPing extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute ping.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent/ping");
        }

        /**
         * Execute ping.
         * @return Result
         */
        public function ping()
        {
            return $this->createRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesShutdown
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesShutdown extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute shutdown.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent/shutdown");
        }

        /**
         * Execute shutdown.
         * @return Result
         */
        public function shutdown()
        {
            return $this->createRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesSuspend_Disk
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesSuspend_Disk extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute suspend-disk.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent/suspend-disk");
        }

        /**
         * Execute suspend-disk.
         * @return Result
         */
        public function suspend_Disk()
        {
            return $this->createRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesSuspend_Hybrid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesSuspend_Hybrid extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute suspend-hybrid.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent/suspend-hybrid");
        }

        /**
         * Execute suspend-hybrid.
         * @return Result
         */
        public function suspend_Hybrid()
        {
            return $this->createRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesSuspend_Ram
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesSuspend_Ram extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Execute suspend-ram.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent/suspend-ram");
        }

        /**
         * Execute suspend-ram.
         * @return Result
         */
        public function suspend_Ram()
        {
            return $this->createRest();
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesSet_User_Password
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesSet_User_Password extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Sets the password for the given user to the given password
         * @param string $password The new password.
         * @param string $username The user to set the password for.
         * @param bool $crypted set to 1 if the password has already been passed through crypt()
         * @return Result
         */
        public function createRest($password, $username, $crypted = null)
        {
            $params = ['password' => $password,
                'username' => $username,
                'crypted' => $crypted];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent/set-user-password", $params);
        }

        /**
         * Sets the password for the given user to the given password
         * @param string $password The new password.
         * @param string $username The user to set the password for.
         * @param bool $crypted set to 1 if the password has already been passed through crypt()
         * @return Result
         */
        public function set_User_Password($password, $username, $crypted = null)
        {
            return $this->createRest($password, $username, $crypted);
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesExec
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesExec extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Executes the given command in the vm via the guest-agent and returns an object with the pid.
         * @param string $command The command as a list of program + arguments
         * @return Result
         */
        public function createRest($command)
        {
            $params = ['command' => $command];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent/exec", $params);
        }

        /**
         * Executes the given command in the vm via the guest-agent and returns an object with the pid.
         * @param string $command The command as a list of program + arguments
         * @return Result
         */
        public function exec($command)
        {
            return $this->createRest($command);
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesExec_Status
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesExec_Status extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Gets the status of the given pid started by the guest-agent
         * @param int $pid The PID to query
         * @return Result
         */
        public function getRest($pid)
        {
            $params = ['pid' => $pid];
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/exec-status", $params);
        }

        /**
         * Gets the status of the given pid started by the guest-agent
         * @param int $pid The PID to query
         * @return Result
         */
        public function exec_Status($pid)
        {
            return $this->getRest($pid);
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesFile_Read
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesFile_Read extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Reads the given file via guest agent. Is limited to 16777216 bytes.
         * @param string $file The path to the file
         * @return Result
         */
        public function getRest($file)
        {
            $params = ['file' => $file];
            return $this->getClient()->get("/nodes/{$this->node}/qemu/{$this->vmid}/agent/file-read", $params);
        }

        /**
         * Reads the given file via guest agent. Is limited to 16777216 bytes.
         * @param string $file The path to the file
         * @return Result
         */
        public function file_Read($file)
        {
            return $this->getRest($file);
        }
    }

    /**
     * Class PVEAgentVmidQemuNodeNodesFile_Write
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAgentVmidQemuNodeNodesFile_Write extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Writes the given file via guest agent.
         * @param string $content The content to write into the file.
         * @param string $file The path to the file.
         * @return Result
         */
        public function createRest($content, $file)
        {
            $params = ['content' => $content,
                'file' => $file];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/agent/file-write", $params);
        }

        /**
         * Writes the given file via guest agent.
         * @param string $content The content to write into the file.
         * @param string $file The path to the file.
         * @return Result
         */
        public function file_Write($content, $file)
        {
            return $this->createRest($content, $file);
        }
    }

    /**
     * Class PVEVmidQemuNodeNodesRrd
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesRrd extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesRrddata
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesRrddata extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesConfig
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesConfig extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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
         * @param string $agent Enable/disable Qemu GuestAgent and its properties.
         * @param string $arch Virtual processor architecture. Defaults to the host.
         *   Enum: x86_64,aarch64
         * @param string $args Arbitrary arguments passed to kvm.
         * @param bool $autostart Automatic restart after crash (currently ignored).
         * @param int $background_delay Time to wait for the task to finish. We return 'null' if the task finish within that time.
         * @param int $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param string $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param string $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param string $bootdisk Enable booting from specified disk.
         * @param string $cdrom This is an alias for option -ide2
         * @param string $cipassword cloud-init: Password to assign the user. Using this is generally not recommended. Use ssh keys instead. Also note that older cloud-init versions do not support hashed passwords.
         * @param string $citype Specifies the cloud-init configuration format. The default depends on the configured operating system type (`ostype`. We use the `nocloud` format for Linux, and `configdrive2` for windows.
         *   Enum: configdrive2,nocloud
         * @param string $ciuser cloud-init: User name to change ssh keys and password for instead of the image's configured default user.
         * @param int $cores The number of cores per socket.
         * @param string $cpu Emulated CPU type.
         * @param int $cpulimit Limit of CPU usage.
         * @param int $cpuunits CPU weight for a VM.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $efidisk0 Configure a Disk for storing EFI vars
         * @param bool $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         * @param bool $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param array $hostpciN Map host PCI devices into guest.
         * @param string $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param string $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param array $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param array $ipconfigN cloud-init: Specify IP addresses and gateways for the corresponding interface.  IP addresses use CIDR notation, gateways are optional but need an IP of the same type specified.  The special string 'dhcp' can be used for IP addresses to use DHCP, in which case no explicit gateway should be provided. For IPv6 the special string 'auto' can be used to use stateless autoconfiguration.  If cloud-init is enabled and neither an IPv4 nor an IPv6 address is specified, it defaults to using dhcp on IPv4.
         * @param string $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.cfg' configuration file.It should not be necessary to set it.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param bool $kvm Enable/disable KVM hardware virtualization.
         * @param bool $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param string $machine Specifies the Qemu machine type.
         * @param int $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param int $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param int $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param string $name Set a name for the VM. Only used on the configuration web interface.
         * @param string $nameserver cloud-init: Sets DNS server IP address for a container. Create will automatically use the setting from the host if neither searchdomain nor nameserver are set.
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
         * @param string $searchdomain cloud-init: Sets DNS search domains for a container. Create will automatically use the setting from the host if neither searchdomain nor nameserver are set.
         * @param array $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param int $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning. Auto-ballooning is done by pvestatd.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param string $smbios1 Specify SMBIOS type 1 fields.
         * @param int $smp The number of CPUs. Please use option -sockets instead.
         * @param int $sockets The number of CPU sockets.
         * @param string $sshkeys cloud-init: Setup public SSH keys (one key per line, OpenSSH format).
         * @param string $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param bool $tablet Enable/disable the USB tablet device.
         * @param bool $tdf Enable/disable time drift fix.
         * @param bool $template Enable/disable Template.
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param array $usbN Configure an USB device (n is 0 to 4).
         * @param int $vcpus Number of hotplugged vcpus.
         * @param string $vga Configure the VGA hardware.
         * @param array $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param string $vmgenid Set VM Generation ID. Use '1' to autogenerate on create or update, pass '0' to disable explicitly.
         * @param string $vmstatestorage Default storage for VM state volumes/files.
         * @param string $watchdog Create a virtual hardware watchdog device.
         * @return Result
         */
        public function createRest($acpi = null, $agent = null, $arch = null, $args = null, $autostart = null, $background_delay = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cipassword = null, $citype = null, $ciuser = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $efidisk0 = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $ipconfigN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $nameserver = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $protection = null, $reboot = null, $revert = null, $sataN = null, $scsiN = null, $scsihw = null, $searchdomain = null, $serialN = null, $shares = null, $skiplock = null, $smbios1 = null, $smp = null, $sockets = null, $sshkeys = null, $startdate = null, $startup = null, $tablet = null, $tdf = null, $template = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $vmgenid = null, $vmstatestorage = null, $watchdog = null)
        {
            $params = ['acpi' => $acpi,
                'agent' => $agent,
                'arch' => $arch,
                'args' => $args,
                'autostart' => $autostart,
                'background_delay' => $background_delay,
                'balloon' => $balloon,
                'bios' => $bios,
                'boot' => $boot,
                'bootdisk' => $bootdisk,
                'cdrom' => $cdrom,
                'cipassword' => $cipassword,
                'citype' => $citype,
                'ciuser' => $ciuser,
                'cores' => $cores,
                'cpu' => $cpu,
                'cpulimit' => $cpulimit,
                'cpuunits' => $cpuunits,
                'delete' => $delete,
                'description' => $description,
                'digest' => $digest,
                'efidisk0' => $efidisk0,
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
                'nameserver' => $nameserver,
                'numa' => $numa,
                'onboot' => $onboot,
                'ostype' => $ostype,
                'protection' => $protection,
                'reboot' => $reboot,
                'revert' => $revert,
                'scsihw' => $scsihw,
                'searchdomain' => $searchdomain,
                'shares' => $shares,
                'skiplock' => $skiplock,
                'smbios1' => $smbios1,
                'smp' => $smp,
                'sockets' => $sockets,
                'sshkeys' => $sshkeys,
                'startdate' => $startdate,
                'startup' => $startup,
                'tablet' => $tablet,
                'tdf' => $tdf,
                'template' => $template,
                'vcpus' => $vcpus,
                'vga' => $vga,
                'vmgenid' => $vmgenid,
                'vmstatestorage' => $vmstatestorage,
                'watchdog' => $watchdog];
            $this->addIndexedParameter($params, 'hostpci', $hostpciN);
            $this->addIndexedParameter($params, 'ide', $ideN);
            $this->addIndexedParameter($params, 'ipconfig', $ipconfigN);
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
         * @param string $agent Enable/disable Qemu GuestAgent and its properties.
         * @param string $arch Virtual processor architecture. Defaults to the host.
         *   Enum: x86_64,aarch64
         * @param string $args Arbitrary arguments passed to kvm.
         * @param bool $autostart Automatic restart after crash (currently ignored).
         * @param int $background_delay Time to wait for the task to finish. We return 'null' if the task finish within that time.
         * @param int $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param string $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param string $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param string $bootdisk Enable booting from specified disk.
         * @param string $cdrom This is an alias for option -ide2
         * @param string $cipassword cloud-init: Password to assign the user. Using this is generally not recommended. Use ssh keys instead. Also note that older cloud-init versions do not support hashed passwords.
         * @param string $citype Specifies the cloud-init configuration format. The default depends on the configured operating system type (`ostype`. We use the `nocloud` format for Linux, and `configdrive2` for windows.
         *   Enum: configdrive2,nocloud
         * @param string $ciuser cloud-init: User name to change ssh keys and password for instead of the image's configured default user.
         * @param int $cores The number of cores per socket.
         * @param string $cpu Emulated CPU type.
         * @param int $cpulimit Limit of CPU usage.
         * @param int $cpuunits CPU weight for a VM.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $efidisk0 Configure a Disk for storing EFI vars
         * @param bool $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         * @param bool $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param array $hostpciN Map host PCI devices into guest.
         * @param string $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param string $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param array $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param array $ipconfigN cloud-init: Specify IP addresses and gateways for the corresponding interface.  IP addresses use CIDR notation, gateways are optional but need an IP of the same type specified.  The special string 'dhcp' can be used for IP addresses to use DHCP, in which case no explicit gateway should be provided. For IPv6 the special string 'auto' can be used to use stateless autoconfiguration.  If cloud-init is enabled and neither an IPv4 nor an IPv6 address is specified, it defaults to using dhcp on IPv4.
         * @param string $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.cfg' configuration file.It should not be necessary to set it.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param bool $kvm Enable/disable KVM hardware virtualization.
         * @param bool $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param string $machine Specifies the Qemu machine type.
         * @param int $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param int $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param int $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param string $name Set a name for the VM. Only used on the configuration web interface.
         * @param string $nameserver cloud-init: Sets DNS server IP address for a container. Create will automatically use the setting from the host if neither searchdomain nor nameserver are set.
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
         * @param string $searchdomain cloud-init: Sets DNS search domains for a container. Create will automatically use the setting from the host if neither searchdomain nor nameserver are set.
         * @param array $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param int $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning. Auto-ballooning is done by pvestatd.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param string $smbios1 Specify SMBIOS type 1 fields.
         * @param int $smp The number of CPUs. Please use option -sockets instead.
         * @param int $sockets The number of CPU sockets.
         * @param string $sshkeys cloud-init: Setup public SSH keys (one key per line, OpenSSH format).
         * @param string $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param bool $tablet Enable/disable the USB tablet device.
         * @param bool $tdf Enable/disable time drift fix.
         * @param bool $template Enable/disable Template.
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param array $usbN Configure an USB device (n is 0 to 4).
         * @param int $vcpus Number of hotplugged vcpus.
         * @param string $vga Configure the VGA hardware.
         * @param array $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param string $vmgenid Set VM Generation ID. Use '1' to autogenerate on create or update, pass '0' to disable explicitly.
         * @param string $vmstatestorage Default storage for VM state volumes/files.
         * @param string $watchdog Create a virtual hardware watchdog device.
         * @return Result
         */
        public function updateVmAsync($acpi = null, $agent = null, $arch = null, $args = null, $autostart = null, $background_delay = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cipassword = null, $citype = null, $ciuser = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $efidisk0 = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $ipconfigN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $nameserver = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $protection = null, $reboot = null, $revert = null, $sataN = null, $scsiN = null, $scsihw = null, $searchdomain = null, $serialN = null, $shares = null, $skiplock = null, $smbios1 = null, $smp = null, $sockets = null, $sshkeys = null, $startdate = null, $startup = null, $tablet = null, $tdf = null, $template = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $vmgenid = null, $vmstatestorage = null, $watchdog = null)
        {
            return $this->createRest($acpi, $agent, $arch, $args, $autostart, $background_delay, $balloon, $bios, $boot, $bootdisk, $cdrom, $cipassword, $citype, $ciuser, $cores, $cpu, $cpulimit, $cpuunits, $delete, $description, $digest, $efidisk0, $force, $freeze, $hostpciN, $hotplug, $hugepages, $ideN, $ipconfigN, $keyboard, $kvm, $localtime, $lock, $machine, $memory, $migrate_downtime, $migrate_speed, $name, $nameserver, $netN, $numa, $numaN, $onboot, $ostype, $parallelN, $protection, $reboot, $revert, $sataN, $scsiN, $scsihw, $searchdomain, $serialN, $shares, $skiplock, $smbios1, $smp, $sockets, $sshkeys, $startdate, $startup, $tablet, $tdf, $template, $unusedN, $usbN, $vcpus, $vga, $virtioN, $vmgenid, $vmstatestorage, $watchdog);
        }

        /**
         * Set virtual machine options (synchrounous API) - You should consider using the POST method instead for any actions involving hotplug or storage allocation.
         * @param bool $acpi Enable/disable ACPI.
         * @param string $agent Enable/disable Qemu GuestAgent and its properties.
         * @param string $arch Virtual processor architecture. Defaults to the host.
         *   Enum: x86_64,aarch64
         * @param string $args Arbitrary arguments passed to kvm.
         * @param bool $autostart Automatic restart after crash (currently ignored).
         * @param int $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param string $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param string $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param string $bootdisk Enable booting from specified disk.
         * @param string $cdrom This is an alias for option -ide2
         * @param string $cipassword cloud-init: Password to assign the user. Using this is generally not recommended. Use ssh keys instead. Also note that older cloud-init versions do not support hashed passwords.
         * @param string $citype Specifies the cloud-init configuration format. The default depends on the configured operating system type (`ostype`. We use the `nocloud` format for Linux, and `configdrive2` for windows.
         *   Enum: configdrive2,nocloud
         * @param string $ciuser cloud-init: User name to change ssh keys and password for instead of the image's configured default user.
         * @param int $cores The number of cores per socket.
         * @param string $cpu Emulated CPU type.
         * @param int $cpulimit Limit of CPU usage.
         * @param int $cpuunits CPU weight for a VM.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $efidisk0 Configure a Disk for storing EFI vars
         * @param bool $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         * @param bool $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param array $hostpciN Map host PCI devices into guest.
         * @param string $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param string $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param array $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param array $ipconfigN cloud-init: Specify IP addresses and gateways for the corresponding interface.  IP addresses use CIDR notation, gateways are optional but need an IP of the same type specified.  The special string 'dhcp' can be used for IP addresses to use DHCP, in which case no explicit gateway should be provided. For IPv6 the special string 'auto' can be used to use stateless autoconfiguration.  If cloud-init is enabled and neither an IPv4 nor an IPv6 address is specified, it defaults to using dhcp on IPv4.
         * @param string $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.cfg' configuration file.It should not be necessary to set it.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param bool $kvm Enable/disable KVM hardware virtualization.
         * @param bool $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param string $machine Specifies the Qemu machine type.
         * @param int $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param int $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param int $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param string $name Set a name for the VM. Only used on the configuration web interface.
         * @param string $nameserver cloud-init: Sets DNS server IP address for a container. Create will automatically use the setting from the host if neither searchdomain nor nameserver are set.
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
         * @param string $searchdomain cloud-init: Sets DNS search domains for a container. Create will automatically use the setting from the host if neither searchdomain nor nameserver are set.
         * @param array $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param int $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning. Auto-ballooning is done by pvestatd.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param string $smbios1 Specify SMBIOS type 1 fields.
         * @param int $smp The number of CPUs. Please use option -sockets instead.
         * @param int $sockets The number of CPU sockets.
         * @param string $sshkeys cloud-init: Setup public SSH keys (one key per line, OpenSSH format).
         * @param string $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param bool $tablet Enable/disable the USB tablet device.
         * @param bool $tdf Enable/disable time drift fix.
         * @param bool $template Enable/disable Template.
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param array $usbN Configure an USB device (n is 0 to 4).
         * @param int $vcpus Number of hotplugged vcpus.
         * @param string $vga Configure the VGA hardware.
         * @param array $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param string $vmgenid Set VM Generation ID. Use '1' to autogenerate on create or update, pass '0' to disable explicitly.
         * @param string $vmstatestorage Default storage for VM state volumes/files.
         * @param string $watchdog Create a virtual hardware watchdog device.
         * @return Result
         */
        public function setRest($acpi = null, $agent = null, $arch = null, $args = null, $autostart = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cipassword = null, $citype = null, $ciuser = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $efidisk0 = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $ipconfigN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $nameserver = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $protection = null, $reboot = null, $revert = null, $sataN = null, $scsiN = null, $scsihw = null, $searchdomain = null, $serialN = null, $shares = null, $skiplock = null, $smbios1 = null, $smp = null, $sockets = null, $sshkeys = null, $startdate = null, $startup = null, $tablet = null, $tdf = null, $template = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $vmgenid = null, $vmstatestorage = null, $watchdog = null)
        {
            $params = ['acpi' => $acpi,
                'agent' => $agent,
                'arch' => $arch,
                'args' => $args,
                'autostart' => $autostart,
                'balloon' => $balloon,
                'bios' => $bios,
                'boot' => $boot,
                'bootdisk' => $bootdisk,
                'cdrom' => $cdrom,
                'cipassword' => $cipassword,
                'citype' => $citype,
                'ciuser' => $ciuser,
                'cores' => $cores,
                'cpu' => $cpu,
                'cpulimit' => $cpulimit,
                'cpuunits' => $cpuunits,
                'delete' => $delete,
                'description' => $description,
                'digest' => $digest,
                'efidisk0' => $efidisk0,
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
                'nameserver' => $nameserver,
                'numa' => $numa,
                'onboot' => $onboot,
                'ostype' => $ostype,
                'protection' => $protection,
                'reboot' => $reboot,
                'revert' => $revert,
                'scsihw' => $scsihw,
                'searchdomain' => $searchdomain,
                'shares' => $shares,
                'skiplock' => $skiplock,
                'smbios1' => $smbios1,
                'smp' => $smp,
                'sockets' => $sockets,
                'sshkeys' => $sshkeys,
                'startdate' => $startdate,
                'startup' => $startup,
                'tablet' => $tablet,
                'tdf' => $tdf,
                'template' => $template,
                'vcpus' => $vcpus,
                'vga' => $vga,
                'vmgenid' => $vmgenid,
                'vmstatestorage' => $vmstatestorage,
                'watchdog' => $watchdog];
            $this->addIndexedParameter($params, 'hostpci', $hostpciN);
            $this->addIndexedParameter($params, 'ide', $ideN);
            $this->addIndexedParameter($params, 'ipconfig', $ipconfigN);
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
         * @param string $agent Enable/disable Qemu GuestAgent and its properties.
         * @param string $arch Virtual processor architecture. Defaults to the host.
         *   Enum: x86_64,aarch64
         * @param string $args Arbitrary arguments passed to kvm.
         * @param bool $autostart Automatic restart after crash (currently ignored).
         * @param int $balloon Amount of target RAM for the VM in MB. Using zero disables the ballon driver.
         * @param string $bios Select BIOS implementation.
         *   Enum: seabios,ovmf
         * @param string $boot Boot on floppy (a), hard disk (c), CD-ROM (d), or network (n).
         * @param string $bootdisk Enable booting from specified disk.
         * @param string $cdrom This is an alias for option -ide2
         * @param string $cipassword cloud-init: Password to assign the user. Using this is generally not recommended. Use ssh keys instead. Also note that older cloud-init versions do not support hashed passwords.
         * @param string $citype Specifies the cloud-init configuration format. The default depends on the configured operating system type (`ostype`. We use the `nocloud` format for Linux, and `configdrive2` for windows.
         *   Enum: configdrive2,nocloud
         * @param string $ciuser cloud-init: User name to change ssh keys and password for instead of the image's configured default user.
         * @param int $cores The number of cores per socket.
         * @param string $cpu Emulated CPU type.
         * @param int $cpulimit Limit of CPU usage.
         * @param int $cpuunits CPU weight for a VM.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Description for the VM. Only used on the configuration web interface. This is saved as comment inside the configuration file.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $efidisk0 Configure a Disk for storing EFI vars
         * @param bool $force Force physical removal. Without this, we simple remove the disk from the config file and create an additional configuration entry called 'unused[n]', which contains the volume ID. Unlink of unused[n] always cause physical removal.
         * @param bool $freeze Freeze CPU at startup (use 'c' monitor command to start execution).
         * @param array $hostpciN Map host PCI devices into guest.
         * @param string $hotplug Selectively enable hotplug features. This is a comma separated list of hotplug features: 'network', 'disk', 'cpu', 'memory' and 'usb'. Use '0' to disable hotplug completely. Value '1' is an alias for the default 'network,disk,usb'.
         * @param string $hugepages Enable/disable hugepages memory.
         *   Enum: any,2,1024
         * @param array $ideN Use volume as IDE hard disk or CD-ROM (n is 0 to 3).
         * @param array $ipconfigN cloud-init: Specify IP addresses and gateways for the corresponding interface.  IP addresses use CIDR notation, gateways are optional but need an IP of the same type specified.  The special string 'dhcp' can be used for IP addresses to use DHCP, in which case no explicit gateway should be provided. For IPv6 the special string 'auto' can be used to use stateless autoconfiguration.  If cloud-init is enabled and neither an IPv4 nor an IPv6 address is specified, it defaults to using dhcp on IPv4.
         * @param string $keyboard Keybord layout for vnc server. Default is read from the '/etc/pve/datacenter.cfg' configuration file.It should not be necessary to set it.
         *   Enum: de,de-ch,da,en-gb,en-us,es,fi,fr,fr-be,fr-ca,fr-ch,hu,is,it,ja,lt,mk,nl,no,pl,pt,pt-br,sv,sl,tr
         * @param bool $kvm Enable/disable KVM hardware virtualization.
         * @param bool $localtime Set the real time clock to local time. This is enabled by default if ostype indicates a Microsoft OS.
         * @param string $lock Lock/unlock the VM.
         *   Enum: migrate,backup,snapshot,rollback
         * @param string $machine Specifies the Qemu machine type.
         * @param int $memory Amount of RAM for the VM in MB. This is the maximum available memory when you use the balloon device.
         * @param int $migrate_downtime Set maximum tolerated downtime (in seconds) for migrations.
         * @param int $migrate_speed Set maximum speed (in MB/s) for migrations. Value 0 is no limit.
         * @param string $name Set a name for the VM. Only used on the configuration web interface.
         * @param string $nameserver cloud-init: Sets DNS server IP address for a container. Create will automatically use the setting from the host if neither searchdomain nor nameserver are set.
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
         * @param string $searchdomain cloud-init: Sets DNS search domains for a container. Create will automatically use the setting from the host if neither searchdomain nor nameserver are set.
         * @param array $serialN Create a serial device inside the VM (n is 0 to 3)
         * @param int $shares Amount of memory shares for auto-ballooning. The larger the number is, the more memory this VM gets. Number is relative to weights of all other running VMs. Using zero disables auto-ballooning. Auto-ballooning is done by pvestatd.
         * @param bool $skiplock Ignore locks - only root is allowed to use this option.
         * @param string $smbios1 Specify SMBIOS type 1 fields.
         * @param int $smp The number of CPUs. Please use option -sockets instead.
         * @param int $sockets The number of CPU sockets.
         * @param string $sshkeys cloud-init: Setup public SSH keys (one key per line, OpenSSH format).
         * @param string $startdate Set the initial date of the real time clock. Valid format for date are: 'now' or '2006-06-17T16:01:21' or '2006-06-17'.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param bool $tablet Enable/disable the USB tablet device.
         * @param bool $tdf Enable/disable time drift fix.
         * @param bool $template Enable/disable Template.
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @param array $usbN Configure an USB device (n is 0 to 4).
         * @param int $vcpus Number of hotplugged vcpus.
         * @param string $vga Configure the VGA hardware.
         * @param array $virtioN Use volume as VIRTIO hard disk (n is 0 to 15).
         * @param string $vmgenid Set VM Generation ID. Use '1' to autogenerate on create or update, pass '0' to disable explicitly.
         * @param string $vmstatestorage Default storage for VM state volumes/files.
         * @param string $watchdog Create a virtual hardware watchdog device.
         * @return Result
         */
        public function updateVm($acpi = null, $agent = null, $arch = null, $args = null, $autostart = null, $balloon = null, $bios = null, $boot = null, $bootdisk = null, $cdrom = null, $cipassword = null, $citype = null, $ciuser = null, $cores = null, $cpu = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $efidisk0 = null, $force = null, $freeze = null, $hostpciN = null, $hotplug = null, $hugepages = null, $ideN = null, $ipconfigN = null, $keyboard = null, $kvm = null, $localtime = null, $lock = null, $machine = null, $memory = null, $migrate_downtime = null, $migrate_speed = null, $name = null, $nameserver = null, $netN = null, $numa = null, $numaN = null, $onboot = null, $ostype = null, $parallelN = null, $protection = null, $reboot = null, $revert = null, $sataN = null, $scsiN = null, $scsihw = null, $searchdomain = null, $serialN = null, $shares = null, $skiplock = null, $smbios1 = null, $smp = null, $sockets = null, $sshkeys = null, $startdate = null, $startup = null, $tablet = null, $tdf = null, $template = null, $unusedN = null, $usbN = null, $vcpus = null, $vga = null, $virtioN = null, $vmgenid = null, $vmstatestorage = null, $watchdog = null)
        {
            return $this->setRest($acpi, $agent, $arch, $args, $autostart, $balloon, $bios, $boot, $bootdisk, $cdrom, $cipassword, $citype, $ciuser, $cores, $cpu, $cpulimit, $cpuunits, $delete, $description, $digest, $efidisk0, $force, $freeze, $hostpciN, $hotplug, $hugepages, $ideN, $ipconfigN, $keyboard, $kvm, $localtime, $lock, $machine, $memory, $migrate_downtime, $migrate_speed, $name, $nameserver, $netN, $numa, $numaN, $onboot, $ostype, $parallelN, $protection, $reboot, $revert, $sataN, $scsiN, $scsihw, $searchdomain, $serialN, $shares, $skiplock, $smbios1, $smp, $sockets, $sshkeys, $startdate, $startup, $tablet, $tdf, $template, $unusedN, $usbN, $vcpus, $vga, $virtioN, $vmgenid, $vmstatestorage, $watchdog);
        }
    }

    /**
     * Class PVEVmidQemuNodeNodesPending
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesPending extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesUnlink
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesUnlink extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesVncproxy
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesVncproxy extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesTermproxy
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesTermproxy extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Creates a TCP proxy connections.
         * @param string $serial opens a serial terminal (defaults to display)
         *   Enum: serial0,serial1,serial2,serial3
         * @return Result
         */
        public function createRest($serial = null)
        {
            $params = ['serial' => $serial];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/termproxy", $params);
        }

        /**
         * Creates a TCP proxy connections.
         * @param string $serial opens a serial terminal (defaults to display)
         *   Enum: serial0,serial1,serial2,serial3
         * @return Result
         */
        public function termproxy($serial = null)
        {
            return $this->createRest($serial);
        }
    }

    /**
     * Class PVEVmidQemuNodeNodesVncwebsocket
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesVncwebsocket extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesSpiceproxy
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesSpiceproxy extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Returns a SPICE configuration to connect to the VM.
         * @param string $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As reasonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @return Result
         */
        public function createRest($proxy = null)
        {
            $params = ['proxy' => $proxy];
            return $this->getClient()->create("/nodes/{$this->node}/qemu/{$this->vmid}/spiceproxy", $params);
        }

        /**
         * Returns a SPICE configuration to connect to the VM.
         * @param string $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As reasonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @return Result
         */
        public function spiceproxy($proxy = null)
        {
            return $this->createRest($proxy);
        }
    }

    /**
     * Class PVEVmidQemuNodeNodesStatus
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesStatus extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * @ignore
         */
        private $current;

        /**
         * Get StatusVmidQemuNodeNodesCurrent
         * @return PVEStatusVmidQemuNodeNodesCurrent
         */
        public function getCurrent()
        {
            return $this->current ?: ($this->current = new PVEStatusVmidQemuNodeNodesCurrent($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $start;

        /**
         * Get StatusVmidQemuNodeNodesStart
         * @return PVEStatusVmidQemuNodeNodesStart
         */
        public function getStart()
        {
            return $this->start ?: ($this->start = new PVEStatusVmidQemuNodeNodesStart($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $stop;

        /**
         * Get StatusVmidQemuNodeNodesStop
         * @return PVEStatusVmidQemuNodeNodesStop
         */
        public function getStop()
        {
            return $this->stop ?: ($this->stop = new PVEStatusVmidQemuNodeNodesStop($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $reset;

        /**
         * Get StatusVmidQemuNodeNodesReset
         * @return PVEStatusVmidQemuNodeNodesReset
         */
        public function getReset()
        {
            return $this->reset ?: ($this->reset = new PVEStatusVmidQemuNodeNodesReset($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $shutdown;

        /**
         * Get StatusVmidQemuNodeNodesShutdown
         * @return PVEStatusVmidQemuNodeNodesShutdown
         */
        public function getShutdown()
        {
            return $this->shutdown ?: ($this->shutdown = new PVEStatusVmidQemuNodeNodesShutdown($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $suspend;

        /**
         * Get StatusVmidQemuNodeNodesSuspend
         * @return PVEStatusVmidQemuNodeNodesSuspend
         */
        public function getSuspend()
        {
            return $this->suspend ?: ($this->suspend = new PVEStatusVmidQemuNodeNodesSuspend($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $resume;

        /**
         * Get StatusVmidQemuNodeNodesResume
         * @return PVEStatusVmidQemuNodeNodesResume
         */
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

    /**
     * Class PVEStatusVmidQemuNodeNodesCurrent
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidQemuNodeNodesCurrent extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStatusVmidQemuNodeNodesStart
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidQemuNodeNodesStart extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Start virtual machine.
         * @param string $machine Specifies the Qemu machine type.
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
         * @param string $machine Specifies the Qemu machine type.
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

    /**
     * Class PVEStatusVmidQemuNodeNodesStop
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidQemuNodeNodesStop extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStatusVmidQemuNodeNodesReset
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidQemuNodeNodesReset extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStatusVmidQemuNodeNodesShutdown
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidQemuNodeNodesShutdown extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStatusVmidQemuNodeNodesSuspend
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidQemuNodeNodesSuspend extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStatusVmidQemuNodeNodesResume
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidQemuNodeNodesResume extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesSendkey
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesSendkey extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesFeature
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesFeature extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesClone
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesClone extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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
         * @param string $format Target format for file storage. Only valid for full clone.
         *   Enum: raw,qcow2,vmdk
         * @param bool $full Create a full copy of all disks. This is always done when you clone a normal VM. For VM templates, we try to create a linked clone by default.
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
         * @param string $format Target format for file storage. Only valid for full clone.
         *   Enum: raw,qcow2,vmdk
         * @param bool $full Create a full copy of all disks. This is always done when you clone a normal VM. For VM templates, we try to create a linked clone by default.
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

    /**
     * Class PVEVmidQemuNodeNodesMoveDisk
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesMoveDisk extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesMigrate
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesMigrate extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesMonitor
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesMonitor extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesResize
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesResize extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesSnapshot
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesSnapshot extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get ItemSnapshotVmidQemuNodeNodesSnapname
         * @param snapname
         * @return PVEItemSnapshotVmidQemuNodeNodesSnapname
         */
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

    /**
     * Class PVEItemSnapshotVmidQemuNodeNodesSnapname
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemSnapshotVmidQemuNodeNodesSnapname extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $snapname;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid, $snapname)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->snapname = $snapname;
        }

        /**
         * @ignore
         */
        private $config;

        /**
         * Get SnapnameSnapshotVmidQemuNodeNodesConfig
         * @return PVESnapnameSnapshotVmidQemuNodeNodesConfig
         */
        public function getConfig()
        {
            return $this->config ?: ($this->config = new PVESnapnameSnapshotVmidQemuNodeNodesConfig($this->client, $this->node, $this->vmid, $this->snapname));
        }

        /**
         * @ignore
         */
        private $rollback;

        /**
         * Get SnapnameSnapshotVmidQemuNodeNodesRollback
         * @return PVESnapnameSnapshotVmidQemuNodeNodesRollback
         */
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

    /**
     * Class PVESnapnameSnapshotVmidQemuNodeNodesConfig
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVESnapnameSnapshotVmidQemuNodeNodesConfig extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $snapname;

        /**
         * @ignore
         */
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

    /**
     * Class PVESnapnameSnapshotVmidQemuNodeNodesRollback
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVESnapnameSnapshotVmidQemuNodeNodesRollback extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $snapname;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidQemuNodeNodesTemplate
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidQemuNodeNodesTemplate extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesLxc
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesLxc extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemLxcNodeNodesVmid
         * @param vmid
         * @return PVEItemLxcNodeNodesVmid
         */
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
         *   Enum: amd64,i386,arm64,armhf
         * @param int $bwlimit Override i/o bandwidth limit (in KiB/s).
         * @param string $cmode Console mode. By default, the console command tries to open a connection to one of the available tty devices. By setting cmode to 'console' it tries to attach to /dev/console instead. If you set cmode to 'shell', it simply invokes a shell inside the container (no login).
         *   Enum: shell,console,tty
         * @param bool $console Attach a console device (/dev/console) to the container.
         * @param int $cores The number of cores assigned to the container. A container can use all available cores by default.
         * @param int $cpulimit Limit of CPU usage.  NOTE: If the computer has 2 CPUs, it has a total of '2' CPU time. Value '0' indicates no CPU limit.
         * @param int $cpuunits CPU weight for a VM. Argument is used in the kernel fair scheduler. The larger the number is, the more CPU time this VM gets. Number is relative to the weights of all the other running VMs.  NOTE: You can disable fair-scheduler configuration by setting this to 0.
         * @param string $description Container description. Only used on the configuration web interface.
         * @param string $features Allow containers access to advanced features.
         * @param bool $force Allow to overwrite existing container.
         * @param string $hostname Set a host name for the container.
         * @param bool $ignore_unpack_errors Ignore errors when extracting the template.
         * @param string $lock Lock/unlock the VM.
         *   Enum: backup,disk,migrate,mounted,rollback,snapshot,snapshot-delete
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
         * @param bool $start Start the CT after its creation finished successfully.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param string $storage Default Storage.
         * @param int $swap Amount of SWAP for the VM in MB.
         * @param bool $template Enable/disable Template.
         * @param int $tty Specify the number of tty available to the container
         * @param bool $unprivileged Makes the container run as unprivileged user. (Should not be modified manually.)
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @return Result
         */
        public function createRest($ostemplate, $vmid, $arch = null, $bwlimit = null, $cmode = null, $console = null, $cores = null, $cpulimit = null, $cpuunits = null, $description = null, $features = null, $force = null, $hostname = null, $ignore_unpack_errors = null, $lock = null, $memory = null, $mpN = null, $nameserver = null, $netN = null, $onboot = null, $ostype = null, $password = null, $pool = null, $protection = null, $restore = null, $rootfs = null, $searchdomain = null, $ssh_public_keys = null, $start = null, $startup = null, $storage = null, $swap = null, $template = null, $tty = null, $unprivileged = null, $unusedN = null)
        {
            $params = ['ostemplate' => $ostemplate,
                'vmid' => $vmid,
                'arch' => $arch,
                'bwlimit' => $bwlimit,
                'cmode' => $cmode,
                'console' => $console,
                'cores' => $cores,
                'cpulimit' => $cpulimit,
                'cpuunits' => $cpuunits,
                'description' => $description,
                'features' => $features,
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
                'start' => $start,
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
         *   Enum: amd64,i386,arm64,armhf
         * @param int $bwlimit Override i/o bandwidth limit (in KiB/s).
         * @param string $cmode Console mode. By default, the console command tries to open a connection to one of the available tty devices. By setting cmode to 'console' it tries to attach to /dev/console instead. If you set cmode to 'shell', it simply invokes a shell inside the container (no login).
         *   Enum: shell,console,tty
         * @param bool $console Attach a console device (/dev/console) to the container.
         * @param int $cores The number of cores assigned to the container. A container can use all available cores by default.
         * @param int $cpulimit Limit of CPU usage.  NOTE: If the computer has 2 CPUs, it has a total of '2' CPU time. Value '0' indicates no CPU limit.
         * @param int $cpuunits CPU weight for a VM. Argument is used in the kernel fair scheduler. The larger the number is, the more CPU time this VM gets. Number is relative to the weights of all the other running VMs.  NOTE: You can disable fair-scheduler configuration by setting this to 0.
         * @param string $description Container description. Only used on the configuration web interface.
         * @param string $features Allow containers access to advanced features.
         * @param bool $force Allow to overwrite existing container.
         * @param string $hostname Set a host name for the container.
         * @param bool $ignore_unpack_errors Ignore errors when extracting the template.
         * @param string $lock Lock/unlock the VM.
         *   Enum: backup,disk,migrate,mounted,rollback,snapshot,snapshot-delete
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
         * @param bool $start Start the CT after its creation finished successfully.
         * @param string $startup Startup and shutdown behavior. Order is a non-negative number defining the general startup order. Shutdown in done with reverse ordering. Additionally you can set the 'up' or 'down' delay in seconds, which specifies a delay to wait before the next VM is started or stopped.
         * @param string $storage Default Storage.
         * @param int $swap Amount of SWAP for the VM in MB.
         * @param bool $template Enable/disable Template.
         * @param int $tty Specify the number of tty available to the container
         * @param bool $unprivileged Makes the container run as unprivileged user. (Should not be modified manually.)
         * @param array $unusedN Reference to unused volumes. This is used internally, and should not be modified manually.
         * @return Result
         */
        public function createVm($ostemplate, $vmid, $arch = null, $bwlimit = null, $cmode = null, $console = null, $cores = null, $cpulimit = null, $cpuunits = null, $description = null, $features = null, $force = null, $hostname = null, $ignore_unpack_errors = null, $lock = null, $memory = null, $mpN = null, $nameserver = null, $netN = null, $onboot = null, $ostype = null, $password = null, $pool = null, $protection = null, $restore = null, $rootfs = null, $searchdomain = null, $ssh_public_keys = null, $start = null, $startup = null, $storage = null, $swap = null, $template = null, $tty = null, $unprivileged = null, $unusedN = null)
        {
            return $this->createRest($ostemplate, $vmid, $arch, $bwlimit, $cmode, $console, $cores, $cpulimit, $cpuunits, $description, $features, $force, $hostname, $ignore_unpack_errors, $lock, $memory, $mpN, $nameserver, $netN, $onboot, $ostype, $password, $pool, $protection, $restore, $rootfs, $searchdomain, $ssh_public_keys, $start, $startup, $storage, $swap, $template, $tty, $unprivileged, $unusedN);
        }
    }

    /**
     * Class PVEItemLxcNodeNodesVmid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemLxcNodeNodesVmid extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * @ignore
         */
        private $config;

        /**
         * Get VmidLxcNodeNodesConfig
         * @return PVEVmidLxcNodeNodesConfig
         */
        public function getConfig()
        {
            return $this->config ?: ($this->config = new PVEVmidLxcNodeNodesConfig($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $status;

        /**
         * Get VmidLxcNodeNodesStatus
         * @return PVEVmidLxcNodeNodesStatus
         */
        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVEVmidLxcNodeNodesStatus($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $snapshot;

        /**
         * Get VmidLxcNodeNodesSnapshot
         * @return PVEVmidLxcNodeNodesSnapshot
         */
        public function getSnapshot()
        {
            return $this->snapshot ?: ($this->snapshot = new PVEVmidLxcNodeNodesSnapshot($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $firewall;

        /**
         * Get VmidLxcNodeNodesFirewall
         * @return PVEVmidLxcNodeNodesFirewall
         */
        public function getFirewall()
        {
            return $this->firewall ?: ($this->firewall = new PVEVmidLxcNodeNodesFirewall($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $rrd;

        /**
         * Get VmidLxcNodeNodesRrd
         * @return PVEVmidLxcNodeNodesRrd
         */
        public function getRrd()
        {
            return $this->rrd ?: ($this->rrd = new PVEVmidLxcNodeNodesRrd($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $rrddata;

        /**
         * Get VmidLxcNodeNodesRrddata
         * @return PVEVmidLxcNodeNodesRrddata
         */
        public function getRrddata()
        {
            return $this->rrddata ?: ($this->rrddata = new PVEVmidLxcNodeNodesRrddata($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $vncproxy;

        /**
         * Get VmidLxcNodeNodesVncproxy
         * @return PVEVmidLxcNodeNodesVncproxy
         */
        public function getVncproxy()
        {
            return $this->vncproxy ?: ($this->vncproxy = new PVEVmidLxcNodeNodesVncproxy($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $termproxy;

        /**
         * Get VmidLxcNodeNodesTermproxy
         * @return PVEVmidLxcNodeNodesTermproxy
         */
        public function getTermproxy()
        {
            return $this->termproxy ?: ($this->termproxy = new PVEVmidLxcNodeNodesTermproxy($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $vncwebsocket;

        /**
         * Get VmidLxcNodeNodesVncwebsocket
         * @return PVEVmidLxcNodeNodesVncwebsocket
         */
        public function getVncwebsocket()
        {
            return $this->vncwebsocket ?: ($this->vncwebsocket = new PVEVmidLxcNodeNodesVncwebsocket($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $spiceproxy;

        /**
         * Get VmidLxcNodeNodesSpiceproxy
         * @return PVEVmidLxcNodeNodesSpiceproxy
         */
        public function getSpiceproxy()
        {
            return $this->spiceproxy ?: ($this->spiceproxy = new PVEVmidLxcNodeNodesSpiceproxy($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $migrate;

        /**
         * Get VmidLxcNodeNodesMigrate
         * @return PVEVmidLxcNodeNodesMigrate
         */
        public function getMigrate()
        {
            return $this->migrate ?: ($this->migrate = new PVEVmidLxcNodeNodesMigrate($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $feature;

        /**
         * Get VmidLxcNodeNodesFeature
         * @return PVEVmidLxcNodeNodesFeature
         */
        public function getFeature()
        {
            return $this->feature ?: ($this->feature = new PVEVmidLxcNodeNodesFeature($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $template;

        /**
         * Get VmidLxcNodeNodesTemplate
         * @return PVEVmidLxcNodeNodesTemplate
         */
        public function getTemplate()
        {
            return $this->template ?: ($this->template = new PVEVmidLxcNodeNodesTemplate($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $clone;

        /**
         * Get VmidLxcNodeNodesClone
         * @return PVEVmidLxcNodeNodesClone
         */
        public function getClone()
        {
            return $this->clone ?: ($this->clone = new PVEVmidLxcNodeNodesClone($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $resize;

        /**
         * Get VmidLxcNodeNodesResize
         * @return PVEVmidLxcNodeNodesResize
         */
        public function getResize()
        {
            return $this->resize ?: ($this->resize = new PVEVmidLxcNodeNodesResize($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $moveVolume;

        /**
         * Get VmidLxcNodeNodesMoveVolume
         * @return PVEVmidLxcNodeNodesMoveVolume
         */
        public function getMoveVolume()
        {
            return $this->moveVolume ?: ($this->moveVolume = new PVEVmidLxcNodeNodesMoveVolume($this->client, $this->node, $this->vmid));
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

    /**
     * Class PVEVmidLxcNodeNodesConfig
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesConfig extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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
         *   Enum: amd64,i386,arm64,armhf
         * @param string $cmode Console mode. By default, the console command tries to open a connection to one of the available tty devices. By setting cmode to 'console' it tries to attach to /dev/console instead. If you set cmode to 'shell', it simply invokes a shell inside the container (no login).
         *   Enum: shell,console,tty
         * @param bool $console Attach a console device (/dev/console) to the container.
         * @param int $cores The number of cores assigned to the container. A container can use all available cores by default.
         * @param int $cpulimit Limit of CPU usage.  NOTE: If the computer has 2 CPUs, it has a total of '2' CPU time. Value '0' indicates no CPU limit.
         * @param int $cpuunits CPU weight for a VM. Argument is used in the kernel fair scheduler. The larger the number is, the more CPU time this VM gets. Number is relative to the weights of all the other running VMs.  NOTE: You can disable fair-scheduler configuration by setting this to 0.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Container description. Only used on the configuration web interface.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $features Allow containers access to advanced features.
         * @param string $hostname Set a host name for the container.
         * @param string $lock Lock/unlock the VM.
         *   Enum: backup,disk,migrate,mounted,rollback,snapshot,snapshot-delete
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
        public function setRest($arch = null, $cmode = null, $console = null, $cores = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $features = null, $hostname = null, $lock = null, $memory = null, $mpN = null, $nameserver = null, $netN = null, $onboot = null, $ostype = null, $protection = null, $rootfs = null, $searchdomain = null, $startup = null, $swap = null, $template = null, $tty = null, $unprivileged = null, $unusedN = null)
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
                'features' => $features,
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
         *   Enum: amd64,i386,arm64,armhf
         * @param string $cmode Console mode. By default, the console command tries to open a connection to one of the available tty devices. By setting cmode to 'console' it tries to attach to /dev/console instead. If you set cmode to 'shell', it simply invokes a shell inside the container (no login).
         *   Enum: shell,console,tty
         * @param bool $console Attach a console device (/dev/console) to the container.
         * @param int $cores The number of cores assigned to the container. A container can use all available cores by default.
         * @param int $cpulimit Limit of CPU usage.  NOTE: If the computer has 2 CPUs, it has a total of '2' CPU time. Value '0' indicates no CPU limit.
         * @param int $cpuunits CPU weight for a VM. Argument is used in the kernel fair scheduler. The larger the number is, the more CPU time this VM gets. Number is relative to the weights of all the other running VMs.  NOTE: You can disable fair-scheduler configuration by setting this to 0.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Container description. Only used on the configuration web interface.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param string $features Allow containers access to advanced features.
         * @param string $hostname Set a host name for the container.
         * @param string $lock Lock/unlock the VM.
         *   Enum: backup,disk,migrate,mounted,rollback,snapshot,snapshot-delete
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
        public function updateVm($arch = null, $cmode = null, $console = null, $cores = null, $cpulimit = null, $cpuunits = null, $delete = null, $description = null, $digest = null, $features = null, $hostname = null, $lock = null, $memory = null, $mpN = null, $nameserver = null, $netN = null, $onboot = null, $ostype = null, $protection = null, $rootfs = null, $searchdomain = null, $startup = null, $swap = null, $template = null, $tty = null, $unprivileged = null, $unusedN = null)
        {
            return $this->setRest($arch, $cmode, $console, $cores, $cpulimit, $cpuunits, $delete, $description, $digest, $features, $hostname, $lock, $memory, $mpN, $nameserver, $netN, $onboot, $ostype, $protection, $rootfs, $searchdomain, $startup, $swap, $template, $tty, $unprivileged, $unusedN);
        }
    }

    /**
     * Class PVEVmidLxcNodeNodesStatus
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesStatus extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * @ignore
         */
        private $current;

        /**
         * Get StatusVmidLxcNodeNodesCurrent
         * @return PVEStatusVmidLxcNodeNodesCurrent
         */
        public function getCurrent()
        {
            return $this->current ?: ($this->current = new PVEStatusVmidLxcNodeNodesCurrent($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $start;

        /**
         * Get StatusVmidLxcNodeNodesStart
         * @return PVEStatusVmidLxcNodeNodesStart
         */
        public function getStart()
        {
            return $this->start ?: ($this->start = new PVEStatusVmidLxcNodeNodesStart($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $stop;

        /**
         * Get StatusVmidLxcNodeNodesStop
         * @return PVEStatusVmidLxcNodeNodesStop
         */
        public function getStop()
        {
            return $this->stop ?: ($this->stop = new PVEStatusVmidLxcNodeNodesStop($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $shutdown;

        /**
         * Get StatusVmidLxcNodeNodesShutdown
         * @return PVEStatusVmidLxcNodeNodesShutdown
         */
        public function getShutdown()
        {
            return $this->shutdown ?: ($this->shutdown = new PVEStatusVmidLxcNodeNodesShutdown($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $suspend;

        /**
         * Get StatusVmidLxcNodeNodesSuspend
         * @return PVEStatusVmidLxcNodeNodesSuspend
         */
        public function getSuspend()
        {
            return $this->suspend ?: ($this->suspend = new PVEStatusVmidLxcNodeNodesSuspend($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $resume;

        /**
         * Get StatusVmidLxcNodeNodesResume
         * @return PVEStatusVmidLxcNodeNodesResume
         */
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

    /**
     * Class PVEStatusVmidLxcNodeNodesCurrent
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidLxcNodeNodesCurrent extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStatusVmidLxcNodeNodesStart
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidLxcNodeNodesStart extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStatusVmidLxcNodeNodesStop
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidLxcNodeNodesStop extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStatusVmidLxcNodeNodesShutdown
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidLxcNodeNodesShutdown extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStatusVmidLxcNodeNodesSuspend
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidLxcNodeNodesSuspend extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStatusVmidLxcNodeNodesResume
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStatusVmidLxcNodeNodesResume extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidLxcNodeNodesSnapshot
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesSnapshot extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get ItemSnapshotVmidLxcNodeNodesSnapname
         * @param snapname
         * @return PVEItemSnapshotVmidLxcNodeNodesSnapname
         */
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

    /**
     * Class PVEItemSnapshotVmidLxcNodeNodesSnapname
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemSnapshotVmidLxcNodeNodesSnapname extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $snapname;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid, $snapname)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->snapname = $snapname;
        }

        /**
         * @ignore
         */
        private $rollback;

        /**
         * Get SnapnameSnapshotVmidLxcNodeNodesRollback
         * @return PVESnapnameSnapshotVmidLxcNodeNodesRollback
         */
        public function getRollback()
        {
            return $this->rollback ?: ($this->rollback = new PVESnapnameSnapshotVmidLxcNodeNodesRollback($this->client, $this->node, $this->vmid, $this->snapname));
        }

        /**
         * @ignore
         */
        private $config;

        /**
         * Get SnapnameSnapshotVmidLxcNodeNodesConfig
         * @return PVESnapnameSnapshotVmidLxcNodeNodesConfig
         */
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

    /**
     * Class PVESnapnameSnapshotVmidLxcNodeNodesRollback
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVESnapnameSnapshotVmidLxcNodeNodesRollback extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $snapname;

        /**
         * @ignore
         */
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

    /**
     * Class PVESnapnameSnapshotVmidLxcNodeNodesConfig
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVESnapnameSnapshotVmidLxcNodeNodesConfig extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $snapname;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidLxcNodeNodesFirewall
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesFirewall extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * @ignore
         */
        private $rules;

        /**
         * Get FirewallVmidLxcNodeNodesRules
         * @return PVEFirewallVmidLxcNodeNodesRules
         */
        public function getRules()
        {
            return $this->rules ?: ($this->rules = new PVEFirewallVmidLxcNodeNodesRules($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $aliases;

        /**
         * Get FirewallVmidLxcNodeNodesAliases
         * @return PVEFirewallVmidLxcNodeNodesAliases
         */
        public function getAliases()
        {
            return $this->aliases ?: ($this->aliases = new PVEFirewallVmidLxcNodeNodesAliases($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $ipset;

        /**
         * Get FirewallVmidLxcNodeNodesIpset
         * @return PVEFirewallVmidLxcNodeNodesIpset
         */
        public function getIpset()
        {
            return $this->ipset ?: ($this->ipset = new PVEFirewallVmidLxcNodeNodesIpset($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $options;

        /**
         * Get FirewallVmidLxcNodeNodesOptions
         * @return PVEFirewallVmidLxcNodeNodesOptions
         */
        public function getOptions()
        {
            return $this->options ?: ($this->options = new PVEFirewallVmidLxcNodeNodesOptions($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $log;

        /**
         * Get FirewallVmidLxcNodeNodesLog
         * @return PVEFirewallVmidLxcNodeNodesLog
         */
        public function getLog()
        {
            return $this->log ?: ($this->log = new PVEFirewallVmidLxcNodeNodesLog($this->client, $this->node, $this->vmid));
        }

        /**
         * @ignore
         */
        private $refs;

        /**
         * Get FirewallVmidLxcNodeNodesRefs
         * @return PVEFirewallVmidLxcNodeNodesRefs
         */
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

    /**
     * Class PVEFirewallVmidLxcNodeNodesRules
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallVmidLxcNodeNodesRules extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get ItemRulesFirewallVmidLxcNodeNodesPos
         * @param pos
         * @return PVEItemRulesFirewallVmidLxcNodeNodesPos
         */
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

    /**
     * Class PVEItemRulesFirewallVmidLxcNodeNodesPos
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemRulesFirewallVmidLxcNodeNodesPos extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $pos;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallVmidLxcNodeNodesAliases
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallVmidLxcNodeNodesAliases extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get ItemAliasesFirewallVmidLxcNodeNodesName
         * @param name
         * @return PVEItemAliasesFirewallVmidLxcNodeNodesName
         */
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

    /**
     * Class PVEItemAliasesFirewallVmidLxcNodeNodesName
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemAliasesFirewallVmidLxcNodeNodesName extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $name;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallVmidLxcNodeNodesIpset
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallVmidLxcNodeNodesIpset extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Get ItemIpsetFirewallVmidLxcNodeNodesName
         * @param name
         * @return PVEItemIpsetFirewallVmidLxcNodeNodesName
         */
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

    /**
     * Class PVEItemIpsetFirewallVmidLxcNodeNodesName
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemIpsetFirewallVmidLxcNodeNodesName extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $name;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid, $name)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
            $this->name = $name;
        }

        /**
         * Get ItemNameIpsetFirewallVmidLxcNodeNodesCidr
         * @param cidr
         * @return PVEItemNameIpsetFirewallVmidLxcNodeNodesCidr
         */
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

    /**
     * Class PVEItemNameIpsetFirewallVmidLxcNodeNodesCidr
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemNameIpsetFirewallVmidLxcNodeNodesCidr extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;
        /**
         * @ignore
         */
        private $name;
        /**
         * @ignore
         */
        private $cidr;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallVmidLxcNodeNodesOptions
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallVmidLxcNodeNodesOptions extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallVmidLxcNodeNodesLog
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallVmidLxcNodeNodesLog extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallVmidLxcNodeNodesRefs
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallVmidLxcNodeNodesRefs extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidLxcNodeNodesRrd
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesRrd extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidLxcNodeNodesRrddata
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesRrddata extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidLxcNodeNodesVncproxy
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesVncproxy extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidLxcNodeNodesTermproxy
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesTermproxy extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Creates a TCP proxy connection.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/termproxy");
        }

        /**
         * Creates a TCP proxy connection.
         * @return Result
         */
        public function termproxy()
        {
            return $this->createRest();
        }
    }

    /**
     * Class PVEVmidLxcNodeNodesVncwebsocket
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesVncwebsocket extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidLxcNodeNodesSpiceproxy
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesSpiceproxy extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Returns a SPICE configuration to connect to the CT.
         * @param string $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As reasonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @return Result
         */
        public function createRest($proxy = null)
        {
            $params = ['proxy' => $proxy];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/spiceproxy", $params);
        }

        /**
         * Returns a SPICE configuration to connect to the CT.
         * @param string $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As reasonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @return Result
         */
        public function spiceproxy($proxy = null)
        {
            return $this->createRest($proxy);
        }
    }

    /**
     * Class PVEVmidLxcNodeNodesMigrate
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesMigrate extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVmidLxcNodeNodesFeature
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesFeature extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
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
            return $this->getClient()->get("/nodes/{$this->node}/lxc/{$this->vmid}/feature", $params);
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

    /**
     * Class PVEVmidLxcNodeNodesTemplate
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesTemplate extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Create a Template.
         * @return Result
         */
        public function createRest()
        {
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/template");
        }

        /**
         * Create a Template.
         * @return Result
         */
        public function template()
        {
            return $this->createRest();
        }
    }

    /**
     * Class PVEVmidLxcNodeNodesClone
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesClone extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Create a container clone/copy
         * @param int $newid VMID for the clone.
         * @param string $description Description for the new CT.
         * @param bool $full Create a full copy of all disks. This is always done when you clone a normal CT. For CT templates, we try to create a linked clone by default.
         * @param string $hostname Set a hostname for the new CT.
         * @param string $pool Add the new CT to the specified pool.
         * @param string $snapname The name of the snapshot.
         * @param string $storage Target storage for full clone.
         * @param string $target Target node. Only allowed if the original VM is on shared storage.
         * @return Result
         */
        public function createRest($newid, $description = null, $full = null, $hostname = null, $pool = null, $snapname = null, $storage = null, $target = null)
        {
            $params = ['newid' => $newid,
                'description' => $description,
                'full' => $full,
                'hostname' => $hostname,
                'pool' => $pool,
                'snapname' => $snapname,
                'storage' => $storage,
                'target' => $target];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/clone", $params);
        }

        /**
         * Create a container clone/copy
         * @param int $newid VMID for the clone.
         * @param string $description Description for the new CT.
         * @param bool $full Create a full copy of all disks. This is always done when you clone a normal CT. For CT templates, we try to create a linked clone by default.
         * @param string $hostname Set a hostname for the new CT.
         * @param string $pool Add the new CT to the specified pool.
         * @param string $snapname The name of the snapshot.
         * @param string $storage Target storage for full clone.
         * @param string $target Target node. Only allowed if the original VM is on shared storage.
         * @return Result
         */
        public function cloneVm($newid, $description = null, $full = null, $hostname = null, $pool = null, $snapname = null, $storage = null, $target = null)
        {
            return $this->createRest($newid, $description, $full, $hostname, $pool, $snapname, $storage, $target);
        }
    }

    /**
     * Class PVEVmidLxcNodeNodesResize
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesResize extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Resize a container mount point.
         * @param string $disk The disk you want to resize.
         *   Enum: rootfs,mp0,mp1,mp2,mp3,mp4,mp5,mp6,mp7,mp8,mp9,mp10,mp11,mp12,mp13,mp14,mp15,mp16,mp17,mp18,mp19,mp20,mp21,mp22,mp23,mp24,mp25,mp26,mp27,mp28,mp29,mp30,mp31,mp32,mp33,mp34,mp35,mp36,mp37,mp38,mp39,mp40,mp41,mp42,mp43,mp44,mp45,mp46,mp47,mp48,mp49,mp50,mp51,mp52,mp53,mp54,mp55,mp56,mp57,mp58,mp59,mp60,mp61,mp62,mp63,mp64,mp65,mp66,mp67,mp68,mp69,mp70,mp71,mp72,mp73,mp74,mp75,mp76,mp77,mp78,mp79,mp80,mp81,mp82,mp83,mp84,mp85,mp86,mp87,mp88,mp89,mp90,mp91,mp92,mp93,mp94,mp95,mp96,mp97,mp98,mp99,mp100,mp101,mp102,mp103,mp104,mp105,mp106,mp107,mp108,mp109,mp110,mp111,mp112,mp113,mp114,mp115,mp116,mp117,mp118,mp119,mp120,mp121,mp122,mp123,mp124,mp125,mp126,mp127,mp128,mp129,mp130,mp131,mp132,mp133,mp134,mp135,mp136,mp137,mp138,mp139,mp140,mp141,mp142,mp143,mp144,mp145,mp146,mp147,mp148,mp149,mp150,mp151,mp152,mp153,mp154,mp155,mp156,mp157,mp158,mp159,mp160,mp161,mp162,mp163,mp164,mp165,mp166,mp167,mp168,mp169,mp170,mp171,mp172,mp173,mp174,mp175,mp176,mp177,mp178,mp179,mp180,mp181,mp182,mp183,mp184,mp185,mp186,mp187,mp188,mp189,mp190,mp191,mp192,mp193,mp194,mp195,mp196,mp197,mp198,mp199,mp200,mp201,mp202,mp203,mp204,mp205,mp206,mp207,mp208,mp209,mp210,mp211,mp212,mp213,mp214,mp215,mp216,mp217,mp218,mp219,mp220,mp221,mp222,mp223,mp224,mp225,mp226,mp227,mp228,mp229,mp230,mp231,mp232,mp233,mp234,mp235,mp236,mp237,mp238,mp239,mp240,mp241,mp242,mp243,mp244,mp245,mp246,mp247,mp248,mp249,mp250,mp251,mp252,mp253,mp254,mp255
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
         *   Enum: rootfs,mp0,mp1,mp2,mp3,mp4,mp5,mp6,mp7,mp8,mp9,mp10,mp11,mp12,mp13,mp14,mp15,mp16,mp17,mp18,mp19,mp20,mp21,mp22,mp23,mp24,mp25,mp26,mp27,mp28,mp29,mp30,mp31,mp32,mp33,mp34,mp35,mp36,mp37,mp38,mp39,mp40,mp41,mp42,mp43,mp44,mp45,mp46,mp47,mp48,mp49,mp50,mp51,mp52,mp53,mp54,mp55,mp56,mp57,mp58,mp59,mp60,mp61,mp62,mp63,mp64,mp65,mp66,mp67,mp68,mp69,mp70,mp71,mp72,mp73,mp74,mp75,mp76,mp77,mp78,mp79,mp80,mp81,mp82,mp83,mp84,mp85,mp86,mp87,mp88,mp89,mp90,mp91,mp92,mp93,mp94,mp95,mp96,mp97,mp98,mp99,mp100,mp101,mp102,mp103,mp104,mp105,mp106,mp107,mp108,mp109,mp110,mp111,mp112,mp113,mp114,mp115,mp116,mp117,mp118,mp119,mp120,mp121,mp122,mp123,mp124,mp125,mp126,mp127,mp128,mp129,mp130,mp131,mp132,mp133,mp134,mp135,mp136,mp137,mp138,mp139,mp140,mp141,mp142,mp143,mp144,mp145,mp146,mp147,mp148,mp149,mp150,mp151,mp152,mp153,mp154,mp155,mp156,mp157,mp158,mp159,mp160,mp161,mp162,mp163,mp164,mp165,mp166,mp167,mp168,mp169,mp170,mp171,mp172,mp173,mp174,mp175,mp176,mp177,mp178,mp179,mp180,mp181,mp182,mp183,mp184,mp185,mp186,mp187,mp188,mp189,mp190,mp191,mp192,mp193,mp194,mp195,mp196,mp197,mp198,mp199,mp200,mp201,mp202,mp203,mp204,mp205,mp206,mp207,mp208,mp209,mp210,mp211,mp212,mp213,mp214,mp215,mp216,mp217,mp218,mp219,mp220,mp221,mp222,mp223,mp224,mp225,mp226,mp227,mp228,mp229,mp230,mp231,mp232,mp233,mp234,mp235,mp236,mp237,mp238,mp239,mp240,mp241,mp242,mp243,mp244,mp245,mp246,mp247,mp248,mp249,mp250,mp251,mp252,mp253,mp254,mp255
         * @param string $size The new size. With the '+' sign the value is added to the actual size of the volume and without it, the value is taken as an absolute one. Shrinking disk size is not supported.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function resizeVm($disk, $size, $digest = null)
        {
            return $this->setRest($disk, $size, $digest);
        }
    }

    /**
     * Class PVEVmidLxcNodeNodesMoveVolume
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVmidLxcNodeNodesMoveVolume extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $vmid;

        /**
         * @ignore
         */
        function __construct($client, $node, $vmid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->vmid = $vmid;
        }

        /**
         * Move a rootfs-/mp-volume to a different storage
         * @param string $storage Target Storage.
         * @param string $volume Volume which will be moved.
         *   Enum: rootfs,mp0,mp1,mp2,mp3,mp4,mp5,mp6,mp7,mp8,mp9,mp10,mp11,mp12,mp13,mp14,mp15,mp16,mp17,mp18,mp19,mp20,mp21,mp22,mp23,mp24,mp25,mp26,mp27,mp28,mp29,mp30,mp31,mp32,mp33,mp34,mp35,mp36,mp37,mp38,mp39,mp40,mp41,mp42,mp43,mp44,mp45,mp46,mp47,mp48,mp49,mp50,mp51,mp52,mp53,mp54,mp55,mp56,mp57,mp58,mp59,mp60,mp61,mp62,mp63,mp64,mp65,mp66,mp67,mp68,mp69,mp70,mp71,mp72,mp73,mp74,mp75,mp76,mp77,mp78,mp79,mp80,mp81,mp82,mp83,mp84,mp85,mp86,mp87,mp88,mp89,mp90,mp91,mp92,mp93,mp94,mp95,mp96,mp97,mp98,mp99,mp100,mp101,mp102,mp103,mp104,mp105,mp106,mp107,mp108,mp109,mp110,mp111,mp112,mp113,mp114,mp115,mp116,mp117,mp118,mp119,mp120,mp121,mp122,mp123,mp124,mp125,mp126,mp127,mp128,mp129,mp130,mp131,mp132,mp133,mp134,mp135,mp136,mp137,mp138,mp139,mp140,mp141,mp142,mp143,mp144,mp145,mp146,mp147,mp148,mp149,mp150,mp151,mp152,mp153,mp154,mp155,mp156,mp157,mp158,mp159,mp160,mp161,mp162,mp163,mp164,mp165,mp166,mp167,mp168,mp169,mp170,mp171,mp172,mp173,mp174,mp175,mp176,mp177,mp178,mp179,mp180,mp181,mp182,mp183,mp184,mp185,mp186,mp187,mp188,mp189,mp190,mp191,mp192,mp193,mp194,mp195,mp196,mp197,mp198,mp199,mp200,mp201,mp202,mp203,mp204,mp205,mp206,mp207,mp208,mp209,mp210,mp211,mp212,mp213,mp214,mp215,mp216,mp217,mp218,mp219,mp220,mp221,mp222,mp223,mp224,mp225,mp226,mp227,mp228,mp229,mp230,mp231,mp232,mp233,mp234,mp235,mp236,mp237,mp238,mp239,mp240,mp241,mp242,mp243,mp244,mp245,mp246,mp247,mp248,mp249,mp250,mp251,mp252,mp253,mp254,mp255
         * @param bool $delete Delete the original volume after successful copy. By default the original is kept as an unused volume entry.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function createRest($storage, $volume, $delete = null, $digest = null)
        {
            $params = ['storage' => $storage,
                'volume' => $volume,
                'delete' => $delete,
                'digest' => $digest];
            return $this->getClient()->create("/nodes/{$this->node}/lxc/{$this->vmid}/move_volume", $params);
        }

        /**
         * Move a rootfs-/mp-volume to a different storage
         * @param string $storage Target Storage.
         * @param string $volume Volume which will be moved.
         *   Enum: rootfs,mp0,mp1,mp2,mp3,mp4,mp5,mp6,mp7,mp8,mp9,mp10,mp11,mp12,mp13,mp14,mp15,mp16,mp17,mp18,mp19,mp20,mp21,mp22,mp23,mp24,mp25,mp26,mp27,mp28,mp29,mp30,mp31,mp32,mp33,mp34,mp35,mp36,mp37,mp38,mp39,mp40,mp41,mp42,mp43,mp44,mp45,mp46,mp47,mp48,mp49,mp50,mp51,mp52,mp53,mp54,mp55,mp56,mp57,mp58,mp59,mp60,mp61,mp62,mp63,mp64,mp65,mp66,mp67,mp68,mp69,mp70,mp71,mp72,mp73,mp74,mp75,mp76,mp77,mp78,mp79,mp80,mp81,mp82,mp83,mp84,mp85,mp86,mp87,mp88,mp89,mp90,mp91,mp92,mp93,mp94,mp95,mp96,mp97,mp98,mp99,mp100,mp101,mp102,mp103,mp104,mp105,mp106,mp107,mp108,mp109,mp110,mp111,mp112,mp113,mp114,mp115,mp116,mp117,mp118,mp119,mp120,mp121,mp122,mp123,mp124,mp125,mp126,mp127,mp128,mp129,mp130,mp131,mp132,mp133,mp134,mp135,mp136,mp137,mp138,mp139,mp140,mp141,mp142,mp143,mp144,mp145,mp146,mp147,mp148,mp149,mp150,mp151,mp152,mp153,mp154,mp155,mp156,mp157,mp158,mp159,mp160,mp161,mp162,mp163,mp164,mp165,mp166,mp167,mp168,mp169,mp170,mp171,mp172,mp173,mp174,mp175,mp176,mp177,mp178,mp179,mp180,mp181,mp182,mp183,mp184,mp185,mp186,mp187,mp188,mp189,mp190,mp191,mp192,mp193,mp194,mp195,mp196,mp197,mp198,mp199,mp200,mp201,mp202,mp203,mp204,mp205,mp206,mp207,mp208,mp209,mp210,mp211,mp212,mp213,mp214,mp215,mp216,mp217,mp218,mp219,mp220,mp221,mp222,mp223,mp224,mp225,mp226,mp227,mp228,mp229,mp230,mp231,mp232,mp233,mp234,mp235,mp236,mp237,mp238,mp239,mp240,mp241,mp242,mp243,mp244,mp245,mp246,mp247,mp248,mp249,mp250,mp251,mp252,mp253,mp254,mp255
         * @param bool $delete Delete the original volume after successful copy. By default the original is kept as an unused volume entry.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function moveVolume($storage, $volume, $delete = null, $digest = null)
        {
            return $this->createRest($storage, $volume, $delete, $digest);
        }
    }

    /**
     * Class PVENodeNodesCeph
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesCeph extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * @ignore
         */
        private $osd;

        /**
         * Get CephNodeNodesOsd
         * @return PVECephNodeNodesOsd
         */
        public function getOsd()
        {
            return $this->osd ?: ($this->osd = new PVECephNodeNodesOsd($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $mds;

        /**
         * Get CephNodeNodesMds
         * @return PVECephNodeNodesMds
         */
        public function getMds()
        {
            return $this->mds ?: ($this->mds = new PVECephNodeNodesMds($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $fs;

        /**
         * Get CephNodeNodesFs
         * @return PVECephNodeNodesFs
         */
        public function getFs()
        {
            return $this->fs ?: ($this->fs = new PVECephNodeNodesFs($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $disks;

        /**
         * Get CephNodeNodesDisks
         * @return PVECephNodeNodesDisks
         */
        public function getDisks()
        {
            return $this->disks ?: ($this->disks = new PVECephNodeNodesDisks($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $config;

        /**
         * Get CephNodeNodesConfig
         * @return PVECephNodeNodesConfig
         */
        public function getConfig()
        {
            return $this->config ?: ($this->config = new PVECephNodeNodesConfig($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $mon;

        /**
         * Get CephNodeNodesMon
         * @return PVECephNodeNodesMon
         */
        public function getMon()
        {
            return $this->mon ?: ($this->mon = new PVECephNodeNodesMon($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $init;

        /**
         * Get CephNodeNodesInit
         * @return PVECephNodeNodesInit
         */
        public function getInit()
        {
            return $this->init ?: ($this->init = new PVECephNodeNodesInit($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $mgr;

        /**
         * Get CephNodeNodesMgr
         * @return PVECephNodeNodesMgr
         */
        public function getMgr()
        {
            return $this->mgr ?: ($this->mgr = new PVECephNodeNodesMgr($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $stop;

        /**
         * Get CephNodeNodesStop
         * @return PVECephNodeNodesStop
         */
        public function getStop()
        {
            return $this->stop ?: ($this->stop = new PVECephNodeNodesStop($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $start;

        /**
         * Get CephNodeNodesStart
         * @return PVECephNodeNodesStart
         */
        public function getStart()
        {
            return $this->start ?: ($this->start = new PVECephNodeNodesStart($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $restart;

        /**
         * Get CephNodeNodesRestart
         * @return PVECephNodeNodesRestart
         */
        public function getRestart()
        {
            return $this->restart ?: ($this->restart = new PVECephNodeNodesRestart($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $status;

        /**
         * Get CephNodeNodesStatus
         * @return PVECephNodeNodesStatus
         */
        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVECephNodeNodesStatus($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $pools;

        /**
         * Get CephNodeNodesPools
         * @return PVECephNodeNodesPools
         */
        public function getPools()
        {
            return $this->pools ?: ($this->pools = new PVECephNodeNodesPools($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $flags;

        /**
         * Get CephNodeNodesFlags
         * @return PVECephNodeNodesFlags
         */
        public function getFlags()
        {
            return $this->flags ?: ($this->flags = new PVECephNodeNodesFlags($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $crush;

        /**
         * Get CephNodeNodesCrush
         * @return PVECephNodeNodesCrush
         */
        public function getCrush()
        {
            return $this->crush ?: ($this->crush = new PVECephNodeNodesCrush($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $log;

        /**
         * Get CephNodeNodesLog
         * @return PVECephNodeNodesLog
         */
        public function getLog()
        {
            return $this->log ?: ($this->log = new PVECephNodeNodesLog($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $rules;

        /**
         * Get CephNodeNodesRules
         * @return PVECephNodeNodesRules
         */
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

    /**
     * Class PVECephNodeNodesOsd
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesOsd extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemOsdCephNodeNodesOsdid
         * @param osdid
         * @return PVEItemOsdCephNodeNodesOsdid
         */
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
         * @param bool $bluestore Use bluestore instead of filestore. This is the default.
         * @param string $fstype File system type (filestore only).
         *   Enum: xfs,ext4
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
         * @param bool $bluestore Use bluestore instead of filestore. This is the default.
         * @param string $fstype File system type (filestore only).
         *   Enum: xfs,ext4
         * @param string $journal_dev Block device name for journal (filestore) or block.db (bluestore).
         * @param string $wal_dev Block device name for block.wal (bluestore only).
         * @return Result
         */
        public function createosd($dev, $bluestore = null, $fstype = null, $journal_dev = null, $wal_dev = null)
        {
            return $this->createRest($dev, $bluestore, $fstype, $journal_dev, $wal_dev);
        }
    }

    /**
     * Class PVEItemOsdCephNodeNodesOsdid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemOsdCephNodeNodesOsdid extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $osdid;

        /**
         * @ignore
         */
        function __construct($client, $node, $osdid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->osdid = $osdid;
        }

        /**
         * @ignore
         */
        private $in;

        /**
         * Get OsdidOsdCephNodeNodesIn
         * @return PVEOsdidOsdCephNodeNodesIn
         */
        public function getIn()
        {
            return $this->in ?: ($this->in = new PVEOsdidOsdCephNodeNodesIn($this->client, $this->node, $this->osdid));
        }

        /**
         * @ignore
         */
        private $out;

        /**
         * Get OsdidOsdCephNodeNodesOut
         * @return PVEOsdidOsdCephNodeNodesOut
         */
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

    /**
     * Class PVEOsdidOsdCephNodeNodesIn
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEOsdidOsdCephNodeNodesIn extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $osdid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEOsdidOsdCephNodeNodesOut
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEOsdidOsdCephNodeNodesOut extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $osdid;

        /**
         * @ignore
         */
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

    /**
     * Class PVECephNodeNodesMds
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesMds extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemMdsCephNodeNodesName
         * @param name
         * @return PVEItemMdsCephNodeNodesName
         */
        public function get($name)
        {
            return new PVEItemMdsCephNodeNodesName($this->client, $this->node, $name);
        }

        /**
         * MDS directory index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/ceph/mds");
        }

        /**
         * MDS directory index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEItemMdsCephNodeNodesName
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemMdsCephNodeNodesName extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $name;

        /**
         * @ignore
         */
        function __construct($client, $node, $name)
        {
            $this->client = $client;
            $this->node = $node;
            $this->name = $name;
        }

        /**
         * Destroy Ceph Metadata Server
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/nodes/{$this->node}/ceph/mds/{$this->name}");
        }

        /**
         * Destroy Ceph Metadata Server
         * @return Result
         */
        public function destroymds()
        {
            return $this->deleteRest();
        }

        /**
         * Create Ceph Metadata Server (MDS)
         * @param bool $hotstandby Determines whether a ceph-mds daemon should poll and replay the log of an active MDS. Faster switch on MDS failure, but needs more idle resources.
         * @return Result
         */
        public function createRest($hotstandby = null)
        {
            $params = ['hotstandby' => $hotstandby];
            return $this->getClient()->create("/nodes/{$this->node}/ceph/mds/{$this->name}", $params);
        }

        /**
         * Create Ceph Metadata Server (MDS)
         * @param bool $hotstandby Determines whether a ceph-mds daemon should poll and replay the log of an active MDS. Faster switch on MDS failure, but needs more idle resources.
         * @return Result
         */
        public function createmds($hotstandby = null)
        {
            return $this->createRest($hotstandby);
        }
    }

    /**
     * Class PVECephNodeNodesFs
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesFs extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemFsCephNodeNodesName
         * @param name
         * @return PVEItemFsCephNodeNodesName
         */
        public function get($name)
        {
            return new PVEItemFsCephNodeNodesName($this->client, $this->node, $name);
        }

        /**
         * Directory index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/ceph/fs");
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

    /**
     * Class PVEItemFsCephNodeNodesName
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemFsCephNodeNodesName extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $name;

        /**
         * @ignore
         */
        function __construct($client, $node, $name)
        {
            $this->client = $client;
            $this->node = $node;
            $this->name = $name;
        }

        /**
         * Create a Ceph filesystem
         * @param bool $add_storage Configure the created CephFS as storage for this cluster.
         * @param int $pg_num Number of placement groups for the backing data pool. The metadata pool will use a quarter of this.
         * @return Result
         */
        public function createRest($add_storage = null, $pg_num = null)
        {
            $params = ['add-storage' => $add_storage,
                'pg_num' => $pg_num];
            return $this->getClient()->create("/nodes/{$this->node}/ceph/fs/{$this->name}", $params);
        }

        /**
         * Create a Ceph filesystem
         * @param bool $add_storage Configure the created CephFS as storage for this cluster.
         * @param int $pg_num Number of placement groups for the backing data pool. The metadata pool will use a quarter of this.
         * @return Result
         */
        public function createfs($add_storage = null, $pg_num = null)
        {
            return $this->createRest($add_storage, $pg_num);
        }
    }

    /**
     * Class PVECephNodeNodesDisks
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesDisks extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVECephNodeNodesConfig
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesConfig extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVECephNodeNodesMon
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesMon extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemMonCephNodeNodesMonid
         * @param monid
         * @return PVEItemMonCephNodeNodesMonid
         */
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
         * @param string $mon_address Overwrites autodetected monitor IP address. Must be in the public network of ceph.
         * @return Result
         */
        public function createRest($exclude_manager = null, $id = null, $mon_address = null)
        {
            $params = ['exclude-manager' => $exclude_manager,
                'id' => $id,
                'mon-address' => $mon_address];
            return $this->getClient()->create("/nodes/{$this->node}/ceph/mon", $params);
        }

        /**
         * Create Ceph Monitor and Manager
         * @param bool $exclude_manager When set, only a monitor will be created.
         * @param string $id The ID for the monitor, when omitted the same as the nodename
         * @param string $mon_address Overwrites autodetected monitor IP address. Must be in the public network of ceph.
         * @return Result
         */
        public function createmon($exclude_manager = null, $id = null, $mon_address = null)
        {
            return $this->createRest($exclude_manager, $id, $mon_address);
        }
    }

    /**
     * Class PVEItemMonCephNodeNodesMonid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemMonCephNodeNodesMonid extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $monid;

        /**
         * @ignore
         */
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

    /**
     * Class PVECephNodeNodesInit
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesInit extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Create initial ceph default configuration and setup symlinks.
         * @param string $cluster_network Declare a separate cluster network, OSDs will routeheartbeat, object replication and recovery traffic over it
         * @param bool $disable_cephx Disable cephx authentification.  WARNING: cephx is a security feature protecting against man-in-the-middle attacks. Only consider disabling cephx if your network is private!
         * @param int $min_size Minimum number of available replicas per object to allow I/O
         * @param string $network Use specific network for all ceph related traffic
         * @param int $pg_bits Placement group bits, used to specify the default number of placement groups.  NOTE: 'osd pool default pg num' does not work for default pools.
         * @param int $size Targeted number of replicas per object
         * @return Result
         */
        public function createRest($cluster_network = null, $disable_cephx = null, $min_size = null, $network = null, $pg_bits = null, $size = null)
        {
            $params = ['cluster-network' => $cluster_network,
                'disable_cephx' => $disable_cephx,
                'min_size' => $min_size,
                'network' => $network,
                'pg_bits' => $pg_bits,
                'size' => $size];
            return $this->getClient()->create("/nodes/{$this->node}/ceph/init", $params);
        }

        /**
         * Create initial ceph default configuration and setup symlinks.
         * @param string $cluster_network Declare a separate cluster network, OSDs will routeheartbeat, object replication and recovery traffic over it
         * @param bool $disable_cephx Disable cephx authentification.  WARNING: cephx is a security feature protecting against man-in-the-middle attacks. Only consider disabling cephx if your network is private!
         * @param int $min_size Minimum number of available replicas per object to allow I/O
         * @param string $network Use specific network for all ceph related traffic
         * @param int $pg_bits Placement group bits, used to specify the default number of placement groups.  NOTE: 'osd pool default pg num' does not work for default pools.
         * @param int $size Targeted number of replicas per object
         * @return Result
         */
        public function init($cluster_network = null, $disable_cephx = null, $min_size = null, $network = null, $pg_bits = null, $size = null)
        {
            return $this->createRest($cluster_network, $disable_cephx, $min_size, $network, $pg_bits, $size);
        }
    }

    /**
     * Class PVECephNodeNodesMgr
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesMgr extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemMgrCephNodeNodesId
         * @param id
         * @return PVEItemMgrCephNodeNodesId
         */
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

    /**
     * Class PVEItemMgrCephNodeNodesId
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemMgrCephNodeNodesId extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $id;

        /**
         * @ignore
         */
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

    /**
     * Class PVECephNodeNodesStop
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesStop extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVECephNodeNodesStart
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesStart extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVECephNodeNodesRestart
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesRestart extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Restart ceph services.
         * @param string $service Ceph service name.
         * @return Result
         */
        public function createRest($service = null)
        {
            $params = ['service' => $service];
            return $this->getClient()->create("/nodes/{$this->node}/ceph/restart", $params);
        }

        /**
         * Restart ceph services.
         * @param string $service Ceph service name.
         * @return Result
         */
        public function restart($service = null)
        {
            return $this->createRest($service);
        }
    }

    /**
     * Class PVECephNodeNodesStatus
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesStatus extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVECephNodeNodesPools
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesPools extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemPoolsCephNodeNodesName
         * @param name
         * @return PVEItemPoolsCephNodeNodesName
         */
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
         * @param bool $add_storages Configure VM and CT storage using the new pool.
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
         * @param bool $add_storages Configure VM and CT storage using the new pool.
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

    /**
     * Class PVEItemPoolsCephNodeNodesName
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemPoolsCephNodeNodesName extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $name;

        /**
         * @ignore
         */
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

    /**
     * Class PVECephNodeNodesFlags
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesFlags extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemFlagsCephNodeNodesFlag
         * @param flag
         * @return PVEItemFlagsCephNodeNodesFlag
         */
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

    /**
     * Class PVEItemFlagsCephNodeNodesFlag
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemFlagsCephNodeNodesFlag extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $flag;

        /**
         * @ignore
         */
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

    /**
     * Class PVECephNodeNodesCrush
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesCrush extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVECephNodeNodesLog
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesLog extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVECephNodeNodesRules
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECephNodeNodesRules extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesVzdump
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesVzdump extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * @ignore
         */
        private $extractconfig;

        /**
         * Get VzdumpNodeNodesExtractconfig
         * @return PVEVzdumpNodeNodesExtractconfig
         */
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

    /**
     * Class PVEVzdumpNodeNodesExtractconfig
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVzdumpNodeNodesExtractconfig extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesServices
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesServices extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemServicesNodeNodesService
         * @param service
         * @return PVEItemServicesNodeNodesService
         */
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

    /**
     * Class PVEItemServicesNodeNodesService
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemServicesNodeNodesService extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $service;

        /**
         * @ignore
         */
        function __construct($client, $node, $service)
        {
            $this->client = $client;
            $this->node = $node;
            $this->service = $service;
        }

        /**
         * @ignore
         */
        private $state;

        /**
         * Get ServiceServicesNodeNodesState
         * @return PVEServiceServicesNodeNodesState
         */
        public function getState()
        {
            return $this->state ?: ($this->state = new PVEServiceServicesNodeNodesState($this->client, $this->node, $this->service));
        }

        /**
         * @ignore
         */
        private $start;

        /**
         * Get ServiceServicesNodeNodesStart
         * @return PVEServiceServicesNodeNodesStart
         */
        public function getStart()
        {
            return $this->start ?: ($this->start = new PVEServiceServicesNodeNodesStart($this->client, $this->node, $this->service));
        }

        /**
         * @ignore
         */
        private $stop;

        /**
         * Get ServiceServicesNodeNodesStop
         * @return PVEServiceServicesNodeNodesStop
         */
        public function getStop()
        {
            return $this->stop ?: ($this->stop = new PVEServiceServicesNodeNodesStop($this->client, $this->node, $this->service));
        }

        /**
         * @ignore
         */
        private $restart;

        /**
         * Get ServiceServicesNodeNodesRestart
         * @return PVEServiceServicesNodeNodesRestart
         */
        public function getRestart()
        {
            return $this->restart ?: ($this->restart = new PVEServiceServicesNodeNodesRestart($this->client, $this->node, $this->service));
        }

        /**
         * @ignore
         */
        private $reload;

        /**
         * Get ServiceServicesNodeNodesReload
         * @return PVEServiceServicesNodeNodesReload
         */
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

    /**
     * Class PVEServiceServicesNodeNodesState
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEServiceServicesNodeNodesState extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $service;

        /**
         * @ignore
         */
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

    /**
     * Class PVEServiceServicesNodeNodesStart
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEServiceServicesNodeNodesStart extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $service;

        /**
         * @ignore
         */
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

    /**
     * Class PVEServiceServicesNodeNodesStop
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEServiceServicesNodeNodesStop extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $service;

        /**
         * @ignore
         */
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

    /**
     * Class PVEServiceServicesNodeNodesRestart
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEServiceServicesNodeNodesRestart extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $service;

        /**
         * @ignore
         */
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

    /**
     * Class PVEServiceServicesNodeNodesReload
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEServiceServicesNodeNodesReload extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $service;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesSubscription
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesSubscription extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesNetwork
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesNetwork extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemNetworkNodeNodesIface
         * @param iface
         * @return PVEItemNetworkNodeNodesIface
         */
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

        /**
         * Reload network configuration
         * @return Result
         */
        public function setRest()
        {
            return $this->getClient()->set("/nodes/{$this->node}/network");
        }

        /**
         * Reload network configuration
         * @return Result
         */
        public function reloadNetworkConfig()
        {
            return $this->setRest();
        }
    }

    /**
     * Class PVEItemNetworkNodeNodesIface
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemNetworkNodeNodesIface extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $iface;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesTasks
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesTasks extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemTasksNodeNodesUpid
         * @param upid
         * @return PVEItemTasksNodeNodesUpid
         */
        public function get($upid)
        {
            return new PVEItemTasksNodeNodesUpid($this->client, $this->node, $upid);
        }

        /**
         * Read task list for one node (finished tasks).
         * @param bool $errors
         * @param int $limit Only list this amount of tasks.
         * @param int $start List tasks beginning from this offset.
         * @param string $userfilter Only list tasks from this user.
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
         * @param int $limit Only list this amount of tasks.
         * @param int $start List tasks beginning from this offset.
         * @param string $userfilter Only list tasks from this user.
         * @param int $vmid Only list tasks for this VM.
         * @return Result
         */
        public function nodeTasks($errors = null, $limit = null, $start = null, $userfilter = null, $vmid = null)
        {
            return $this->getRest($errors, $limit, $start, $userfilter, $vmid);
        }
    }

    /**
     * Class PVEItemTasksNodeNodesUpid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemTasksNodeNodesUpid extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $upid;

        /**
         * @ignore
         */
        function __construct($client, $node, $upid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->upid = $upid;
        }

        /**
         * @ignore
         */
        private $log;

        /**
         * Get UpidTasksNodeNodesLog
         * @return PVEUpidTasksNodeNodesLog
         */
        public function getLog()
        {
            return $this->log ?: ($this->log = new PVEUpidTasksNodeNodesLog($this->client, $this->node, $this->upid));
        }

        /**
         * @ignore
         */
        private $status;

        /**
         * Get UpidTasksNodeNodesStatus
         * @return PVEUpidTasksNodeNodesStatus
         */
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

    /**
     * Class PVEUpidTasksNodeNodesLog
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEUpidTasksNodeNodesLog extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $upid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEUpidTasksNodeNodesStatus
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEUpidTasksNodeNodesStatus extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $upid;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesScan
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesScan extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * @ignore
         */
        private $zfs;

        /**
         * Get ScanNodeNodesZfs
         * @return PVEScanNodeNodesZfs
         */
        public function getZfs()
        {
            return $this->zfs ?: ($this->zfs = new PVEScanNodeNodesZfs($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $nfs;

        /**
         * Get ScanNodeNodesNfs
         * @return PVEScanNodeNodesNfs
         */
        public function getNfs()
        {
            return $this->nfs ?: ($this->nfs = new PVEScanNodeNodesNfs($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $cifs;

        /**
         * Get ScanNodeNodesCifs
         * @return PVEScanNodeNodesCifs
         */
        public function getCifs()
        {
            return $this->cifs ?: ($this->cifs = new PVEScanNodeNodesCifs($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $glusterfs;

        /**
         * Get ScanNodeNodesGlusterfs
         * @return PVEScanNodeNodesGlusterfs
         */
        public function getGlusterfs()
        {
            return $this->glusterfs ?: ($this->glusterfs = new PVEScanNodeNodesGlusterfs($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $iscsi;

        /**
         * Get ScanNodeNodesIscsi
         * @return PVEScanNodeNodesIscsi
         */
        public function getIscsi()
        {
            return $this->iscsi ?: ($this->iscsi = new PVEScanNodeNodesIscsi($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $lvm;

        /**
         * Get ScanNodeNodesLvm
         * @return PVEScanNodeNodesLvm
         */
        public function getLvm()
        {
            return $this->lvm ?: ($this->lvm = new PVEScanNodeNodesLvm($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $lvmthin;

        /**
         * Get ScanNodeNodesLvmthin
         * @return PVEScanNodeNodesLvmthin
         */
        public function getLvmthin()
        {
            return $this->lvmthin ?: ($this->lvmthin = new PVEScanNodeNodesLvmthin($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $usb;

        /**
         * Get ScanNodeNodesUsb
         * @return PVEScanNodeNodesUsb
         */
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

    /**
     * Class PVEScanNodeNodesZfs
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEScanNodeNodesZfs extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVEScanNodeNodesNfs
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEScanNodeNodesNfs extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Scan remote NFS server.
         * @param string $server The server address (name or IP).
         * @return Result
         */
        public function getRest($server)
        {
            $params = ['server' => $server];
            return $this->getClient()->get("/nodes/{$this->node}/scan/nfs", $params);
        }

        /**
         * Scan remote NFS server.
         * @param string $server The server address (name or IP).
         * @return Result
         */
        public function nfsscan($server)
        {
            return $this->getRest($server);
        }
    }

    /**
     * Class PVEScanNodeNodesCifs
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEScanNodeNodesCifs extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Scan remote CIFS server.
         * @param string $server The server address (name or IP).
         * @param string $domain SMB domain (Workgroup).
         * @param string $password User password.
         * @param string $username User name.
         * @return Result
         */
        public function getRest($server, $domain = null, $password = null, $username = null)
        {
            $params = ['server' => $server,
                'domain' => $domain,
                'password' => $password,
                'username' => $username];
            return $this->getClient()->get("/nodes/{$this->node}/scan/cifs", $params);
        }

        /**
         * Scan remote CIFS server.
         * @param string $server The server address (name or IP).
         * @param string $domain SMB domain (Workgroup).
         * @param string $password User password.
         * @param string $username User name.
         * @return Result
         */
        public function cifsscan($server, $domain = null, $password = null, $username = null)
        {
            return $this->getRest($server, $domain, $password, $username);
        }
    }

    /**
     * Class PVEScanNodeNodesGlusterfs
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEScanNodeNodesGlusterfs extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Scan remote GlusterFS server.
         * @param string $server The server address (name or IP).
         * @return Result
         */
        public function getRest($server)
        {
            $params = ['server' => $server];
            return $this->getClient()->get("/nodes/{$this->node}/scan/glusterfs", $params);
        }

        /**
         * Scan remote GlusterFS server.
         * @param string $server The server address (name or IP).
         * @return Result
         */
        public function glusterfsscan($server)
        {
            return $this->getRest($server);
        }
    }

    /**
     * Class PVEScanNodeNodesIscsi
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEScanNodeNodesIscsi extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Scan remote iSCSI server.
         * @param string $portal The iSCSI portal (IP or DNS name with optional port).
         * @return Result
         */
        public function getRest($portal)
        {
            $params = ['portal' => $portal];
            return $this->getClient()->get("/nodes/{$this->node}/scan/iscsi", $params);
        }

        /**
         * Scan remote iSCSI server.
         * @param string $portal The iSCSI portal (IP or DNS name with optional port).
         * @return Result
         */
        public function iscsiscan($portal)
        {
            return $this->getRest($portal);
        }
    }

    /**
     * Class PVEScanNodeNodesLvm
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEScanNodeNodesLvm extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVEScanNodeNodesLvmthin
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEScanNodeNodesLvmthin extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVEScanNodeNodesUsb
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEScanNodeNodesUsb extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesHardware
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesHardware extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * @ignore
         */
        private $pci;

        /**
         * Get HardwareNodeNodesPci
         * @return PVEHardwareNodeNodesPci
         */
        public function getPci()
        {
            return $this->pci ?: ($this->pci = new PVEHardwareNodeNodesPci($this->client, $this->node));
        }

        /**
         * Index of hardware types
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/hardware");
        }

        /**
         * Index of hardware types
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEHardwareNodeNodesPci
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEHardwareNodeNodesPci extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemPciHardwareNodeNodesPciid
         * @param pciid
         * @return PVEItemPciHardwareNodeNodesPciid
         */
        public function get($pciid)
        {
            return new PVEItemPciHardwareNodeNodesPciid($this->client, $this->node, $pciid);
        }

        /**
         * List local PCI devices.
         * @param string $pci_class_blacklist A list of blacklisted PCI classes, which will not be returned. Following are filtered by default: Memory Controller (05), Bridge (06), Generic System Peripheral (08) and Processor (0b).
         * @param bool $verbose If disabled, does only print the PCI IDs. Otherwise, additional information like vendor and device will be returned.
         * @return Result
         */
        public function getRest($pci_class_blacklist = null, $verbose = null)
        {
            $params = ['pci-class-blacklist' => $pci_class_blacklist,
                'verbose' => $verbose];
            return $this->getClient()->get("/nodes/{$this->node}/hardware/pci", $params);
        }

        /**
         * List local PCI devices.
         * @param string $pci_class_blacklist A list of blacklisted PCI classes, which will not be returned. Following are filtered by default: Memory Controller (05), Bridge (06), Generic System Peripheral (08) and Processor (0b).
         * @param bool $verbose If disabled, does only print the PCI IDs. Otherwise, additional information like vendor and device will be returned.
         * @return Result
         */
        public function pciscan($pci_class_blacklist = null, $verbose = null)
        {
            return $this->getRest($pci_class_blacklist, $verbose);
        }
    }

    /**
     * Class PVEItemPciHardwareNodeNodesPciid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemPciHardwareNodeNodesPciid extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $pciid;

        /**
         * @ignore
         */
        function __construct($client, $node, $pciid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->pciid = $pciid;
        }

        /**
         * @ignore
         */
        private $mdev;

        /**
         * Get PciidPciHardwareNodeNodesMdev
         * @return PVEPciidPciHardwareNodeNodesMdev
         */
        public function getMdev()
        {
            return $this->mdev ?: ($this->mdev = new PVEPciidPciHardwareNodeNodesMdev($this->client, $this->node, $this->pciid));
        }

        /**
         * Index of available pci methods
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/hardware/pci/{$this->pciid}");
        }

        /**
         * Index of available pci methods
         * @return Result
         */
        public function pciindex()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEPciidPciHardwareNodeNodesMdev
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEPciidPciHardwareNodeNodesMdev extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $pciid;

        /**
         * @ignore
         */
        function __construct($client, $node, $pciid)
        {
            $this->client = $client;
            $this->node = $node;
            $this->pciid = $pciid;
        }

        /**
         * List mediated device types for given PCI device.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/hardware/pci/{$this->pciid}/mdev");
        }

        /**
         * List mediated device types for given PCI device.
         * @return Result
         */
        public function mdevscan()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVENodeNodesStorage
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesStorage extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemStorageNodeNodesStorage
         * @param storage
         * @return PVEItemStorageNodeNodesStorage
         */
        public function get($storage)
        {
            return new PVEItemStorageNodeNodesStorage($this->client, $this->node, $storage);
        }

        /**
         * Get status for all datastores.
         * @param string $content Only list stores which support this content type.
         * @param bool $enabled Only list stores which are enabled (not disabled in config).
         * @param bool $format Include information about formats
         * @param string $storage Only list status for  specified storage
         * @param string $target If target is different to 'node', we only lists shared storages which content is accessible on this 'node' and the specified 'target' node.
         * @return Result
         */
        public function getRest($content = null, $enabled = null, $format = null, $storage = null, $target = null)
        {
            $params = ['content' => $content,
                'enabled' => $enabled,
                'format' => $format,
                'storage' => $storage,
                'target' => $target];
            return $this->getClient()->get("/nodes/{$this->node}/storage", $params);
        }

        /**
         * Get status for all datastores.
         * @param string $content Only list stores which support this content type.
         * @param bool $enabled Only list stores which are enabled (not disabled in config).
         * @param bool $format Include information about formats
         * @param string $storage Only list status for  specified storage
         * @param string $target If target is different to 'node', we only lists shared storages which content is accessible on this 'node' and the specified 'target' node.
         * @return Result
         */
        public function index($content = null, $enabled = null, $format = null, $storage = null, $target = null)
        {
            return $this->getRest($content, $enabled, $format, $storage, $target);
        }
    }

    /**
     * Class PVEItemStorageNodeNodesStorage
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemStorageNodeNodesStorage extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $storage;

        /**
         * @ignore
         */
        function __construct($client, $node, $storage)
        {
            $this->client = $client;
            $this->node = $node;
            $this->storage = $storage;
        }

        /**
         * @ignore
         */
        private $content;

        /**
         * Get StorageStorageNodeNodesContent
         * @return PVEStorageStorageNodeNodesContent
         */
        public function getContent()
        {
            return $this->content ?: ($this->content = new PVEStorageStorageNodeNodesContent($this->client, $this->node, $this->storage));
        }

        /**
         * @ignore
         */
        private $status;

        /**
         * Get StorageStorageNodeNodesStatus
         * @return PVEStorageStorageNodeNodesStatus
         */
        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVEStorageStorageNodeNodesStatus($this->client, $this->node, $this->storage));
        }

        /**
         * @ignore
         */
        private $rrd;

        /**
         * Get StorageStorageNodeNodesRrd
         * @return PVEStorageStorageNodeNodesRrd
         */
        public function getRrd()
        {
            return $this->rrd ?: ($this->rrd = new PVEStorageStorageNodeNodesRrd($this->client, $this->node, $this->storage));
        }

        /**
         * @ignore
         */
        private $rrddata;

        /**
         * Get StorageStorageNodeNodesRrddata
         * @return PVEStorageStorageNodeNodesRrddata
         */
        public function getRrddata()
        {
            return $this->rrddata ?: ($this->rrddata = new PVEStorageStorageNodeNodesRrddata($this->client, $this->node, $this->storage));
        }

        /**
         * @ignore
         */
        private $upload;

        /**
         * Get StorageStorageNodeNodesUpload
         * @return PVEStorageStorageNodeNodesUpload
         */
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

    /**
     * Class PVEStorageStorageNodeNodesContent
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStorageStorageNodeNodesContent extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $storage;

        /**
         * @ignore
         */
        function __construct($client, $node, $storage)
        {
            $this->client = $client;
            $this->node = $node;
            $this->storage = $storage;
        }

        /**
         * Get ItemContentStorageStorageNodeNodesVolume
         * @param volume
         * @return PVEItemContentStorageStorageNodeNodesVolume
         */
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

    /**
     * Class PVEItemContentStorageStorageNodeNodesVolume
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemContentStorageStorageNodeNodesVolume extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $storage;
        /**
         * @ignore
         */
        private $volume;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStorageStorageNodeNodesStatus
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStorageStorageNodeNodesStatus extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $storage;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStorageStorageNodeNodesRrd
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStorageStorageNodeNodesRrd extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $storage;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStorageStorageNodeNodesRrddata
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStorageStorageNodeNodesRrddata extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $storage;

        /**
         * @ignore
         */
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

    /**
     * Class PVEStorageStorageNodeNodesUpload
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStorageStorageNodeNodesUpload extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $storage;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesDisks
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesDisks extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * @ignore
         */
        private $lvm;

        /**
         * Get DisksNodeNodesLvm
         * @return PVEDisksNodeNodesLvm
         */
        public function getLvm()
        {
            return $this->lvm ?: ($this->lvm = new PVEDisksNodeNodesLvm($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $lvmthin;

        /**
         * Get DisksNodeNodesLvmthin
         * @return PVEDisksNodeNodesLvmthin
         */
        public function getLvmthin()
        {
            return $this->lvmthin ?: ($this->lvmthin = new PVEDisksNodeNodesLvmthin($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $directory;

        /**
         * Get DisksNodeNodesDirectory
         * @return PVEDisksNodeNodesDirectory
         */
        public function getDirectory()
        {
            return $this->directory ?: ($this->directory = new PVEDisksNodeNodesDirectory($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $zfs;

        /**
         * Get DisksNodeNodesZfs
         * @return PVEDisksNodeNodesZfs
         */
        public function getZfs()
        {
            return $this->zfs ?: ($this->zfs = new PVEDisksNodeNodesZfs($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $list;

        /**
         * Get DisksNodeNodesList
         * @return PVEDisksNodeNodesList
         */
        public function getList()
        {
            return $this->list ?: ($this->list = new PVEDisksNodeNodesList($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $smart;

        /**
         * Get DisksNodeNodesSmart
         * @return PVEDisksNodeNodesSmart
         */
        public function getSmart()
        {
            return $this->smart ?: ($this->smart = new PVEDisksNodeNodesSmart($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $initgpt;

        /**
         * Get DisksNodeNodesInitgpt
         * @return PVEDisksNodeNodesInitgpt
         */
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

    /**
     * Class PVEDisksNodeNodesLvm
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEDisksNodeNodesLvm extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * List LVM Volume Groups
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/disks/lvm");
        }

        /**
         * List LVM Volume Groups
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Create an LVM Volume Group
         * @param string $device The block device you want to create the volume group on
         * @param string $name The storage identifier.
         * @param bool $add_storage Configure storage using the Volume Group
         * @return Result
         */
        public function createRest($device, $name, $add_storage = null)
        {
            $params = ['device' => $device,
                'name' => $name,
                'add_storage' => $add_storage];
            return $this->getClient()->create("/nodes/{$this->node}/disks/lvm", $params);
        }

        /**
         * Create an LVM Volume Group
         * @param string $device The block device you want to create the volume group on
         * @param string $name The storage identifier.
         * @param bool $add_storage Configure storage using the Volume Group
         * @return Result
         */
        public function create($device, $name, $add_storage = null)
        {
            return $this->createRest($device, $name, $add_storage);
        }
    }

    /**
     * Class PVEDisksNodeNodesLvmthin
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEDisksNodeNodesLvmthin extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * List LVM thinpools
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/disks/lvmthin");
        }

        /**
         * List LVM thinpools
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Create an LVM thinpool
         * @param string $device The block device you want to create the thinpool on.
         * @param string $name The storage identifier.
         * @param bool $add_storage Configure storage using the thinpool.
         * @return Result
         */
        public function createRest($device, $name, $add_storage = null)
        {
            $params = ['device' => $device,
                'name' => $name,
                'add_storage' => $add_storage];
            return $this->getClient()->create("/nodes/{$this->node}/disks/lvmthin", $params);
        }

        /**
         * Create an LVM thinpool
         * @param string $device The block device you want to create the thinpool on.
         * @param string $name The storage identifier.
         * @param bool $add_storage Configure storage using the thinpool.
         * @return Result
         */
        public function create($device, $name, $add_storage = null)
        {
            return $this->createRest($device, $name, $add_storage);
        }
    }

    /**
     * Class PVEDisksNodeNodesDirectory
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEDisksNodeNodesDirectory extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * PVE Managed Directory storages.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/disks/directory");
        }

        /**
         * PVE Managed Directory storages.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Create a Filesystem on an unused disk. Will be mounted under '/mnt/pve/NAME'.
         * @param string $device The block device you want to create the filesystem on.
         * @param string $name The storage identifier.
         * @param bool $add_storage Configure storage using the directory.
         * @param string $filesystem The desired filesystem.
         *   Enum: ext4,xfs
         * @return Result
         */
        public function createRest($device, $name, $add_storage = null, $filesystem = null)
        {
            $params = ['device' => $device,
                'name' => $name,
                'add_storage' => $add_storage,
                'filesystem' => $filesystem];
            return $this->getClient()->create("/nodes/{$this->node}/disks/directory", $params);
        }

        /**
         * Create a Filesystem on an unused disk. Will be mounted under '/mnt/pve/NAME'.
         * @param string $device The block device you want to create the filesystem on.
         * @param string $name The storage identifier.
         * @param bool $add_storage Configure storage using the directory.
         * @param string $filesystem The desired filesystem.
         *   Enum: ext4,xfs
         * @return Result
         */
        public function create($device, $name, $add_storage = null, $filesystem = null)
        {
            return $this->createRest($device, $name, $add_storage, $filesystem);
        }
    }

    /**
     * Class PVEDisksNodeNodesZfs
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEDisksNodeNodesZfs extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemZfsDisksNodeNodesName
         * @param name
         * @return PVEItemZfsDisksNodeNodesName
         */
        public function get($name)
        {
            return new PVEItemZfsDisksNodeNodesName($this->client, $this->node, $name);
        }

        /**
         * List Zpools.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/disks/zfs");
        }

        /**
         * List Zpools.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }

        /**
         * Create a ZFS pool.
         * @param string $devices The block devices you want to create the zpool on.
         * @param string $name The storage identifier.
         * @param string $raidlevel The RAID level to use.
         *   Enum: single,mirror,raid10,raidz,raidz2,raidz3
         * @param bool $add_storage Configure storage using the zpool.
         * @param int $ashift Pool sector size exponent.
         * @param string $compression The compression algorithm to use.
         *   Enum: on,off,gzip,lz4,lzjb,zle
         * @return Result
         */
        public function createRest($devices, $name, $raidlevel, $add_storage = null, $ashift = null, $compression = null)
        {
            $params = ['devices' => $devices,
                'name' => $name,
                'raidlevel' => $raidlevel,
                'add_storage' => $add_storage,
                'ashift' => $ashift,
                'compression' => $compression];
            return $this->getClient()->create("/nodes/{$this->node}/disks/zfs", $params);
        }

        /**
         * Create a ZFS pool.
         * @param string $devices The block devices you want to create the zpool on.
         * @param string $name The storage identifier.
         * @param string $raidlevel The RAID level to use.
         *   Enum: single,mirror,raid10,raidz,raidz2,raidz3
         * @param bool $add_storage Configure storage using the zpool.
         * @param int $ashift Pool sector size exponent.
         * @param string $compression The compression algorithm to use.
         *   Enum: on,off,gzip,lz4,lzjb,zle
         * @return Result
         */
        public function create($devices, $name, $raidlevel, $add_storage = null, $ashift = null, $compression = null)
        {
            return $this->createRest($devices, $name, $raidlevel, $add_storage, $ashift, $compression);
        }
    }

    /**
     * Class PVEItemZfsDisksNodeNodesName
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemZfsDisksNodeNodesName extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $name;

        /**
         * @ignore
         */
        function __construct($client, $node, $name)
        {
            $this->client = $client;
            $this->node = $node;
            $this->name = $name;
        }

        /**
         * Get details about a zpool.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/disks/zfs/{$this->name}");
        }

        /**
         * Get details about a zpool.
         * @return Result
         */
        public function detail()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEDisksNodeNodesList
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEDisksNodeNodesList extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * List local disks.
         * @param bool $skipsmart Skip smart checks.
         * @param string $type Only list specific types of disks.
         *   Enum: unused,journal_disks
         * @return Result
         */
        public function getRest($skipsmart = null, $type = null)
        {
            $params = ['skipsmart' => $skipsmart,
                'type' => $type];
            return $this->getClient()->get("/nodes/{$this->node}/disks/list", $params);
        }

        /**
         * List local disks.
         * @param bool $skipsmart Skip smart checks.
         * @param string $type Only list specific types of disks.
         *   Enum: unused,journal_disks
         * @return Result
         */
        public function list_($skipsmart = null, $type = null)
        {
            return $this->getRest($skipsmart, $type);
        }
    }

    /**
     * Class PVEDisksNodeNodesSmart
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEDisksNodeNodesSmart extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVEDisksNodeNodesInitgpt
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEDisksNodeNodesInitgpt extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesApt
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesApt extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * @ignore
         */
        private $update;

        /**
         * Get AptNodeNodesUpdate
         * @return PVEAptNodeNodesUpdate
         */
        public function getUpdate()
        {
            return $this->update ?: ($this->update = new PVEAptNodeNodesUpdate($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $changelog;

        /**
         * Get AptNodeNodesChangelog
         * @return PVEAptNodeNodesChangelog
         */
        public function getChangelog()
        {
            return $this->changelog ?: ($this->changelog = new PVEAptNodeNodesChangelog($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $versions;

        /**
         * Get AptNodeNodesVersions
         * @return PVEAptNodeNodesVersions
         */
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

    /**
     * Class PVEAptNodeNodesUpdate
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAptNodeNodesUpdate extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVEAptNodeNodesChangelog
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAptNodeNodesChangelog extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVEAptNodeNodesVersions
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAptNodeNodesVersions extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesFirewall
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesFirewall extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * @ignore
         */
        private $rules;

        /**
         * Get FirewallNodeNodesRules
         * @return PVEFirewallNodeNodesRules
         */
        public function getRules()
        {
            return $this->rules ?: ($this->rules = new PVEFirewallNodeNodesRules($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $options;

        /**
         * Get FirewallNodeNodesOptions
         * @return PVEFirewallNodeNodesOptions
         */
        public function getOptions()
        {
            return $this->options ?: ($this->options = new PVEFirewallNodeNodesOptions($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $log;

        /**
         * Get FirewallNodeNodesLog
         * @return PVEFirewallNodeNodesLog
         */
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

    /**
     * Class PVEFirewallNodeNodesRules
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallNodeNodesRules extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemRulesFirewallNodeNodesPos
         * @param pos
         * @return PVEItemRulesFirewallNodeNodesPos
         */
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

    /**
     * Class PVEItemRulesFirewallNodeNodesPos
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemRulesFirewallNodeNodesPos extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $pos;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallNodeNodesOptions
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallNodeNodesOptions extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVEFirewallNodeNodesLog
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEFirewallNodeNodesLog extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesReplication
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesReplication extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get ItemReplicationNodeNodesId
         * @param id
         * @return PVEItemReplicationNodeNodesId
         */
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

    /**
     * Class PVEItemReplicationNodeNodesId
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemReplicationNodeNodesId extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $id;

        /**
         * @ignore
         */
        function __construct($client, $node, $id)
        {
            $this->client = $client;
            $this->node = $node;
            $this->id = $id;
        }

        /**
         * @ignore
         */
        private $status;

        /**
         * Get IdReplicationNodeNodesStatus
         * @return PVEIdReplicationNodeNodesStatus
         */
        public function getStatus()
        {
            return $this->status ?: ($this->status = new PVEIdReplicationNodeNodesStatus($this->client, $this->node, $this->id));
        }

        /**
         * @ignore
         */
        private $log;

        /**
         * Get IdReplicationNodeNodesLog
         * @return PVEIdReplicationNodeNodesLog
         */
        public function getLog()
        {
            return $this->log ?: ($this->log = new PVEIdReplicationNodeNodesLog($this->client, $this->node, $this->id));
        }

        /**
         * @ignore
         */
        private $scheduleNow;

        /**
         * Get IdReplicationNodeNodesScheduleNow
         * @return PVEIdReplicationNodeNodesScheduleNow
         */
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

    /**
     * Class PVEIdReplicationNodeNodesStatus
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEIdReplicationNodeNodesStatus extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $id;

        /**
         * @ignore
         */
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

    /**
     * Class PVEIdReplicationNodeNodesLog
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEIdReplicationNodeNodesLog extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $id;

        /**
         * @ignore
         */
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

    /**
     * Class PVEIdReplicationNodeNodesScheduleNow
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEIdReplicationNodeNodesScheduleNow extends Base
    {
        /**
         * @ignore
         */
        private $node;
        /**
         * @ignore
         */
        private $id;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesCertificates
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesCertificates extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * @ignore
         */
        private $acme;

        /**
         * Get CertificatesNodeNodesAcme
         * @return PVECertificatesNodeNodesAcme
         */
        public function getAcme()
        {
            return $this->acme ?: ($this->acme = new PVECertificatesNodeNodesAcme($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $info;

        /**
         * Get CertificatesNodeNodesInfo
         * @return PVECertificatesNodeNodesInfo
         */
        public function getInfo()
        {
            return $this->info ?: ($this->info = new PVECertificatesNodeNodesInfo($this->client, $this->node));
        }

        /**
         * @ignore
         */
        private $custom;

        /**
         * Get CertificatesNodeNodesCustom
         * @return PVECertificatesNodeNodesCustom
         */
        public function getCustom()
        {
            return $this->custom ?: ($this->custom = new PVECertificatesNodeNodesCustom($this->client, $this->node));
        }

        /**
         * Node index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/certificates");
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

    /**
     * Class PVECertificatesNodeNodesAcme
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECertificatesNodeNodesAcme extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * @ignore
         */
        private $certificate;

        /**
         * Get AcmeCertificatesNodeNodesCertificate
         * @return PVEAcmeCertificatesNodeNodesCertificate
         */
        public function getCertificate()
        {
            return $this->certificate ?: ($this->certificate = new PVEAcmeCertificatesNodeNodesCertificate($this->client, $this->node));
        }

        /**
         * ACME index.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/certificates/acme");
        }

        /**
         * ACME index.
         * @return Result
         */
        public function index()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVEAcmeCertificatesNodeNodesCertificate
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAcmeCertificatesNodeNodesCertificate extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Revoke existing certificate from CA.
         * @return Result
         */
        public function deleteRest()
        {
            return $this->getClient()->delete("/nodes/{$this->node}/certificates/acme/certificate");
        }

        /**
         * Revoke existing certificate from CA.
         * @return Result
         */
        public function revokeCertificate()
        {
            return $this->deleteRest();
        }

        /**
         * Order a new certificate from ACME-compatible CA.
         * @param bool $force Overwrite existing custom certificate.
         * @return Result
         */
        public function createRest($force = null)
        {
            $params = ['force' => $force];
            return $this->getClient()->create("/nodes/{$this->node}/certificates/acme/certificate", $params);
        }

        /**
         * Order a new certificate from ACME-compatible CA.
         * @param bool $force Overwrite existing custom certificate.
         * @return Result
         */
        public function newCertificate($force = null)
        {
            return $this->createRest($force);
        }

        /**
         * Renew existing certificate from CA.
         * @param bool $force Force renewal even if expiry is more than 30 days away.
         * @return Result
         */
        public function setRest($force = null)
        {
            $params = ['force' => $force];
            return $this->getClient()->set("/nodes/{$this->node}/certificates/acme/certificate", $params);
        }

        /**
         * Renew existing certificate from CA.
         * @param bool $force Force renewal even if expiry is more than 30 days away.
         * @return Result
         */
        public function renewCertificate($force = null)
        {
            return $this->setRest($force);
        }
    }

    /**
     * Class PVECertificatesNodeNodesInfo
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECertificatesNodeNodesInfo extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get information about node's certificates.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/certificates/info");
        }

        /**
         * Get information about node's certificates.
         * @return Result
         */
        public function info()
        {
            return $this->getRest();
        }
    }

    /**
     * Class PVECertificatesNodeNodesCustom
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVECertificatesNodeNodesCustom extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * DELETE custom certificate chain and key.
         * @param bool $restart Restart pveproxy.
         * @return Result
         */
        public function deleteRest($restart = null)
        {
            $params = ['restart' => $restart];
            return $this->getClient()->delete("/nodes/{$this->node}/certificates/custom", $params);
        }

        /**
         * DELETE custom certificate chain and key.
         * @param bool $restart Restart pveproxy.
         * @return Result
         */
        public function removeCustomCert($restart = null)
        {
            return $this->deleteRest($restart);
        }

        /**
         * Upload or update custom certificate chain and key.
         * @param string $certificates PEM encoded certificate (chain).
         * @param bool $force Overwrite existing custom or ACME certificate files.
         * @param string $key PEM encoded private key.
         * @param bool $restart Restart pveproxy.
         * @return Result
         */
        public function createRest($certificates, $force = null, $key = null, $restart = null)
        {
            $params = ['certificates' => $certificates,
                'force' => $force,
                'key' => $key,
                'restart' => $restart];
            return $this->getClient()->create("/nodes/{$this->node}/certificates/custom", $params);
        }

        /**
         * Upload or update custom certificate chain and key.
         * @param string $certificates PEM encoded certificate (chain).
         * @param bool $force Overwrite existing custom or ACME certificate files.
         * @param string $key PEM encoded private key.
         * @param bool $restart Restart pveproxy.
         * @return Result
         */
        public function uploadCustomCert($certificates, $force = null, $key = null, $restart = null)
        {
            return $this->createRest($certificates, $force, $key, $restart);
        }
    }

    /**
     * Class PVENodeNodesConfig
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesConfig extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get node configuration options.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/config");
        }

        /**
         * Get node configuration options.
         * @return Result
         */
        public function getConfig()
        {
            return $this->getRest();
        }

        /**
         * Set node configuration options.
         * @param string $acme Node specific ACME settings.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Node description/comment.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function setRest($acme = null, $delete = null, $description = null, $digest = null)
        {
            $params = ['acme' => $acme,
                'delete' => $delete,
                'description' => $description,
                'digest' => $digest];
            return $this->getClient()->set("/nodes/{$this->node}/config", $params);
        }

        /**
         * Set node configuration options.
         * @param string $acme Node specific ACME settings.
         * @param string $delete A list of settings you want to delete.
         * @param string $description Node description/comment.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function setOptions($acme = null, $delete = null, $description = null, $digest = null)
        {
            return $this->setRest($acme, $delete, $description, $digest);
        }
    }

    /**
     * Class PVENodeNodesVersion
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesVersion extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesStatus
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesStatus extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesNetstat
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesNetstat extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesExecute
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesExecute extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesRrd
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesRrd extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesRrddata
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesRrddata extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesSyslog
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesSyslog extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Read system log
         * @param int $limit
         * @param string $service Service ID
         * @param string $since Display all log since this date-time string.
         * @param int $start
         * @param string $until Display all log until this date-time string.
         * @return Result
         */
        public function getRest($limit = null, $service = null, $since = null, $start = null, $until = null)
        {
            $params = ['limit' => $limit,
                'service' => $service,
                'since' => $since,
                'start' => $start,
                'until' => $until];
            return $this->getClient()->get("/nodes/{$this->node}/syslog", $params);
        }

        /**
         * Read system log
         * @param int $limit
         * @param string $service Service ID
         * @param string $since Display all log since this date-time string.
         * @param int $start
         * @param string $until Display all log until this date-time string.
         * @return Result
         */
        public function syslog($limit = null, $service = null, $since = null, $start = null, $until = null)
        {
            return $this->getRest($limit, $service, $since, $start, $until);
        }
    }

    /**
     * Class PVENodeNodesVncshell
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesVncshell extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesTermproxy
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesTermproxy extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Creates a VNC Shell proxy.
         * @param bool $upgrade Run 'apt-get dist-upgrade' instead of normal shell.
         * @return Result
         */
        public function createRest($upgrade = null)
        {
            $params = ['upgrade' => $upgrade];
            return $this->getClient()->create("/nodes/{$this->node}/termproxy", $params);
        }

        /**
         * Creates a VNC Shell proxy.
         * @param bool $upgrade Run 'apt-get dist-upgrade' instead of normal shell.
         * @return Result
         */
        public function termproxy($upgrade = null)
        {
            return $this->createRest($upgrade);
        }
    }

    /**
     * Class PVENodeNodesVncwebsocket
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesVncwebsocket extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesSpiceshell
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesSpiceshell extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Creates a SPICE shell.
         * @param string $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As reasonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
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
         * @param string $proxy SPICE proxy server. This can be used by the client to specify the proxy server. All nodes in a cluster runs 'spiceproxy', so it is up to the client to choose one. By default, we return the node where the VM is currently running. As reasonable setting is to use same node you use to connect to the API (This is window.location.hostname for the JS GUI).
         * @param bool $upgrade Run 'apt-get dist-upgrade' instead of normal shell.
         * @return Result
         */
        public function spiceshell($proxy = null, $upgrade = null)
        {
            return $this->createRest($proxy, $upgrade);
        }
    }

    /**
     * Class PVENodeNodesDns
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesDns extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesTime
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesTime extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesAplinfo
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesAplinfo extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesReport
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesReport extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesStartall
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesStartall extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesStopall
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesStopall extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesMigrateall
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesMigrateall extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
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

    /**
     * Class PVENodeNodesHosts
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVENodeNodesHosts extends Base
    {
        /**
         * @ignore
         */
        private $node;

        /**
         * @ignore
         */
        function __construct($client, $node)
        {
            $this->client = $client;
            $this->node = $node;
        }

        /**
         * Get the content of /etc/hosts.
         * @return Result
         */
        public function getRest()
        {
            return $this->getClient()->get("/nodes/{$this->node}/hosts");
        }

        /**
         * Get the content of /etc/hosts.
         * @return Result
         */
        public function getEtcHosts()
        {
            return $this->getRest();
        }

        /**
         * Write /etc/hosts.
         * @param string $data The target content of /etc/hosts.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function createRest($data, $digest = null)
        {
            $params = ['data' => $data,
                'digest' => $digest];
            return $this->getClient()->create("/nodes/{$this->node}/hosts", $params);
        }

        /**
         * Write /etc/hosts.
         * @param string $data The target content of /etc/hosts.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @return Result
         */
        public function writeEtcHosts($data, $digest = null)
        {
            return $this->createRest($data, $digest);
        }
    }

    /**
     * Class PVEStorage
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEStorage extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemStorageStorage
         * @param storage
         * @return PVEItemStorageStorage
         */
        public function get($storage)
        {
            return new PVEItemStorageStorage($this->client, $storage);
        }

        /**
         * Storage index.
         * @param string $type Only list storage of specific type
         *   Enum: cephfs,cifs,dir,drbd,fake,glusterfs,iscsi,iscsidirect,lvm,lvmthin,nfs,rbd,sheepdog,zfs,zfspool
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
         *   Enum: cephfs,cifs,dir,drbd,fake,glusterfs,iscsi,iscsidirect,lvm,lvmthin,nfs,rbd,sheepdog,zfs,zfspool
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
         *   Enum: cephfs,cifs,dir,drbd,fake,glusterfs,iscsi,iscsidirect,lvm,lvmthin,nfs,rbd,sheepdog,zfs,zfspool
         * @param string $authsupported Authsupported.
         * @param string $base Base volume. This volume is automatically activated.
         * @param string $blocksize block size
         * @param string $bwlimit Set bandwidth/io limits various operations.
         * @param string $comstar_hg host group for comstar views
         * @param string $comstar_tg target group for comstar views
         * @param string $content Allowed content types.  NOTE: the value 'rootdir' is used for Containers, and value 'images' for VMs.
         * @param bool $disable Flag to disable the storage.
         * @param string $domain CIFS domain.
         * @param string $export NFS export path.
         * @param string $format Default image format.
         * @param bool $fuse Mount CephFS through FUSE.
         * @param string $is_mountpoint Assume the given path is an externally managed mountpoint and consider the storage offline if it is not mounted. Using a boolean (yes/no) value serves as a shortcut to using the target path in this field.
         * @param string $iscsiprovider iscsi provider
         * @param bool $krbd Always access rbd through krbd kernel module.
         * @param string $lio_tpg target portal group for Linux LIO targets
         * @param int $maxfiles Maximal number of backup files per VM. Use '0' for unlimted.
         * @param bool $mkdir Create the directory if it doesn't exist.
         * @param string $monhost IP addresses of monitors (for external clusters).
         * @param string $nodes List of cluster node names.
         * @param bool $nowritecache disable write caching on the target
         * @param string $options NFS mount options (see 'man nfs')
         * @param string $password Password for CIFS share.
         * @param string $path File system path.
         * @param string $pool Pool.
         * @param string $portal iSCSI portal (IP or DNS name with optional port).
         * @param int $redundancy The redundancy count specifies the number of nodes to which the resource should be deployed. It must be at least 1 and at most the number of nodes in the cluster.
         * @param bool $saferemove Zero-out data when removing LVs.
         * @param string $saferemove_throughput Wipe throughput (cstream -t parameter value).
         * @param string $server Server IP or DNS name.
         * @param string $server2 Backup volfile server IP or DNS name.
         * @param string $share CIFS share.
         * @param bool $shared Mark storage as shared.
         * @param string $smbversion SMB protocol version
         *   Enum: 2.0,2.1,3.0
         * @param bool $sparse use sparse volumes
         * @param string $subdir Subdir to mount.
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
        public function createRest($storage, $type, $authsupported = null, $base = null, $blocksize = null, $bwlimit = null, $comstar_hg = null, $comstar_tg = null, $content = null, $disable = null, $domain = null, $export = null, $format = null, $fuse = null, $is_mountpoint = null, $iscsiprovider = null, $krbd = null, $lio_tpg = null, $maxfiles = null, $mkdir = null, $monhost = null, $nodes = null, $nowritecache = null, $options = null, $password = null, $path = null, $pool = null, $portal = null, $redundancy = null, $saferemove = null, $saferemove_throughput = null, $server = null, $server2 = null, $share = null, $shared = null, $smbversion = null, $sparse = null, $subdir = null, $tagged_only = null, $target = null, $thinpool = null, $transport = null, $username = null, $vgname = null, $volume = null)
        {
            $params = ['storage' => $storage,
                'type' => $type,
                'authsupported' => $authsupported,
                'base' => $base,
                'blocksize' => $blocksize,
                'bwlimit' => $bwlimit,
                'comstar_hg' => $comstar_hg,
                'comstar_tg' => $comstar_tg,
                'content' => $content,
                'disable' => $disable,
                'domain' => $domain,
                'export' => $export,
                'format' => $format,
                'fuse' => $fuse,
                'is_mountpoint' => $is_mountpoint,
                'iscsiprovider' => $iscsiprovider,
                'krbd' => $krbd,
                'lio_tpg' => $lio_tpg,
                'maxfiles' => $maxfiles,
                'mkdir' => $mkdir,
                'monhost' => $monhost,
                'nodes' => $nodes,
                'nowritecache' => $nowritecache,
                'options' => $options,
                'password' => $password,
                'path' => $path,
                'pool' => $pool,
                'portal' => $portal,
                'redundancy' => $redundancy,
                'saferemove' => $saferemove,
                'saferemove_throughput' => $saferemove_throughput,
                'server' => $server,
                'server2' => $server2,
                'share' => $share,
                'shared' => $shared,
                'smbversion' => $smbversion,
                'sparse' => $sparse,
                'subdir' => $subdir,
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
         *   Enum: cephfs,cifs,dir,drbd,fake,glusterfs,iscsi,iscsidirect,lvm,lvmthin,nfs,rbd,sheepdog,zfs,zfspool
         * @param string $authsupported Authsupported.
         * @param string $base Base volume. This volume is automatically activated.
         * @param string $blocksize block size
         * @param string $bwlimit Set bandwidth/io limits various operations.
         * @param string $comstar_hg host group for comstar views
         * @param string $comstar_tg target group for comstar views
         * @param string $content Allowed content types.  NOTE: the value 'rootdir' is used for Containers, and value 'images' for VMs.
         * @param bool $disable Flag to disable the storage.
         * @param string $domain CIFS domain.
         * @param string $export NFS export path.
         * @param string $format Default image format.
         * @param bool $fuse Mount CephFS through FUSE.
         * @param string $is_mountpoint Assume the given path is an externally managed mountpoint and consider the storage offline if it is not mounted. Using a boolean (yes/no) value serves as a shortcut to using the target path in this field.
         * @param string $iscsiprovider iscsi provider
         * @param bool $krbd Always access rbd through krbd kernel module.
         * @param string $lio_tpg target portal group for Linux LIO targets
         * @param int $maxfiles Maximal number of backup files per VM. Use '0' for unlimted.
         * @param bool $mkdir Create the directory if it doesn't exist.
         * @param string $monhost IP addresses of monitors (for external clusters).
         * @param string $nodes List of cluster node names.
         * @param bool $nowritecache disable write caching on the target
         * @param string $options NFS mount options (see 'man nfs')
         * @param string $password Password for CIFS share.
         * @param string $path File system path.
         * @param string $pool Pool.
         * @param string $portal iSCSI portal (IP or DNS name with optional port).
         * @param int $redundancy The redundancy count specifies the number of nodes to which the resource should be deployed. It must be at least 1 and at most the number of nodes in the cluster.
         * @param bool $saferemove Zero-out data when removing LVs.
         * @param string $saferemove_throughput Wipe throughput (cstream -t parameter value).
         * @param string $server Server IP or DNS name.
         * @param string $server2 Backup volfile server IP or DNS name.
         * @param string $share CIFS share.
         * @param bool $shared Mark storage as shared.
         * @param string $smbversion SMB protocol version
         *   Enum: 2.0,2.1,3.0
         * @param bool $sparse use sparse volumes
         * @param string $subdir Subdir to mount.
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
        public function create($storage, $type, $authsupported = null, $base = null, $blocksize = null, $bwlimit = null, $comstar_hg = null, $comstar_tg = null, $content = null, $disable = null, $domain = null, $export = null, $format = null, $fuse = null, $is_mountpoint = null, $iscsiprovider = null, $krbd = null, $lio_tpg = null, $maxfiles = null, $mkdir = null, $monhost = null, $nodes = null, $nowritecache = null, $options = null, $password = null, $path = null, $pool = null, $portal = null, $redundancy = null, $saferemove = null, $saferemove_throughput = null, $server = null, $server2 = null, $share = null, $shared = null, $smbversion = null, $sparse = null, $subdir = null, $tagged_only = null, $target = null, $thinpool = null, $transport = null, $username = null, $vgname = null, $volume = null)
        {
            return $this->createRest($storage, $type, $authsupported, $base, $blocksize, $bwlimit, $comstar_hg, $comstar_tg, $content, $disable, $domain, $export, $format, $fuse, $is_mountpoint, $iscsiprovider, $krbd, $lio_tpg, $maxfiles, $mkdir, $monhost, $nodes, $nowritecache, $options, $password, $path, $pool, $portal, $redundancy, $saferemove, $saferemove_throughput, $server, $server2, $share, $shared, $smbversion, $sparse, $subdir, $tagged_only, $target, $thinpool, $transport, $username, $vgname, $volume);
        }
    }

    /**
     * Class PVEItemStorageStorage
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemStorageStorage extends Base
    {
        /**
         * @ignore
         */
        private $storage;

        /**
         * @ignore
         */
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
         * @param string $bwlimit Set bandwidth/io limits various operations.
         * @param string $comstar_hg host group for comstar views
         * @param string $comstar_tg target group for comstar views
         * @param string $content Allowed content types.  NOTE: the value 'rootdir' is used for Containers, and value 'images' for VMs.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $disable Flag to disable the storage.
         * @param string $domain CIFS domain.
         * @param string $format Default image format.
         * @param bool $fuse Mount CephFS through FUSE.
         * @param string $is_mountpoint Assume the given path is an externally managed mountpoint and consider the storage offline if it is not mounted. Using a boolean (yes/no) value serves as a shortcut to using the target path in this field.
         * @param bool $krbd Always access rbd through krbd kernel module.
         * @param string $lio_tpg target portal group for Linux LIO targets
         * @param int $maxfiles Maximal number of backup files per VM. Use '0' for unlimted.
         * @param bool $mkdir Create the directory if it doesn't exist.
         * @param string $monhost IP addresses of monitors (for external clusters).
         * @param string $nodes List of cluster node names.
         * @param bool $nowritecache disable write caching on the target
         * @param string $options NFS mount options (see 'man nfs')
         * @param string $password Password for CIFS share.
         * @param string $pool Pool.
         * @param int $redundancy The redundancy count specifies the number of nodes to which the resource should be deployed. It must be at least 1 and at most the number of nodes in the cluster.
         * @param bool $saferemove Zero-out data when removing LVs.
         * @param string $saferemove_throughput Wipe throughput (cstream -t parameter value).
         * @param string $server Server IP or DNS name.
         * @param string $server2 Backup volfile server IP or DNS name.
         * @param bool $shared Mark storage as shared.
         * @param string $smbversion SMB protocol version
         *   Enum: 2.0,2.1,3.0
         * @param bool $sparse use sparse volumes
         * @param string $subdir Subdir to mount.
         * @param bool $tagged_only Only use logical volumes tagged with 'pve-vm-ID'.
         * @param string $transport Gluster transport: tcp or rdma
         *   Enum: tcp,rdma,unix
         * @param string $username RBD Id.
         * @return Result
         */
        public function setRest($blocksize = null, $bwlimit = null, $comstar_hg = null, $comstar_tg = null, $content = null, $delete = null, $digest = null, $disable = null, $domain = null, $format = null, $fuse = null, $is_mountpoint = null, $krbd = null, $lio_tpg = null, $maxfiles = null, $mkdir = null, $monhost = null, $nodes = null, $nowritecache = null, $options = null, $password = null, $pool = null, $redundancy = null, $saferemove = null, $saferemove_throughput = null, $server = null, $server2 = null, $shared = null, $smbversion = null, $sparse = null, $subdir = null, $tagged_only = null, $transport = null, $username = null)
        {
            $params = ['blocksize' => $blocksize,
                'bwlimit' => $bwlimit,
                'comstar_hg' => $comstar_hg,
                'comstar_tg' => $comstar_tg,
                'content' => $content,
                'delete' => $delete,
                'digest' => $digest,
                'disable' => $disable,
                'domain' => $domain,
                'format' => $format,
                'fuse' => $fuse,
                'is_mountpoint' => $is_mountpoint,
                'krbd' => $krbd,
                'lio_tpg' => $lio_tpg,
                'maxfiles' => $maxfiles,
                'mkdir' => $mkdir,
                'monhost' => $monhost,
                'nodes' => $nodes,
                'nowritecache' => $nowritecache,
                'options' => $options,
                'password' => $password,
                'pool' => $pool,
                'redundancy' => $redundancy,
                'saferemove' => $saferemove,
                'saferemove_throughput' => $saferemove_throughput,
                'server' => $server,
                'server2' => $server2,
                'shared' => $shared,
                'smbversion' => $smbversion,
                'sparse' => $sparse,
                'subdir' => $subdir,
                'tagged_only' => $tagged_only,
                'transport' => $transport,
                'username' => $username];
            return $this->getClient()->set("/storage/{$this->storage}", $params);
        }

        /**
         * Update storage configuration.
         * @param string $blocksize block size
         * @param string $bwlimit Set bandwidth/io limits various operations.
         * @param string $comstar_hg host group for comstar views
         * @param string $comstar_tg target group for comstar views
         * @param string $content Allowed content types.  NOTE: the value 'rootdir' is used for Containers, and value 'images' for VMs.
         * @param string $delete A list of settings you want to delete.
         * @param string $digest Prevent changes if current configuration file has different SHA1 digest. This can be used to prevent concurrent modifications.
         * @param bool $disable Flag to disable the storage.
         * @param string $domain CIFS domain.
         * @param string $format Default image format.
         * @param bool $fuse Mount CephFS through FUSE.
         * @param string $is_mountpoint Assume the given path is an externally managed mountpoint and consider the storage offline if it is not mounted. Using a boolean (yes/no) value serves as a shortcut to using the target path in this field.
         * @param bool $krbd Always access rbd through krbd kernel module.
         * @param string $lio_tpg target portal group for Linux LIO targets
         * @param int $maxfiles Maximal number of backup files per VM. Use '0' for unlimted.
         * @param bool $mkdir Create the directory if it doesn't exist.
         * @param string $monhost IP addresses of monitors (for external clusters).
         * @param string $nodes List of cluster node names.
         * @param bool $nowritecache disable write caching on the target
         * @param string $options NFS mount options (see 'man nfs')
         * @param string $password Password for CIFS share.
         * @param string $pool Pool.
         * @param int $redundancy The redundancy count specifies the number of nodes to which the resource should be deployed. It must be at least 1 and at most the number of nodes in the cluster.
         * @param bool $saferemove Zero-out data when removing LVs.
         * @param string $saferemove_throughput Wipe throughput (cstream -t parameter value).
         * @param string $server Server IP or DNS name.
         * @param string $server2 Backup volfile server IP or DNS name.
         * @param bool $shared Mark storage as shared.
         * @param string $smbversion SMB protocol version
         *   Enum: 2.0,2.1,3.0
         * @param bool $sparse use sparse volumes
         * @param string $subdir Subdir to mount.
         * @param bool $tagged_only Only use logical volumes tagged with 'pve-vm-ID'.
         * @param string $transport Gluster transport: tcp or rdma
         *   Enum: tcp,rdma,unix
         * @param string $username RBD Id.
         * @return Result
         */
        public function update($blocksize = null, $bwlimit = null, $comstar_hg = null, $comstar_tg = null, $content = null, $delete = null, $digest = null, $disable = null, $domain = null, $format = null, $fuse = null, $is_mountpoint = null, $krbd = null, $lio_tpg = null, $maxfiles = null, $mkdir = null, $monhost = null, $nodes = null, $nowritecache = null, $options = null, $password = null, $pool = null, $redundancy = null, $saferemove = null, $saferemove_throughput = null, $server = null, $server2 = null, $shared = null, $smbversion = null, $sparse = null, $subdir = null, $tagged_only = null, $transport = null, $username = null)
        {
            return $this->setRest($blocksize, $bwlimit, $comstar_hg, $comstar_tg, $content, $delete, $digest, $disable, $domain, $format, $fuse, $is_mountpoint, $krbd, $lio_tpg, $maxfiles, $mkdir, $monhost, $nodes, $nowritecache, $options, $password, $pool, $redundancy, $saferemove, $saferemove_throughput, $server, $server2, $shared, $smbversion, $sparse, $subdir, $tagged_only, $transport, $username);
        }
    }

    /**
     * Class PVEAccess
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAccess extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * @ignore
         */
        private $users;

        /**
         * Get AccessUsers
         * @return PVEAccessUsers
         */
        public function getUsers()
        {
            return $this->users ?: ($this->users = new PVEAccessUsers($this->client));
        }

        /**
         * @ignore
         */
        private $groups;

        /**
         * Get AccessGroups
         * @return PVEAccessGroups
         */
        public function getGroups()
        {
            return $this->groups ?: ($this->groups = new PVEAccessGroups($this->client));
        }

        /**
         * @ignore
         */
        private $roles;

        /**
         * Get AccessRoles
         * @return PVEAccessRoles
         */
        public function getRoles()
        {
            return $this->roles ?: ($this->roles = new PVEAccessRoles($this->client));
        }

        /**
         * @ignore
         */
        private $acl;

        /**
         * Get AccessAcl
         * @return PVEAccessAcl
         */
        public function getAcl()
        {
            return $this->acl ?: ($this->acl = new PVEAccessAcl($this->client));
        }

        /**
         * @ignore
         */
        private $domains;

        /**
         * Get AccessDomains
         * @return PVEAccessDomains
         */
        public function getDomains()
        {
            return $this->domains ?: ($this->domains = new PVEAccessDomains($this->client));
        }

        /**
         * @ignore
         */
        private $ticket;

        /**
         * Get AccessTicket
         * @return PVEAccessTicket
         */
        public function getTicket()
        {
            return $this->ticket ?: ($this->ticket = new PVEAccessTicket($this->client));
        }

        /**
         * @ignore
         */
        private $password;

        /**
         * Get AccessPassword
         * @return PVEAccessPassword
         */
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

    /**
     * Class PVEAccessUsers
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAccessUsers extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemUsersAccessUserid
         * @param userid
         * @return PVEItemUsersAccessUserid
         */
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
         * @param bool $enable Enable the account (default). You can set this to '0' to disable the account
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
         * @param bool $enable Enable the account (default). You can set this to '0' to disable the account
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

    /**
     * Class PVEItemUsersAccessUserid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemUsersAccessUserid extends Base
    {
        /**
         * @ignore
         */
        private $userid;

        /**
         * @ignore
         */
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
         * @param bool $enable Enable the account (default). You can set this to '0' to disable the account
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
         * @param bool $enable Enable the account (default). You can set this to '0' to disable the account
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

    /**
     * Class PVEAccessGroups
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAccessGroups extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemGroupsAccessGroupid
         * @param groupid
         * @return PVEItemGroupsAccessGroupid
         */
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

    /**
     * Class PVEItemGroupsAccessGroupid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemGroupsAccessGroupid extends Base
    {
        /**
         * @ignore
         */
        private $groupid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEAccessRoles
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAccessRoles extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemRolesAccessRoleid
         * @param roleid
         * @return PVEItemRolesAccessRoleid
         */
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

    /**
     * Class PVEItemRolesAccessRoleid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemRolesAccessRoleid extends Base
    {
        /**
         * @ignore
         */
        private $roleid;

        /**
         * @ignore
         */
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
         * Update an existing role.
         * @param bool $append
         * @param string $privs
         * @return Result
         */
        public function setRest($append = null, $privs = null)
        {
            $params = ['append' => $append,
                'privs' => $privs];
            return $this->getClient()->set("/access/roles/{$this->roleid}", $params);
        }

        /**
         * Update an existing role.
         * @param bool $append
         * @param string $privs
         * @return Result
         */
        public function updateRole($append = null, $privs = null)
        {
            return $this->setRest($append, $privs);
        }
    }

    /**
     * Class PVEAccessAcl
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAccessAcl extends Base
    {
        /**
         * @ignore
         */
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

    /**
     * Class PVEAccessDomains
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAccessDomains extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemDomainsAccessRealm
         * @param realm
         * @return PVEItemDomainsAccessRealm
         */
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

    /**
     * Class PVEItemDomainsAccessRealm
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemDomainsAccessRealm extends Base
    {
        /**
         * @ignore
         */
        private $realm;

        /**
         * @ignore
         */
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

    /**
     * Class PVEAccessTicket
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAccessTicket extends Base
    {
        /**
         * @ignore
         */
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

    /**
     * Class PVEAccessPassword
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEAccessPassword extends Base
    {
        /**
         * @ignore
         */
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
        public function changePassword($password, $userid)
        {
            return $this->setRest($password, $userid);
        }
    }

    /**
     * Class PVEPools
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEPools extends Base
    {
        /**
         * @ignore
         */
        function __construct($client)
        {
            $this->client = $client;
        }

        /**
         * Get ItemPoolsPoolid
         * @param poolid
         * @return PVEItemPoolsPoolid
         */
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

    /**
     * Class PVEItemPoolsPoolid
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEItemPoolsPoolid extends Base
    {
        /**
         * @ignore
         */
        private $poolid;

        /**
         * @ignore
         */
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

    /**
     * Class PVEVersion
     * @package EnterpriseVE\ProxmoxVE\Api
     */
    class PVEVersion extends Base
    {
        /**
         * @ignore
         */
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