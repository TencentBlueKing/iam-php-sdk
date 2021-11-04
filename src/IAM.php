<?php
/*
 * TencentBlueKing is pleased to support the open source community by making
 * 蓝鲸智云-权限中心PHP SDK(iam-php-sdk) available.
 * Copyright (C) 2017-2021 THL A29 Limited, a Tencent company. All rights reserved.
 * Licensed under the MIT License (the "License"); you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at http://opensource.org/licenses/MIT
 * Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations under the License.
 */

declare(strict_types=1);

namespace IAM;

use Exception;
use IAM\Client\IAMClient;
use IAM\Evaluation\ExprCell;
use IAM\Evaluation\ObjectSet;
use IAM\Model\Application;
use IAM\Model\MultiActionRequest;
use IAM\Model\Request;
use IAM\Model\Resource;
use JsonMapper;
use JsonMapper_Exception;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Cache\CacheItemPoolInterface;
use Google\Auth\Cache\MemoryCacheItemPool;
use Safe\Exceptions\StringsException;
use function Safe\substr;

class IAM
{
    /**
     * @var IAMClient
     */
    private $client;

    /**
     * @var LoggerInterface The PSR-3 logger
     */
    private $logger;

    /**
     * @var CacheItemPoolInterface
     */
    private $pool;
    /**
     * @var int
     */
    private $ttl;


    /**
     * @throws Exception
     */
    public function __construct(
        string           $app_code,
        string $app_secret,
        string           $bk_iam_host = "",
        string $bk_paas_host = "",
        string $bk_apigateway_url = "",
        ?LoggerInterface $logger = null,
        bool $debug = false,
        ?CacheItemPoolInterface $pool = null,
        int $ttl = 10
    ) {
        $this->client = new IAMClient(
            $app_code,
            $app_secret,
            $bk_iam_host,
            $bk_paas_host,
            $bk_apigateway_url,
            $logger,
            $debug
        );

        $this->logger = $logger ?? new NullLogger();

        $this->pool = $pool ?? new MemoryCacheItemPool();
        $this->ttl = $ttl;
    }

    /**
     *  如果只有一个本地资源, 直接返回不带类型的ID;
     *     [("flow", "1")]   => "1"
     *  如果存在层级资源 返回 {type},{id}/{type2},{id2}
     *     [("cluster", "a"), ("area", "b")) =>  "cluster,a/area,b"
     *
     *  NOTE: 这里不会存在=> 跨系统资源依赖
     *     不会存在=> [("job", "script", "a"), ("cmdb", "host", "b")) =>  "job:script,a/cmdb:host,b"
     * @param string $system
     * @param Resource $resource
     * @return string
     * @throws Exception
     */
    protected function getResourceID(string $system, Resource $resource): string
    {
        $ids = [];

        if ($resource->size() == 1) {
            return $resource->getResourceChainNodes()[0]->getId();
        }

        foreach ($resource as $node) {
            if ($node["system"] != $system) {
                throw new Exception("getResourceID not support make resourceID with not own system");
            }

            $ids[] = $node["type"] . "," . $node["id"];
        }

        return implode("/", $ids);
    }

    /**
     * 构建object set用于策略执行
     * @param string $system
     * @param Resource $resource
     * @param bool $only_local
     * @return ObjectSet
     */
    protected function buildObjectSet(string $system, Resource $resource, bool $only_local = false): ObjectSet
    {
        $obj_set = new ObjectSet();

        // if no resource(array size 0)
        if ($resource->size() == 0) {
            return $obj_set;
        }

        foreach ($resource as $node) {
            // only local resource need to be calculated
            // 跨系统资源依赖的策略在服务端就计算完了, 策略表达式中只会存在本系统的
            if ($only_local && $node->getSystem() != $system) {
                continue;
            }

            $attrs = $node->getAttribute();
            $attrs["id"] = $node->getId();

            $obj_set->add($node->getType(), $attrs);
        }
        return $obj_set;
    }

    /**
     * @param Request $request
     * @param bool $withResource
     * @return array
     */
    protected function doPolicyQuery(Request $request, bool $withResource = true): array
    {
        $data = $request->toArray();

        # NOTE: 不向服务端传任何resource, 用于统一类资源的批量鉴权
        # 将会返回所有策略, 然后遍历资源列表和策略列表, 逐一计算
        if (!$withResource) {
            $data["resources"] = [];
        }

        $this->logger->debug("the request: ", ['data'=>$data]);
        return $this->client->policyQuery($data);
    }

