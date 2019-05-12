<?php
/**
 * Upload.php
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

\think\Loader::addNamespace('data', 'data/');
use data\service\Album as Album;
use data\service\Config as WebConfig;
use think\Controller;

/**
 * 图片上传控制器
 * goods（文件夹存放商品）
 * goods_id（每个商品的）
 * images（商品主图）
 * sku_img（sku图片）
 *
 * common文件夹（存放公共图）
 *
 * advertising文件夹（存放广告位图）
 *
 * avator文件夹（用户头像）
 *
 * pay文件夹（支付生成的图）
 *
 *
 * @author Administrator
 *        
 */
use data\service\Upload\QiNiu;
use think\Config;
use think\Image;

// 存放商品图片、主图、sku
define("UPLOAD_GOODS", Config::get('view_replace_str.UPLOAD_GOODS'));
// 存放商品图片、主图、sku
define("UPLOAD_GOODS_SKU", Config::get('view_replace_str.UPLOAD_GOODS_SKU'));

// 存放商品品牌图
define("UPLOAD_GOODS_BRAND", Config::get('view_replace_str.UPLOAD_GOODS_BRAND'));

// 存放商品分组图片
define("UPLOAD_GOODS_GROUP", Config::get('view_replace_str.UPLOAD_GOODS_GROUP'));

// 存放商品分类图片
define("UPLOAD_GOODS_CATEGORY", Config::get('view_replace_str.UPLOAD_GOODS_CATEGORY'));

// 存放公共图片、网站logo、独立图片、没有任何关联的图片
define("UPLOAD_COMMON", Config::get('view_replace_str.UPLOAD_COMMON'));

// 存放用户头像
define("UPLOAD_AVATOR", Config::get('view_replace_str.UPLOAD_AVATOR'));

// 存放支付生成的二维码图片
define("UPLOAD_PAY", Config::get('view_replace_str.UPLOAD_PAY'));

// 存放广告位图片
define("UPLOAD_ADV", Config::get('view_replace_str.UPLOAD_ADV'));

// 存放物流图片
define("UPLOAD_EXPRESS", Config::get('view_replace_str.UPLOAD_EXPRESS'));

// 存放文章图片
define("UPLOAD_CMS", Config::get('view_replace_str.UPLOAD_CMS'));

// 存放视频文件
define("UPLOAD_VIDEO", Config::get('view_replace_str.UPLOAD_VIDEO'));

class Upload extends Controller
{

    private $return = array();
    
    // 文件路径
    private $file_path = "";
    
    // 重新设置的文件路径
    private $reset_file_path = "";
    
    // 文件名称
    private $file_name = "";
    
    // 文件大小
    private $file_size = 0;
    
    // 文件类型
    private $file_type = "";

    private $upload_type = 1;

    private $instance_id = "";
    
    // 缩略类型
    private $thumb_type = 1;
    
    // 是否开启水印功能
    private $is_watermark = false;
    
    // 水印图片透明度
    private $transparency = 100;
    
    // 水印图片位置
    private $waterPosition = \think\Image::WATER_SOUTHEAST;
    
    // 水印图片，默认图
    private $imgWatermark = "public/static/images/show-water.png";
    
    private $ext = "";

    public function __construct()
    {
        $this->instance_id = 0;
        $config = new WebConfig();
        $this->upload_type = $config->getUploadType(0);
        $picture_info = $config->getPictureUploadSetting(0);
        $this->thumb_type = $picture_info["thumb_type"];
        
        $config_water_info = $config->getWatermarkConfig($this->instance_id);
        
        if (! empty($config_water_info)) {
            if (! empty($config_water_info['watermark']) && $config_water_info['watermark'] == "1") {
                $this->is_watermark = true;
                $this->imgWatermark = $config_water_info['imgWatermark'];
                if (! empty($config_water_info['transparency'])) {
                    $this->transparency = $config_water_info['transparency'];
                }
                if (! empty($config_water_info['waterPosition'])) {
                    $this->waterPosition = $config_water_info['waterPosition'];
                }
            }
        }
    }

