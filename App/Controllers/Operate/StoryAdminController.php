<?php

namespace App\Controllers\Operate;

use App\Controllers\BasicController;
use App\Model\ProjectModel;
use App\Model\StoryModel;
use App\Utility\AliyunOss;
use App\Utility\ArticleCrawler;
use App\Utility\exception\AppException;
use App\Utility\WaitGroup;
use GuzzleHttp\Client;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as v;
use Symfony\Component\DomCrawler\Crawler;

class StoryAdminController extends BasicController
{
    /**
     * @var StoryModel
     */
    protected $model;

    public function __construct($request, $response, $server = null)
    {
        parent::__construct($request, $response, $server);
        $this->model = new StoryModel();
    }

    public function create(){
        if($this->get['action'] == 'crawl'){
            return $this->crawl();
        }
        v::arrayVal()
            ->key('title', v::notEmpty())
            ->key('content', v::notEmpty())
            ->key('picture', v::notEmpty())
            ->key('bg_image', v::notEmpty())
            ->key('nickname', v::notEmpty())
            ->key('avatar', v::notEmpty())
            ->key('views', v::floatVal(),false)
            ->key('is_enabled', v::boolVal(), false)
            ->assert($this->post);
        $this->model->createOne($this->post);
        $this->response->respond_data['info'] = $this->model->getSavedData();
        return $this->ok();
    }

    public function update(){
        if($this->get['action'] == 'info'){
            return $this->info();
        }
        if($this->get['action'] == 'crawl'){
            return $this->crawl();
        }
        v::arrayVal()
            ->key('uuid', v::findCallback(function ($val){
                return $this->model::where('uuid', $val)->find();
            }))
            ->key('title', v::notEmpty(), false)
            ->key('content', v::notEmpty(), false)
            ->key('picture', v::notEmpty(), false)
            ->key('bg_image', v::notEmpty(), false)
            ->key('nickname', v::notEmpty(),false)
            ->key('avatar', v::notEmpty(),false)
            ->key('views', v::floatVal(), false)
            ->key('is_enabled', v::boolVal(), false)
            ->assert($this->post);
        $this->model->updateOne($this->post, ['uuid' => $this->post['uuid']]);
        $this->response->respond_data['info'] = $this->model->getSavedData();
        return $this->ok();
    }

    /**
     * 抓取远程文章
     * @return array
     */
    protected function crawl(){
        $info = [];
        // $content = file_get_contents($this->post['url']);
        $content = (new Client())->get($this->post['url'])->getBody()->getContents();
        $crawler = new Crawler($content);
        if(strpos($content, 'class="contcon-main"') !== false){
            $content = sprintf('<p>%s</p>', $crawler->filter('.contcon-intro')->text()).$crawler->filter('.contcon-main')->html();
        }
        else{
            $content = $crawler->filter('#js_content')->html();
        }
        $content = $this->cleanContent($content, $this->post['url']);
        $info['content'] = $content;
        $this->response->respond_data['info'] = $info;
        return $this->ok();
    }

