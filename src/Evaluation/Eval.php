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

use Exception;
use function Safe\preg_replace;

/**
 * @throws Exception
 */
function eval_binary_operator(string $op, string $field, $policy_value, ObjectSet $data): bool
{
    $object_value = $data->getAttribute($field);

    // support _bk_iam_path_, starts with from `/a,1/b,*/` to `/a,1/b,`
    if ($op == Operator::STARTS_WITH && str_ends_with($field, KEYWORD_BK_IAM_PATH_FIELD_SUFFIX)) {
        if (is_array($policy_value)) {
            foreach ($policy_value as &$v) {
                $v = preg_replace('/,\*\/$/', '', $v);
            }
        } else {
            $policy_value = preg_replace('/,\*\/$/', '', $policy_value);
        }
    }

    // do validate
    // 1. eq/not_eq/lt/lte/gt/gte the policy value should be a single value
    // 2. important: starts_with/not_starts_with/ends_with/not_ends_with, the policy value should be a single value too!
    // 3. in/not_in, the policy value should be an array
    // 4. contains/not_contains, the object value should be an array

    switch ($op) {
        case Operator::ANY:
            return true;

        // policy value is a single value!
        case Operator::EQ:
        case Operator::LT:
        case Operator::LTE:
        case Operator::GT:
        case Operator::GTE:
            // NOTE: starts_with and ends_with should be a single value!!!!!
        case Operator::STARTS_WITH:
        case Operator::ENDS_WITH:
            if (is_array($policy_value)) {
                throw new Exception("wrong policy values! should not be an array");
            }

            return evalPositive($op, $object_value, $policy_value);
        // policy value is an array
        case Operator::IN:
            if (!is_array($policy_value)) {
                throw new Exception("the policy value of operator `in` should be an array");
            }

            return evalPositive($op, $object_value, $policy_value);
        case Operator::CONTAINS:
            // NOTE: objectValue is an array, policyValue is single value
            if (!is_array($object_value)) {
                throw new Exception("the object attribute should be array of operator `contains`");
            }
            return evalPositive($op, $object_value, $policy_value);

        case Operator::NOT_EQ:
            // NOTE: not_starts_with and not_ends_with should be a single value!!!!!
        case Operator::NOT_STARTS_WITH:
        case Operator::NOT_ENDS_WITH:
            if (is_array($policy_value)) {
                throw new Exception("wrong policy values! should not be an array");
            }
            return evalNegative($op, $object_value, $policy_value);
        // policy value is an array
        case Operator::NOT_IN:
            if (!is_array($policy_value)) {
                throw new Exception("the policy value of operator `not_in` should be an array");
            }
            return evalNegative($op, $object_value, $policy_value);
        case Operator::NOT_CONTAINS:
            // NOTE: objectValue is an array, policyValue is single value
            if (!is_array($object_value)) {
                throw new Exception("the object attribute should be array of operator `contains`");
            }
            return evalNegative($op, $object_value, $policy_value);
        default:
            return false;
    }
}

const OP_COMPARE_FUNCTIONS = [
    Operator::EQ => "IAM\Evaluation\cmp_equal",
    Operator::LT =>"IAM\Evaluation\cmp_lt",
    Operator::LTE => "IAM\Evaluation\cmp_lte",
    Operator::GT =>"IAM\Evaluation\cmp_gt",
    Operator::GTE =>"IAM\Evaluation\cmp_gte",
    Operator::STARTS_WITH =>"IAM\Evaluation\cmp_starts_with",
    Operator::ENDS_WITH =>"IAM\Evaluation\cmp_ends_with",
    Operator::IN => "IAM\Evaluation\cmp_in",
    Operator::NOT_EQ =>"IAM\Evaluation\cmp_not_equal",
    Operator::NOT_STARTS_WITH =>"IAM\Evaluation\cmp_not_starts_with",
    Operator::NOT_ENDS_WITH => "IAM\Evaluation\cmp_not_ends_with",
    Operator::NOT_IN =>"IAM\Evaluation\cmp_not_in",
    Operator::CONTAINS => "IAM\Evaluation\cmp_contains",
    Operator::NOT_CONTAINS => "IAM\Evaluation\cmp_not_contains",
];


/**
 * @throws Exception
 */
function evalPositive(string $op, $object_value, $policy_value): bool
{
    if (!array_key_exists($op, OP_COMPARE_FUNCTIONS)) {
        throw new Exception("operator not support");
    }
    $cmp_func = OP_COMPARE_FUNCTIONS[$op];

    if (!is_array($object_value)) {
        return $cmp_func($object_value, $policy_value);
    }

    // contains object_value is array, policy is a single value or an array;
    if ($op == Operator::CONTAINS) {
        if (is_array($policy_value)) {
            foreach ($policy_value as $pv) {
                // got one contains, return true;
                if ($cmp_func($object_value, $pv)) {
                    return true;
                }
            }
            // all not contains, return false;
            return false;
        }

        return $cmp_func($object_value, $policy_value);
    }

    // the object_value is an array
    foreach ($object_value as $ov) {
        // if one true, return true
        if ($cmp_func($ov, $policy_value)) {
            return true;
        }
    }

    // all not true, return false
    return false;
}

/**
 * @throws Exception
 */
function evalNegative(string $op, $object_value, $policy_value): bool
{
    if (!array_key_exists($op, OP_COMPARE_FUNCTIONS)) {
        throw new Exception("operator not support");
    }
    $cmp_func = OP_COMPARE_FUNCTIONS[$op];

    if (!is_array($object_value)) {
        return $cmp_func($object_value, $policy_value);
    }

    // contains object_value is array, policy is a single value or an array;
    if ($op == Operator::NOT_CONTAINS) {
        if (is_array($policy_value)) {
            foreach ($policy_value as $pv) {
                // got one contains, return false;
                if (!$cmp_func($object_value, $pv)) {
                    return false;
                }
            }
            // all not contains, return true;
            return true;
        }

        return $cmp_func($object_value, $policy_value);
    }

    // the object_value is an array
    foreach ($object_value as $ov) {
        // if got one false, return false
        if (!$cmp_func($ov, $policy_value)) {
            return false;
        }
    }

    // all `not` pass, return true
    return true;
}