    /**
     * 功能说明：文件(图片)上传(存入相册)
     */
    public function uploadFile()
    {
        $this->file_path = request()->post("file_path", "");
        if ($this->file_path == "") {
            $this->return['message'] = "文件路径不能为空";
            return $this->ajaxFileReturn();
        }
        // 重新设置文件路径
        $this->resetFilePath();
        
        if(empty($this->reset_file_path)){
            $this->return['message'] = "文件路径不能为空";
            return $this->ajaxFileReturn();
        }
        
        // 检测文件夹是否存在，不存在则创建文件夹
        if (! file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        
        $this->file_name = $_FILES["file_upload"]["name"]; // 文件原名
        $this->file_size = $_FILES["file_upload"]["size"]; // 文件大小
        $this->file_type = $_FILES["file_upload"]["type"]; // 文件类型
        
        if ($this->file_size == 0) {
            $this->return['message'] = "文件大小为0MB";
            return $this->ajaxFileReturn();
        }
        
        $guid = time();
        $file_name_explode = explode(".", $this->file_name); // 图片名称
        $suffix = count($file_name_explode) - 1;
        $this->ext = "." . $file_name_explode[$suffix]; // 获取后缀名
        $newfile = $guid . $this->ext; // 重新命名文件
        
        // 验证文件
        if (! $this->validationFile()) {
            return $this->ajaxFileReturn();
        }
        
        $ok = $this->generateImage($newfile);
        if ($ok["code"]) {
            
            // 文件上传成功执行下边的操作
            if (! strstr($this->reset_file_path, UPLOAD_VIDEO) && ! strstr($this->reset_file_path, GOODS_VIDEO_PATH) && ! strstr($this->reset_file_path, UPLOAD_FILE)) {
                @unlink($_FILES['file_upload']);
                $image_size = @getimagesize($ok["path"]); // 获取图片尺寸
                
                if ($image_size) {
                    
                    $width = $image_size[0];
                    $height = $image_size[1];
                    $name = $file_name_explode[0];
                    
                    switch ($this->file_path) {
                        case UPLOAD_GOODS:
                            
                            // 商品图
                            $type = request()->post("type", "");
                            $pic_name = request()->post("pic_name", $name);
                            $album_id = request()->post("album_id", 0);
                            $pic_tag = request()->post("pic_tag", $name);
                            $pic_id = request()->post("pic_id", "");
                            $upload_flag = request()->post("upload_flag", "");
                            // 上传到相册管理，生成四张大小不一的图
                            $retval = $this->photoCreate($this->reset_file_path, $this->reset_file_path . $newfile, "." . $file_name_explode[$suffix], $type, $pic_name, $album_id, $width, $height, $pic_tag, $pic_id, $ok["domain"], $ok["bucket"], $ok["path"]);
                            if ($retval > 0) {
                                $this->return['code'] = 1;
                                $this->return['message'] = "上传成功";
                            } else {
                                $this->return['message'] = "上传失败";
                                $this->return['code'] = 0;
                            }
                            $this->return['data'] = $ok["path"];
                            break;
                        case UPLOAD_GOODS_SKU:
                            
                            // 商品SKU图片
                            $this->return['code'] = 1;
                            $this->return['data'] = $ok["path"];
                            $this->return['message'] = "上传成功";
                            break;
                        case UPLOAD_GOODS_BRAND:
                            $this->return['code'] = 1;
                            $this->return['data'] = $ok["path"];
                            $this->return['message'] = "上传成功";
                            // 商品品牌
                            break;
                        case UPLOAD_GOODS_GROUP:
                            
                            // 商品分组
                            $this->return['code'] = 1;
                            $this->return['data'] = $ok["path"];
                            $this->return['message'] = "上传成功";
                            break;
                        case UPLOAD_GOODS_CATEGORY:
                            
                            // 商品分类
                            $this->return['code'] = 1;
                            $this->return['data'] = $ok["path"];
                            $this->return['message'] = "上传成功";
                            break;
                        case UPLOAD_COMMON:
                            
                            // 公共
                            $this->return['code'] = 1;
                            $this->return['data'] = $ok["path"];
                            $this->return['message'] = "上传成功";
                            break;
                        case UPLOAD_AVATOR:
                            
                            // 用户头像
                            $new_key = $this->reset_file_path . md5(time() . $name) . "_120_120" . $this->ext;
                            $retval = $this->uploadThumbFile($this->reset_file_path . $newfile, $new_key, 120, 120, 2);
                            if ($retval > 0) {
                                $this->return['message'] = "上传成功";
                                $this->return['code'] = 1;
                            } else {
                                $this->return['message'] = "上传失败";
                                $this->return['code'] = 0;
                            }
                            $this->return['data'] = $retval["path"];
                            break;
                        case UPLOAD_PAY:
                            
                            // 支付
                            $this->return['code'] = 1;
                            $this->return['data'] = $ok["path"];
                            $this->return['message'] = "上传成功";
                            break;
                        case UPLOAD_ADV:
                            
                            // 广告位
                            $this->return['code'] = 1;
                            $this->return['data'] = $ok["path"];
                            $this->return['message'] = "上传成功";
                            break;
                        case UPLOAD_EXPRESS:
                            
                            // 物流
                            $this->return['code'] = 1;
                            $this->return['data'] = $ok["path"];
                            $this->return['message'] = "上传成功";
                            break;
                        case UPLOAD_CMS:
                            
                            // 文章
                            $this->return['code'] = 1;
                            $this->return['data'] = $ok["path"];
                            $this->return['message'] = "上传成功";
                            break;
                        case UPLOAD_COMMENT:
                            
                            // 评论
                            $new_key = $this->reset_file_path . md5(time() . $name) . "_600_600" . $this->ext;
                            $retval = $this->uploadThumbFile($this->reset_file_path . $newfile, $new_key, 600, 600, 2);
                            if ($retval > 0) {
                                $this->return['message'] = "上传成功";
                                $this->return['code'] = 1;
                            } else {
                                $this->return['message'] = "上传失败";
                                $this->return['code'] = 0;
                            }
                            $this->return['data'] = $retval["path"];
                            break;
                        case UPLOAD_WEB_COMMON:
                            
                            // 系统默认图
                            $new_key = $this->reset_file_path . md5(time() . $name) . "_360_360" . $this->ext;
                            $retval = $this->uploadThumbFile($this->reset_file_path . $newfile, $new_key, 360, 360, 2);
                            if ($retval > 0) {
                                $this->return['message'] = "上传成功";
                                $this->return['code'] = 1;
                            } else {
                                $this->return['message'] = "上传失败";
                                $this->return['code'] = 0;
                            }
                            $this->return['data'] = $retval["path"];
                            break;
                        case UPLOAD_ICO:
                            $new_key = $this->reset_file_path . md5(time() . $name) . "_60_60" . $this->ext;
                            $retval = $this->uploadThumbFile($this->reset_file_path . $newfile, $new_key, 60, 60, 2);
                            if ($retval > 0) {
                                $this->return['message'] = "上传成功";
                                $this->return['code'] = 1;
                            } else {
                                $this->return['message'] = "上传失败";
                                $this->return['code'] = 0;
                            }
                            $this->return['data'] = $retval["path"];
                            break;
                        case UPLOAD_WATERMARK:
                            
                            // 水印图片
                            $this->return['message'] = "上传成功";
                            $this->return['code'] = 1;
                            $this->return['data'] = $ok["path"];
                            break;
                    }
                } else {
                    // 强制将文件后缀改掉，文件流不同会导致上传文件失败 上传失败后将生成的文件删除
                    @unlink($ok["path"]);
                    $this->return['message'] = "请检查您的上传参数配置或上传的文件是否有误";
                }
            } else {
                switch ($this->file_path) {
                    case UPLOAD_VIDEO:
                        
                        // 公共视频文件
                        $this->return['code'] = 1;
                        $this->return['data'] = $ok["path"];
                        $this->return['message'] = "上传成功";
                        break;
                    case GOODS_VIDEO_PATH:
                        
                        // 商品视频文件
                        $this->return['code'] = 1;
                        $this->return['data'] = $ok["path"];
                        $this->return['message'] = "上传成功";
                        break;
                    case UPLOAD_FILE:
                        
                        // 文件
                        $this->return['code'] = 1;
                        $this->return['data'] = $ok["path"];
                        $this->return['message'] = "上传成功";
                        break;
                }
            }
            // 删除本地的图片
            if ($this->upload_type == 2) {
                @unlink($this->reset_file_path . $newfile);
            }
        } else {
            // 强制将文件后缀改掉，文件流不同会导致上传文件失败
            @unlink($ok["path"]);
            $this->return['message'] = $ok['message'];
        }
        return $this->ajaxFileReturn();
    }


    /**
     * 用于相册多图上传
     *
     * @return string|multitype:string
     */
    public function photoAlbumUpload()
    {
        $data = array();
        $this->file_path = request()->post("file_path", "");
        if ($this->file_path == "") {
            $data['state'] = '0';
            $data['message'] = "文件路径不能为空";
            $data['origin_file_name'] = $this->file_name;
            return $data;
        }
        // 重新设置文件路径
        $this->resetFilePath();

        if(empty($this->reset_file_path)){
            $this->return['message'] = "文件路径不能为空";
            return $this->ajaxFileReturn();
        }
        
        
        // 检测文件夹是否存在，不存在则创建文件夹
        if (! file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
    
        $this->file_name = $_FILES["file_upload"]["name"]; // 文件原名
        $this->file_size = $_FILES["file_upload"]["size"]; // 文件大小
        $this->file_type = $_FILES["file_upload"]["type"]; // 文件类型
    
        if ($this->file_size == 0) {
            $data['state'] = '0';
            $data['message'] = "文件大小为0MB";
            $data['origin_file_name'] = $this->file_name;
            return $data;
        }
        if ($this->file_size > 5000000) {
            $data['state'] = '0';
            $data['message'] = "文件大小不能超过5MB";
            $data['origin_file_name'] = $this->file_name;
            return $data;
        }
    
        $guid = time();
        $file_name_explode = explode(".", $this->file_name); // 图片名称
        $suffix = count($file_name_explode) - 1;
        $this->ext = "." . $file_name_explode[$suffix]; // 获取后缀名
        // 获取原文件名
        $tmp_array = $file_name_explode;
        unset($tmp_array[$suffix]);
        $file_new_name = implode(".", $tmp_array);
        $newfile = md5($file_new_name . $guid) . $this->ext; // 重新命名文件
    
        // 验证文件
        if (! $this->validationFile()) {
            return $this->ajaxFileReturn();
        }
    
        $ok = $this->generateImage($newfile);
    
        if ($ok["code"]) {
            @unlink($_FILES['file_upload']);
            $image_size = @getimagesize($ok["path"]); // 获取图片尺寸
    
            if ($image_size) {
                $width = $image_size[0];
                $height = $image_size[1];
                $name = $file_name_explode[0];
                $type = request()->post("type", "");
                $pic_name = request()->post("pic_name", $file_new_name);
                $album_id = request()->post("album_id", 0);
                $pic_tag = request()->post("pic_tag", $file_new_name);
                $pic_id = request()->post("pic_id", "");
                $upload_flag = request()->post("upload_flag", "");
                // 上传到相册管理，生成四张大小不一的图 只有商品图才会生成
                 
                $retval = $this->photoCreate($this->reset_file_path, $this->reset_file_path . $newfile, "." . $file_name_explode[$suffix], $type, $pic_name, $album_id, $width, $height, $pic_tag, $pic_id, $ok["domain"], $ok["bucket"], $ok["path"]);
                 
                if ($retval > 0) {
                    // $album = new Album();
                    // $picture_info = $album->getAlubmPictureDetail([
                    // "pic_id" => $retval
                    // ]);
                    $data['file_id'] = $retval;
                    // $data['file_name'] = $picture_info["pic_cover_mid"];
                    $data['file_name'] = $ok["path"];
                    $data['origin_file_name'] = $this->file_name;
                    $data['file_path'] = $this->reset_file_path . $newfile;
                    $data['state'] = '1';
                } else {
                    $data['state'] = '0';
                    $data['message'] = "图片上传失败";
                    $data['origin_file_name'] = $this->file_name;
                }
            } else {
                $data['state'] = '0';
                $data['message'] = "图片上传失败";
                $data['origin_file_name'] = $this->file_name;
            }
            // 删除本地的图片
            if ($this->upload_type == 2) {
                @unlink($this->reset_file_path . $newfile);
            }
        } else {
            $data['state'] = '0';
            $data['message'] = "图片上传失败";
            $data['origin_file_name'] = $this->file_name;
        }
        if(isset($data['state']) && $data['state'] == 0){
            @unlink($this->reset_file_path . $newfile);
        }
        return $data;
    }
    
    /**
     * 商品规格图片上传
     *
     * @return multitype:string |string
     */
    public function specImgUpload()
    {
        $data = array();
        $this->file_path = request()->post("file_path", "");
        if ($this->file_path == "") {
            $data['code'] = '0';
            $data['message'] = "文件路径不能为空";
            return json_encode($data);
        }
        // 重新设置文件路径
        $this->resetFilePath();

        if(empty($this->reset_file_path)){
            $this->return['message'] = "文件路径不能为空";
            return $this->ajaxFileReturn();
        }
        
        
        // 检测文件夹是否存在，不存在则创建文件夹
        if (! file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
    
        $this->file_name = $_FILES["file_upload"]["name"]; // 文件原名
        $this->file_size = $_FILES["file_upload"]["size"]; // 文件大小
        $this->file_type = $_FILES["file_upload"]["type"]; // 文件类型
    
        if ($this->file_size == 0) {
            $data['code'] = '0';
            $data['message'] = "文件大小为0MB";
            return json_encode($data);
        }
        if ($this->file_size > 5000000) {
            $data['code'] = '0';
            $data['message'] = "文件大小不能超过5MB";
            return json_encode($data);
        }
    
        // 验证文件
        if (! $this->validationFile()) {
            $data['code'] = '0';
            $data['message'] = "文件大小不能超过5MB";
            return json_encode($data);
        }
        $guid = time();
        $file_name_explode = explode(".", $this->file_name); // 图片名称
        $suffix = count($file_name_explode) - 1;
        $ext = "." . $file_name_explode[$suffix]; // 获取后缀名
        // 获取原文件名
        $tmp_array = $file_name_explode;
        unset($tmp_array[$suffix]);
        $file_new_name = implode(".", $tmp_array);
        $newfile = md5($file_new_name . $guid) . $ext; // 重新命名文件
        $ok = @move_uploaded_file($_FILES["file_upload"]["tmp_name"], $this->reset_file_path . $newfile);
    
        if ($ok) {
            @unlink($_FILES['file_upload']);
            $image_size = @getimagesize($this->reset_file_path . $newfile); // 获取图片尺寸
            if ($image_size) {
                $image = \think\Image::open($this->reset_file_path . $newfile);
                $image->thumb(60, 60, \think\Image::THUMB_CENTER)->save($this->reset_file_path . md5(time() . $file_new_name) . "4" . $ext);
                $data['code'] = 1;
                $data['file_path'] = $this->reset_file_path . md5(time() . $file_new_name) . "4" . $ext;
                $data['message'] = "图片上传成功";
                return json_encode($data);
            } else {
                $data['code'] = 0;
                $data['message'] = "图片上传失败";
                return json_encode($data);
            }
        } else {
            $data['code'] = '0';
            $data['message'] = "图片上传失败";
            return json_encode($data);
        }
    }
    
    private function resetFilePath()
    {
        $file_path = "";
        switch ($this->file_path) {
            case UPLOAD_GOODS:
                $file_path = $this->file_path . date("Ymd") . "/";
                break;
            case UPLOAD_GOODS_SKU:
//                 $file_path = $this->file_path . request()->post("goods_path", "") . "/";
                $goods_path = request()->post("goods_path", "");
                $file_path = !empty($goods_path) ? $this->file_path . $goods_path . '/' : $this->file_path;
                break;
            case UPLOAD_GOODS_BRAND:
                $file_path = $this->file_path;
                // 商品品牌
                break;
            case UPLOAD_GOODS_GROUP:
                
                // 商品分组
                $file_path = $this->file_path;
                break;
            case UPLOAD_GOODS_CATEGORY:
                
                // 商品分类
                $file_path = $this->file_path;
                break;
            case UPLOAD_COMMON:
                $file_path = $this->file_path;
                // 公共
                break;
            case UPLOAD_AVATOR:
                $file_path = $this->file_path;
                // 用户头像
                break;
            case UPLOAD_PAY:
                $file_path = $this->file_path;
                // 支付
                break;
            case UPLOAD_ADV:
                $file_path = $this->file_path;
                // 广告位
                break;
            case UPLOAD_EXPRESS:
                
                // 物流
                $file_path = $this->file_path;
                break;
            case UPLOAD_CMS:
                
                // 文章
                $file_path = $this->file_path;
                break;
            case UPLOAD_VIDEO:
                
                // 视频
                $file_path = $this->file_path;
                break;
            case UPLOAD_COMMENT:
                
                // 评论
                $file_path = $this->file_path;
                break;
            case GOODS_VIDEO_PATH:
                
                // 商品视频
                $file_path = $this->file_path . request()->post("goods_id", "") . "/";
                break;
            case UPLOAD_WEB_COMMON:
                
                // 系统默认图
                $file_path = $this->file_path;
                break;
            case UPLOAD_ICO:
                
                // 商家服务小图标
                $file_path = $this->file_path;
                break;
            case UPLOAD_WATERMARK:
                
                // 水印图片
                $file_path = $this->file_path;
                break;
            
            case UPLOAD_FILE:
                
                // 商家服务小图标
                $file_path = $this->file_path;
                break;
        }
        $this->reset_file_path = $file_path;
    }

    /**
     * 上传文件后，ajax返回信息
     *
     * 2017年6月9日 19:54:46 王永杰
     *
     * @param array $return            
     */
    private function ajaxFileReturn()
    {
        if (empty($this->return['code']) || null == $this->return['code'] || "" == $this->return['code']) {
            $this->return['code'] = 0; // 错误码
        }
        
        if (empty($this->return['message']) || null == $this->return['message'] || "" == $this->return['message']) {
            $this->return['message'] = ""; // 消息
        }
        
        if (empty($this->return['data']) || null == $this->return['data'] || "" == $this->return['data']) {
            $this->return['data'] = ""; // 数据
        }
        return json_encode($this->return);
    }

    /**
     *
     * @param unknown $this->file_path
     *            文件路径
     * @param unknown $this->file_size
     *            文件大小
     * @param unknown $this->file_type
     *            文件类型
     * @return string|unknown|number|\think\false
     */
    private function validationFile()
    {
        $flag = true;
        switch ($this->reset_file_path) {
            case UPLOAD_GOODS:
                
                // 商品图片
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 3000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过3MB';
                    $flag = false;
                }
                break;
            case UPLOAD_GOODS_SKU:
                
                // 商品SKU图片
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 1000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过1MB';
                    $flag = false;
                }
                break;
            case UPLOAD_GOODS_BRAND:
                
                // 商品品牌
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 1000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过1MB';
                    $flag = false;
                }
                break;
            case UPLOAD_GOODS_GROUP:
                
                // 商品分组
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 1000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过1MB';
                    $flag = false;
                }
                break;
            case UPLOAD_GOODS_CATEGORY:
                
                // 商品分类
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 1000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过1MB';
                    $flag = false;
                }
                break;
            case UPLOAD_COMMON:
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 1000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过1MB';
                    $flag = false;
                }
                // 公共
                break;
            case UPLOAD_AVATOR:
                
                // 用户头像
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 1000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过1MB';
                    $flag = false;
                }
                break;
            case UPLOAD_PAY:
                
                // 支付
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 1000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过1MB';
                    $flag = false;
                }
                break;
            case UPLOAD_ADV:
                
                // 广告位
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 1000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过1MB';
                    $flag = false;
                }
                break;
            case UPLOAD_EXPRESS:
                
                // 物流
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 1000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过1MB';
                    $flag = false;
                }
                break;
            case UPLOAD_CMS:
                
                // 文章
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 1000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过1MB';
                    $flag = false;
                }
                break;
            case UPLOAD_VIDEO:
                
                // 公共视频
                if ($this->file_type != "video/mp4" || $this->file_size > 500000000) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过500MB';
                    $flag = false;
                }
                break;
            case UPLOAD_COMMENT:
                
                // 评论
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg" || !$this->checkImgSuffix())) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型';
                    $flag = false;
                }
                break;
            case GOODS_VIDEO_PATH:
                
                // 商品视频
                if ($this->file_type != "video/mp4" || !$this->checkFileSuffix() || $this->file_size > 500000000) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过500MB';
                    $flag = false;
                }
                break;
            case UPLOAD_ICO:
                
                // 商家服务小图标
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 1000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过1MB';
                    $flag = false;
                }
                break;
            case UPLOAD_WATERMARK:
                
                // 水印图片
                if (($this->file_type != "image/gif" && $this->file_type != "image/png" && $this->file_type != "image/jpeg" && $this->file_type != "image/jpg") || $this->file_size > 1000000 || !$this->checkImgSuffix()) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过1MB';
                    $flag = false;
                }
                break;
            case UPLOAD_FILE:
                // 存放文件
                if (($this->file_type != "application/zip" && $this->file_type != "application/x-rar" && $this->file_type != "application/x-zip-compressed") || !$this->checkFileSuffix() || $this->file_size > 500000000) {
                    $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过500MB';
                    $flag = false;
                }
                break;
        }
        return $flag;
    }
    
    /**
     * 检测上传图片后缀
     */
    private function checkImgSuffix(){
        $white_list = ['.jpg','.png','.jpeg','.gif'];
        if(in_array($this->ext, $white_list)){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 检测上传图片后缀
     */
    private function checkFileSuffix(){
        $white_list = ['.zip','.rar','.apk','.mp4'];
        if(in_array($this->ext, $white_list)){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 各类型图片生成
     *
     * @param unknown $photoPath            
     * @param unknown $ext            
     * @param number $type            
     */
    private function photoCreate($upFilePath, $photoPath, $ext, $type = 0, $pic_name, $album_id, $width, $height, $pic_tag, $pic_id, $domain, $bucket, $upload_img)
    {
        $photoArray = array(
            "bigPath" => array(
                "path" => '',
                "width" => 840,
                "height" => 480,
                'type' => '1'
            ),
            "middlePath" => array(
                "path" => '',
                "width" => 350,
                "height" => 200,
                'type' => '2'
            ),
            "smallPath" => array(
                "path" => '',
                "width" => 245,
                "height" => 140,
                'type' => '3'
            ),
            "littlePath" => array(
                "path" => '',
                "width" => 70,
                "height" => 40,
                'type' => '4'
            )
        );
        
        $photoArray["bigPath"]["path"] = $upFilePath . md5(time() . $pic_tag) . "1" . $ext;
        $photoArray["middlePath"]["path"] = $upFilePath . md5(time() . $pic_tag) . "2" . $ext;
        $photoArray["smallPath"]["path"] = $upFilePath . md5(time() . $pic_tag) . "3" . $ext;
        $photoArray["littlePath"]["path"] = $upFilePath . md5(time() . $pic_tag) . "4" . $ext;
        // 循环生成4张大小不一的图
        foreach ($photoArray as $k => $v) {
            if (stristr($type, $v['type'])) {
                $result = $this->uploadThumbFile($photoPath, $v["path"], $v["width"], $v["height"]);
                if ($result["code"]) {
                    $photoArray[$k]["path"] = $result["path"];
                } else {
                    return 0;
                }
            }
        }
        
        $album = new Album();
        if ($pic_id == "") {
            $retval = $album->addPicture($pic_name, $pic_tag, $album_id, $upload_img, $width . "," . $height, $width . "," . $height, $photoArray["bigPath"]["path"], $photoArray["bigPath"]["width"] . "," . $photoArray["bigPath"]["height"], $photoArray["bigPath"]["width"] . "," . $photoArray["bigPath"]["height"], $photoArray["middlePath"]["path"], $photoArray["middlePath"]["width"] . "," . $photoArray["middlePath"]["height"], $photoArray["middlePath"]["width"] . "," . $photoArray["middlePath"]["height"], $photoArray["smallPath"]["path"], $photoArray["smallPath"]["width"] . "," . $photoArray["smallPath"]["height"], $photoArray["smallPath"]["width"] . "," . $photoArray["smallPath"]["height"], $photoArray["littlePath"]["path"], $photoArray["littlePath"]["width"] . "," . $photoArray["littlePath"]["height"], $photoArray["littlePath"]["width"] . "," . $photoArray["littlePath"]["height"], $this->instance_id, $this->upload_type, $domain, $bucket);
        } else {
            $retval = $album->ModifyAlbumPicture($pic_id, $upload_img, $width . "," . $height, $width . "," . $height, $photoArray["bigPath"]["path"], $photoArray["bigPath"]["width"] . "," . $photoArray["bigPath"]["height"], $photoArray["bigPath"]["width"] . "," . $photoArray["bigPath"]["height"], $photoArray["middlePath"]["path"], $photoArray["middlePath"]["width"] . "," . $photoArray["middlePath"]["height"], $photoArray["middlePath"]["width"] . "," . $photoArray["middlePath"]["height"], $photoArray["smallPath"]["path"], $photoArray["smallPath"]["width"] . "," . $photoArray["smallPath"]["height"], $photoArray["smallPath"]["width"] . "," . $photoArray["smallPath"]["height"], $photoArray["littlePath"]["path"], $photoArray["littlePath"]["width"] . "," . $photoArray["littlePath"]["height"], $photoArray["littlePath"]["width"] . "," . $photoArray["littlePath"]["height"], $this->instance_id, $this->upload_type, $domain, $bucket);
            $retval = $pic_id;
        }
        return $retval;
    }
    
    /**
     * 原图上传(上传到外网的同时,也会在本地生成图片(在缩略图生成使用后会被删除))
     *
     * @param unknown $file_path            
     * @param unknown $key            
     */
    private function moveUploadFile($file_path, $key)
    {
        $ok = @move_uploaded_file($file_path, $key);
        $result = [
            "code" => $ok,
            "path" => $key,
            "domain" => '',
            "bucket" => '',
            "message" => '上传成功'
        ];
        if ($ok) {
            if($this->ext != '.gif'){                
                if(getimagesize($key)){
                    $image = \think\Image::open($key);
                    $image->save($key, null, 100);
                    unset($image);
                }
                if ($this->upload_type == 2) {
                    $qiniu = new QiNiu();
                    $result = $qiniu->setQiniuUplaod($key, $key);
                }
            }
        }
        return $result;
    }

    /**
     * 用户缩略图上传
     *
     * @param unknown $file_path            
     * @param unknown $key            
     */
    private function uploadThumbFile($photoPath, $key, $width, $height, $upload_type = null)
    {
        try {
            $image = \think\Image::open($photoPath);
            $image->thumb($width, $height, isset($upload_type) ? $upload_type : $this->thumb_type);
            $image->save($key, null, 100);
            unset($image);
            $result = array(
                "code" => true,
                "path" => $key
            );
            if ($this->upload_type == 2) {
                $qiniu = new QiNiu();
                $result = $qiniu->setQiniuUplaod($key, $key);
                @unlink($key);
            }
            return $result;
        } catch (\Exception $e) {
            return array(
                "code" => false
            );
        }
    }

    /**
     * 生成图片
     * 创建时间：2018年3月31日15:11:36 王永杰
     */
    private function generateImage($newfile)
    {
        // 开启水印功能，目前只针对商品图片添加水印
        if ($this->is_watermark && ! empty($this->imgWatermark) && $this->file_path == UPLOAD_GOODS) {
            
            try {
                $image = \think\Image::open(request()->file('file_upload'));
                $res = $image->water($this->imgWatermark, $this->waterPosition, $this->transparency)->save($this->reset_file_path . $newfile);
                if (! empty($res)) {
                    $ok = [
                        "code" => 1,
                        "path" => $this->reset_file_path . $newfile,
                        "domain" => '',
                        "bucket" => ''
                    ];
                    if ($this->upload_type == 2) {
                        $qiniu = new QiNiu();
                        $ok = $qiniu->setQiniuUplaod($ok['path'], $ok['path']);
                    }
                } else {
                    $ok = $this->moveUploadFile($_FILES["file_upload"]["tmp_name"], $this->reset_file_path . $newfile);
                }
            } catch (\Exception $e) {
                // 水印图片不存在或者其他错误，则生成正常的图片
                $ok = $this->moveUploadFile($_FILES["file_upload"]["tmp_name"], $this->reset_file_path . $newfile);
            }
        } else {
            $ok = $this->moveUploadFile($_FILES["file_upload"]["tmp_name"], $this->reset_file_path . $newfile);
        }
        return $ok;
    }   
    
    public function ueditorUploadQiNiu(){
        $key = request()->post("key", "");
        $return_path = $key;
        if ($this->upload_type == 2) {
            $qiniu = new QiNiu();
            $result = $qiniu->setQiniuUplaod($key, $key);
            if($result["code"]){
                $return_path = $result['path'];
                if(file_exists($key)){
                    @unlink($key);
                }
            }
        }
        return $return_path;
    }
}