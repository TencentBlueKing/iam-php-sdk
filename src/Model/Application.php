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

require_once "Abstract.php";

/*
 * build the application
 * # 无资源实例的操作权限, 例如 访问开发者中心
 * {
 *   "system_id": "bk_job",  # 权限的系统
 *   "actions": [
 *     {
 *       "id": "execute_job",  # 操作id
 *       "related_resource_types": []
 *     }
 *   ]
 * }
 * # 有资源实例的操作权限, 例如 管理应用framework
 * {
 *   "system_id": "bk_job",  # 权限的系统
 *   "actions": [
 *     {
 *       "id": "execute_job",  # 操作id
 *       "related_resource_types": [  # 关联的资源类型, 无关联资源类型的操作, 可以为空
 *         {
 *           "system_id": "bk_job",  # 资源类型所属的系统id
 *           "type": "job",  # 资源类型
 *           "instances": [  # 申请权限的资源实例
 *             [  # 带层级的实例表示
 *               {
 *                 "type": "job",  # 层级节点的资源类型
 *                 "id": "job1",  # 层级节点的资源实例id
 *                 "name": "作业1"  # 层级节点的资源名称
 *               }
 *             ]
 *           ]
 *         },
 *         {
 *           "system_id": "bk_cmdb",  # 资源类型所属的系统id
 *           "type": "host",  # 操作依赖的另外一个资源类型
 *           "instances": [
 *             [
 *               {
 *                 "type": "biz",
 *                 "id": "biz1",
 *                 "name": "业务1"
 *               }, {
 *                 "type": "set",
 *                 "id": "set1",
 *                 "name": "集群1"
 *               }, {
 *                 "type": "module",
 *                 "id": "module1",
 *                 "name": "模块1"
 *               }, {
 *                 "type": "host",
 *                 "id": "host1",
 *                 "name": "主机1"
 *               }
 *             ]
 *           ]
 *         }
 *       ]
 *     }
 *   ],
 *   "environment": {  # 权限的可用条件
 *     "source_system_id": ""  # 选填, 来源系统, 如果需要限制权限的来源系统可填
 *   }
 * }
 */

class Node extends AbstractContext
{
    private $type;
    private $id;
    private $name;

    /**
     * @param string $type
     * @param string $id
     * @param string $name
     */
    public function __construct(string $type, string $id, string $name)
    {
        $this->type = $type;
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * @throws Exception
     *
     * @return void
     */
    public function validate(): void
    {
        if ($this->type == "") {
            throw new Exception("resource_node type should not be empty");
        }
        if ($this->id== "") {
            throw new Exception("resource_node id should not be empty");
        }
        if ($this->name== "") {
            throw new Exception("resource_node name should not be empty");
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'type'=> $this->type,
            'id'=> $this->id,
            'name' => $this->name,
        ];
    }
}

class ResourceInstance extends AbstractContext
{
    // should not be empty and should be type of Node
    private $resource_nodes;

    /**
     * @param Node[] $resource_nodes
     */
    public function __construct(array $resource_nodes)
    {
        $this->resource_nodes = $resource_nodes;
    }

    /**
     * @throws Exception
     * @return void
     */
    public function validate(): void
    {
        foreach ($this->resource_nodes as $node) {
            $node->validate();
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->resource_nodes as $node) {
            $result[] = $node->toArray();
        }
        return $result;
    }
}

class RelatedResourceType extends AbstractContext
{
    private $system_id;
    private $type;
    private $instances;

    /**
     * @param string $system_id
     * @param string $type
     * @param ResourceInstance[] $instances
     */
    public function __construct(string $system_id, string $type, array $instances)
    {
        $this->system_id = $system_id;
        $this->type = $type;
        $this->instances = $instances;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function validate(): void
    {
        foreach ($this->instances as $instance) {
            $instance->validate();
        }

        if ($this->system_id == "") {
            throw new Exception("related_resource_type system_id should not be empty");
        }
        if ($this->type == "") {
            throw new Exception("related_resource_type type should not be empty");
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->instances as $instance) {
            $data[] = $instance->toArray();
        }

        return [
            'system_id' => $this->system_id,
            'type' => $this->type,
            'instances' => $data,
        ];
    }
}

abstract class BaseAction extends AbstractContext
{
    protected $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function validate(): void
    {
        if ($this->id == "") {
            throw new Exception("action id should not be empty");
        }
    }
}

class ActionWithoutResources extends BaseAction
{
    /**
     * @return void
     * @throws Exception
     */
    public function validate(): void
    {
        parent::validate();
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'related_resource_types' => [],
        ];
    }
}

class ActionWithResources extends BaseAction
{
    /**
     * @var RelatedResourceType[]
     */
    protected $related_resource_types;

    /**
     * @param string $id
     * @param RelatedResourceType[] $related_resource_types
     */
    public function __construct(string $id, array $related_resource_types)
    {
        parent::__construct($id);
        $this->related_resource_types = $related_resource_types;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function validate(): void
    {
        parent::validate();
        foreach ($this->related_resource_types as $rrt) {
            $rrt->validate();
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->related_resource_types as $rrt) {
            $data[] = $rrt->toArray();
        }
        return [
            'id' => $this->id,
            'related_resource_types'=> $data,
        ];
    }
}



class Application extends AbstractContext
{
    private $system_id;
    private $actions;

    /**
     * @param string $system_id
     * @param ActionWithoutResources[]|ActionWithResources[] $actions
     */
    public function __construct(string $system_id, array $actions)
    {
        $this->system_id = $system_id;
        $this->actions = $actions;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function validate(): void
    {
        if ($this->system_id == "") {
            throw new Exception("application system_id should not be empty");
        }

        foreach ($this->actions as $action) {
            $action->validate();
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $actions = [];
        foreach ($this->actions as $action) {
            $actions[] = $action->toArray();
        }

        return [
            "system_id" => $this->system_id,
            "actions" => $actions,
        ];
    }
}
