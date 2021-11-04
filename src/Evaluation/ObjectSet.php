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

namespace IAM\Evaluation;

class ObjectSet
{

    /**
     * @var array
     */
    private $data;

    public function __construct()
    {
        $this->data = [];
    }

    public function add(string $type, array $object): void
    {
        $this->data[$type] = $object;
    }

    public function get(string $type): array
    {
        return $this->data[$type];
    }

    public function has(string $type): bool
    {
        return array_key_exists($type, $this->data);
    }

    public function delete(string $type): void
    {
        unset($this->data[$type]);
    }

    public function size(): int
    {
        return count($this->data);
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getAttribute(string $key)
    {
        $parts = explode(".", $key);
        if (count($parts) != 2) {
            return null;
        }

        $type = $parts[0];
        $attribute_name = $parts[1];

        if (!$this->has($type)) {
            return null;
        }

        $obj = $this->get($type);

        if (array_key_exists($attribute_name, $obj)) {
            return $obj[$attribute_name];
        }
        return null;
    }
}
