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

function cmp_equal($v1, $v2): bool
{
    return $v1 === $v2;
}
function cmp_not_equal($v1, $v2): bool
{
    return $v1 !== $v2;
}

function cmp_lt($v1, $v2): bool
{
    return $v1 < $v2;
}

function cmp_lte($v1, $v2): bool
{
    return $v1 <= $v2;
}

function cmp_gt($v1, $v2): bool
{
    return $v1 > $v2;
}

function cmp_gte($v1, $v2): bool
{
    return $v1 >= $v2;
}

function cmp_starts_with($v1, $v2): bool
{
    return str_starts_with($v1, $v2);
}

function cmp_not_starts_with($v1, $v2): bool
{
    return !str_starts_with($v1, $v2);
}

function cmp_ends_with($v1, $v2): bool
{
    return str_ends_with($v1, $v2);
}

function cmp_not_ends_with($v1, $v2): bool
{
    return !str_ends_with($v1, $v2);
}

function cmp_in($v1, $v2): bool
{
    if (!is_array($v2)) {
        return false;
    }

    return in_array($v1, $v2, true);
}

function cmp_not_in($v1, $v2): bool
{
    if (!is_array($v2)) {
        return false;
    }

    return !in_array($v1, $v2, true);
}

function cmp_contains($v1, $v2): bool
{
    if (!is_array($v1)) {
        return false;
    }

    return in_array($v2, $v1, true);
}

function cmp_not_contains($v1, $v2): bool
{
    if (!is_array($v1)) {
        return false;
    }

    return !in_array($v2, $v1, true);
}
