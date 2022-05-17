<?php

namespace App\Config;

use App\Controllers\Operate\PageConfigAdminController;
use App\Controllers\Operate\ProjectAdminController;
use App\Controllers\Operate\RecommendProjectAdminController;
use App\Controllers\Operate\RecommendProjectV2AdminController;
use App\Controllers\Operate\RecommendSchemeAdminController;
use App\Controllers\Operate\StoryAdminController;
use App\Controllers\Operate\TopicAdminController;
use App\Controllers\Operate\TopicCommentAdminController;
use App\Controllers\Operate\IndexAdminController;
use App\Controllers\ProjectController;
use App\Controllers\StoryController;
use App\Controllers\TopicController;
use App\Controllers\HomeController;
use App\Controllers\IndexController;
use App\Controllers\TestController;
use App\Controllers\WechatPayController;
use App\Middleware\HttpMiddleware;
use One\Http\Router;

Router::get('/check-service', IndexController::class . '@checkService');//服务检测(供consul)
Router::get('/rpc-client-helper', IndexController::class . '@rpcClientHelper');//rpc sdk生成
Router::get('/test', TestController::class . '@index');
Router::get('/study-tour/', IndexController::class . '@index');
Router::group([
    'middle' => [ //后面的后进先出, 可嵌套
        HttpMiddleware::class. '@output',//根据设置的content_type输出相应格式
        HttpMiddleware::class. '@setJsonContentType',//设置json content_type
        HttpMiddleware::class. '@handleException',// 处理所有异常
    ]
], function () {
    //通用接口
    Router::group([
        'middle' => [
            HttpMiddleware::class.'@logRequest',//记录请求日志
        ]
    ], function () {
        Router::get('/study-tour/test/weixin-jsapi', TestController::class . '@weixinJsapi');//微信API测试
        Router::post('/study-tour/sendCaptcha', HomeController::class . '@sendCaptcha');//发送验证码
        Router::get('/study-tour/viewCaptcha', HomeController::class . '@viewCaptcha');//查看验证码(测试)
        Router::post('/study-tour/login', HomeController::class . '@login');//登录(注册)
        Router::post('/study-tour/fakeUser', HomeController::class . '@fakeUser');//生成假用户
        Router::get('/study-tour/receiveWeixinPush', HomeController::class . '@receiveWeixinPush');//微信通知(get)
        Router::post('/study-tour/receiveWeixinPush', HomeController::class . '@receiveWeixinPush');//微信通知
        Router::get('/study-tour/weixinAppSessionAccess', HomeController::class . '@weixinAppSessionAccess');//微信小程序会话授权
        Router::get('/study-tour/weixinOauth2Authorize', HomeController::class . '@weixinOauth2Authorize');//微信网页授权(静默登录)
        Router::get('/study-tour/weixinOauth2AuthorizeUserInfo', HomeController::class . '@weixinOauth2AuthorizeUserInfo');//微信网页授权
        Router::get('/study-tour/weixinOauth2AuthorizeCallback', HomeController::class . '@weixinOauth2AuthorizeCallback');//微信网页授权回调
        Router::get('/study-tour/getUserInfo', HomeController::class . '@getUserInfo');//获取当前用户信息
        Router::get('/study-tour/getJsTicket', HomeController::class . '@getJsTicket');//获取js sdk所需签名参数
        Router::get('/study-tour/validateCaptcha', HomeController::class . '@validateCaptcha');//校验验证码
        Router::post('/study-tour/syncEnvCache', HomeController::class . '@syncEnvCache'); //接收同步缓存数据(微信Accesstoken)
        Router::post('/study-tour/makeAppt', HomeController::class . '@makeAppt'); //预约
    });
    Router::group([
        'middle' => [
            HttpMiddleware::class.'@logRequest',//记录请求日志
            HttpMiddleware::class . '@checkWeixinAppAccess',//需要微信会话授权
        ]
    ], function () {
        Router::post('/study-tour/decryptWeixinData', HomeController::class . '@decryptWeixinData');//解密微信数据
    });

    //M站
    Router::group([
        'middle' => [
            HttpMiddleware::class.'@logRequest',//记录请求日志
        ]
    ], function () {
        Router::get('/study-tour/Home/index', HomeController::class . '@index'); //首页链接配置
        Router::get('/study-tour/Topic/index', TopicController::class . '@index'); //话题列表
        Router::get('/study-tour/Topic/info', TopicController::class . '@info'); //话题详情
        Router::get('/study-tour/Topic/commentList', TopicController::class . '@commentList'); //评论列表
        Router::post('/study-tour/Topic/postComment', TopicController::class . '@postComment'); //添加评论
        Router::post('/study-tour/Topic/likeComment', TopicController::class . '@likeComment'); //评论点赞
        Router::post('/study-tour/Topic/vote', TopicController::class . '@vote'); //投票
        Router::get('/study-tour/Project/index', ProjectController::class . '@index'); //名校项目列表
        Router::get('/study-tour/Project/info', ProjectController::class . '@info'); //项目详情
        Router::get('/study-tour/Project/country', ProjectController::class . '@country'); //国家列表
        Router::get('/study-tour/Project/hasRecommendedScehme', ProjectController::class . '@hasRecommendedScehme'); //已评测
        Router::get('/study-tour/Project/questions', ProjectController::class . '@questions'); //评测问题数据
        Router::post('/study-tour/Project/recommendScheme', ProjectController::class . '@recommendScheme'); //评测及获取推荐方案
        Router::post('/study-tour/Project/recommendSchemeV2', ProjectController::class . '@recommendSchemeV2'); //评测及获取推荐方案V2
        Router::post('/study-tour/Project/recommendSchemeV2Again', ProjectController::class . '@recommendSchemeV2Again'); //评测及获取推荐方案V2(再次)
        Router::post('/study-tour/Project/makeAppt', HomeController::class . '@makeAppt'); //预约
        Router::get('/study-tour/Project/validateCaptcha', ProjectController::class . '@validateCaptcha'); //手机号验证
        Router::get('/study-tour/Story/index', StoryController::class . '@index'); //游学故事列表
        Router::get('/study-tour/Story/info', StoryController::class . '@info'); //故事详情

    });


    //PC站
    Router::group([
        'middle' => [
            HttpMiddleware::class.'@logRequest',//记录请求日志
            HttpMiddleware::class. '@setHinabianOldOutput',//海那边老接口格式
        ]
    ], function () {
        Router::post('/study-tour/pc/sendCaptcha', HomeController::class . '@sendCaptcha');//发送验证码
        Router::get('/study-tour/pc/Home/index', HomeController::class . '@index'); //首页链接配置
        Router::get('/study-tour/pc/Topic/index', TopicController::class . '@index'); //话题列表
        Router::get('/study-tour/pc/Topic/info', TopicController::class . '@info'); //话题详情
        Router::get('/study-tour/pc/Topic/commentList', TopicController::class . '@commentList'); //评论列表
        Router::post('/study-tour/pc/Topic/postComment', TopicController::class . '@postComment'); //添加评论
        Router::post('/study-tour/pc/Topic/likeComment', TopicController::class . '@likeComment'); //评论点赞
        Router::post('/study-tour/pc/Topic/vote', TopicController::class . '@vote'); //投票
        Router::get('/study-tour/pc/Project/index', ProjectController::class . '@index'); //名校项目列表
        Router::get('/study-tour/pc/Project/info', ProjectController::class . '@info'); //项目详情
        Router::get('/study-tour/pc/Project/country', ProjectController::class . '@country'); //国家列表
        Router::get('/study-tour/pc/Project/hasRecommendedScehme', ProjectController::class . '@hasRecommendedScehme'); //已评测
        Router::get('/study-tour/pc/Project/questions', ProjectController::class . '@questions'); //评测问题数据
        Router::post('/study-tour/pc/Project/recommendScheme', ProjectController::class . '@recommendScheme'); //评测及获取推荐方案
        Router::post('/study-tour/pc/Project/recommendSchemeV2', ProjectController::class . '@recommendSchemeV2'); //评测及获取推荐方案V2
        Router::post('/study-tour/pc/Project/recommendSchemeV2Again', ProjectController::class . '@recommendSchemeV2Again'); //评测及获取推荐方案V2(再次)
        Router::get('/study-tour/pc/Project/validateCaptcha', ProjectController::class . '@validateCaptcha'); //手机号验证
        Router::get('/study-tour/pc/Story/index', StoryController::class . '@index'); //游学故事列表
        Router::get('/study-tour/pc/Story/info', StoryController::class . '@info'); //故事详情
        Router::post('/study-tour/pc/makeAppt', HomeController::class . '@makeAppt'); //预约

    });

    //海那边 APP iOS
    Router::group([
        'middle' => [
            HttpMiddleware::class.'@logRequest',//记录请求日志
            HttpMiddleware::class. '@setHinabianAppIOSOutput',//海那边 APP iOS 接口格式
        ]
    ], function () {
        Router::post('/study-tour/hinabian-app/sendCaptcha', HomeController::class . '@sendCaptcha');//发送验证码
        Router::get('/study-tour/hinabian-app/validateCaptcha', HomeController::class . '@validateCaptcha');//校验验证码
        Router::get('/study-tour/hinabian-app/Home/index', HomeController::class . '@index');//首页配置
        Router::post('/study-tour/hinabian-app/Project/recommendSchemeV2', ProjectController::class . '@recommendSchemeV2'); //评测及获取推荐方案V2
    });

    //海那边 APP Android
    Router::group([
        'middle' => [
            HttpMiddleware::class.'@logRequest',//记录请求日志
            HttpMiddleware::class. '@setHinabianAppAndroidOutput',//海那边 APP Android 接口格式
        ]
    ], function () {
        Router::post('/study-tour/hinabian-app-android/sendCaptcha', HomeController::class . '@sendCaptcha');//发送验证码
        Router::get('/study-tour/hinabian-app-android/validateCaptcha', HomeController::class . '@validateCaptcha');//校验验证码
        Router::get('/study-tour/hinabian-app-android/Home/index', HomeController::class . '@index');//首页配置
        Router::post('/study-tour/hinabian-app-android/Project/recommendSchemeV2', ProjectController::class . '@recommendSchemeV2'); //评测及获取推荐方案V2
        Router::get('/study-tour/hinabian-app-android/Project/questions', ProjectController::class . '@questions'); //评测问题数据
        Router::post('/study-tour/hinabian-app-android/Project/recommendSchemeV2Again', ProjectController::class . '@recommendSchemeV2Again'); //评测及获取推荐方案V2(再次)
    });

    //运营后台管理系统
    Router::group([
        'middle' => []
    ], function () {
        Router::get('/study-tour/Operate/index/unionLogin', IndexAdminController::class . '@unionLogin');//运营后台同步登录
        Router::get('/study-tour/Operate/index/unionLogout', IndexAdminController::class . '@unionLogout');//运营后台同步登出
        Router::get('/study-tour/Operate/commonCss', IndexAdminController::class . '@commonCss');//公共css文件(php直接渲染)
        Router::get('/study-tour/Operate/tinymceUpload', IndexAdminController::class . '@tinymceUpload');//富文本编辑器批量上传插件(跨域问题)
        Router::group([
            'middle' => [
                HttpMiddleware::class . '@checkOperatePermission',//运营后台管理授权
            ]
        ], function () {
            //推荐项目V2
            Router::post('/study-tour/Operate/RecommendProjectV2Admin/create', RecommendProjectV2AdminController::class . '@create');//创建//
            Router::post('/study-tour/Operate/RecommendProjectV2Admin/update', RecommendProjectV2AdminController::class . '@update');//更新
            Router::get('/study-tour/Operate/RecommendProjectV2Admin/update', RecommendProjectV2AdminController::class . '@update');//编辑查看
            Router::get('/study-tour/Operate/RecommendProjectV2Admin/index', RecommendProjectV2AdminController::class . '@index');//列表数据
            //推荐项目
            Router::post('/study-tour/Operate/RecommendProjectAdmin/create', RecommendProjectAdminController::class . '@create');//创建
            Router::post('/study-tour/Operate/RecommendProjectAdmin/update', RecommendProjectAdminController::class . '@update');//更新
            Router::get('/study-tour/Operate/RecommendProjectAdmin/update', RecommendProjectAdminController::class . '@update');//编辑查看
            Router::get('/study-tour/Operate/RecommendProjectAdmin/index', RecommendProjectAdminController::class . '@index');//列表数据
            //推荐方案
            Router::post('/study-tour/Operate/RecommendSchemeAdmin/create', RecommendSchemeAdminController::class . '@create');//创建
            Router::post('/study-tour/Operate/RecommendSchemeAdmin/update', RecommendSchemeAdminController::class . '@update');//更新
            Router::get('/study-tour/Operate/RecommendSchemeAdmin/update', RecommendSchemeAdminController::class . '@update');//编辑查看
            Router::get('/study-tour/Operate/RecommendSchemeAdmin/index', RecommendSchemeAdminController::class . '@index');//列表数据
            //游学话题
            Router::post('/study-tour/Operate/TopicAdmin/create', TopicAdminController::class . '@create');//创建
            Router::post('/study-tour/Operate/TopicAdmin/update', TopicAdminController::class . '@update');//更新
            Router::get('/study-tour/Operate/TopicAdmin/update', TopicAdminController::class . '@update');//编辑查看
            Router::get('/study-tour/Operate/TopicAdmin/index', TopicAdminController::class . '@index');//列表数据
            //话题评论
            Router::post('/study-tour/Operate/TopicCommentAdmin/create', TopicCommentAdminController::class . '@create');//创建
            Router::post('/study-tour/Operate/TopicCommentAdmin/update', TopicCommentAdminController::class . '@update');//更新
            Router::get('/study-tour/Operate/TopicCommentAdmin/update', TopicCommentAdminController::class . '@update');//编辑查看
            Router::get('/study-tour/Operate/TopicCommentAdmin/index', TopicCommentAdminController::class . '@index');//列表数据
            Router::post('/study-tour/Operate/TopicCommentAdmin/delete', TopicCommentAdminController::class . '@delete');//删除
            //游学故事
            Router::post('/study-tour/Operate/StoryAdmin/create', StoryAdminController::class . '@create');//创建
            Router::post('/study-tour/Operate/StoryAdmin/update', StoryAdminController::class . '@update');//更新
            Router::get('/study-tour/Operate/StoryAdmin/update', StoryAdminController::class . '@update');//编辑查看
            Router::get('/study-tour/Operate/StoryAdmin/index', StoryAdminController::class . '@index');//列表数据
            Router::get('/study-tour/Operate/PageConfigAdmin/storyListProject', PageConfigAdminController::class . '@storyListProject');//pc列表页推荐项目
            //名校项目
            Router::post('/study-tour/Operate/ProjectAdmin/create', ProjectAdminController::class . '@create');//创建
            Router::post('/study-tour/Operate/ProjectAdmin/update', ProjectAdminController::class . '@update');//更新
            Router::get('/study-tour/Operate/ProjectAdmin/update', ProjectAdminController::class . '@update');//编辑查看
            Router::get('/study-tour/Operate/ProjectAdmin/index', ProjectAdminController::class . '@index');//列表数据
            Router::get('/study-tour/Operate/ProjectAdmin/sort', ProjectAdminController::class . '@sort');//排序列表数据
            Router::post('/study-tour/Operate/ProjectAdmin/updateSort', ProjectAdminController::class . '@updateSort');//更新排序
            Router::get('/study-tour/Operate/ProjectAdmin/selector', ProjectAdminController::class . '@index');//选择器组件
            //H5首页配置
            Router::post('/study-tour/Operate/PageConfigAdmin/create', PageConfigAdminController::class . '@create');//创建
            Router::post('/study-tour/Operate/PageConfigAdmin/update', PageConfigAdminController::class . '@update');//更新
            Router::get('/study-tour/Operate/PageConfigAdmin/update', PageConfigAdminController::class . '@update');//编辑查看
            Router::get('/study-tour/Operate/PageConfigAdmin/banner', PageConfigAdminController::class . '@banner');//轮播图列表数据
            Router::get('/study-tour/Operate/PageConfigAdmin/icon', PageConfigAdminController::class . '@icon');//图标导航列表数据
            Router::get('/study-tour/Operate/PageConfigAdmin/hotProject', PageConfigAdminController::class . '@hotProject');//热门项目列表数据//
            Router::get('/study-tour/Operate/PageConfigAdmin/story', PageConfigAdminController::class . '@story');//游学故事列表数据
            Router::get('/study-tour/Operate/PageConfigAdmin/hotTopic', PageConfigAdminController::class . '@hotTopic');//热门话题列表数据
            //pc首页配置
            Router::get('/study-tour/Operate/PageConfigAdmin/pcBanner', PageConfigAdminController::class . '@pcBanner');//轮播图列表数据
            Router::get('/study-tour/Operate/PageConfigAdmin/pcHotProject', PageConfigAdminController::class . '@pcHotProject');//热门话题列表数据
            //App首页配置
            Router::get('/study-tour/Operate/PageConfigAdmin/recommendSchemePop', PageConfigAdminController::class . '@recommendSchemePop');//游学方案弹窗
            //组件
            Router::get('/study-tour/Operate/richText', ProjectAdminController::class . '@index');//富文本编辑器
        });
    });
});