    /**
     * 清理并本地化图片
     * @param $content
     * @return mixed|string|string[]|null
     */
    protected function cleanContent($content, $url){
        $content = str_replace(['点击图片 即可阅读', 'visibility: hidden;', '二少 语录'], '', $content);
        $content = str_replace(['data-src', 'background-image:', 'background:', '保鱼君'], ['src', 'bgi:', 'bg:', '小鲸'], $content);
        $content = preg_replace(sprintf('/%s.*?</ius', implode_preg_hex('点击“阅读原文”')),  '<', $content);
        $content = preg_replace('/(\<section [^\>]*?)display:[ ]?inline-block;/',  '\1display: block;', $content);
        $content = preg_replace([
            '/\<img[^\>]*? src="[^\>]*?(wx_fmt=gif|Zia1nmTN5SZY9gWzvM0CxicdiarjZHQDFawCicrWxU3rF3sDadpttAllibILmxndVBAfj1HzE2cwO17Q0xF3XW7Mv4Q).*?\>/',
            '/\<img[^\>]*? src="[^\>]*?\.gif.*?\>/',
            sprintf('/\<section[^>]*?\>\<section[^>]*?data-tools="%s".*?\<\/section\>.*?\<\/section\>/isu', implode_preg_hex('新媒体排版')),
            '/\<span class="js_jump_icon h5_image_link".*?\<\/span\>/'
        ], '', $content);
        # 去除空内容标签
        $pattern = '/\<(((?!p\s)[a-z])+)[^\>]*?\>[\s\n ]*?\<\/\1\>/';
        while(preg_match($pattern, $content)){
            $content = preg_replace($pattern, '', $content);
        }
        # 去除内容中多余的空格或换行
        $content = preg_replace('/\>[\s\n]*?\</', '><', $content);
        $content = $this->replace_content_url($content, $url);
        # 上传与替换图片
        preg_match_all('/\<img[^\>]*? src="(.*?)\??"/', $content, $imgs);
        $imgs = $imgs[1] ?? [];
        if ($imgs) {
            $search = [];
            $replace = [];
            $wg = new WaitGroup();
            foreach ($imgs as $v){
                $wg->add();
                one_go(function () use ($wg,  $v, &$search, &$replace) {
                    if(!in_array($v, $search)){
                        $file = AliyunOss::putImageUrl($v);
                        $search[] = $v;
                        $replace[] = $file;
                    }
                    $wg->done();
                });
            }
            $wg->wait();
            $content = str_replace($search, $replace, $content);
        }
        return $content;
    }

    protected function replace_content_url($content, $feed_url) {
        preg_match('/(http|https|ftp):\/\//', $feed_url, $protocol);
        $server_url = preg_replace("/(http|https|ftp|news):\/\//", "", $feed_url);
        $server_url = preg_replace("/\/.*/", "", $server_url);

        if ($server_url == '') {
            return $content;
        }

        if (isset($protocol[0])) {
            $new_content = preg_replace('/href="\//', 'href="'.$protocol[0].$server_url.'/', $content);
            $new_content = preg_replace('/src="\//', 'src="'.$protocol[0].$server_url.'/', $new_content);
        } else {
            $new_content = $content;
        }
        return $new_content;
    }

    protected function info(){
        $info = null;
        v::arrayVal()
            ->key('uuid', v::findCallback(function ($val) use(&$info){
                $info = $this->model::where('uuid', $val)->find();
                return $info;
            }))
            ->assert($this->get);
        $info->project_list = [];
        foreach ($info['project_uuid_list'] as $v){
            $project = ProjectModel::getByUuid($v);
            if($project){
                $info->project_list[] = $project;
            }
        }
        $this->response->respond_data['info'] = $info;
        return $this->ok();
    }

    public function index(){
        v::arrayVal()
            ->key('keywords', v::notEmpty(), false)
            ->assert($this->get);
        $limit = $this->get['limit'] ?? 7;
        $page = $this->get['page'] ?? 1;
        $page_total = 0;
        $list = [];
        $filter = $this->get;
        $total = $this->model->getFilterTotal($filter);
        if($total > 0){
            $page_total = ceil($total/$limit);
            $page = min($page, $page_total);
            $list = $this->model->getFilterList($filter, min(100, $page), min(100, $limit));
            foreach ($list as $k => $v){
                $v->formatFields();
            }
        }
        $this->response->respond_data['list'] = $list;
        $this->response->respond_data['total'] = $total;
        $this->response->respond_data['page_total'] = $page_total;
        $authors=$this->model->column('nickname as value,avatar')->groupBy('nickname')->orderBy('sys_id desc')->findAll();
        $this->response->respond_data['authors'] = $authors;
        return $this->ok();
    }

}




