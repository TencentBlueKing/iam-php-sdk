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

namespace IAM\Model;

use Exception;
use function Safe\sprintf;

class Request extends AbstractContext
{
    /**
     * @var string
     */
    private $system;

    /**
     * @var Subject
     */
    private $subject;

    /**
     * @var Action
     */
    private $action;

    /**
     * @var Resource
     */
    private $resource;

    public function __construct(string $system, Subject $subject, Action $action, Resource $resource)
    {
        $this->system = $system;
        $this->subject = $subject;
        $this->action = $action;
        $this->resource = $resource;
    }

    /**
     * @return string
     */
    public function getSystem(): string
    {
        return $this->system;
    }

    /**
     * @return Resource
     */
    public function getResource(): Resource
    {
        return $this->resource;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function validate(): void
    {
        if ($this->system == "") {
            throw new Exception("request `system` should not be empty");
        }

        try {
            $this->subject->validate();
        } catch (Exception $e) {
            throw new Exception(sprintf("requests `subject` validate fail! %s", $e->getMessage()));
        }

        try {
            $this->action->validate();
        } catch (Exception $e) {
            throw new Exception(sprintf("requests `action` validate fail! %s", $e->getMessage()));
        }

        try {
            $this->resource->validate();
        } catch (Exception $e) {
            throw new Exception(sprintf("requests `resource` validate fail! %s", $e->getMessage()));
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            "system" => $this->system,
            "subject" => $this->subject->toArray(),
            "action" => $this->action->toArray(),
            // NOTE: in /api/v1/policy/query,
            // it's resources means `one resource`[=array(resource_node)]; will change later
            "resources" => $this->resource->toArray(),
        ];
    }

    /**
     * @return string
     */
    public function hash(): string
    {
        return md5(serialize($this));
    }
}