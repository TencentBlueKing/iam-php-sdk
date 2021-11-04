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

namespace IAM\Client;

use Exception;
use Safe\Exceptions\JsonException;
use Safe\Exceptions\StringsException;
use function Safe\sprintf;
use function Safe\substr;
use function Safe\json_encode;
use Fig\Http\Message\RequestMethodInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class IAMClient
{
    private const BK_IAM_VERSION = "1";

    /**
     * @var HttpClient
     */
    private $http_client;

    /**
     * @var string
     */
    private $app_code;

    /**
     * @var string
     */
    private $app_secret;

    /**
     * @var string
     */
    private $host;

    /**
     * @var null|string
     */
    private $bk_paas_host;

    /**
     * @var bool
     */
    private $apigateway_on;

    /**
     * @var LoggerInterface The PSR-3 logger
     */
    private $logger;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @throws Exception
     */
    public function __construct(
        string $app_code,
        string $app_secret,
        string $bk_iam_host = "",
        string $bk_paas_host = "",
        string $bk_apigateway_url = "",
        ?LoggerInterface $logger = null,
        bool $debug = false
    ) {
        $this->app_code = $app_code;
        $this->app_secret = $app_secret;

        $this->apigateway_on = false;
        if ($bk_apigateway_url != "") {
            $this->apigateway_on = true;

            # replace the host
            $this->host = rtrim($bk_apigateway_url, "/");
        } else {
            if ($bk_iam_host == "" || $bk_paas_host == "") {
                throw new Exception("init Client fail, bk_iam_host and bk_paas_host should not be empty");
            }

            $this->host = $bk_iam_host;
            $this->bk_paas_host = $bk_paas_host;
        }

        $this->logger = $logger ?? new NullLogger();
        $this->debug = $debug;
        $this->http_client = new HttpClient();
    }

    /**
     * @param string $http_func
     * @param string $host
     * @param string $path
     * @param array $data
     * @param array $headers
     * @param int $timeout
     * @return array
     * @throws JsonException
     * @throws StringsException
     * @throws Exception
     */
    protected function sendRequest(
        string $http_func,
        string $host,
        string $path,
        array $data,
        array $headers,
        int $timeout = 0
    ): array {
        if ($this->debug) {
            $path = $path . "?debug=true&force=true";
        }

        $this->logger->debug(
            "[IAM] do http request.",
            ['method'=>$http_func, 'host'=>$host, 'path'=>$path, 'data'=>$data]
        );

        $start = microtime(true);
        try {
            $data = $this->http_client->send($http_func, $host, $path, $data, $headers, $timeout);
        } catch (Exception $e) {
            $this->logger->error(
                "[IAM] http request fail.",
                ['method'=>$http_func, 'host'=>$host, 'path'=>$path, 'data'=>$data, 'error'=>$e->getMessage()]
            );
            throw $e;
        }

        $content = json_encode($data);

        $this->logger->debug("[IAM] http request result", $data);
        $time_elapsed_secs = microtime(true) - $start;
        $this->logger->debug("[IAM] http request took", ['ms' => $time_elapsed_secs * 1000]);

        if (!array_key_exists("code", $data)) {
            throw new Exception(sprintf("got no `code` in response json body. content=`%s`", $content));
        }

        if ($data["code"] != 0) {
            throw new Exception(sprintf(
                "the `code` in response json body is `%d`, not `0`. content=`%s`",
                $data["code"],
                $content
            ));
        }

        if (!array_key_exists("data", $data)) {
            throw new Exception(sprintf("got no `data` in response json body. content=`%s`", $content));
        }
        return $data["data"];
    }


    /**
     * 统一后, 所有接口调用走APIGateway
     * @throws Exception
     */
    protected function sendRequestToAPIGateway(string $http_func, string $path, array $data, int $timeout = 0): array
    {
        $headers = [
            'X-Bkapi-Authorization' => json_encode([
                'bk_app_code' => $this->app_code,
                'bk_app_secret' => $this->app_secret]),
            "X-Bk-IAM-Version" => $this::BK_IAM_VERSION,
        ];

        return $this->sendRequest($http_func, $this->host, $path, $data, $headers, $timeout);
    }

    /**
     * 兼容切换到apigateway, 统一后, 这个方法应该去掉
     * @param string $http_func
     * @param string $path
     * @param array $data
     * @param int $timeout
     * @return array
     * @throws JsonException
     * @throws StringsException
     */
    public function callIamAPI(string $http_func, string $path, array $data, int $timeout = 0)
    {
        if ($this->apigateway_on) {
            return $this->sendRequestToAPIGateway($http_func, $path, $data, $timeout);
        }

        //call directly
        $headers = [
            'X-BK-APP-CODE' => $this->app_code,
            'X-BK-APP-SECRET' => $this->app_secret,
            'X-Bk-IAM-Version' => $this::BK_IAM_VERSION,
        ];

        return $this->sendRequest($http_func, $this->host, $path, $data, $headers, $timeout);
    }


    /**
     * 兼容切换到apigateway, 统一后, 这个方法应该去掉
     * @param string $http_func
     * @param string $path
     * @param array $data
     * @param string $bk_token
     * @param string $bk_username
     * @param int $timeout
     * @return array
     * @throws JsonException
     * @throws StringsException
     * @throws Exception
     */
    public function callEsbAPI(
        string $http_func,
        string $path,
        array $data,
        string $bk_token,
        string $bk_username,
        int $timeout = 0
    ) {
        if ($this->apigateway_on) {
            $apigw_path = str_replace('/api/c/compapi/v2/iam/', '/api/v1/open/', $path);

            $path_prefix = '/api/v1/open/';
            if (substr($apigw_path, 0, strlen($path_prefix)) != $path_prefix) {
                throw new Exception(sprintf("can't find the matched apigateway path, the esb api path is %s", $path));
            }

            return $this->sendRequestToAPIGateway($http_func, $apigw_path, $data, $timeout);
        }

        $headers = [];

        $data['bk_app_code'] = $this->app_code;
        $data['bk_app_secret'] = $this->app_secret;
        $data['bk_token'] = $bk_token;
        $data['bk_username'] = $bk_username;

        return $this->sendRequest($http_func, $this->bk_paas_host, $path, $data, $headers, $timeout);
    }

    /**
     * call /ping, it's special, will not use the call_xxx_api
     * @return array
     * @throws Exception
     */
    public function ping(): array
    {
        return $this->http_client->send("GET", "http://127.0.0.1:9000", "/ping", [], [], 60);
    }

    /**
     * @param string $system_id
     * @return string
     * @throws StringsException
     * @throws Exception
     */
    public function getToken(string $system_id): string
    {
        $path = sprintf("/api/v1/model/systems/%s/token", $system_id);
        $data = $this->callIamAPI(RequestMethodInterface::METHOD_GET, $path, []);

        if (!array_key_exists("token", $data)) {
            throw new Exception("no `token` in response json body");
        }

        return $data["token"];
    }

    public function policyQuery(array $json): array
    {
        $path = "/api/v1/policy/query";
        return $this->callIamAPI(RequestMethodInterface::METHOD_POST, $path, $json);
    }

    public function policyQueryByActions(array $json): array
    {
        $path = "/api/v1/policy/query_by_actions";
        return $this->callIamAPI(RequestMethodInterface::METHOD_POST, $path, $json);
    }

    public function policyAuth(array $json): array
    {
        $path = "/api/v1/policy/auth";
        return $this->callIamAPI(RequestMethodInterface::METHOD_POST, $path, $json);
    }

    public function policyAuthByResources(array $json): array
    {
        $path = "/api/v1/policy/auth_by_resources";
        return $this->callIamAPI(RequestMethodInterface::METHOD_POST, $path, $json);
    }

    public function policyAuthByActions(array $json): array
    {
        $path = "/api/v1/policy/auth_by_actions";
        return $this->callIamAPI(RequestMethodInterface::METHOD_POST, $path, $json);
    }

    /**
     * @throws StringsException
     */
    public function policyGet(string $system_id, int $policy_id): array
    {
        $path = sprintf("/api/v1/systems/%s/policies/%d", $system_id, $policy_id);
        return $this->callIamAPI(RequestMethodInterface::METHOD_GET, $path, []);
    }

    /**
     * @throws StringsException
     */
    public function policyList(string $system_id, array $query): array
    {
        $path = sprintf("/api/v1/systems/%s/policies", $system_id);
        return $this->callIamAPI(RequestMethodInterface::METHOD_GET, $path, $query);
    }

    /**
     * @throws StringsException
     */
    public function policySubjects(string $system_id, array $policy_ids): array
    {
        $path = sprintf("/api/v1/systems/%s/policies/-/subjects", $system_id);

        $query = [
            "ids" =>implode(",", array_map("strval", $policy_ids))
        ];

        return $this->callIamAPI(RequestMethodInterface::METHOD_GET, $path, $query);
    }

    /**
     * @throws Exception
     */
    public function getApplyUrl(array $json, string $bk_token, string $bk_username): string
    {
        $path = "/api/c/compapi/v2/iam/application/";

        $data = $this->callEsbAPI(RequestMethodInterface::METHOD_POST, $path, $json, $bk_token, $bk_username);

        if (!array_key_exists("url", $data)) {
            throw new Exception("no `url` in response json body");
        }

        return  $data["url"];
    }
}
