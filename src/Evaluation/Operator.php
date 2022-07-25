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

# iam keywords
const KEYWORD_BK_IAM_PATH = "_bk_iam_path_";
const KEYWORD_BK_IAM_PATH_FIELD_SUFFIX = "._bk_iam_path_";


class Operator
{
    const AND = "AND";
    const OR = "OR";

    const EQ = "eq";
    const NOT_EQ = "not_eq";

    const IN = "in";
    const NOT_IN = "not_in";

    const CONTAINS = "contains";
    const NOT_CONTAINS = "not_contains";

    const STARTS_WITH = "starts_with";
    const NOT_STARTS_WITH = "not_starts_with";

    const ENDS_WITH = "ends_with";
    const NOT_ENDS_WITH = "not_ends_with";

    const STRING_CONTAINS = "string_contains";

    const LT = "lt";
    const LTE = "lte";
    const GT = "gt";
    const GTE = "gte";

    const ANY = "any";
}
