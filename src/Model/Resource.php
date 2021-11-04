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
use IteratorAggregate;
use Safe\Exceptions\StringsException;
use Traversable;
use ArrayIterator;
use function Safe\sprintf;

class Resource extends AbstractContext implements IteratorAggregate
{
    /**
     * @var ResourceNode[]
     * @psalm-var array<ResourceNode>
     */
    private $resource_chain_nodes;

    /**
     * @return ResourceNode[]
     */
    public function getResourceChainNodes(): array
    {
        return $this->resource_chain_nodes;
    }

    /**
     * @param ResourceNode[] $resource_chain_nodes
     */
    public function __construct(array $resource_chain_nodes)
    {
        $this->resource_chain_nodes = $resource_chain_nodes;
    }

    /**
     * @param ResourceNode $node
     */
    public function addNode(ResourceNode $node): void
    {
        $this->resource_chain_nodes[] = $node;
    }

    public function size(): int
    {
        return count($this->resource_chain_nodes);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->resource_chain_nodes);
    }

    /**
     * @return void
     * @throws StringsException
     * @throws Exception
     */
    public function validate(): void
    {
        foreach ($this->resource_chain_nodes as $node) {
            try {
                $node->validate();
            } catch (Exception $e) {
                throw new Exception(sprintf("got one node in array validate fail. %s", $e->getMessage()));
            }
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->resource_chain_nodes as $node) {
            $result[] = $node->toArray();
        }
        return $result;
    }
}