

## 环境变量配置文件
请将.env.example文件拷贝并命名为.env

.env文件为各环境的本地配置, 用于定义环境标识符及敏感数据(数据库ip,密码...)等. 
该配置文件也可能被用于其他组件(非PHP),请谨慎删除配置项. 
该文件须加入.gitignore忽略名单.

以下文件根据.env文件中定义的environment变量动态加载.用于定义各环境非敏感数据的配置, 如: 开发环境设置错误邮件接收者仅为自己而生产需要设置其他接收者; 开发及测试环境需要设置debug模式而生产不需要等等. 
请不要将这些文件(如有更多环境可相应增加)加入.gitignore忽略名单
- .env.dev 开发环境
- .env.test 测试环境(251)
- .env.sim 仿真环境(76) 
- .env.real 准生产环境 
- .env.pro 生产环境

项目中可使用getenv()或env()函数获取配置项, env()自动根据值转换了类型, 如数值将自动转为整形,请注意.
请参阅 [https://github.com/vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)。