[toc]

注意: 可以在 [examples](../examples)目录下的文件中查看相应代码;

## 1. 基本使用

### 1.1 创建一个IAM实例

```php
use IAM\IAM;

// 2. new IAM instance
$i = new IAM(
    "{app_code}",
    "{app_secret}",
    "http://{iam_backend_addr}",
    "http://{paas_domain}",
    "",
    $log,
    false
);
```

如果是使用 APIGateway 的方式(如果有, 推荐使用这种方式)


```php
use IAM\IAM;

// 2. new IAM instance
// if your TencentBlueking has a APIGateway, use NewAPIGatewayIAM, the url suffix is /stage/(for testing) and /prod/(for production)
$i = new IAM(
    "{app_code}",
    "{app_secret}",
    "",
    "",
    "http://bk-iam.{APIGATEWAY_DOMAIN}/stage/",
    $log,
    false
);
```

未来 APIGateway 高性能网关会作为一个基础的蓝鲸服务, 权限中心将会把后台及 SaaS 的所有开放 API 接入到网关中`bk-iam`

此时, 对于接入方, 不管是鉴权/申请权限还是其他接口, 都可以通过同一个网关访问到.

理解成本更低, 且相关的调用日志/文档/流控/监控等都可以在 APIGateway 统一管控.

网关地址类似: `http://bk-iam.{APIGATEWAY_DOMAIN}/{env}`, 其中 `env`值 `prod(生产)/stage(预发布)`

### 1.2 设置logger

开发时, 可以将log level设置为debug, 这样能在日志中查看到请求/响应/求值过程的详细数据;

注意: 生产环境请将日志级别设为 ERROR

如果生产环境开启debug带来的问题:
- 日志量过大
- 影响请求速度(性能大幅降低)
- 敏感信息泄漏(权限相关)

```php
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

$log = new Logger('debug');
$log->pushHandler(new ErrorLogHandler());
```

### 1.3 开启api debug

某些情况下, 例如在排查为什么有权限/无权限问题时, 需要开启api debug, 此时, 通过调用接口参数加入 debug 来获取更多信息

开启api debug 及开启force强制服务端不走缓存:

```php
use IAM\IAM;

// 2. new IAM instance, 最后一个参数true
$i = new IAM(
    "{app_code}",
    "{app_secret}",
    "",
    "",
    "",
    $log,
    true
);
```

注意, 开启后性能非常低, 不应该在生产环境中使用

## 2. 鉴权

### 2.1 isAllowed

> 查询是否有某个操作权限(没有资源实例), 例如访问开发者中心

```php
//    3.1 build the request
$system = "demo";
$subject = new Subject("user", "admin");

// NOTE: action without relatedResourceTypes
$action = new Action("access_developer_center");
$resource = new Resource([]);
$req = new Request($system, $subject, $action, $resource);

echo "isAllowed: " . ($i->isAllowed($req)?"true": "false") . "\n";
```

> 查询是否有某个资源的某个操作权限(有资源实例), 例如管理某个应用

```php
//    3.1 build the request
$system = "demo";
$subject = new Subject("user", "admin");

// NOTE: action without relatedResourceTypes
$action = new Action("access_developer_center");

// NOTE: action with relatedResourceTypes
// NOTE: a Resource is a chain of ResourceNode, even though only got 1 node;
$resource = new Resource([
        new ResourceNode('demo', 'app', '001', []),
]);
$req = new Request($system, $subject, $action, $resource);
echo "isAllowed: " . ($i->isAllowed($req)?"true": "false") . "\n";
```

### 2.3 batchIsAllowed

> 对一批资源同时进行鉴权

可以调用`batchIsAllowed` (注意这个封装不支持跨系统资源依赖, 只支持接入系统自己的本地资源)

```php
//    3.1 build the request
$system = "demo";
$subject = new Subject("user", "admin");
//$subject = new Subject("user", "user001");
$action = new Action("develop_app");

// NOTE: a Resource is a chain of ResourceNode, even though only got 1 node;
$app1 = new Resource(
    [
        new ResourceNode('demo', 'app', '001', []),
    ]
);
// NOTE: a Resource is a chain of ResourceNode, even though only got 1 node;
$app2 = new Resource(
    [
        new ResourceNode('demo', 'app', '002', []),
    ]
);

$resource_list = [$app1, $app2];

// NOTE: here, the Resource in Request is not used;
$req = new Request($system, $subject, $action, new Resource([]));

echo "batchIsAllowed: ";
print_r($i->batchIsAllowed($req, $resource_list));
echo "\n";
```

### 2.4 resourceMultiActionsAllowed

