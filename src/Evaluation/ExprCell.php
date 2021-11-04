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

namespace IAM\Evaluation;

require_once "Eval.php";

use Exception;
use Safe\Exceptions\StringsException;
use function Safe\sprintf;

class ExprCell
{
    /**
     * @var string
     */
    public $op;
    /**
     * @var string
     */
    public $field;
    public $value;

    /**
     * @var ExprCell[]
     */
    public $content;

    /**
     * @throws Exception
     */
    public function eval(ObjectSet $data): bool
    {
        switch ($this->op) {
            case Operator::AND:
                foreach ($this->content as $c) {
                    if (!$c->eval($data)) {
                        return false;
                    }
                }
                return true;
            case Operator::OR:
                foreach ($this->content as $c) {
                    if ($c->eval($data)) {
                        return true;
                    }
                }
                return false;
            default:
                return eval_binary_operator($this->op, $this->field, $this->value, $data);
        }
    }

    /** string return the text of expression cell
     * @return string
     * @throws StringsException
     */
    public function string(): string
    {
        switch ($this->op) {
            case Operator::AND:
            case Operator::OR:
                $separator = sprintf(" %s ", $this->op);

                $sub_exprs = [];
                foreach ($this->content as $c) {
                    $sub_exprs[] = $c->string();
                }
                return sprintf("(%s)", implode($separator, $sub_exprs));
            default:
                $value = raw_string($this->value);
                return sprintf("(%s %s %s)", $this->field, $this->op, $value);
        }
    }

    /** render return the rendered text of expression with ObjectSet
     * @param ObjectSet $data
     * @return string
     * @throws StringsException
     */
    public function render(ObjectSet $data): string
    {
        switch ($this->op) {
            case Operator::AND:
            case Operator::OR:
                $separator = sprintf(" %s ", $this->op);

                $sub_exprs = [];
                foreach ($this->content as $c) {
                    $sub_exprs[] = $c->render($data);
                }
                return sprintf("(%s)", implode($separator, $sub_exprs));
            default:
                $value = raw_string($this->value);
                $attribute = raw_string($data->getAttribute($this->field));
                return sprintf("(%s %s %s)", $attribute, $this->op, $value);
        }
    }
}

/**
 * @throws StringsException
 */
function raw_string($value): string
{
    if (is_array($value)) {
        return "[" . implode(", ", $value) . "]";
    }
    return sprintf("%s", $value);
}

