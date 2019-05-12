<?php
/**
 * Article.php
 * Niushop商城系统 - 团队十年电商经验汇集巨献!
 * =========================================================
 * Copy right 2015-2025 山西牛酷信息科技有限公司, 保留所有权利。
 * ----------------------------------------------
 * 官方网址: http://www.niushop.com.cn
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用。
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================
 * @author : niuteam
 * @date : 2015.1.17
 * @version : v1.0.0.0
 */
namespace app\wap\controller;

use data\service\Article;
use data\service\Goods;
use think\Controller;
use data\service\Config;
\think\Loader::addNamespace('data', 'data/');

class Applet extends Controller
{
    public $style;
    
    public function __construct()
    {
        parent::__construct();
        $config = new Config();
        $use_wap_template = $config->getUseWapTemplate($this->instance_id);
        
        if (empty($use_wap_template)) {
            $use_wap_template['value'] = 'default_new';
        }
        // 检查模版是否存在
        if (! checkTemplateIsExists("wap", $use_wap_template['value'])) {
            $this->error("模板配置有误，请联系商城管理员");
        }
        
        $defaultImages = $config->getDefaultImages($this->instance_id);
        $this->assign("default_goods_img", $defaultImages["value"]["default_goods_img"]);//默认商品图片
        $this->assign("default_headimg", $defaultImages["value"]["default_headimg"]);//默认用户头像
        
        $this->style = "wap/" . $use_wap_template['value'] . "/";
        $this->assign("style", "wap/" . $use_wap_template['value']);
    }
    

    /**
     * 文章内容
     */
    public function articleContent()
    {
        $article_id = request()->get('article_id', '');
        $article = new Article();
        $article_info = $article->getArticleDetail($article_id);
        if (empty($article_info)) {
            $this->error("未获取到文章信息");
        }
        $this->assign("title_before",$article_info['title']);
        $this->assign('article_info', $article_info);
        return view($this->style . 'Applet/articleContent');
    }
    
    /**
     * 商品详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function goodsDetail()
    {
        $goods_id = request()->get('id', 0);
        if ($goods_id == 0) {
            $this->error("没有获取到商品信息");
        }
        $goods = new Goods();
    
        $goods_detail = $goods->getBasisGoodsDetail($goods_id);

        $this->assign("goods_detail", $goods_detail);
    
        return view($this->style . 'Applet/goodsDetail');
    }
}