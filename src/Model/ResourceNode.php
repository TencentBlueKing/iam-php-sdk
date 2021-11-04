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

class ResourceNode extends AbstractContext
{
    /**
     * @var string
     */
    private $system;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $id;

    /**
     * @var array
     */
    private $attribute;

    public function __construct(string $system, string $type, string $id, array $attribute)
    {
        $this->system = $system;
        $this->type = $type;
        $this->id = $id;
        $this->attribute = $attribute;
    }

    /**
     * @return string
     */
    public function getSystem(): string
    {
        return $this->system;
    }

    /**
     * @return array
     */
    public function getAttribute(): array
    {
        return $this->attribute;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function validate(): void
    {
        if ($this->system == "") {
            throw new Exception("resource_node system should not be empty");
        }

        if ($this->type == "") {
            throw new Exception("resource_node type should not be empty");
        }

        if ($this->id == "") {
            throw new Exception("resource_node id should not be empty");
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'system'=>$this->system,
            'type'=>$this->type,
            'id'=>$this->id,
            'attributes' =>$this->attribute,
        ];
    }
}