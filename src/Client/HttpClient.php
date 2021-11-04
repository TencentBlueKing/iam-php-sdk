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

namespace IAM\Client;

use Exception;
use Safe\Exceptions\JsonException;
use function Safe\sprintf;
use function Safe\json_decode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;

class HttpClient
{
    /**
     * @throws Exception
     */
    public function send(
        string $http_func,
        string $host,
        string $path,
        array $data,
        array $headers,
        int $timeout = 0
    ): array {
        $client = new Client([
            'base_uri' => $host,
            'timeout' => $timeout,
//            'debug' => $debug,
        ]);

        switch ($http_func) {
            case RequestMethodInterface::METHOD_POST:
            case RequestMethodInterface::METHOD_PUT:
            case RequestMethodInterface::METHOD_PATCH:
            case RequestMethodInterface::METHOD_DELETE:
                $key = "json";
                break;
            case RequestMethodInterface::METHOD_GET:
            case RequestMethodInterface::METHOD_OPTIONS:
            case RequestMethodInterface::METHOD_HEAD:
                $key = "query";
                break;
            default:
                throw new Exception(sprintf("http_func %s not supported", $http_func));
        }

        try {
            $request = new Request($http_func, $path);
            $response = $client->send($request, [$key => $data, 'headers' => $headers]);
        } catch (RequestException $e) {
            throw new Exception(sprintf("request fail RequestException! %s", $e->getMessage()));
        } catch (GuzzleException $e) {
            throw new Exception(sprintf("request fail GuzzleException! %s", $e->getMessage()));
        }


        if ($response->getStatusCode() != StatusCodeInterface::STATUS_OK) {
            throw new Exception(sprintf("status is %d not 200!", $response->getStatusCode()));
        }

        try {
            $body = "";
            $body = strval($response->getBody());
            $respData = json_decode($body, true);
        } catch (JsonException $ex) {
            throw new Exception(sprintf("decode response bo to json fail! body=`%s`", $body));
        }

        return $respData;
    }
}
