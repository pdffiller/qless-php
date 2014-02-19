<?php

namespace Qless;

class Jobs implements \ArrayAccess {

    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client) {
        $this->client = $client;
    }

    /**
     * Return a paginated list of JIDs which are in a completed state
     *
     * @param int $offset
     * @param int $count
     *
     * @return string[]
     */
    public function complete($offset=0, $count=25) {
        return $this->client->jobs('complete', $offset, $count);
    }

    /**
     * Return {@see Job} objects for the specified job identifiers
     *
     * @param string[] $jids a list of job identifiers to fetch
     *
     * @return Job[]
     */
    public function get($jids) {
        if (empty($jids)) return [];

        $results = call_user_func_array([$this->client, 'multiget'], $jids);
        $jobs = json_decode($results, true);
        $ret = [];
        foreach ($jobs as $job_data) {
            $ret []= new Job($this->client, $job_data);
        }
        return $ret;
    }

    /**
     * Fetches a report of failed jobs for the specified group
     *
     * @param bool $group
     * @param int  $start
     * @param int  $limit
     *
     * @return \Iterator|Job[]
     */
    public function failedForGroup($group, $start=0, $limit=25) {
        $results = json_decode($this->client->failed($group, $start, $limit), true);
        $results['jobs'] = $this->get($results['jobs']);
        return $results;
    }

    /**
     * Fetches a report of failed jobs, where the key is the group and the value is the number of jobs
     *
     * @return array
     */
    public function failed() {
        return json_decode($this->client->failed(), true);
    }

    #region ArrayAccess

    /**
     * @inheritdoc
     */
    public function offsetExists($jid) {
        return $this->client->get($jid) !== false;
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($jid) {
        $job_data = $this->client->get($jid);
        if ($job_data === false) {
            $job_data = $this->client->{'recur.get'}($jid);
            if ($job_data === false) return null;
        }

        return new Job($this->client, json_decode($job_data, true));
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value) {
        throw new \LogicException('set not supported');
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset) {
        throw new \LogicException('unset not supported');
    }

    #endregion
}