> 对一个资源的多个操作同时进行鉴权

可以调用`resourceMultiActionsAllowed`进行批量操作权限的鉴权

```php
use IAM\Model\Action;
use IAM\Model\MultiActionRequest;
use IAM\Model\Subject;
use IAM\Model\Resource;
use IAM\Model\ResourceNode;
use Monolog\Handler\ErrorLogHan

//    3.1 build the request
$system = "demo";
$subject = new Subject("user", "admin");
//$subject = new Subject("user", "user001");
// NOTE: here actions is Action[]
$actions = [
    new Action("develop_app"),
];

$resource = new Resource([new ResourceNode('demo', 'app', '001', [])]);
$req = new MultiActionRequest($system, $subject, $actions, $resource);

echo "resourceMultiActionsAllowed: ";
print_r($i->resourceMultiActionsAllowed($req));
echo "\n";
```

### 2.5 batchResourceMultiActionsAllowed

> 对于批量资源的多个操作同时进行鉴权, 例如进入资源列表也，可能需要在前端展示当前用户关于列表中的资源的一批操作的权限信息

可以调用`batchResourceMultiActionsAllowed`

```php
//    3.1 build the request
$system = "demo";
$subject = new Subject("user", "admin");
//$subject = new Subject("user", "user001");
// NOTE: here actions is Action[]
$actions = [
    new Action("develop_app"),
];

// NOTE: a Resource is a chain of ResourceNode, even though only got 1 node;
$app1 = new Resource(
    [
        new ResourceNode('demo', 'app', '001', []),
    ]
);
// NOTE: a Resource is a chain of ResourceNode, even though only got 1 node;
$app2 = new Resource(
    [
        new ResourceNode('demo', 'app', '002', []),
    ]
);

$resource_list = [$app1, $app2];

// NOTE: here, the Resource in Request is not used;
$req = new MultiActionRequest($system, $subject, $actions, new Resource([]));


//    3.2 call the functions and echo result
echo "resourceMultiActionsAllowed: ";
print_r($i->batchResourceMultiActionsAllowed($req, $resource_list));
echo "\n";
```

### 2.2 isAllowedWithCache

> 对于非敏感权限

可以调用`isAllowedWithCache(request)`, 缓存10s. (注意: 不要用于新建关联权限的资源is_allowed判定, 否则可能新建一个资源新建关联生效之后跳转依旧没权限; 更多用于管理权限/未依赖资源的权限权限判断)

```php
//    3.1 build the request
$system = "demo";
$subject = new Subject("user", "admin");

// NOTE: action without relatedResourceTypes
$action = new Action("access_developer_center");
$resource = new Resource([]);
$req = new Request($system, $subject, $action, $resource);

echo "isAllowedWithCache: " . ($i->isAllowedWithCache($req)?"true": "false") . "\n";
```

## 3. 非鉴权

### 3.1 获取无权限申请跳转url

> 没有权限时, 在前端展示要申请的权限列表, 需要访问IAM接口, 拿到申请权限url; 用户点击跳转到IAM SaaS对应页面申请权限

```php
// 3. call
//    3.1 build the request
$system = "demo";
$type = "app";
$bk_token = "";
$bk_username = "admin";

$action1 = new ActionWithoutResources("access_developer_center");

$instances = [
    new ResourceInstance([
        new Node($type, "001", "firstApp"),
    ]),
    new ResourceInstance([
        new Node($type, "002", "secondApp"),
    ]),
];
$related_resource_types = [
    new RelatedResourceType($system, $type, $instances),
];
$action2 = new ActionWithResources("develop_app", $related_resource_types);

$actions = [$action1, $action2];
$application = new Application($system, $actions);

//    3.2 call the functions and echo result
echo "getApplyUrl: " . $i->getApplyUrl($application, $bk_token, $bk_username) . "\n";
```

### 3.2 生成无权限描述协议数据

可以生成生成 [无权限描述协议数据](https://bk.tencent.com/docs/document/6.0/160/8463)

SDK暂未提供相应接口

### 3.3 回调接口basic auth校验

```php
// 3. call
//    3.1 build the request
$system = "demo";

//    3.2 call the functions and echo result
echo "isBasicAuthAllowed: ";
echo($i->isBasicAuthAllowed($system, "bk_iam", "63yr6hs11bsqa8u4d9i0acbpjuuyizaw")?"true": "false");
echo "\n";
```

### 3.4 查询系统的Token

```php
// 3. call
//    3.1 build the request
$system = "demo";

//    3.2 call the functions and echo result
echo "getToken: " . $i->getToken($system) . "\n";
```