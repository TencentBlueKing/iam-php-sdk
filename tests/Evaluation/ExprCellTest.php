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

namespace IAM\Tests;

use IAM\Evaluation\ExprCell;
use IAM\Evaluation\ObjectSet;
use JsonMapper;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../../src/Evaluation/CompareFunction.php";
//final class ExprCellTest extends TestCase
//{
//}

final class ExprEvalTest extends TestCase
{
    // make all expression and do eval
    protected function makeExpression(array $policy): ExprCell
    {
        $mapper = new JsonMapper();
        $mapper->bEnforceMapType = false;

        return $mapper->map($policy, new ExprCell());
    }
    protected function getObject(string $id): ObjectSet
    {
        $a = new ObjectSet();
        $a->add("obj", [
            "id" => $id,
        ]);
        return $a;
    }
    protected function getObjectWithAttribute(string $id, array $attribute): ObjectSet
    {
        $a = new ObjectSet();
        $attribute["id"] = $id;
        $a->add("obj", $attribute);
        return $a;
    }

    public function testEqual(): void
    {
        $policy = [
            'op' => 'eq',
            'field' => 'obj.id',
            'value' => '100',
        ];

        $expr = $this->makeExpression($policy);

        $obj100 = $this->getObject("100");
        $this->assertTrue($expr->eval($obj100));

        $obj200 = $this->getObject("200");
        $this->assertFalse($expr->eval($obj200));
    }

    public function testIn(): void
    {
        $policy = [
            'op' => 'in',
            'field' => 'obj.id',
            'value' => ['100', '200'],
        ];

        $expr = $this->makeExpression($policy);

        $obj100 = $this->getObject("100");
        $this->assertTrue($expr->eval($obj100));

        $obj200 = $this->getObject("200");
        $this->assertTrue($expr->eval($obj200));

        $obj300 = $this->getObject("300");
        $this->assertFalse($expr->eval($obj300));
    }

    public function testStartsWith(): void
    {
        $policy = [
            'op' => 'starts_with',
            'field' => 'obj._bk_iam_path_',
            'value' => '/biz,1/set,2/host,*/',
        ];

        $expr = $this->makeExpression($policy);

        // hit, ok
        $obj1 = $this->getObjectWithAttribute("1", [
            '_bk_iam_path_' => ['/biz,1/set,2/host,3/'],
        ]);
        $this->assertTrue($expr->eval($obj1));

        // string hit, ok
        $obj1 = $this->getObjectWithAttribute("1", [
            '_bk_iam_path_' => '/biz,1/set,2/host,3/',
        ]);
        $this->assertTrue($expr->eval($obj1));

        // hit, ok
        $obj1 = $this->getObjectWithAttribute("1", [
            '_bk_iam_path_' => ['/biz,1/set,2/host,3/aaa,5/'],
        ]);
        $this->assertTrue($expr->eval($obj1));

        // not match, false
        $obj1 = $this->getObjectWithAttribute("1", [
            '_bk_iam_path_' => ['/biz,1/set,2/bbb,3/'],
        ]);
        $this->assertFalse($expr->eval($obj1));

        // empty array, false
        $obj1 = $this->getObjectWithAttribute("1", [
            '_bk_iam_path_' => [],
        ]);
        $this->assertFalse($expr->eval($obj1));

        // empty string, false
        $obj1 = $this->getObjectWithAttribute("1", [
            '_bk_iam_path_' => '',
        ]);
        $this->assertFalse($expr->eval($obj1));

        // has no that attribute, false
        $obj1 = $this->getObjectWithAttribute("1", [
        ]);
        $this->assertFalse($expr->eval($obj1));
    }


    public function testOR(): void
    {
        $policy = [
            'op' => 'OR',
            'content' => [
                [
                    'op' => 'eq',
                    'field' => 'obj.id',
                    'value' => '100',
                ],
                [
                    'op' => 'eq',
                    'field' => 'obj.name',
                    'value' => 'hello',
                ],
            ]
        ];

        $expr = $this->makeExpression($policy);

        // id = 100
        $obj1 = $this->getObjectWithAttribute("100", []);
        $this->assertTrue($expr->eval($obj1));

        // name = hello
        $obj1 = $this->getObjectWithAttribute("200", [
            'name' => 'hello'
        ]);
        $this->assertTrue($expr->eval($obj1));

        // none
        $obj1 = $this->getObjectWithAttribute("300", [
            'name' => 'hello2'
        ]);
        $this->assertFalse($expr->eval($obj1));
    }

    public function testAND(): void
    {
        $policy = [
            'op' => 'AND',
            'content' => [
                [
                    'op' => 'eq',
                    'field' => 'obj.id',
                    'value' => '100',
                ],
                [
                    'op' => 'eq',
                    'field' => 'obj.name',
                    'value' => 'hello',
                ],
            ]
        ];

        $expr = $this->makeExpression($policy);

        // id=100, name = hello
        $obj1 = $this->getObjectWithAttribute("100", [
            'name' => 'hello'
        ]);
        $this->assertTrue($expr->eval($obj1));

        // id=200, name = hello
        $obj1 = $this->getObjectWithAttribute("200", [
            'name' => 'hello'
        ]);
        $this->assertFalse($expr->eval($obj1));

        // id=100, name = hello2
        $obj1 = $this->getObjectWithAttribute("100", [
            'name' => 'hello2'
        ]);
        $this->assertFalse($expr->eval($obj1));

        $obj1 = $this->getObjectWithAttribute("300", [
        ]);
        $this->assertFalse($expr->eval($obj1));
    }
}