    /**
     * @param MultiActionRequest $request
     * @param bool $withResource
     * @return array
     */
    protected function doPolicyQueryByActions(MultiActionRequest $request, bool $withResource = true): array
    {
        $data = $request->toArray();

        # NOTE: 不向服务端传任何resource, 用于统一类资源的批量鉴权
        # 将会返回所有策略, 然后遍历资源列表和策略列表, 逐一计算
        if (!$withResource) {
            $data["resources"] = [];
        }

        $this->logger->debug("the request: ", ['data'=>$data]);
        return $this->client->policyQueryByActions($data);
    }

    /**
     * @param array $policy
     * @return ExprCell
     * @throws JsonMapper_Exception
     */
    protected function makeExpression(array $policy): ExprCell
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;

        return $mapper->map($policy, new ExprCell());
    }

    /**
     * 单个资源是否有权限校验
     * request中会带resource到IAM, IAM会进行两阶段计算, 即resources也会参与到计算中
     * 支持:
     * - 本地资源 resources中只有本地资源
     * - 跨系统资源依赖 resources中有本地也有远程资源 (此时resoruces一定要传, 因为需要IAM帮助获取跨系统资源)
     * @param Request $request
     * @return bool
     * @throws JsonMapper_Exception
     * @throws Exception
     */
    public function isAllowed(Request $request): bool
    {
        $this->logger->debug("calling IAM.isAllowed(request)......");

        $request-> validate();
        $policy = $this->doPolicyQuery($request);

        $this->logger->debug("the return: ", ['policy'=> $policy]);
        if (empty($policy)) {
            $this->logger->debug("no return policies, will return False");
            return false;
        }

        $obj_set = $this->buildObjectSet($request->getSystem(), $request->getResource(), false);
        $expr = $this->makeExpression($policy);

        return $expr->eval($obj_set);
    }

    /**
     * 单个资源是否有权限校验, 缓存一段时间, 用于非敏感权限获得更快的鉴权速度
     * @param Request $request
     * @return bool
     * @throws JsonMapper_Exception
     */
    public function isAllowedWithCache(Request $request): bool
    {
        $this->logger->debug("calling IAM.isAllowedWithCache(request)......");

        $key = $request->hash();

        try {
            $item = $this->pool->getItem($key);
            if ($item->isHit()) {
                return $item->get();
            }

            $is_allowed = $this->isAllowed($request);

            $item->set($is_allowed);
            $item->expiresAfter($this->ttl);
            $this->pool->save($item);

            return $is_allowed;
        } catch (InvalidArgumentException $e) {
            $this->logger->error("get Item from cache fail!", ['error'=>$e]);
        }

        return false;
    }

    /**
     * 多个资源是否有权限校验
     * request中不会带resource到IAM, IAM不会会进行两阶段计算, 直接返回system+action+subejct的所有策略 然后逐一计算
     * - 一次策略查询, 多次计算
     * 支持:
     * - 本地资源 resources中只有本地资源
     * - **不支持**跨系统资源依赖
     * @param Request $request
     * @param Resource[] $resource_list
     * @return array
     * @throws JsonMapper_Exception
     * @throws Exception
     */
    public function batchIsAllowed(Request $request, array $resource_list): array
    {
        $this->logger->debug("calling IAM->batchIsAllowed(request, resources_list)......");

        // 1. validate
        $request-> validate();

        // 2. _client.policy_query
        // NOTE: 不向服务端传任何resource
        $policy = $this->doPolicyQuery($request, false);
        $this->logger->debug("the return: ", ['policy'=> $policy]);

        if (empty($policy)) {
            $this->logger->debug("no return policies, will return False");

            $result = [];
            foreach ($resource_list as $resource) {
                $result[$this->getResourceID($request->getSystem(), $resource)] = false;
            }
            return $result;
        }

        // 3. make expr
        $expr = $this->makeExpression($policy);

        // 4. make objSet and eval
        $result = [];
        foreach ($resource_list as $resource) {
            $obj_set = $this->buildObjectSet($request->getSystem(), $resource, false);
            $resource_id = $this->getResourceID($request->getSystem(), $resource);

            $result[$resource_id] = $expr->eval($obj_set);
        }

        return $result;
    }

    /**
     * 单个资源多个action是否有权限校验
     * request中会带resource到IAM, IAM会进行两阶段计算, 即resources也会参与到计算中
     * 支持:
     *   - 本地资源 resources中只有本地资源
     *   - 跨系统资源依赖 resources中有本地也有远程资源 (此时resoruces一定要传, 因为需要IAM帮助获取跨系统资源)
     * @param MultiActionRequest $request
     * @return array
     * @throws Exception
     */
    public function resourceMultiActionsAllowed(MultiActionRequest $request): array
    {
        $this->logger->debug("calling IAM->resourceMultiActionsAllowed(request)......");
        // 1. validate
        $request-> validate();

        // 2. _client.policy_query_by_actions
        $action_policy = $this->doPolicyQueryByActions($request);
        $this->logger->debug("the return action=>policy: ", $action_policy);

        if (count($action_policy) == 0) {
            $this->logger->debug("no return policies, will reject all perms");

            $actions_allowed = [];
            foreach ($request->getActions() as $action) {
                $actions_allowed[$action->getId()] = false;
            }
            return $actions_allowed;
        }

        // 3. generate objSet
        $obj_set = $this->buildObjectSet($request->getSystem(), $request->getResource(), true);

        # 4. 一个策略是一个表达式, 计算一次
        $actions_allowed = [];
        foreach ($action_policy as $ap) {
            $action_id = $ap["action"]["id"];
            $policy = $ap["condition"];
            $expr = $this->makeExpression($policy);

            $actions_allowed[$action_id] = $expr->eval($obj_set);
        }
        return $actions_allowed;
    }

    /**
     * 批量资源多个action是否有权限校验
     * request中会带resource到IAM, IAM会进行两阶段计算, 即resources也会参与到计算中
     * 支持:
     *   - 本地资源 resources中只有本地资源
     *   - **不支持**跨系统资源依赖
     * @param MultiActionRequest $request
     * @param array $resource_list
     * @return array
     * @throws JsonMapper_Exception
     * @throws Exception
     */
    public function batchResourceMultiActionsAllowed(MultiActionRequest $request, array $resource_list): array
    {
        $this->logger->debug("calling IAM.batchResourceMultiActionsAllowed(request, resources_list)......");
        // 1. validate
        $request-> validate();
        foreach ($resource_list as $r) {
            if (!($r instanceof Resource)) {
                throw new Exception("the item in resource_list should be instance of Resource");
            }
        }

        // 2. _client.policy_query_by_actions
        // NOTE: 不向服务端传任何resource
        $action_policy = $this->doPolicyQueryByActions($request, false);
        $this->logger->debug("the return action=>policy: ", $action_policy);

        if (count($action_policy) == 0) {
            $this->logger->debug("no return policies, will reject all perms");

            $resource_actions_allowed = [];
            foreach ($resource_list as $r) {
                // init it as an array
                $resource_id = $this->getResourceID($request->getSystem(), $r);
                $resource_actions_allowed[$resource_id] = [];
                foreach ($request->getActions() as $action) {
                    $resource_actions_allowed[$resource_id][$action->getId()] = false;
                }
            }
            return $resource_actions_allowed;
        }

        // 3. for loop resource to do eval
        $resource_actions_allowed = [];
        foreach ($resource_list as $r) {
            $obj_set = $this->buildObjectSet($request->getSystem(), $r, false);
            # FIXME: 未来这里会支持同一个系统的不同资源, 届时怎么表示?

            $resource_id = $this->getResourceID($request->getSystem(), $r);
            $resource_actions_allowed[$resource_id] = [];

            foreach ($action_policy as $ap) {
                $action_id = $ap["action"]["id"];
                $policy = $ap["condition"];
                $expr = $this->makeExpression($policy);

                $resource_actions_allowed[$resource_id][$action_id] = $expr->eval($obj_set);
            }
        }
        return $resource_actions_allowed;
    }

    /**
     * @param string $system
     * @return string
     * @throws StringsException
     */
    public function getToken(string $system): string
    {
        return $this->client->getToken($system);
    }

    /**
     * @param string $system
     * @param string $username
     * @param string $password
     * @return bool
     * @throws StringsException
     */
    public function isBasicAuthAllowed(string $system, string $username, string $password): bool
    {
        if ($username != "bk_iam") {
            $this->logger->error("username is not bk_iam");
            return false;
        }

        try {
            $token = $this->client->getToken($system);
        } catch (Exception $e) {
            $this->logger->error("get system token from iam fail", ['error' => $e->getMessage()]);
            return false;
        }

        if ($password != $token) {
            $this->logger->error("password in basic_auth not equals to system token", [
                'password' => substr($password, 0, 6).'***',
                'token' => substr($token, 0, 6).'***',
            ]);
            return false;
        }

        return true;
    }

    /**
     * @param Application $application
     * @param string $bk_token
     * @param string $bk_username
     * @return string
     * @throws Exception
     */
    public function getApplyUrl(Application $application, string $bk_token, string $bk_username): string
    {
        $application->validate();

        // 1. validate
        if ($bk_token == "" && $bk_username == "") {
            throw new Exception("bk_token and bk_username can not both be empty");
        }
        return $this->client->getApplyUrl($application->toArray(), $bk_token, $bk_username);
    }
}
