<?php
/**
 * Config.php
 *
 * Niushop商城系统 - 团队十年电商经验汇集巨献!
 * =========================================================
 * Copy right 2015-2025 山西牛酷信息科技有限公司, 保留所有权利。
 * ----------------------------------------------
 * 官方网址: http://www.niushop.com.cn
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用。
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================
 * @author : niuteam
 * @date : 2015.4.24
 * @version : v1.0.0.0
 */
namespace data\service;

/**
 * 系统配置业务层
 */
use data\api\IConfig as IConfig;
use data\model\ConfigModel as ConfigModel;
use data\model\NoticeModel;
use data\model\NoticeTemplateItemModel;
use data\model\NoticeTemplateModel;
use data\model\NoticeTemplateTypeModel;
use data\service\BaseService as BaseService;
use Qiniu\json_decode;
use think\Cache;
use think\Config as ThinkPHPConfig;
use think\Db;
use data\model\SysWapCustomTemplateModel;
use data\model\SysShortcutMenuModel;
use data\model\NsAppUpgradeModel;

class Config extends BaseService implements IConfig
{

    private $config_module;

    function __construct()
    {
        parent::__construct();
        $this->config_module = new ConfigModel();
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::getWchatConfig()
     */
    public function getWchatConfig($instance_id)
    {
        $wchat_config = Cache::get("wchat_config" . $instance_id);
        if (empty($wchat_config)) {
            $info = $this->config_module->getInfo([
                'key' => 'WCHAT',
                'instance_id' => $instance_id
            ], 'value,is_use');
            if (empty($info['value'])) {
                $wchat_config = array(
                    'value' => array(
                        'APP_KEY' => '',
                        'APP_SECRET' => '',
                        'AUTHORIZE' => '',
                        'CALLBACK' => ''
                    ),
                    'is_use' => 0
                );
            } else {
                $info['value'] = json_decode($info['value'], true);
                $wchat_config = $info;
            }
            Cache::set("wchat_config" . $instance_id, $wchat_config);
        }
        return $wchat_config;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::setWchatConfig()
     */
    public function setWchatConfig($instance_id, $appid, $appsecret, $url, $call_back_url, $is_use)
    {
        Cache::set("wchat_config" . $instance_id, '');
        $info = array(
            'APP_KEY' => trim($appid),
            'APP_SECRET' => trim($appsecret),
            'AUTHORIZE' => $url,
            'CALLBACK' => $call_back_url
        );
        $value = json_encode($info);
        $count = $this->config_module->where([
            'key' => 'WCHAT',
            'instance_id' => $instance_id
        ])->count();
        if ($count > 0) {
            $data = array(
                'value' => $value,
                'is_use' => $is_use,
                'modify_time' => time()
            );
            $res = $this->config_module->where([
                'key' => 'WCHAT',
                'instance_id' => $instance_id
            ])->update($data);
            if ($res == 1) {
                return SUCCESS;
            } else {
                return UPDATA_FAIL;
            }
        } else {
            $data = array(
                'instance_id' => $instance_id,
                'key' => 'WCHAT',
                'value' => $value,
                'is_use' => $is_use,
                'create_time' => time()
            );
            $res = $this->config_module->save($data);
            return $res;
        }
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::getQQConfig()
     */
    public function getQQConfig($instance_id)
    {
        $qq_config = Cache::get("qq_config" . $instance_id);
        if (empty($qq_config)) {
            $info = $this->config_module->getInfo([
                'key' => 'QQLOGIN',
                'instance_id' => $instance_id
            ], 'value,is_use');
            if (empty($info['value'])) {
                $qq_config = array(
                    'value' => array(
                        'APP_KEY' => '',
                        'APP_SECRET' => '',
                        'AUTHORIZE' => '',
                        'CALLBACK' => ''
                    ),
                    'is_use' => 0
                );
            } else {
                $info['value'] = json_decode($info['value'], true);
                $qq_config = $info;
            }
            Cache::set("qq_config" . $instance_id, $qq_config);
        }
        return $qq_config;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::setQQConfig()
     */
    public function setQQConfig($instance_id, $appkey, $appsecret, $url, $call_back_url, $is_use)
    {
        Cache::set("qq_config" . $instance_id, '');
        $info = array(
            'APP_KEY' => trim($appkey),
            'APP_SECRET' => trim($appsecret),
            'AUTHORIZE' => $url,
            'CALLBACK' => $call_back_url
        );
        $value = json_encode($info);
        $count = $this->config_module->where([
            'key' => 'QQLOGIN',
            'instance_id' => $instance_id
        ])->count();
        if ($count > 0) {
            $data = array(
                'value' => $value,
                'is_use' => $is_use,
                'modify_time' => time()
            );
            $res = $this->config_module->where([
                'key' => 'QQLOGIN',
                'instance_id' => $instance_id
            ])->update($data);
            if ($res == 1) {
                return SUCCESS;
            } else {
                return UPDATA_FAIL;
            }
        } else {
            $data = array(
                'instance_id' => $instance_id,
                'key' => 'QQLOGIN',
                'value' => $value,
                'is_use' => $is_use,
                'create_time' => time()
            );
            $res = $this->config_module->save($data);
            return $res;
        }
    }

    public function getLoginConfig()
    {
        $instance_id = 0;
        $wchat_config = $this->getWchatConfig($instance_id);
        $qq_config = $this->getQQConfig($instance_id);
        
        $mobile_config = $this->getMobileMessage($instance_id);
        $email_config = $this->getEmailMessage($instance_id);
        $data = array(
            'wchat_login_config' => $wchat_config,
            'qq_login_config' => $qq_config,
            'mobile_config' => $mobile_config,
            'email_config' => $email_config
        );
        return $data;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::getWpayConfig()
     */
    public function getWpayConfig($instance_id)
    {
        $cache = Cache::get("getWpayConfig" . $instance_id);
        if (empty($cache)) {
            $info = $this->config_module->getInfo([
                'instance_id' => $instance_id,
                'key' => 'WPAY'
            ], 'value,is_use');
            if (empty($info['value'])) {
                $data = array(
                    'value' => array(
                        'appid' => '',
                        'appkey' => '',
                        'mch_id' => '',
                        'mch_key' => ''
                    ),
                    'is_use' => 0
                );
            } else {
                $info['value'] = json_decode($info['value'], true);
                $data = $info;
            }
            Cache::set("getWpayConfig" . $instance_id, $data);
            return $data;
        } else {
            return $cache;
        }
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::setWpayConfig()
     */
    public function setWpayConfig($instanceid, $appid, $appkey, $mch_id, $mch_key, $is_use)
    {
        Cache::set("getWpayConfig" . $instanceid, null);
        $data = array(
            'appid' => $appid,
            'appkey' => $appkey,
            'mch_id' => $mch_id,
            'mch_key' => $mch_key
        );
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'WPAY',
            'instance_id' => $instanceid
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $instanceid,
                'key' => 'WPAY',
                'value' => $value,
                'is_use' => $is_use,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'key' => 'WPAY',
                'value' => $value,
                'is_use' => $is_use,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $instanceid,
                'key' => 'WPAY'
            ]);
        }
        return $res;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::getAlipayConfig()
     */
    public function getAlipayConfig($instance_id)
    {
        $cache = Cache::get("getAlipayConfig" . $instance_id);
        if (empty($cache)) {
            $info = $this->config_module->getInfo([
                'instance_id' => $instance_id,
                'key' => 'ALIPAY'
            ], 'value,is_use');
            if (empty($info['value'])) {
                $data = array(
                    'value' => array(
                        'ali_partnerid' => '',
                        'ali_seller' => '',
                        'ali_key' => ''
                    ),
                    'is_use' => 0
                );
            } else {
                $info['value'] = json_decode($info['value'], true);
                $data = $info;
            }
            Cache::set("getAlipayConfig" . $instance_id, $data);
            return $data;
        } else {
            return $cache;
        }
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::getAlipayConfig()
     */
    public function getAlipayConfigNew($instance_id)
    {
    	$cache = Cache::get("getAlipayConfigNew" . $instance_id);
    	if (empty($cache)) {
    		$info = $this->config_module->getInfo([
    				'instance_id' => $instance_id,
    				'key' => 'ALIPAY_NEW'
    		], 'value,is_use');
    		if (empty($info['value'])) {
    			$data = array(
    					'value' => array(
    							'appid' => '',
    							'private_key' => '',
    							'public_key' => ''
    					),
    					'is_use' => 0
    			);
    		} else {
    			$info['value'] = json_decode($info['value'], true);
    			$data = $info;
    		}
    		Cache::set("getAlipayConfigNew" . $instance_id, $data);
    		return $data;
    	} else {
    		return $cache;
    	}
    }
    
    /**
     * 获取银联支付配置信息
     */
    public function getUnionpayConfig($instance_id)
    {
        $cache = Cache::get("getUnionpayConfig" . $instance_id);
        if (empty($cache)) {
            $info = $this->config_module->getInfo([
                'instance_id' => $instance_id,
                'key' => 'UNIONPAY'
            ], 'value,is_use');
            if (empty($info['value'])) {
                $data = array(
                    'value' => array(
                        'merchant_number' => '',
                        'sign_cert_pwd' => '',
                        'certs_path' => '',
                        'log_path' => '',
                        'service_charge' => ''
                    ),
                    'is_use' => 0
                );
            } else {
                $info['value'] = json_decode($info['value'], true);
                $data = $info;
            }
            Cache::set("getUnionpayConfig" . $instance_id, $data);
            
            return $data;
        } else {
            
            return $cache;
        }
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::setAlipayConfig()
     */
    public function setAlipayConfig($instanceid, $partnerid, $seller, $ali_key, $is_use = 1)
    {
        Cache::set("getAlipayConfig" . $instanceid, null);
        
        $data = array(
            'ali_partnerid' => $partnerid,
            'ali_seller' => $seller,
            'ali_key' => $ali_key
        );
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'ALIPAY',
            'instance_id' => $instanceid
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $instanceid,
                'key' => 'ALIPAY',
                'value' => $value,
                'is_use' => $is_use,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'key' => 'ALIPAY',
                'value' => $value,
                'is_use' => $is_use,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $instanceid,
                'key' => 'ALIPAY'
            ]);
        }
        return $res;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::setAlipayConfig()
     */
    public function setAlipayConfigNew($instanceid, $appid, $private_key, $public_key, $alipay_public_key)
    {
    	Cache::set("getAlipayConfigNew" . $instanceid, []);
    	$qian=array(" ","　","\t","\n","\r");
    	$private_key = str_replace($qian, '', $private_key);
    	$public_key = str_replace($qian, '', $public_key);
    	$alipay_public_key = str_replace($qian, '', $alipay_public_key);
    	$data = array(
    			'appid' => $appid,
    			'private_key' => $private_key,
    			'public_key' => $public_key,
    			'alipay_public_key' => $alipay_public_key
    	);
    	$is_use = 1;
    	$value = json_encode($data);
    	$info = $this->config_module->getInfo([
    			'key' => 'ALIPAY_NEW',
    			'instance_id' => $instanceid
    	], 'value');
    	if (empty($info)) {
    		$config_module = new ConfigModel();
    		$data = array(
    				'instance_id' => $instanceid,
    				'key' => 'ALIPAY_NEW',
    				'value' => $value,
    				'is_use' => $is_use,
    				'create_time' => time()
    		);
    		$res = $config_module->save($data);
    	} else {
    		
    		$config_module = new ConfigModel();
    		$data = array(
    				'key' => 'ALIPAY_NEW',
    				'value' => $value,
    				'is_use' => $is_use,
    				'modify_time' => time()
    		);
    		$res = $config_module->save($data, [
    				'instance_id' => $instanceid,
    				'key' => 'ALIPAY_NEW'
    		]);
    	}
    	return $res;
    }
    
    /**
     * 银联卡支付配置保存
     *
     * @param unknown $unionPayConfig            
     * @param unknown $certificate_key            
     * @param unknown $service_charge            
     * @param unknown $is_use            
     */
    public function setUnionpayConfig($instanceid, $merchant_number, $sign_cert_pwd, $certs_path, $log_path, $service_charge, $is_use)
    {
        Cache::set("getUnionpayConfig" . $instanceid, null);
        
        $data = array(
            'merchant_number' => $merchant_number,
            'sign_cert_pwd' => $sign_cert_pwd,
            'certs_path' => $certs_path,
            'log_path' => $log_path,
            'service_charge' => $service_charge
        );
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'UNIONPAY',
            'instance_id' => $instanceid
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $instanceid,
                'key' => 'UNIONPAY',
                'value' => $value,
                'is_use' => $is_use,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'key' => 'UNIONPAY',
                'value' => $value,
                'is_use' => $is_use,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $instanceid,
                'key' => 'UNIONPAY'
            ]);
        }
        return $res;
    }

    /**
     * 设置微信和支付宝开关状态
     */
    public function setWpayStatusConfig($instanceid, $is_use, $type)
    {
        Cache::set("getAlipayConfig" . $instanceid, null);
        Cache::set("getWpayConfig" . $instanceid, null);
        Cache::set("getUnionpayConfig" . $instanceid, null);
        $config_module = new ConfigModel();
        $result = $config_module->getInfo([
            'instance_id' => $instanceid,
            'key' => $type
        ], 'value');
        if($type == "ALIPAY_STATUS"){
        	$info = array();
        	$info['is_use'] = $is_use;
        	$value = json_encode($info);
        	$data = array(
        			'value' => $value,
        			'modify_time' => time()
        	);
        }else{
        	if (empty($result['value'])) {
        		$info = array();
        		$info['is_use'] = $is_use;
        		$value = json_encode($info['is_use']);
        		$data = array(
        				'is_use' => $value,
        				'modify_time' => time()
        		);
        	} else {
        		$data = array(
        				'is_use' => $is_use,
        				'modify_time' => time()
        		);
        	}
        }
        
        $res = $config_module->save($data, [
            'instance_id' => $instanceid,
            'key' => $type
        ]);
        return $res;
    }
    
    /*
     * (non-PHPdoc)
     * @see \ata\api\IConfig::getHotsearchConfig()
     */
    public function getHotsearchConfig($instanceid)
    {
        $cache = Cache::get("getHotsearchConfig" . $instanceid);
        if (empty($cache)) {
            $info = $this->config_module->getInfo([
                'key' => 'HOTKEY',
                'instance_id' => $instanceid
            ], 'value');
            if (empty($info['value'])) {
                return null;
            } else {
                $data = json_decode($info['value'], true);
                Cache::set("getHotsearchConfig" . $instanceid, $data);
                return $data;
            }
        } else {
            return $cache;
        }
    }
    
    /*
     * (non-PHPdoc)
     * @see \ata\api\IConfig::setHotsearchConfig()
     */
    public function setHotsearchConfig($instanceid, $keywords, $is_use)
    {
        Cache::set("getHotsearchConfig" . $instanceid, null);
        $data = $keywords;
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'HOTKEY',
            'instance_id' => $instanceid
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $instanceid,
                'key' => 'HOTKEY',
                'value' => $value,
                'is_use' => $is_use,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'value' => $value,
                'is_use' => $is_use,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $instanceid,
                'key' => 'HOTKEY'
            ]);
        }
        return $res;
        // TODO Auto-generated method stub
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \ata\api\IConfig::getDefaultSearchConfig()
     */
    public function getDefaultSearchConfig($instanceid)
    {
        $cache = Cache::get("getDefaultSearchConfig" . $instanceid);
        if (empty($cache)) {
            $info = $this->config_module->getInfo([
                'key' => 'DEFAULTKEY',
                'instance_id' => $instanceid
            ], 'value');
            if (empty($info['value'])) {
                return null;
            } else {
                
                $data = json_decode($info['value'], true);
                Cache::set("getDefaultSearchConfig" . $instanceid, $data);
                return $data;
            }
        } else {
            return $cache;
        }
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \ata\api\IConfig::setDefaultSearchConfig()
     */
    public function setDefaultSearchConfig($instanceid, $keywords, $is_use)
    {
        Cache::set("getDefaultSearchConfig" . $instanceid, null);
        $data = $keywords;
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'DEFAULTKEY',
            'instance_id' => $instanceid
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $instanceid,
                'key' => 'DEFAULTKEY',
                'value' => $value,
                'is_use' => $is_use,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'value' => $value,
                'is_use' => $is_use,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $instanceid,
                'key' => 'DEFAULTKEY'
            ]);
        }
        return $res;
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \ata\api\IConfig::getUserNotice()
     */
    public function getUserNotice($instanceid)
    {
        $cache = Cache::get("config_getUserNotice" . $instanceid);
        if (empty($cache)) {
            $info = $this->config_module->getInfo([
                'key' => 'USERNOTICE',
                'instance_id' => $instanceid
            ], 'value');
            if (empty($info['value'])) {
                return null;
            } else {
                $data = json_decode($info['value'], true);
                Cache::set("config_getUserNotice" . $instanceid, $data);
            }
        } else {
            return $cache;
        }
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \ata\api\IConfig::setUserNotice()
     */
    public function setUserNotice($instanceid, $keywords, $is_use)
    {
        Cache::set("config_getUserNotice" . $instanceid, null);
        $data = $keywords;
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'USERNOTICE',
            'instance_id' => $instanceid
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $instanceid,
                'key' => 'USERNOTICE',
                'value' => $value,
                'is_use' => $is_use,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'value' => $value,
                'is_use' => $is_use,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $instanceid,
                'key' => 'USERNOTICE'
            ]);
        }
        return $res;
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \ata\api\IConfig::getEmailMessage()
     */
    public function getEmailMessage($instanceid)
    {
        $cache = Cache::get("getEmailMessage" . $instanceid);
        if (empty($cache)) {
            $info = $this->config_module->getInfo([
                'key' => 'EMAILMESSAGE',
                'instance_id' => $instanceid
            ], 'value, is_use');
            if (empty($info['value'])) {
                $data = array(
                    'value' => array(
                        'email_host' => '',
                        'email_port' => '',
                        'email_addr' => '',
                        'email_pass' => '',
                        'email_id' => '',
                        'email_is_security' => false
                    ),
                    'is_use' => 0
                );
            } else {
                $info['value'] = json_decode($info['value'], true);
                $data = $info;
            }
            Cache::set("getEmailMessage" . $instanceid, $data);
            return $data;
        } else {
            return $cache;
        }
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \ata\api\IConfig::setEmailMessage()
     */
    public function setEmailMessage($instanceid, $email_host, $email_port, $email_addr, $email_id, $email_pass, $is_use, $email_is_security)
    {
        Cache::set("getEmailMessage" . $instanceid, null);
        
        $data = array(
            'email_host' => trim($email_host),
            'email_port' => trim($email_port),
            'email_addr' => trim($email_addr),
            'email_id' => trim($email_id),
            'email_pass' => trim($email_pass),
            'email_is_security' => $email_is_security
        );
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'EMAILMESSAGE',
            'instance_id' => $instanceid
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $instanceid,
                'key' => 'EMAILMESSAGE',
                'value' => $value,
                'is_use' => $is_use,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'key' => 'EMAILMESSAGE',
                'value' => $value,
                'is_use' => $is_use,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $instanceid,
                'key' => 'EMAILMESSAGE'
            ]);
        }
        return $res;
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \ata\api\IConfig::getMobileMessage()
     */
    public function getMobileMessage($instanceid)
    {
        $cache = Cache::get("getMobileMessage" . $instanceid);
        if (empty($cache)) {
            $info = $this->config_module->getInfo([
                'key' => 'MOBILEMESSAGE',
                'instance_id' => $instanceid
            ], 'value, is_use');
            if (empty($info['value'])) {
                $data = array(
                    'value' => array(
                        'appKey' => '',
                        'secretKey' => '',
                        'freeSignName' => ''
                    ),
                    'is_use' => $info["is_use"]
                );
            } else {
                $info['value'] = json_decode($info['value'], true);
                $data = $info;
            }
            Cache::set("getMobileMessage" . $instanceid, $data);
            return $data;
        } else {
            return $cache;
        }
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \ata\api\IConfig::setMobileMessage()
     */
    public function setMobileMessage($instanceid, $app_key, $secret_key, $free_sign_name, $is_use, $user_type)
    {
        Cache::set("getMobileMessage" . $instanceid, null);
        $data = array(
            'appKey' => trim($app_key),
            'secretKey' => trim($secret_key),
            'freeSignName' => trim($free_sign_name),
            'user_type' => $user_type
        );
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'MOBILEMESSAGE',
            'instance_id' => $instanceid
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $instanceid,
                'key' => 'MOBILEMESSAGE',
                'value' => $value,
                'is_use' => $is_use,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'key' => 'MOBILEMESSAGE',
                'value' => $value,
                'is_use' => $is_use,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $instanceid,
                'key' => 'MOBILEMESSAGE'
            ]);
        }
        return $res;
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \ata\api\IConfig::getWinxinOpenPlatformConfig()
     */
    public function getWinxinOpenPlatformConfig($instanceid)
    {
        $info = $this->config_module->getInfo([
            'key' => 'WXOPENPLATFORM',
            'instance_id' => $instanceid
        ], 'value, is_use');
        if (empty($info['value'])) {
            return array(
                'value' => array(
                    'appId' => '',
                    'appsecret' => '',
                    'encodingAesKey' => '',
                    'tk' => ''
                ),
                'is_use' => 1
            );
        } else {
            $info['value'] = json_decode($info['value'], true);
            return $info;
        }
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \ata\api\IConfig::setWinxinOpenPlatformConfig()
     */
    public function setWinxinOpenPlatformConfig($instanceid, $appid, $appsecret, $encodingAesKey, $tk)
    {
        $data = array(
            'appId' => $appid,
            'appsecret' => $appsecret,
            'encodingAesKey' => $encodingAesKey,
            'tk' => $tk
        );
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'WXOPENPLATFORM',
            'instance_id' => $instanceid
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $instanceid,
                'key' => 'WXOPENPLATFORM',
                'value' => $value,
                'is_use' => 1,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'key' => 'WXOPENPLATFORM',
                'value' => $value,
                'is_use' => 1,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $instanceid,
                'key' => 'WXOPENPLATFORM'
            ]);
        }
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::getLoginVerifyCodeConfig()
     */
    public function getLoginVerifyCodeConfig($instanceid)
    {
        $verify_config = Cache::get("LoginVerifyCodeConfig" . $instanceid);
        if (empty($verify_config)) {
            $info = $this->config_module->getInfo([
                'key' => 'LOGINVERIFYCODE',
                'instance_id' => $instanceid
            ], 'value, is_use');
            if (empty($info['value'])) {
                $verify_config = array(
                    'value' => array(
                        'platform' => 0,
                        'admin' => 0,
                        'pc' => 0,
                        'error_num' => 0
                    ),
                    'is_use' => 1
                );
                Cache::set("LoginVerifyCodeConfig" . $instanceid, $verify_config);
            } else {
                $info['value'] = json_decode($info['value'], true);
                $verify_config = $info;
                Cache::set("LoginVerifyCodeConfig" . $instanceid, $verify_config);
            }
        }
        return $verify_config;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::setLoginVerifyCodeConfig()
     */
    public function setLoginVerifyCodeConfig($instanceid, $platform, $admin, $pc, $error_num)
    {
        $data = array(
            'platform' => $platform,
            'admin' => $admin,
            'pc' => $pc,
            'error_num' => $error_num
        );
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'LOGINVERIFYCODE',
            'instance_id' => $instanceid
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $instanceid,
                'key' => 'LOGINVERIFYCODE',
                'value' => $value,
                'is_use' => 1,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'key' => 'LOGINVERIFYCODE',
                'value' => $value,
                'is_use' => 1,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $instanceid,
                'key' => 'LOGINVERIFYCODE'
            ]);
        }
        Cache::set("LoginVerifyCodeConfig" . $instanceid, '');
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::setInstanceWchatConfig()
     */
    public function setInstanceWchatConfig($instance_id, $appid, $appsecret, $token)
    {
        $author_appid = 'instanceid_' . $instance_id;
        cache::set('token-' . $author_appid, null);
        $data = array(
            'appid' => trim($appid),
            'appsecret' => trim($appsecret),
            'token' => trim($token)
        );
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'SHOPWCHAT',
            'instance_id' => $instance_id
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $instance_id,
                'key' => 'SHOPWCHAT',
                'value' => $value,
                'is_use' => 1,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'key' => 'SHOPWCHAT',
                'value' => $value,
                'is_use' => 1,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $instance_id,
                'key' => 'SHOPWCHAT'
            ]);
        }
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::getInstanceWchatConfig()
     */
    public function getInstanceWchatConfig($instance_id)
    {
        $info = $this->config_module->getInfo([
            'key' => 'SHOPWCHAT',
            'instance_id' => $instance_id
        ], 'value');
        if (empty($info['value'])) {
            return array(
                'value' => array(
                    'appid' => '',
                    'appsecret' => '',
                    'token' => 'TOKEN'
                ),
                'is_use' => 1
            );
        } else {
            $info['value'] = json_decode($info['value'], true);
            return $info;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::setInstanceWchatConfig()
     */
    public function setInstanceAppletConfig($instance_id, $appid, $appsecret)
    {
        $data = array(
            'appid' => trim($appid),
            'appsecret' => trim($appsecret)
        );
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'SHOPAPPLET',
            'instance_id' => $instance_id
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $instance_id,
                'key' => 'SHOPAPPLET',
                'value' => $value,
                'is_use' => 1,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'key' => 'SHOPAPPLET',
                'value' => $value,
                'is_use' => 1,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $instance_id,
                'key' => 'SHOPAPPLET'
            ]);
        }
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::getInstanceAppletConfig()
     */
    public function getInstanceAppletConfig($instance_id)
    {
        $info = $this->config_module->getInfo([
            'key' => 'SHOPAPPLET',
            'instance_id' => $instance_id
        ], 'value');
        if (empty($info['value'])) {
            return array(
                'value' => array(
                    'appid' => '',
                    'appsecret' => ''
                ),
                'is_use' => 1
            );
        } else {
            $info['value'] = json_decode($info['value'], true);
            return $info;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::getOtherPayTypeConfig()
     */
    public function getOtherPayTypeConfig()
    {
        $info = $this->config_module->getInfo([
            'key' => 'OTHER_PAY',
            'instance_id' => 0
        ], 'value');
        if (empty($info['value'])) {
            return array(
                'value' => array(
                    'is_coin_pay' => 0,
                    'is_balance_pay' => 0
                ),
                'is_use' => 1
            );
        } else {
            $info['value'] = json_decode($info['value'], true);
            return $info;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::setOtherPayTypeConfig()
     */
    public function setOtherPayTypeConfig($is_coin_pay, $is_balance_pay)
    {
        $data = array(
            'is_coin_pay' => $is_coin_pay,
            'is_balance_pay' => $is_balance_pay
        );
        $value = json_encode($data);
        $info = $this->config_module->getInfo([
            'key' => 'OTHER_PAY',
            'instance_id' => 0
        ], 'value');
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => 0,
                'key' => 'OTHER_PAY',
                'value' => $value,
                'is_use' => 1,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'key' => 'OTHER_PAY',
                'value' => $value,
                'is_use' => 1,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => 0,
                'key' => 'OTHER_PAY'
            ]);
        }
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::setNotice()
     */
    public function setNotice($shopid, $notice_message, $is_enable)
    {
        Cache::set("config_setNotice" . $shopid, null);
        $notice = new NoticeModel();
        $data = array(
            'notice_message' => $notice_message,
            'is_enable' => $is_enable
        );
        $res = $notice->save($data, [
            'shopid' => $shopid
        ]);
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::getNotice()
     */
    public function getNotice($shopid)
    {
        $cache = Cache::get("config_getNotice" . $shopid);
        if (empty($cache)) {
            $notice = new NoticeModel();
            $notice_info = $notice->getInfo([
                'shopid' => $shopid
            ]);
            if (empty($notice_info)) {
                $data = array(
                    'shopid' => $shopid,
                    'notice_message' => '',
                    'is_enable' => 0
                );
                $notice->save($data);
                $notice_info = $notice->getInfo([
                    'shopid' => $shopid
                ]);
            }
            Cache::set("config_setNotice" . $shopid, $notice_info);
            return $notice_info;
        } else {
            return $cache;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::getConfig()
     */
    public function getConfig($instance_id, $key)
    {
        $config = new ConfigModel();
        $array = array();
        $info = $config->getInfo([
            'instance_id' => $instance_id,
            'key' => $key
        ]);
        return $info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::setConfig()
     */
    public function setConfig($params)
    {
        $config = new ConfigModel();
        foreach ($params as $key => $value) {
            if ($this->checkConfigKeyIsset($value['instance_id'], $value['key'])) {
                $res = $this->updateConfig($value['instance_id'], $value['key'], $value['value'], $value['desc'], $value['is_use']);
            } else {
                $res = $this->addConfig($value['instance_id'], $value['key'], $value['value'], $value['desc'], $value['is_use']);
            }
        }
        return $res;
    }

    /**
     * 添加设置
     *
     * @param unknown $instance_id            
     * @param unknown $key            
     * @param unknown $value            
     * @param unknown $desc            
     * @param unknown $is_use            
     */
    protected function addConfig($instance_id, $key, $value, $desc, $is_use)
    {
        $config = new ConfigModel();
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $data = array(
            'instance_id' => $instance_id,
            'key' => $key,
            'value' => $value,
            'desc' => $desc,
            'is_use' => $is_use,
            'create_time' => time()
        );
        $res = $config->save($data);
        return $res;
    }

    /**
     * 修改配置
     *
     * @param unknown $instance_id            
     * @param unknown $key            
     * @param unknown $value            
     * @param unknown $desc            
     * @param unknown $is_use            
     */
    protected function updateConfig($instance_id, $key, $value, $desc, $is_use)
    {
        $config = new ConfigModel();
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $data = array(
            'value' => $value,
            'desc' => $desc,
            'is_use' => $is_use,
            'modify_time' => time()
        );
        $res = $config->save($data, [
            'instance_id' => $instance_id,
            'key' => $key
        ]);
        return $res;
    }

    /**
     * 判断当前设置是否存在
     * 存在返回 true 不存在返回 false
     *
     * @param unknown $instance_id            
     * @param unknown $key            
     */
    public function checkConfigKeyIsset($instance_id, $key)
    {
        $config = new ConfigModel();
        $num = $config->where([
            'instance_id' => $instance_id,
            'key' => $key
        ])->count();
        return $num > 0 ? true : false;
    }

    /**
     *
     * 得到店铺的系统通知的详情
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::getNoticeTemplateDetail()
     */
    public function getNoticeTemplateDetail($shop_id, $template_type, $notify_type)
    {
        $notice_template_model = new NoticeTemplateModel();
        $condition = array(
            "template_type" => $template_type,
            "instance_id" => $shop_id,
            "notify_type" => $notify_type
        );
        $template_list = $notice_template_model->getQuery($condition, "*", "");
        return $template_list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::getNoticeTemplateOneDetail()
     */
    public function getNoticeTemplateOneDetail($shop_id, $template_type, $template_code)
    {
        $notice_template_model = new NoticeTemplateModel();
        $info = $notice_template_model->getInfo([
            'instance_id' => $shop_id,
            'template_type' => $template_type,
            'template_code' => $template_code
        ]);
        return $info;
    }

    /**
     * 更新通知模板的信息
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::updateNoticeTemplate()
     */
    public function updateNoticeTemplate($shop_id, $template_type, $template_array, $notify_type)
    {
        $template_data = json_decode($template_array, true);
        foreach ($template_data as $template_obj) {
            $template_code = $template_obj["template_code"];
            $template_title = $template_obj["template_title"];
            $template_content = $template_obj["template_content"];
            $sign_name = $template_obj["sign_name"];
            $is_enable = $template_obj["is_enable"];
            $notification_mode = $template_obj["notification_mode"];
            $notice_template_model = new NoticeTemplateModel();
            $t_count = $notice_template_model->getCount([
                "instance_id" => $shop_id,
                "template_type" => $template_type,
                "template_code" => $template_code,
                "notify_type" => $notify_type
            ]);
            
            if ($t_count > 0) {
                // 更新
                $data = array(
                    "template_title" => $template_title,
                    "template_content" => $template_content,
                    "sign_name" => $sign_name,
                    "is_enable" => $is_enable,
                    "modify_time" => time(),
                    "notification_mode" => $notification_mode
                );
                $res = $notice_template_model->save($data, [
                    "instance_id" => $shop_id,
                    "template_type" => $template_type,
                    "template_code" => $template_code,
                    "notify_type" => $notify_type
                ]);
            } else {
                // 添加
                $data = array(
                    "instance_id" => $shop_id,
                    "template_type" => $template_type,
                    "template_code" => $template_code,
                    "template_title" => $template_title,
                    "template_content" => $template_content,
                    "sign_name" => $sign_name,
                    "is_enable" => $is_enable,
                    "modify_time" => time(),
                    "notify_type" => $notify_type,
                    "notification_mode" => $notification_mode
                );
                $res = $notice_template_model->save($data);
            }
        }
        return $res;
    }

    /**
     * 得到店铺的系统通知项
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::getNoticeConfig()
     */
    public function getNoticeConfig($shop_id)
    {
        $config_model = new ConfigModel();
        $condition = array(
            'instance_id' => $shop_id,
            'key' => array(
                'in',
                'EMAILMESSAGE,MOBILEMESSAGE'
            )
        );
        $notify_list = $config_model->getQuery($condition, "*", "");
        if (! empty($notify_list)) {
            for ($i = 0; $i < count($notify_list); $i ++) {
                if ($notify_list[$i]["key"] == "EMAILMESSAGE") {
                    $notify_list[$i]["notify_name"] = "邮件通知";
                } else 
                    if ($notify_list[$i]["key"] == "MOBILEMESSAGE") {
                        $notify_list[$i]["notify_name"] = "短信通知";
                    }
            }
            return $notify_list;
        } else {
            return null;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::getNoticeConfig()
     */
    public function getMobileConfig($shop_id)
    {
        $config_model = new ConfigModel();
        $condition = array(
            'instance_id' => $shop_id,
            'key' => 'MOBILEMESSAGE'
        );
        $notify_list = $config_model->getQuery($condition, "*", "");
        if (! empty($notify_list)) {
            if ($notify_list["key"] == "MOBILEMESSAGE") {
                $notify_list["notify_name"] = "短信通知";
            }
            
            return $notify_list;
        } else {
            return null;
        }
    }

    /**
     * 得到店铺的email的配置信息
     *
     * @param unknown $shop_id            
     */
    public function getNoticeEmailConfig($shop_id)
    {
        $config_model = new ConfigModel();
        $condition = array(
            'instance_id' => $shop_id,
            'key' => 'EMAILMESSAGE'
        );
        $email_detail = $config_model->getQuery($condition, "*", "");
        return $email_detail;
    }

    /**
     * 得到店铺的短信配置信息
     *
     * @param unknown $shop_id            
     */
    public function getNoticeMobileConfig($shop_id)
    {
        $config_model = new ConfigModel();
        $condition = array(
            'instance_id' => $shop_id,
            'key' => 'MOBILEMESSAGE'
        );
        $mobile_detail = $config_model->getQuery($condition, "*", "");
        return $mobile_detail;
    }

    /**
     * 得到店铺的邮件发送项
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::getNoticeSendItem()
     */
    public function getNoticeTemplateItem($template_code)
    {
        $notice_model = new NoticeTemplateItemModel();
        $item_list = $notice_model->where("FIND_IN_SET('" . $template_code . "', type_ids)")->select();
        return $item_list;
    }

    /**
     * 得到店铺模板的集合
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::getNoticeTemplateType()
     */
    public function getNoticeTemplateType($template_type, $notify_type)
    {
        $notice_type_model = new NoticeTemplateTypeModel();
        $type_list = $notice_type_model->where("template_type='" . $template_type . "' or template_type='all' and notify_type = '" . $notify_type . "'")->select();
        return $type_list;
    }

    /**
     * 支付的通知项
     *
     * @param unknown $shop_id            
     * @return string|NULL
     */
    public function getPayConfig($shop_id)
    {
    	
        $config_model = new ConfigModel();
        $alipay_status = $config_model->getInfo(['key'=>'ALIPAY_STATUS']);
        if(empty($alipay_status)){
        	$data = [
        		'is_use' => 0,
        	];
        	$data = json_encode($data);
        	$this->setAlipayStatus($this->instance_id, '', $data);
        }
        $condition = array(
            'instance_id' => $shop_id,
            'key' => array(
                'in',
                'WPAY,ALIPAY_STATUS,UNIONPAY'
            )
        );
        $notify_list = $config_model->getQuery($condition, "*", "");
        if (! empty($notify_list)) {
            for ($i = 0; $i < count($notify_list); $i ++) {
                if ($notify_list[$i]["key"] == "WPAY") {
                    $notify_list[$i]["logo"] = "public/admin/images/wchat.png";
                    $notify_list[$i]["pay_name"] = "微信支付";
                    $notify_list[$i]["desc"] = "该系统支持微信网页支付和扫码支付";
                } else 
                    if ($notify_list[$i]["key"] == "ALIPAY_STATUS") {
                    	$is_use = json_decode($notify_list[$i]['value'],true);
                    	$notify_list[$i]['is_use'] = $is_use['is_use'];
                        $notify_list[$i]["pay_name"] = "支付宝支付";
                        $notify_list[$i]["logo"] = "public/admin/images/pay.png";
                        $notify_list[$i]["desc"] = "该系统支持即时到账接口";
                    } else 
                        if ($notify_list[$i]["key"] == "UNIONPAY") {
                            $notify_list[$i]["pay_name"] = "银联卡支付";
                            $notify_list[$i]["logo"] = "public/admin/images/unionpay.png";
                            $notify_list[$i]["desc"] = "该系统支持即时到账接口";
                        }
            }
            return $notify_list;
        } else {
            return null;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::getBalanceWithdrawConfig()
     */
    public function getBalanceWithdrawConfig($shop_id)
    {
        $key = 'WITHDRAW_BALANCE';
        $info = $this->getConfig($shop_id, $key);
        if (empty($info)) {
            $params[0] = array(
                'instance_id' => $shop_id,
                'key' => $key,
                'value' => array(
                    'withdraw_cash_min' => 0.00,
                    'withdraw_multiple' => 0,
                    'withdraw_poundage' => 0,
                    'withdraw_message' => '',
                    'withdraw_account' => array(
                        array(
                            'id' => 'bank_card',
                            'name' => '银行卡',
                            'value' => 1,
                            'is_checked' => 1
                        ),
                        array(
                            'id' => 'wechat',
                            'name' => '微信',
                            'value' => 2,
                            'is_checked' => 0
                        ),
                        array(
                            'id' => 'alipay',
                            'name' => '支付宝',
                            'value' => 3,
                            'is_checked' => 0
                        )
                    )
                ),
                'desc' => '会员余额提现设置',
                'is_use' => 0
            );
            $this->setConfig($params);
            $info = $this->getConfig($shop_id, $key);
        }
        
        if (empty($info['value'])) {
            $info['id'] = '';
            $info['value']['withdraw_cash_min'] = '';
            $info['value']['withdraw_multiple'] = '';
            $info['value']['withdraw_poundage'] = '';
            $info['value']['withdraw_message'] = '';
            $info['value']['withdraw_account'] = array(
                array(
                    'id' => 'bank_card',
                    'name' => '银行卡',
                    'value' => 1,
                    'is_checked' => 1
                ),
                array(
                    'id' => 'wechat',
                    'name' => '微信',
                    'value' => 2,
                    'is_checked' => 0
                ),
                array(
                    'id' => 'alipay',
                    'name' => '支付宝',
                    'value' => 3,
                    'is_checked' => 0
                )
            );
        } else {
            $info['value'] = json_decode($info['value'], true);
        }
        return $info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::setBalanceWithdrawConfig()
     */
    public function setBalanceWithdrawConfig($shop_id, $key, $value, $is_use)
    {
        $params[0] = array(
            'instance_id' => $shop_id,
            'key' => $key,
            'value' => array(
                'withdraw_cash_min' => $value['withdraw_cash_min'],
                'withdraw_multiple' => $value['withdraw_multiple'],
                'withdraw_poundage' => $value['withdraw_poundage'],
                'withdraw_message' => $value['withdraw_message'],
                'withdraw_account' => $value['withdraw_account']
            ),
            'desc' => '会员余额提现设置',
            'is_use' => $is_use
        );
        $res = $this->setConfig($params);
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::getcustomserviceConfig()
     */
    public function getcustomserviceConfig($shop_id)
    {
        $cache = cache("customserviceConfig");
        if (empty($cache)) {
            $key = 'SERVICE_ADDR';
            $info = $this->getConfig($shop_id, $key);
            $info['value'] = json_decode($info['value'], true);
            // var_dump($info['value']['checked_num']);
            if (empty($info)) {
                $params[0] = array(
                    'instance_id' => $shop_id,
                    'key' => $key,
                    'value' => array(
                        'service_addr' => ''
                    ),
                    'desc' => '客服链接地址设置'
                );
                $this->setConfig($params);
                $info = $this->getConfig($shop_id, $key);
            } else 
                if ($info['value']['checked_num'] == 1) {
                    $info['value']['service_addr'] = $info['value']['meiqia_service_addr'];
                } else 
                    if ($info['value']['checked_num'] == 2) {
                        $info['value']['service_addr'] = $info['value']['kf_service_addr'];
                    } else 
                        if ($info['value']['checked_num'] == 3) {
                            $info['value']['service_addr'] = 'http://wpa.qq.com/msgrd?v=3&uin=' . $info['value']['qq_service_addr'] . '&site=qq&menu=yes';
                        }
            
            cache("customserviceConfig", $info);
            return $info;
        } else {
            return $cache;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::getrecommendConfig()
     */
    public function getrecommendConfig($shop_id)
    {
        $key = 'IS_RECOMMEND';
        $info = $this->getConfig($shop_id, $key);
        if (empty($info)) {
            $params[0] = array(
                'instance_id' => $shop_id,
                'key' => $key,
                'value' => array(
                    'is_recommend' => ''
                ),
                'desc' => '首页商城促销版块显示设置'
            );
            $this->setConfig($params);
            $info = $this->getConfig($shop_id, $key);
        }
        $info['value'] = json_decode($info['value'], true);
        return $info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::setisrecommendConfig()
     */
    public function setisrecommendConfig($shop_id, $key, $value)
    {
        $params[0] = array(
            'instance_id' => $shop_id,
            'key' => $key,
            'value' => array(
                'is_recommend' => $value['is_recommend']
            ),
            'desc' => '首页商品促销版块是否显示设置',
            'is_use' => 1
        );
        $res = $this->setConfig($params);
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::setiscategoryConfig()
     */
    public function setiscategoryConfig($shop_id, $key, $value)
    {
        $params[0] = array(
            'instance_id' => $shop_id,
            'key' => $key,
            'value' => array(
                'is_category' => $value['is_category']
            ),
            'desc' => '首页商品分类是否显示设置',
            'is_use' => 1
        );
        $res = $this->setConfig($params);
        return $res;
    }

    public function getcategoryConfig($shop_id)
    {
        $key = 'IS_CATEGORY';
        $info = $this->getConfig($shop_id, $key);
        if (empty($info)) {
            $params[0] = array(
                'instance_id' => $shop_id,
                'key' => $key,
                'value' => array(
                    'is_category' => ''
                ),
                'desc' => '首页商品分类是否显示设置'
            );
            $this->setConfig($params);
            $info = $this->getConfig($shop_id, $key);
        }
        $info['value'] = json_decode($info['value'], true);
        return $info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::setcustomserviceConfig()
     */
    public function setcustomserviceConfig($shop_id, $key, $value)
    {
        $params[0] = array(
            'instance_id' => $shop_id,
            'key' => $key,
            'value' => array(
                'meiqia_service_addr' => trim($value['meiqia_service_addr']),
                'kf_service_addr' => trim($value['kf_service_addr']),
                'qq_service_addr' => trim($value['qq_service_addr']),
                'checked_num' => trim($value['checked_num'])
            ),
            'desc' => '客服链接地址'
        );
        cache("customserviceConfig", null);
        $res = $this->setConfig($params);
        return $res;
    }

    public function getSeoConfig($shop_id)
    {
        $seo_config = Cache::get("seo_config" . $shop_id);
        if (empty($seo_config)) {
            $seo_title = $this->getConfig($shop_id, 'SEO_TITLE');
            $seo_meta = $this->getConfig($shop_id, 'SEO_META');
            $seo_desc = $this->getConfig($shop_id, 'SEO_DESC');
            $seo_other = $this->getConfig($shop_id, 'SEO_OTHER');
            if (empty($seo_title) || empty($seo_meta) || empty($seo_desc) || empty($seo_other)) {
                $this->SetSeoConfig($shop_id, '', '', '', '');
                $array = array(
                    'seo_title' => '',
                    'seo_meta' => '',
                    'seo_desc' => '',
                    'seo_other' => ''
                );
            } else {
                $array = array(
                    'seo_title' => $seo_title['value'],
                    'seo_meta' => $seo_meta['value'],
                    'seo_desc' => $seo_desc['value'],
                    'seo_other' => $seo_other['value']
                );
            }
            Cache::set("seo_config" . $shop_id, $array);
            $seo_config = $array;
        }
        
        return $seo_config;
    }

    public function getCopyrightConfig($shop_id)
    {
        $copyright_logo = $this->getConfig($shop_id, 'COPYRIGHT_LOGO');
        $copyright_meta = $this->getConfig($shop_id, 'COPYRIGHT_META');
        $copyright_link = $this->getConfig($shop_id, 'COPYRIGHT_LINK');
        $copyright_desc = $this->getConfig($shop_id, 'COPYRIGHT_DESC');
        $copyright_companyname = $this->getConfig($shop_id, 'COPYRIGHT_COMPANYNAME');
        if (empty($copyright_logo) || empty($copyright_meta) || empty($copyright_link) || empty($copyright_desc) || empty($copyright_companyname)) {
            $this->SetCopyrightConfig($shop_id, '', '', '', '', '');
            $array = array(
                'copyright_logo' => '',
                'copyright_meta' => '',
                'copyright_link' => '',
                'copyright_desc' => '',
                'copyright_companyname' => ''
            );
        } else {
            $array = array(
                'copyright_logo' => $copyright_logo['value'],
                'copyright_meta' => $copyright_meta['value'],
                'copyright_link' => $copyright_link['value'],
                'copyright_desc' => $copyright_desc['value'],
                'copyright_companyname' => $copyright_companyname['value']
            );
        }
        return $array;
    }

    public function getShopConfig($shop_id)
    {
        $order_auto_delinery = $this->getConfig($shop_id, 'ORDER_AUTO_DELIVERY');
        $order_balance_pay = $this->getConfig($shop_id, 'ORDER_BALANCE_PAY');
        $order_delivery_complete_time = $this->getConfig($shop_id, 'ORDER_DELIVERY_COMPLETE_TIME');
        $order_show_buy_record = $this->getConfig($shop_id, 'ORDER_SHOW_BUY_RECORD');
        $order_invoice_tax = $this->getConfig($shop_id, 'ORDER_INVOICE_TAX');
        $order_invoice_content = $this->getConfig($shop_id, 'ORDER_INVOICE_CONTENT');
        $order_delivery_pay = $this->getConfig($shop_id, 'ORDER_DELIVERY_PAY');
        $order_buy_close_time = $this->getConfig($shop_id, 'ORDER_BUY_CLOSE_TIME');
        $buyer_self_lifting = $this->getConfig($shop_id, 'BUYER_SELF_LIFTING');
        
        $seller_dispatching = $this->getConfig($shop_id, 'ORDER_SELLER_DISPATCHING');
        $is_open_o2o = $this->getConfig($shop_id, 'IS_OPEN_O2O');
        $is_logistics = $this->getConfig($shop_id, 'ORDER_IS_LOGISTICS');
        $shopping_back_points = $this->getConfig($shop_id, 'SHOPPING_BACK_POINTS');
        $is_open_virtual_goods = $this->getConfig($shop_id, 'IS_OPEN_VIRTUAL_GOODS');
        $order_designated_delivery_time = $this->getConfig($shop_id, "IS_OPEN_ORDER_DESIGNATED_DELIVERY_TIME"); // 是否开启指定配送时间
        $time_slot = $this->getConfig($shop_id, "DISTRIBUTION_TIME_SLOT"); // 配送时间时间段
        $system_default_evaluate = $this->getConfig($shop_id, "SYSTEM_DEFAULT_EVALUATE");  
        $shouhou_day_number = $this->getConfig($shop_id, "SHOPHOU_DAY_NUMBER");
        
        if (empty($order_auto_delinery) && empty($order_balance_pay) && empty($order_delivery_complete_time) && empty($order_show_buy_record) && empty($order_invoice_tax) && empty($order_invoice_content) && empty($order_delivery_pay) && empty($order_buy_close_time) && empty($system_default_evaluate)) {
            $this->SetShopConfig($shop_id, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0, '');
            $array = array(
                'order_auto_delinery' => '',
                'order_balance_pay' => '',
                'order_delivery_complete_time' => '',
                'order_show_buy_record' => '',
                'order_invoice_tax' => '',
                'order_invoice_content' => '',
                'order_delivery_pay' => '',
                'order_buy_close_time' => '',
                'buyer_self_lifting' => '',
                'seller_dispatching' => '',
                'is_open_o2o' => '',
                'is_logistics' => '1',
                'is_open_virtual_goods' => 0,
                'shopping_back_points' => '',
                'order_designated_delivery_time' => 0,
                'time_slot' => '',
                'system_default_evaluate' => array(
                    'day' => 0,
                    'evaluate' => ''
                ),
            	'shouhou_day_number' => 0
            );
        } else {
            $array = array(
                'order_auto_delinery' => $order_auto_delinery['value'],
                'order_balance_pay' => $order_balance_pay['value'],
                'order_delivery_complete_time' => $order_delivery_complete_time['value'],
                'order_show_buy_record' => $order_show_buy_record['value'],
                'order_invoice_tax' => $order_invoice_tax['value'],
                'order_invoice_content' => $order_invoice_content['value'],
                'order_delivery_pay' => $order_delivery_pay['value'],
                'order_buy_close_time' => $order_buy_close_time['value'],
                'buyer_self_lifting' => $buyer_self_lifting['value'],
                'seller_dispatching' => $seller_dispatching['value'],
                'is_open_o2o' => $is_open_o2o['value'],
                'is_logistics' => $is_logistics['value'],
                'is_open_virtual_goods' => $is_open_virtual_goods['value'],
                'shopping_back_points' => $shopping_back_points['value'],
                'order_designated_delivery_time' => $order_designated_delivery_time['value'],
                'time_slot' => json_decode($time_slot['value'], true),
                'system_default_evaluate' => json_decode($system_default_evaluate['value'], true),
            	'shouhou_day_number' => $shouhou_day_number['value'],
            );
        }
        /* if ($array['order_buy_close_time'] == 0) {
            $array['order_buy_close_time'] = 60;
        } */
        
        return $array;
    }

    public function SetSeoConfig($shop_id, $seo_title, $seo_meta, $seo_desc, $seo_other)
    {
        $array[0] = array(
            'instance_id' => $shop_id,
            'key' => 'SEO_TITLE',
            'value' => $seo_title,
            'desc' => '标题附加字',
            'is_use' => 1
        );
        $array[1] = array(
            'instance_id' => $shop_id,
            'key' => 'SEO_META',
            'value' => $seo_meta,
            'desc' => '商城关键词',
            'is_use' => 1
        );
        $array[2] = array(
            'instance_id' => $shop_id,
            'key' => 'SEO_DESC',
            'value' => $seo_desc,
            'desc' => '关键词描述',
            'is_use' => 1
        );
        $array[3] = array(
            'instance_id' => $shop_id,
            'key' => 'SEO_OTHER',
            'value' => $seo_other,
            'desc' => '其他页头信息',
            'is_use' => 1
        );
        $res = $this->setConfig($array);
        Cache::set("seo_config" . $shop_id, '');
        return $res;
    }

    public function SetCopyrightConfig($shop_id, $copyright_logo, $copyright_meta, $copyright_link, $copyright_desc, $copyright_companyname)
    {
        $array[0] = array(
            'instance_id' => $shop_id,
            'key' => 'COPYRIGHT_LOGO',
            'value' => $copyright_logo,
            'desc' => '版权logo',
            'is_use' => 1
        );
        $array[1] = array(
            'instance_id' => $shop_id,
            'key' => 'COPYRIGHT_META',
            'value' => $copyright_meta,
            'desc' => '备案号',
            'is_use' => 1
        );
        $array[2] = array(
            'instance_id' => $shop_id,
            'key' => 'COPYRIGHT_LINK',
            'value' => $copyright_link,
            'desc' => '版权链接',
            'is_use' => 1
        );
        $array[3] = array(
            'instance_id' => $shop_id,
            'key' => 'COPYRIGHT_DESC',
            'value' => $copyright_desc,
            'desc' => '版权信息',
            'is_use' => 1
        );
        $array[4] = array(
            'instance_id' => $shop_id,
            'key' => 'COPYRIGHT_COMPANYNAME',
            'value' => $copyright_companyname,
            'desc' => '公司名称',
            'is_use' => 1
        );
        $res = $this->setConfig($array);
        return $res;
    }

    public function SetShopConfig($shop_id, $order_auto_delinery, $order_balance_pay, $order_delivery_complete_time, $order_show_buy_record, $order_invoice_tax, $order_invoice_content, $order_delivery_pay, $order_buy_close_time, $buyer_self_lifting, $seller_dispatching, $is_open_o2o, $is_logistics, $shopping_back_points, $is_open_virtual_goods, $order_designated_delivery_time, $time_slot, $evaluate_day, $evaluate, $shouhoudate)
    {
        $array[0] = array(
            'instance_id' => $this->instance_id,
            'key' => 'ORDER_AUTO_DELIVERY',
            'value' => $order_auto_delinery,
            'desc' => '订单多长时间自动完成',
            'is_use' => 1
        );
        $array[1] = array(
            'instance_id' => $this->instance_id,
            'key' => 'ORDER_BALANCE_PAY',
            'value' => $order_balance_pay,
            'desc' => '是否开启余额支付',
            'is_use' => 1
        );
        $array[2] = array(
            'instance_id' => $this->instance_id,
            'key' => 'ORDER_DELIVERY_COMPLETE_TIME',
            'value' => $order_delivery_complete_time,
            'desc' => '收货后多长时间自动完成',
            'is_use' => 1
        );
        $array[3] = array(
            'instance_id' => $this->instance_id,
            'key' => 'ORDER_SHOW_BUY_RECORD',
            'value' => $order_show_buy_record,
            'desc' => '是否显示购买记录',
            'is_use' => 1
        );
        $array[4] = array(
            'instance_id' => $this->instance_id,
            'key' => 'ORDER_INVOICE_TAX',
            'value' => $order_invoice_tax,
            'desc' => '发票税率',
            'is_use' => 1
        );
        $array[5] = array(
            'instance_id' => $this->instance_id,
            'key' => 'ORDER_INVOICE_CONTENT',
            'value' => $order_invoice_content,
            'desc' => '发票内容',
            'is_use' => 1
        );
        $array[6] = array(
            'instance_id' => $this->instance_id,
            'key' => 'ORDER_DELIVERY_PAY',
            'value' => $order_delivery_pay,
            'desc' => '是否开启货到付款',
            'is_use' => 1
        );
        $array[7] = array(
            'instance_id' => $this->instance_id,
            'key' => 'ORDER_BUY_CLOSE_TIME',
            'value' => $order_buy_close_time,
            'desc' => '订单自动关闭时间',
            'is_use' => 1
        );
        $array[8] = array(
            'instance_id' => $this->instance_id,
            'key' => 'BUYER_SELF_LIFTING',
            'value' => $buyer_self_lifting,
            'desc' => '是否开启买家自提',
            'is_use' => 1
        );
        $array[9] = array(
            'instance_id' => $this->instance_id,
            'key' => 'ORDER_SELLER_DISPATCHING',
            'value' => $seller_dispatching,
            'desc' => '是否开启商家配送',
            'is_use' => 1
        );
        $array[10] = array(
            'instance_id' => $this->instance_id,
            'key' => 'ORDER_IS_LOGISTICS',
            'value' => $is_logistics,
            'desc' => '是否允许选择物流',
            'is_use' => 1
        );
        $array[11] = array(
            'instance_id' => $this->instance_id,
            'key' => 'SHOPPING_BACK_POINTS',
            'value' => $shopping_back_points,
            'desc' => '购物返积分设置',
            'is_use' => 1
        );
        $array[12] = array(
            'instance_id' => $this->instance_id,
            'key' => 'IS_OPEN_VIRTUAL_GOODS',
            'value' => $is_open_virtual_goods,
            'desc' => '是否开启虚拟商品',
            'is_use' => 1
        );
        $array[13] = array(
            'instance_id' => $this->instance_id,
            'key' => 'IS_OPEN_ORDER_DESIGNATED_DELIVERY_TIME',
            'value' => $order_designated_delivery_time,
            'desc' => '是否开启订单指定配送时间',
            'is_use' => 1
        );
        $array[14] = array(
            'instance_id' => $this->instance_id,
            'key' => 'IS_OPEN_O2O',
            'value' => $is_open_o2o,
            'desc' => '是否开启本地配送',
            'is_use' => 1
        );
        $array[15] = array(
            'instance_id' => $this->instance_id,
            'key' => 'DISTRIBUTION_TIME_SLOT',
            'value' => $time_slot,
            'desc' => '配送时间时间段',
            'is_use' => 1
        );
        $array[16] = array(
            'instance_id' => $this->instance_id,
            'key' => 'SYSTEM_DEFAULT_EVALUATE',
            'value' => json_encode([
                'day' => $evaluate_day,
                'evaluate' => $evaluate
            ]),
            'desc' => '系统默认评价',
            'is_use' => 1
        );
        $array[17] = array(
        		'instance_id' => $this->instance_id,
        		'key' => 'SHOPHOU_DAY_NUMBER',
        		'value' => $shouhoudate,
        		'desc' => '可以售后的时间段',
        		'is_use' => 1
        );
        $res = $this->setConfig($array);
        return $res;
    }

    public function SetIntegralConfig($shop_id, $register, $sign, $share, $reg_coupon, $click_coupon, $comment_coupon, $sign_coupon, $share_coupon)
    {
        $array[0] = array(
            'instance_id' => $shop_id,
            'key' => 'REGISTER_INTEGRAL',
            'value' => $register,
            'desc' => '注册送积分',
            'is_use' => 1
        );
        $array[1] = array(
            'instance_id' => $shop_id,
            'key' => 'SIGN_INTEGRAL',
            'value' => $sign,
            'desc' => '签到送积分',
            'is_use' => 1
        );
        $array[2] = array(
            'instance_id' => $shop_id,
            'key' => 'SHARE_INTEGRAL',
            'value' => $share,
            'desc' => '分享送积分',
            'is_use' => 1
        );
        $array[3] = array(
            'instance_id' => $shop_id,
            'key' => 'SHARE_COUPON',
            'value' => $share_coupon,
            'desc' => '分享送优惠券',
            'is_use' => 1
        );
        $array[4] = array(
            'instance_id' => $shop_id,
            'key' => 'SIGN_COUPON',
            'value' => $sign_coupon,
            'desc' => '签到送优惠券',
            'is_use' => 1
        );
        $array[5] = array(
            'instance_id' => $shop_id,
            'key' => 'REGISTER_COUPON',
            'value' => $reg_coupon,
            'desc' => '注册送优惠券',
            'is_use' => 1
        );
        $array[6] = array(
            'instance_id' => $shop_id,
            'key' => 'COMMENT_COUPON',
            'value' => $comment_coupon,
            'desc' => '评论送优惠券',
            'is_use' => 1
        );
        $array[7] = array(
            'instance_id' => $shop_id,
            'key' => 'CLICK_COUPON',
            'value' => $click_coupon,
            'desc' => '点赞送优惠券',
            'is_use' => 1
        );
        $res = $this->setConfig($array);
        return $res;
    }

    public function getIntegralConfig($shop_id)
    {
        $register_integral = $this->getConfig($shop_id, 'REGISTER_INTEGRAL');
        $sign_integral = $this->getConfig($shop_id, 'SIGN_INTEGRAL');
        $share_integral = $this->getConfig($shop_id, 'SHARE_INTEGRAL');
        
        $register_coupon = $this->getConfig($shop_id, 'REGISTER_COUPON');
        $sign_coupon = $this->getConfig($shop_id, 'SIGN_COUPON');
        $share_coupon = $this->getConfig($shop_id, 'SHARE_COUPON');
        $click_coupon = $this->getConfig($shop_id, 'CLICK_COUPON');
        $comment_coupon = $this->getConfig($shop_id, 'COMMENT_COUPON');
        
        if (empty($register_integral) || empty($sign_integral) || empty($share_integral)) {
            $this->SetIntegralConfig($shop_id, '', '', '');
            $array = array(
                'register_integral' => '',
                'sign_integral' => '',
                'share_integral' => '',
                'register_coupon' => '',
                'sign_coupon' => '',
                'share_coupon' => '',
                'click_coupon' => '',
                'comment_coupon' => ''
            );
        } else {
            $array = array(
                'register_integral' => $register_integral['value'],
                'sign_integral' => $sign_integral['value'],
                'share_integral' => $share_integral['value'],
                'register_coupon' => $register_coupon['value'],
                'sign_coupon' => $sign_coupon['value'],
                'share_coupon' => $share_coupon['value'],
                'click_coupon' => $click_coupon['value'],
                'comment_coupon' => $comment_coupon['value']
            );
        }
        return $array;
    }

    /**
     * 修改状态
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::updateConfigEnable()
     */
    public function updateConfigEnable($id, $is_use, $instanceid)
    {
        Cache::set("getEmailMessage" . $instanceid, null);
        Cache::set("getMobileMessage" . $instanceid, null);
        $config_model = new ConfigModel();
        $data = array(
            "is_use" => $is_use,
            "modify_time" => time()
        );
        $retval = $config_model->save($data, [
            "id" => $id
        ]);
        return $retval;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::getRegisterAndVisit()
     */
    public function getRegisterAndVisit($shop_id)
    {
        $register_and_visit = $this->getConfig($shop_id, 'REGISTERANDVISIT');
        if (empty($register_and_visit) || $register_and_visit == null) {
            // 按照默认值显示生成
            $value_array = array(
                'is_register' => "1",
                'register_info' => "plain",
                'name_keyword' => "",
                'pwd_len' => "5",
                'pwd_complexity' => "",
                'terms_of_service' => "",
                'is_requiretel' => 0
            );
            
            $data = array(
                'instance_id' => $shop_id,
                'key' => 'REGISTERANDVISIT',
                'value' => json_encode($value_array),
                'create_time' => time(),
                'is_use' => "1"
            );
            
            $config_model = new ConfigModel();
            $res = $config_model->save($data);
            if ($res > 0) {
                $register_and_visit = $this->getConfig($shop_id, 'REGISTERANDVISIT');
            }
        }
        return $register_and_visit;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::setRegisterAndVisit()
     */
    public function setRegisterAndVisit($shop_id, $is_register, $register_info, $name_keyword, $pwd_len, $pwd_complexity, $terms_of_service, $is_requiretel, $is_use)
    {
        $value_array = array(
            'is_register' => $is_register,
            'register_info' => $register_info,
            'name_keyword' => $name_keyword,
            'pwd_len' => $pwd_len,
            'pwd_complexity' => $pwd_complexity,
            'is_requiretel' => $is_requiretel,
            'terms_of_service' => $terms_of_service
        );
        
        $data = array(
            'value' => json_encode($value_array),
            'modify_time' => time(),
            'is_use' => $is_use
        );
        
        $config_model = new ConfigModel();
        $res = $config_model->save($data, [
            'key' => 'REGISTERANDVISIT',
            'instance_id' => $shop_id
        ]);
        return $res;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::databaseList()
     */
    public function getDatabaseList()
    {
        // TODO Auto-generated method stub
        $databaseList = Db::query("SHOW TABLE STATUS");
        return $databaseList;
    }

    /**
     * 查询物流跟踪的配置信息
     *
     * @param unknown $shop_id            
     */
    public function getOrderExpressMessageConfig($shop_id)
    {
        $express_detail = $this->config_module->getInfo([
            'instance_id' => $shop_id,
            'key' => 'ORDER_EXPRESS_MESSAGE'
        ], 'value,is_use');
        if (empty($express_detail['value'])) {
            return array(
                'value' => array(
                    'type' => 1,
                    'appid' => '',
                    'appkey' => '',
                    'back_url' => ''
                ),
                'is_use' => 0
            );
        } else {
            $express_detail['value'] = json_decode($express_detail['value'], true);
            return $express_detail;
        }
    }

    /**
     * 更新物流跟踪的配置信息
     *
     * @param unknown $shop_id            
     * @param unknown $appid            
     * @param unknown $appkey            
     * @param unknown $is_use            
     */
    public function updateOrderExpressMessageConfig($shop_id, $appid, $appkey, $back_url, $is_use, $type, $customer)
    {
        $express_detail = $this->config_module->getInfo([
            'instance_id' => $shop_id,
            'key' => 'ORDER_EXPRESS_MESSAGE'
        ], 'value,is_use');
        $value = array(
            "type" => $type,
            "appid" => trim($appid),
            "appkey" => trim($appkey),
            "back_url" => $back_url,
            "customer" => $customer
        );
        $value = json_encode($value);
        $config_model = new ConfigModel();
        if (empty($express_detail)) {
            $data = array(
                "instance_id" => $shop_id,
                "key" => 'ORDER_EXPRESS_MESSAGE',
                "value" => $value,
                "create_time" => time(),
                "modify_time" => time(),
                "desc" => "物流跟踪配置信息",
                "is_use" => $is_use
            );
            $config_model->save($data);
            return $config_model->id;
        } else {
            $data = array(
                "key" => 'ORDER_EXPRESS_MESSAGE',
                "value" => $value,
                "modify_time" => time(),
                "is_use" => $is_use
            );
            $result = $config_model->save($data, [
                "instance_id" => $shop_id,
                "key" => "ORDER_EXPRESS_MESSAGE"
            ]);
            return $result;
        }
    }

    /**
     * 获取当前使用的手机模板
     * 2017年7月25日 11:42:57 王永杰
     */
    public function getUseWapTemplate($instanceid)
    {
        $config_model = new ConfigModel();
        $res = $config_model->getInfo([
            'key' => 'USE_WAP_TEMPLATE',
            'instance_id' => $instanceid
        ], 'value', '');
        return $res;
    }

    /**
     * 设置要使用手机模板
     * 2017年7月25日 11:46:46 王永杰
     *
     * @param 实例id $instanceid            
     * @param 模板文件夹名称 $template_name            
     */
    public function setUseWapTemplate($instanceid, $folder)
    {
        $res = 0;
        $config_model = new ConfigModel();
        $info = $this->config_module->getInfo([
            'key' => 'USE_WAP_TEMPLATE',
            'instance_id' => $instanceid
        ], 'value');
        if (empty($info)) {
            $data['instance_id'] = $instanceid;
            $data['key'] = 'USE_WAP_TEMPLATE';
            $data['value'] = $folder;
            $data['create_time'] = time();
            $data['modify_time'] = time();
            $data['desc'] = '当前使用的手机端模板文件夹';
            $data['is_use'] = 1;
            $res = $config_model->save($data);
        } else {
            $data['instance_id'] = $instanceid;
            $data['value'] = $folder;
            $data['modify_time'] = time();
            $res = $config_model->save($data, [
                'key' => 'USE_WAP_TEMPLATE'
            ]);
        }
        return $res;
    }

    /**
     * 获取当前使用的PC端模板
     * 创建时间：2017年9月5日 09:17:14 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IConfig::getUsePCTemplate()
     */
    public function getUsePCTemplate($instanceid)
    {
        $user_pc_template = Cache::get("user_pc_template" . $instanceid);
        if (empty($user_pc_template)) {
            $config_model = new ConfigModel();
            $user_pc_template = $config_model->getInfo([
                'key' => 'USE_PC_TEMPLATE',
                'instance_id' => $instanceid
            ], 'value', '');
            Cache::set("user_pc_template" . $instanceid, $user_pc_template);
        }
        return $user_pc_template;
    }

    /**
     * 设置要使用的PC端模板
     * 创建时间：2017年9月5日 09:17:43 王永杰
     *
     * @param 实例id $instanceid            
     * @param 模板文件夹名称 $template_name            
     */
    public function setUsePCTemplate($instanceid, $folder)
    {
        Cache::set("user_pc_template" . $instanceid, '');
        $res = 0;
        $config_model = new ConfigModel();
        $info = $this->config_module->getInfo([
            'key' => 'USE_PC_TEMPLATE',
            'instance_id' => $instanceid
        ], 'value');
        
        $data['instance_id'] = $instanceid;
        $data['key'] = 'USE_PC_TEMPLATE';
        $data['value'] = $folder;
        $data['create_time'] = time();
        $data['modify_time'] = time();
        if (empty($info)) {
            $data['desc'] = '当前使用的PC端模板文件夹';
            $data['is_use'] = 1;
            $res = $config_model->save($data);
        } else {
            $res = $config_model->save($data, [
                'key' => 'USE_PC_TEMPLATE'
            ]);
        }
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::setPickupPointFreight()
     */
    public function setPickupPointFreight($is_enable, $pickup_freight, $manjian_freight)
    {
        $config_value = array(
            'is_enable' => $is_enable,
            'pickup_freight' => $pickup_freight,
            'manjian_freight' => $manjian_freight
        );
        $config_key = 'PICKUPPOINT_FREIGHT';
        $config_info = $this->getConfig($this->instance_id, $config_key);
        if (empty($config_info)) {
            $res = $this->addConfig($this->instance_id, $config_key, json_encode($config_value), '自提点运费菜单配置', 1);
        } else {
            $res = $this->updateConfig($this->instance_id, $config_key, json_encode($config_value), '自提点运费菜单配置', 1);
        }
        return $res;
    }

    /**
     * 开启关闭自定义模板
     * 2017年8月9日 14:42:21
     *
     * @param 店铺id $shop_id            
     * @param 1：开启，0：禁用 $is_enable            
     */
    public function setIsEnableWapCustomTemplate($shop_id, $is_enable)
    {
        $res = 0;
        $config_model = new ConfigModel();
        $info = $this->config_module->getInfo([
            'key' => 'WAP_CUSTOM_TEMPLATE_IS_ENABLE',
            'instance_id' => $shop_id
        ], 'value');
        $data['instance_id'] = $shop_id;
        $data['value'] = $is_enable;
        if (empty($info)) {
            $data['key'] = 'WAP_CUSTOM_TEMPLATE_IS_ENABLE';
            $data['is_use'] = 1;
            $data['create_time'] = time();
            $res = $config_model->save($data);
        } else {
            $data['modify_time'] = time();
            $res = $config_model->save($data, [
                'key' => 'WAP_CUSTOM_TEMPLATE_IS_ENABLE'
            ]);
        }
        return $res;
    }

    /**
     * 获取自定义模板是否启用，0 不启用 1 启用
     * 创建时间：2017年9月20日 16:06:23 王永杰
     *
     * @param unknown $shop_id            
     * @return number|unknown
     */
    public function getIsEnableWapCustomTemplate($shop_id)
    {
        $is_enable = 0;
        $config_model = new ConfigModel();
        $value = $config_model->getInfo([
            'key' => 'WAP_CUSTOM_TEMPLATE_IS_ENABLE',
            'instance_id' => $shop_id
        ], 'value');
        if (! empty($value)) {
            $is_enable = $value["value"];
        }
        return $is_enable;
    }

    /**
     * 获取格式化后的手机端自定义模板
     * 创建时间：2018年3月7日10:11:51
     *
     * @param number $id            
     * @return multitype:Ambigous <multitype:, mixed, unknown> unknown
     */
    public function getFormatCustomTemplate($id = 0)
    {
        $custom_template = array();
        if ($id === 0) {
            $template_info = $this->getDefaultWapCustomTemplate();
        } else {
            $template_info = $this->getWapCustomTemplateById($id);
        }
        $template_name = ""; // 模板名称
        if (! empty($template_info)) {
            $goods = new Goods();
            $custom_template_info = json_decode($template_info["template_data"], true);
            foreach ($custom_template_info as $k => $v) {
                $custom_template_info[$k]["style_data"] = json_decode($v["control_data"], true);
            }
            // 给数组排序
            $sort = array(
                'direction' => 'SORT_ASC', // 排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
                'field' => 'sort'
            );
            $arrSort = array();
            foreach ($custom_template_info as $uniqid => $row) {
                foreach ($row as $key => $value) {
                    $arrSort[$key][$uniqid] = $value;
                }
            }
            if ($sort['direction']) {
                array_multisort($arrSort[$sort['field']], constant($sort['direction']), $custom_template_info);
            }
            foreach ($custom_template_info as $k => $v) {
                
                if ($v['control_name'] == "GoodsSearch") {
                    
                    // 商品搜索
                    $custom_template_info[$k]["style_data"]['goods_search'] = json_decode($v["style_data"]['goods_search'], true);
                } elseif ($v["control_name"] == "GoodsList") {
                    
                    // 商品列表
                    $custom_template_info[$k]["style_data"]['goods_list'] = json_decode($v["style_data"]['goods_list'], true);
                    if ($custom_template_info[$k]["style_data"]['goods_list']["goods_source"] > 0) {
                        
                        $goods_list = $goods->getGoodsListNew(1, $custom_template_info[$k]["style_data"]['goods_list']["goods_limit_count"], [
                            "ng.category_id" => $custom_template_info[$k]["style_data"]['goods_list']["goods_source"],
                            "ng.state" => 1
                        ], "ng.sort asc,ng.create_time desc");
                        $goods_query = array();
                        if (! empty($goods_list)) {
                            $goods_query = $goods_list["data"];
                        }
                        $custom_template_info[$k]["goods_list"] = $goods_query;
                    }
                } elseif ($v["control_name"] == "ImgAd") {
                    
                    // 图片广告
                    if (trim($v["style_data"]["img_ad"]) != "") {
                        $custom_template_info[$k]["style_data"]["img_ad"] = json_decode($v["style_data"]["img_ad"], true);
                    } else {
                        $custom_template_info[$k]["style_data"]["img_ad"] = array();
                    }
                } elseif ($v["control_name"] == "NavHyBrid") {
                    
                    $custom_template_info[$k]["style_data"]["nav_hybrid"] = json_decode($v["style_data"]["nav_hybrid"], true);
                } elseif ($v["control_name"] == "GoodsClassify") {
                    
                    // 商品分类
                    if (trim($v["style_data"]["goods_classify"]) != "") {
                        $category = new GoodsCategory();
                        $category_array = json_decode($v["style_data"]["goods_classify"], true);
                        foreach ($category_array as $t => $m) {
                            $category_info = $category->getGoodsCategoryDetail($m["id"]);
                            $category_array[$t]["name"] = $category_info["short_name"];
                            $goods_list = $goods->getGoodsListNew(1, $m["show_count"], [
                                "ng.category_id" => $m["id"],
                                "ng.state" => 1
                            ], "ng.sort asc,ng.create_time desc");
                            $category_array[$t]["goods_list"] = $goods_list["data"];
                        }
                        $custom_template_info[$k]["style_data"]["goods_classify"] = $category_array;
                    } else {
                        $custom_template_info[$k]["style_data"]["goods_classify"] = array();
                    }
                } elseif ($v["control_name"] == "Footer") {
                    
                    // 底部菜单
                    if (trim($v["style_data"]["footer"]) != "") {
                        $custom_template_info[$k]["style_data"]["footer"] = json_decode($v["style_data"]["footer"], true);
                    } else {
                        $custom_template_info[$k]["style_data"]["footer"] = array();
                    }
                } elseif ($v["control_name"] == "CustomModule") {
                    
                    // 自定义模块
                    $custom_module = json_decode($v["style_data"]['custom_module'], true);
                    
                    $custom_module_list = $this->getFormatCustomTemplate($custom_module['module_id']);
                    if (! empty($custom_module_list)) {
                        for ($i = 0; $i < count($custom_module_list['template_data']); $i ++) {
                            
                            array_push($custom_template_info, $custom_module_list['template_data'][$i]);
                        }
                    }
                } elseif ($v["control_name"] == "Coupons") {
                    
                    // 优惠券
                    $custom_template_info[$k]["style_data"]['coupons'] = json_decode($v["style_data"]['coupons'], true);
                } elseif ($v["control_name"] == "Video") {
                    
                    // 视频
                    $custom_template_info[$k]["style_data"]['video'] = json_decode($v["style_data"]['video'], true);
                } elseif ($v["control_name"] == "ShowCase") {
                    
                    // 橱窗
                    $custom_template_info[$k]["style_data"]['show_case'] = json_decode($v["style_data"]['show_case'], true);
                } elseif ($v['control_name'] == "Notice") {
                    
                    // 公告
                    $custom_template_info[$k]['style_data']['notice'] = json_decode($v['style_data']['notice'], true);
                } elseif ($v['control_name'] == "TextNavigation") {
                    
                    // 文本导航
                    $custom_template_info[$k]['style_data']['text_navigation'] = json_decode($v['style_data']['text_navigation'], true);
                } elseif ($v['control_name'] == "Title") {
                    
                    // 标题
                    $custom_template_info[$k]['style_data']['title'] = json_decode($v['style_data']['title'], true);
                } elseif ($v['control_name'] == "AuxiliaryLine") {
                    
                    // 辅助线
                    $custom_template_info[$k]['style_data']['auxiliary_line'] = json_decode($v['style_data']['auxiliary_line'], true);
                } elseif ($v['control_name'] == "AuxiliaryBlank") {
                    
                    // 辅助空白
                    $custom_template_info[$k]['style_data']['auxiliary_blank'] = json_decode($v['style_data']['auxiliary_blank'], true);
                }
            }
            $custom_template["template_name"] = $template_info["template_name"];
            $custom_template["template_data"] = $custom_template_info;
        }
        return $custom_template;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::getUploadType()
     */
    public function getUploadType($shop_id)
    {
        // TODO Auto-generated method stub
        $upload_type = $this->config_module->getInfo([
            "key" => "UPLOAD_TYPE",
            "instance_id" => $shop_id
        ], "*");
        if (empty($upload_type)) {
            $res = $this->addConfig($shop_id, "UPLOAD_TYPE", 1, "上传方式 1 本地  2 七牛", 1);
            return 1;
        } else {
            return $upload_type['value'];
        }
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::getQiniuConfig()
     */
    public function getQiniuConfig($shop_id)
    {
        // TODO Auto-generated method stub
        $qiniu_info = $this->config_module->getInfo([
            "key" => "QINIU_CONFIG",
            "instance_id" => $shop_id
        ], "*");
        if (empty($qiniu_info)) {
            $data = array(
                "Accesskey" => "",
                "Secretkey" => "",
                "Bucket" => "",
                "QiniuUrl" => ""
            );
            $res = $this->addConfig($shop_id, "QINIU_CONFIG", json_encode($data), "七牛云存储参数配置", 1);
            if (! $res > 0) {
                return null;
            } else {
                $qiniu_info = $this->config_module->getInfo([
                    "key" => "QINIU_CONFIG",
                    "instance_id" => $shop_id
                ], "*");
            }
        }
        $value_info = $qiniu_info["value"];
        $value = json_decode($qiniu_info["value"], true);
        return $value;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::setUploadType()
     */
    public function setUploadType($shop_id, $value)
    {
        $upload_info = $this->config_module->getInfo([
            "key" => "UPLOAD_TYPE",
            "instance_id" => $shop_id
        ], "*");
        if (! empty($upload_info)) {
            $data = array(
                "value" => $value
            );
            $res = $this->config_module->save($data, [
                "instance_id" => $shop_id,
                "key" => "UPLOAD_TYPE"
            ]);
        } else {
            $res = $this->addConfig($shop_id, "UPLOAD_TYPE", $value, "上传方式 1 本地  2 七牛", 1);
        }
        // TODO Auto-generated method stub
        
        return $res;
    }

    public function setQiniuConfig($shop_id, $value)
    {
        $qiniu_info = $this->config_module->getInfo([
            "key" => "QINIU_CONFIG",
            "instance_id" => $shop_id
        ], "*");
        if (empty($qiniu_info)) {
            $data = array(
                "Accesskey" => "",
                "Secretkey" => "",
                "Bucket" => "",
                "QiniuUrl" => ""
            );
            $res = $this->addConfig($shop_id, "QINIU_CONFIG", json_encode($data), "七牛云存储参数配置", 1);
        } else {
            $data = array(
                "value" => $value
            );
            $res = $this->config_module->save($data, [
                "key" => "QINIU_CONFIG",
                "instance_id" => $shop_id
            ]);
        }
        return $res;
    }

    public function getPictureUploadSetting($shop_id)
    {
        // TODO Auto-generated method stub
        $info = $this->config_module->getInfo([
            "key" => "IMG_THUMB",
            "instance_id" => $shop_id
        ], "*");
        if (empty($info)) {
            $data = array(
                "thumb_type" => "2",
                "upload_size" => "0",
                "upload_ext" => "gif,jpg,jpeg,bmp,png"
            );
            $res = $this->addConfig($shop_id, "IMG_THUMB", json_encode($data), "thumb_type(缩略)  3 居中裁剪 2 缩放后填充 4 左上角裁剪 5 右下角裁剪 6 固定尺寸缩放", 1);
            if (! $res > 0) {
                return null;
            } else {
                $info = $this->config_module->getInfo([
                    "key" => "IMG_THUMB",
                    "instance_id" => $shop_id
                ], "*");
            }
        }
        $value_info = $info["value"];
        $value = json_decode($info["value"], true);
        return $value;
    }
    
    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::setUploadType()
     */
    public function setPictureUploadSetting($shop_id, $value)
    {
        $info = $this->config_module->getInfo([
            "key" => "IMG_THUMB",
            "instance_id" => $shop_id
        ], "*");
        if (! empty($info)) {
            $data = array(
                "value" => $value
            );
            $res = $this->config_module->save($data, [
                "instance_id" => $shop_id,
                "key" => "IMG_THUMB"
            ]);
        } else {
            $res = $this->addConfig($shop_id, "IMG_THUMB", $value, "图片生成参数配置  thumb_type(缩略)  3 居中裁剪 2 缩放后填充 4 左上角裁剪 5 右下角裁剪 6 固定尺寸缩放 ", 1);
        }
        // TODO Auto-generated method stub
        
        return $res;
    }

    /**
     * 设置原路退款信息
     * 创建时间：2017年10月13日 17:59:57 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IConfig::setOriginalRoadRefundSetting()
     */
    public function setOriginalRoadRefundSetting($shop_id, $type, $value)
    {
        if ($type == "wechat") {
            $key = 'ORIGINAL_ROAD_REFUND_SETTING_WECHAT';
        } elseif ($type == "alipay") {
            $key = 'ORIGINAL_ROAD_REFUND_SETTING_ALIPAY';
        }elseif($type == "unionpay"){
            $key = 'ORIGINAL_ROAD_REFUND_SETTING_UNIONPAY';
        }
        
        $info = $this->config_module->getInfo([
            'key' => $key,
            'instance_id' => $shop_id
        ], 'value');
        
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $shop_id,
                'key' => $key,
                'value' => $value,
                'is_use' => 1,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'key' => $key,
                'value' => $value,
                'is_use' => 1,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $shop_id,
                'key' => $key
            ]);
        }
        return $res;
    }

    /**
     * 设置版本切换信息
     * 创建时间：2017年10月13日 17:59:57 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IConfig::setOriginalRoadRefundSetting()
     */
    public function setEditionSetting($shop_id, $type, $edition)
    {
    	$key = 'EDITION_ALIPAY';
    	$info = $this->config_module->getInfo([
    			'key' => $key,
    			'instance_id' => $shop_id
    	], 'value');
    
    	if (empty($info)) {
    		$config_module = new ConfigModel();
    		$data = array(
    				'instance_id' => $shop_id,
    				'key' => $key,
    				'value' => $edition,
    				'is_use' => 1,
    				'create_time' => time()
    		);
    		$res = $config_module->save($data);
    	} else {
    		$config_module = new ConfigModel();
    		$data = array(
    				'key' => $key,
    				'value' => $edition,
    				'is_use' => 1,
    				'modify_time' => time()
    		);
    		$res = $config_module->save($data, [
    				'instance_id' => $shop_id,
    				'key' => $key
    		]);
    	}
    	return $res;
    }
    
    /**
     * 设置支付宝是否开启信息
     * 创建时间：2017年10月13日 17:59:57 王永杰
     *
     * @ERROR!!!
     *
     */
    public function setAlipayStatus($shop_id, $type, $status)
    {
    	$key = 'ALIPAY_STATUS';
    	$info = $this->config_module->getInfo([
    			'key' => $key,
    			'instance_id' => $shop_id
    	], 'value');
    
    	if (empty($info)) {
    		$config_module = new ConfigModel();
    		$data = array(
    				'instance_id' => $shop_id,
    				'key' => $key,
    				'value' => $status,
    				'is_use' => 1,
    				'create_time' => time()
    		);
    		$res = $config_module->save($data);
    	} else {
    		$config_module = new ConfigModel();
    		$data = array(
    				'key' => $key,
    				'value' => $status,
    				'is_use' => 1,
    				'modify_time' => time()
    		);
    		$res = $config_module->save($data, [
    				'instance_id' => $shop_id,
    				'key' => $key
    		]);
    	}
    	return $res;
    }
    
    /**
     * 查询支付宝是否开启信息
     * 创建时间：2017年10月13日 17:59:57 王永杰
     *
     * @ERROR!!!
     *
     */
    public function getAlipayStatus($shop_id)
    {
		$info = $this->config_module->getInfo([
    			'key' => 'ALIPAY_STATUS',
    			'instance_id' => $shop_id
    	], 'value');
    	return $info;
    }
    
    /**
     * 获取原路退款信息
     * 创建时间：2017年10月13日 18:01:15 王永杰
     *
     * @ERROR!!!
     *
     * @see \data\api\IConfig::getOriginalRoadRefundSetting()
     */
    public function getOriginalRoadRefundSetting($shop_id, $type)
    {
        if ($type == "wechat") {
            $key = 'ORIGINAL_ROAD_REFUND_SETTING_WECHAT';
        } elseif ($type == "alipay") {
            $key = 'ORIGINAL_ROAD_REFUND_SETTING_ALIPAY';
        }elseif($type == "unionpay"){
            $key = 'ORIGINAL_ROAD_REFUND_SETTING_UNIONPAY';
        }
        
        $info = $this->config_module->getInfo([
            'key' => $key,
            'instance_id' => $shop_id
        ], 'value');
        return $info;
    }

    public function getEditionSetting($shop_id)
    {
    	$info = $this->config_module->getInfo([
    			'key' => 'EDITION_ALIPAY',
    			'instance_id' => $shop_id
    	], 'value');
    	return $info;
    }
    
    /**
     * 设置转账配置信息
     *
     *
     * @ERROR!!!
     *
     * @see \data\api\IConfig::setOriginalRoadRefundSetting()
     */
    public function setTransferAccountsSetting($shop_id, $type, $value)
    {
        if ($type == "wechat") {
            $key = 'TRANSFER_ACCOUNTS_SETTING_WECHAT';
        } elseif ($type == "alipay") {
            $key = 'TRANSFER_ACCOUNTS_SETTING_ALIPAY';
        }
        
        $info = $this->config_module->getInfo([
            'key' => $key,
            'instance_id' => $shop_id
        ], 'value');
        
        if (empty($info)) {
            $config_module = new ConfigModel();
            $data = array(
                'instance_id' => $shop_id,
                'key' => $key,
                'value' => $value,
                'is_use' => 1,
                'create_time' => time()
            );
            $res = $config_module->save($data);
        } else {
            $config_module = new ConfigModel();
            $data = array(
                'key' => $key,
                'value' => $value,
                'is_use' => 1,
                'modify_time' => time()
            );
            $res = $config_module->save($data, [
                'instance_id' => $shop_id,
                'key' => $key
            ]);
        }
        return $res;
    }

    /**
     * 获取转账配置信息
     *
     *
     * @ERROR!!!
     *
     * @see \data\api\IConfig::getOriginalRoadRefundSetting()
     */
    public function getTransferAccountsSetting($shop_id, $type)
    {
        if ($type == "wechat") {
            $key = 'TRANSFER_ACCOUNTS_SETTING_WECHAT';
        } elseif ($type == "alipay") {
            $key = 'TRANSFER_ACCOUNTS_SETTING_ALIPAY';
        }
        
        $info = $this->config_module->getInfo([
            'key' => $key,
            'instance_id' => $shop_id
        ], 'value');
        return $info;
    }

    /**
     * 检测支付配置是否开启，支付配置和原路退款配置都要开启才行（配置信息也要填写）
     *
     * @param unknown $shop_id
     *            店铺id
     * @param unknown $type
     *            wechat,alipay(微信/支付宝)
     */
    public function checkPayConfigEnabled($shop_id, $type)
    {
        $msg = ""; // 支付配置是否开启,1 开启，0 未开启（条件是各个配置项都不能为空，并且是启用状态）
        $admin_main = ThinkPHPConfig::get('view_replace_str.ADMIN_MAIN');
        $original_road_refund_info = $this->getOriginalRoadRefundSetting($shop_id, $type);
        if (! empty($original_road_refund_info['value'])) {
            
            $refund_setting = json_decode($original_road_refund_info['value'], true);
            if ($type == "alipay") {
                
            	$edition = $this->getEditionSetting($this->instance_id);
            	$edition_info = json_decode($edition['value'], true);
            	if($edition_info['is_use'] == 0 || empty($edition)){
            		$pay_info = $this->getAlipayConfig($shop_id);
            		if (empty($pay_info) || empty($pay_info['value']['ali_partnerid']) || empty($pay_info['value']['ali_seller']) || empty($pay_info['value']['ali_key'])) {
            			$msg = "<p>请检查支付宝支付配置信息填写是否正确(<a href='" . __URL($admin_main . "/config/payaliconfig") . "' target='_blank'>点击此处进行配置</a>)</p>";
            			return $msg;
            		}
            	}else{
            		$pay_info = $this->getAlipayConfigNew($shop_id);
            		if (empty($pay_info) || empty($pay_info['value']['appid']) || empty($pay_info['value']['private_key']) || empty($pay_info['value']['public_key'])) {
            			$msg = "<p>请检查支付宝支付配置信息填写是否正确(<a href='" . __URL($admin_main . "/config/payaliconfig") . "' target='_blank'>点击此处进行配置</a>)</p>";
            			return $msg;
            		}
            	}
                
            	$alipaystatus = $this->getAlipayStatus($this->instance_id);
            	$status_info = json_decode($alipaystatus['value'], true);
                
                if ($status_info['is_use'] == 0) {
                    $msg = "<p>当前未开启支付宝支付配置(<a href='" . __URL($admin_main . "/config/payaliconfig") . "' target='_blank'>点击此处去开启</a>)</p>";
                    return $msg;
                } else {
                    
                    // 支付配置开启后，再判断原路退款配置是否开启、填写了各项值
                    if ($refund_setting['is_use'] == 0) {
                        $msg = "<p>当前未开启支付宝原路退款配置(<a href='" . __URL($admin_main . "/config/payaliconfig") . "' target='_blank'>点击此处去开启</a>)</p>";
                        return $msg;
                    }
                }
            } elseif ($type == "wechat") {
                
                $pay_info = $this->getWpayConfig($shop_id);
                if (empty($pay_info) || empty($pay_info['value']['appid']) || empty($pay_info['value']['appkey']) || empty($pay_info['value']['mch_id']) || empty($pay_info['value']['mch_key'])) {
                    $msg = "<p>请检查微信支付配置信息填写是否正确(<a href='" . __URL($admin_main . "/config/payconfig?type=wchat") . "' target='_blank'>点击此处进行配置</a>)</p>";
                    return $msg;
                }
                
                if ($pay_info['is_use'] == 0) {
                    $msg = "<p>当前未开启微信支付配置(<a href='" . __URL($admin_main . "/config/payconfig?type=wchat") . "' target='_blank'>点击此处去开启</a>)</p>";
                    return $msg;
                } else {
                    
                    if (empty($refund_setting['apiclient_cert']) || empty($refund_setting['apiclient_key'])) {
                        $msg = "<p>请检查微信原路退款配置信息填写是否正确(<a href='" . __URL($admin_main . "/config/payconfig?type=wchat") . "' target='_blank'>点击此处进行配置</a>)</p>";
                        return $msg;
                    }
                    if ($refund_setting['is_use'] == 0) {
                        $msg = "<p>当前未开启微信原路退款配置(<a href='" . __URL($admin_main . "/config/payconfig?type=wchat") . "' target='_blank'>点击此处去开启</a>)</p>";
                        return $msg;
                    }
                }
            }elseif ($type == "unionpay"){
                $pay_info = $this->getUnionpayConfig($shop_id);
                
                if (empty($pay_info) || empty($pay_info['value']['sign_cert_pwd']) || empty($pay_info['value']['certs_path']) || empty($pay_info['value']['log_path']) || empty($pay_info['value']['service_charge'])) {
                    $msg = "<p>请检查银联支付配置信息填写是否正确(<a href='" . __URL($admin_main . "/config/unionpayconfig") . "' target='_blank'>点击此处进行配置</a>)</p>";
                    return $msg;
                }
                
                if ($pay_info['is_use'] == 0) {
                    $msg = "<p>当前未开启银联支付配置(<a href='" . __URL($admin_main . "/config/unionpayconfig") . "' target='_blank'>点击此处去开启</a>)</p>";
                    return $msg;
                }else{
                    if ($refund_setting['is_use'] == 0) {
                        $msg = "<p>当前未开启银联退款配置(<a href='" . __URL($admin_main . "/config/unionpayconfig") . "' target='_blank'>点击此处去开启</a>)</p>";
                        return $msg;
                    }
                }
            }
        } else {
            if ($type == "alipay") {
                $msg = "<p>当前未开启支付宝原路退款配置(<a href='" . __URL($admin_main . "/config/payaliconfig") . "' target='_blank'>点击此处进行配置</a>)</p>";
            } elseif ($type == "wechat") {
                $msg = "<p>请检查微信原路退款配置信息填写是否正确(<a href='" . __URL($admin_main . "/config/payconfig?type=wchat") . "' target='_blank'>点击此处进行配置</a>)</p>";
            }elseif($type == "unionpay"){
                $msg = "<p>当前未开启银联退款配置(<a href='" . __URL($admin_main . "/config/unionpayconfig") . "' target='_blank'>点击此处进行配置</a>)</p>";
            }
        }
        return $msg;
    }

    /**
     * 检测支付配置是否开启，支付配置和原路退款配置都要开启才行（配置信息也要填写）
     *
     * @param unknown $shop_id
     *            店铺id
     * @param unknown $type
     *            wechat,alipay(微信/支付宝)
     */
    public function checkPayConfigEnabledOne($shop_id, $type)
    {
        $msg = 1; // 支付配置是否开启,1 开启，0 未开启（条件是各个配置项都不能为空，并且是启用状态）
        $admin_main = ThinkPHPConfig::get('view_replace_str.ADMIN_MAIN');
        $original_road_refund_info = $this->getOriginalRoadRefundSetting($shop_id, $type);
        if (! empty($original_road_refund_info['value'])) {
            
            $refund_setting = json_decode($original_road_refund_info['value'], true);
            if ($type == "alipay") {
                
                $pay_info = $this->getAlipayConfig($shop_id);
                if (empty($pay_info) || empty($pay_info['value']['ali_partnerid']) || empty($pay_info['value']['ali_seller']) || empty($pay_info['value']['ali_key'])) {
                    $msg = "请检查支付宝支付配置信息填写是否正确";
                    return $msg;
                }
                
                if ($pay_info['is_use'] == 0) {
                    $msg = "当前未开启支付宝支付配置>";
                    return $msg;
                } else {
                    
                    // 支付配置开启后，再判断原路退款配置是否开启、填写了各项值
                    if ($refund_setting['is_use'] == 0) {
                        $msg = "当前未开启支付宝原路退款配置";
                        return $msg;
                    }
                }
            } elseif ($type == "wechat") {
                
                $pay_info = $this->getWpayConfig($shop_id);
                if (empty($pay_info) || empty($pay_info['value']['appid']) || empty($pay_info['value']['appkey']) || empty($pay_info['value']['mch_id']) || empty($pay_info['value']['mch_key'])) {
                    $msg = "请检查微信支付配置信息填写是否正确";
                    return $msg;
                }
                
                if ($pay_info['is_use'] == 0) {
                    $msg = "当前未开启微信支付配置";
                    return $msg;
                } else {
                    
                    if (empty($refund_setting['apiclient_cert']) || empty($refund_setting['apiclient_key'])) {
                        $msg = "请检查微信原路退款配置信息填写是否正确";
                        return $msg;
                    }
                    if ($refund_setting['is_use'] == 0) {
                        $msg = "当前未开启微信原路退款配置";
                        return $msg;
                    }
                }
            }
        } else {
            if ($type == "alipay") {
                $msg = "当前未开启支付宝原路退款配置";
            } elseif ($type == "wechat") {
                $msg = "请检查微信原路退款配置信息填写是否正确";
            }
        }
        return $msg;
    }

    /**
     * 获取是否开启虚拟商品配置信息 0:禁用，1:开启
     * 创建时间：2017年11月27日 16:58:14
     */
    public function getIsOpenVirtualGoodsConfig($shop_id)
    {
        $info = $this->config_module->getInfo([
            'key' => 'IS_OPEN_VIRTUAL_GOODS',
            'instance_id' => $shop_id
        ], 'value');
        if (! empty($info)) {
            return $info['value'];
        } else {
            return 0;
        }
    }

    /**
     * 设置默认图片
     *
     * @param unknown $shop_id            
     * @param unknown $value            
     */
    public function setDefaultImages($shop_id, $value)
    {
        Cache::set("getDefaultImages" . $shop_id, null);
        
        $default_image = $this->config_module->getInfo([
            "key" => "DEFAULT_IMAGE",
            "instance_id" => $shop_id
        ], "*");
        if (! empty($default_image)) {
            $data = array(
                "value" => $value
            );
            $res = $this->config_module->save($data, [
                "instance_id" => $shop_id,
                "key" => "DEFAULT_IMAGE"
            ]);
        } else {
            $res = $this->addConfig($shop_id, "DEFAULT_IMAGE", $value, "默认图片", 1);
        }
        
        return $res;
    }

    /**
     * 获取默认图片
     *
     * @param unknown $instanceid            
     * @return mixed|NULL|mixed
     */
    public function getDefaultImages($instanceid)
    {
        $cache = Cache::get("getDefaultImages" . $instanceid);
        if (empty($cache)) {
            $info = $this->config_module->getInfo([
                'key' => 'DEFAULT_IMAGE',
                'instance_id' => $instanceid
            ], 'value, is_use');
            if (empty($info['value'])) {
                $data = array(
                    'value' => array(
                        "default_goods_img" => "",
                        "default_headimg" => "",
                        "default_cms_thumbnail" => ""
                    ),
                    'is_use' => 0
                );
            } else {
                $info['value'] = json_decode($info['value'], true);
                $data = $info;
            }
            Cache::set("getDefaultImages" . $instanceid, $data);
            return $data;
        } else {
            return $cache;
        }
    }

    /**
     * 获取手机端自定义模板列表
     * 创建时间：2018年1月17日12:07:14 全栈小学生
     *
     * @param number $page_index            
     * @param number $page_size            
     * @param string $condition            
     * @param string $order            
     * @param string $field            
     * @return multitype:number unknown
     */
    public function getWapCustomTemplateList($page_index = 1, $page_size = 0, $condition = '', $order = 'id desc', $field = '*')
    {
        $model = new SysWapCustomTemplateModel();
        $list = $model->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $list;
    }

    /**
     * 根据主键id删除手机端自定义模板
     * 创建时间：2018年1月17日12:13:41 全栈小学生
     *
     * @param unknown $id            
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function deleteWapCustomTemplateById($id)
    {
        $model = new SysWapCustomTemplateModel();
        $res = $model->destroy([
            "id" => [
                "in",
                $id
            ]
        ]);
        return $res;
    }

    /**
     * 设置默认手机自定义模板
     * 创建时间：2018年1月17日12:17:17 全栈小学生
     *
     * @ERROR!!!
     *
     * @see \data\api\IConfig::setDefaultCustomTemplate()
     */
    public function setDefaultWapCustomTemplate($id)
    {
        $model = new SysWapCustomTemplateModel();
        $res = $model->save([
            "is_default" => 0
        ], [
            "id" => array(
                'NEQ',
                $id
            )
        ]);
        
        $res = $model->save([
            "is_default" => 1,
            "modify_time" => time()
        ], [
            "id" => $id
        ]);
        return $res;
    }

    /**
     * 根据id获取手机端自定义模板
     * 创建时间：2018年1月17日12:42:52
     *
     * @ERROR!!!
     *
     * @see \data\api\IConfig::getCustomTemplateById()
     */
    public function getWapCustomTemplateById($id)
    {
        $model = new SysWapCustomTemplateModel();
        $res = $model->getInfo([
            'id' => $id
        ]);
        return $res;
    }

    /**
     * 编辑手机端自定义模板
     * 创建时间：2018年1月17日14:24:25
     *
     * @param unknown $template_name            
     * @param unknown $template_data            
     * @return Ambigous <boolean, number, \think\false, string>
     */
    public function editWapCustomTemplate($id, $template_name, $template_data)
    {
        $data['shop_id'] = $this->instance_id;
        $data['template_name'] = $template_name;
        $data['template_data'] = $template_data;
        $data['modify_time'] = time();
        $data['create_time'] = time();
        $model = new SysWapCustomTemplateModel();
        if ($id == 0) {
            // 添加
            $default_custom_template = $this->getDefaultWapCustomTemplate();
            if (empty($default_custom_template)) {
                $data['is_default'] = 1;
            }
            $res = $model->save($data);
        } else {
            $res = $model->save($data, [
                'id' => $id
            ]);
        }
        return $res;
    }

    /**
     * 获取默认手机端自定义模板
     * 创建时间：2018年1月17日14:51:45
     *
     * @return unknown
     */
    public function getDefaultWapCustomTemplate()
    {
        $model = new SysWapCustomTemplateModel();
        $res = $default_custom_template = $model->getInfo([
            "shop_id" => $this->instance_id,
            "is_default" => 1
        ]);
        return $res;
    }

    /**
     * 获取原路退款设置
     * (non-PHPdoc)
     */
    public function getRefundConfig($shop_id)
    {
        $config_model = new ConfigModel();
        $condition = array(
            'instance_id' => $shop_id,
            'key' => array(
                'in',
                'ORIGINAL_ROAD_REFUND_SETTING_WECHAT,ORIGINAL_ROAD_REFUND_SETTING_ALIPAY'
            )
        );
        $notify_list = $config_model->getQuery($condition, "*", "");
        if (! empty($notify_list)) {
            for ($i = 0; $i < count($notify_list); $i ++) {
                if ($notify_list[$i]["key"] == "ORIGINAL_ROAD_REFUND_SETTING_WECHAT") {
                    $notify_list[$i]["logo"] = "public/admin/images/wchat.png";
                    $notify_list[$i]["pay_name"] = "微信";
                    $notify_list[$i]["desc"] = "该系统支持微信网页支付和扫码支付";
                } else 
                    if ($notify_list[$i]["key"] == "ORIGINAL_ROAD_REFUND_SETTING_ALIPAY") {
                        $notify_list[$i]["pay_name"] = "支付宝";
                        $notify_list[$i]["logo"] = "public/admin/images/pay.png";
                        $notify_list[$i]["desc"] = "该系统支持即时到账接口";
                    }
            }
            return $notify_list;
        } else {
            return null;
        }
    }

    /**
     * 设置退款 微信和支付宝开关状态
     */
    public function setRefundStatusConfig($instanceid, $is_use, $type)
    {
        Cache::set("getRefundAlipayConfig" . $instanceid, null);
        Cache::set("getRefundWpayConfig" . $instanceid, null);
        $config_module = new ConfigModel();
        $result = $config_module->getInfo([
            'instance_id' => $instanceid,
            'key' => $type
        ], 'value');
        
        $old_value = array();
        $old_value = json_decode($result['value'], true);
        if (! empty($old_value)) {
            if ($type == 'ORIGINAL_ROAD_REFUND_SETTING_WECHAT') {
                $new_value["is_use"] = $is_use;
                $new_value["apiclient_cert"] = $old_value['apiclient_cert'];
                $new_value["apiclient_key"] = $old_value['apiclient_key'];
                
                $value = json_encode($new_value);
                $config_module->save([
                    "value" => $value
                ], [
                    'instance_id' => $instanceid,
                    'key' => $type
                ]);
            } else {
                $new_value["is_use"] = $is_use;
                $value = json_encode($new_value);
                $config_module->save([
                    "value" => $value
                ], [
                    'instance_id' => $instanceid,
                    'key' => $type
                ]);
            }
            
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 获取转账设置
     * (non-PHPdoc)
     */
    public function getTransferConfig($shop_id)
    {
        $config_model = new ConfigModel();
        $condition = array(
            'instance_id' => $shop_id,
            'key' => array(
                'in',
                'TRANSFER_ACCOUNTS_SETTING_WECHAT,TRANSFER_ACCOUNTS_SETTING_ALIPAY'
            )
        );
        $notify_list = $config_model->getQuery($condition, "*", "");
        if (! empty($notify_list)) {
            for ($i = 0; $i < count($notify_list); $i ++) {
                if ($notify_list[$i]["key"] == "TRANSFER_ACCOUNTS_SETTING_WECHAT") {
                    $notify_list[$i]["logo"] = "public/admin/images/wchat.png";
                    $notify_list[$i]["pay_name"] = "微信转账";
                    $notify_list[$i]["desc"] = "该系统支持微信网页支付和扫码支付";
                } else 
                    if ($notify_list[$i]["key"] == "TRANSFER_ACCOUNTS_SETTING_ALIPAY") {
                        $notify_list[$i]["pay_name"] = "支付宝转账";
                        $notify_list[$i]["logo"] = "public/admin/images/pay.png";
                        $notify_list[$i]["desc"] = "该系统支持即时到账接口";
                    }
            }
            return $notify_list;
        } else {
            return null;
        }
    }

    /**
     * 设置转账 微信和支付宝开关状态
     */
    public function setTransferStatusConfig($instanceid, $is_use, $type)
    {
        Cache::set("getTransferAlipayConfig" . $instanceid, null);
        Cache::set("getTransferWpayConfig" . $instanceid, null);
        $config_module = new ConfigModel();
        $result = $config_module->getInfo([
            'instance_id' => $instanceid,
            'key' => $type
        ], 'value');
        
        $old_value = array();
        $old_value = json_decode($result['value'], true);
        if (! empty($old_value)) {
            
            $new_value["is_use"] = $is_use;
            $value = json_encode($new_value);
            $config_module->save([
                "value" => $value
            ], [
                'instance_id' => $instanceid,
                'key' => $type
            ]);
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 设置商家服务，固定4个
     * 创建时间：2018年1月22日18:12:50
     *
     * @param unknown $instance_id            
     * @param unknown $value            
     */
    public function setMerchantServiceConfig($instance_id, $value)
    {
        $config_module = new ConfigModel();
        $info = $config_module->getInfo([
            'instance_id' => $instance_id,
            'key' => 'MERCHANT_SERVICE'
        ], "value");
        
        $data = array(
            'key' => 'MERCHANT_SERVICE',
            'instance_id' => $instance_id,
            'value' => $value,
            'is_use' => 1,
            'desc' => '商家服务',
            'modify_time' => time()
        );
        if (empty($info['value'])) {
            
            $res = $config_module->save($data);
        } else {
            
            $res = $config_module->save($data, [
                'instance_id' => $instance_id,
                'key' => 'MERCHANT_SERVICE'
            ]);
        }
        return $res;
    }

    /**
     * 获取商家服务，固定4个
     * 创建时间：2018年1月22日18:15:20
     *
     * @param unknown $instance_id            
     * @return unknown
     */
    public function getMerchantServiceConfig($instance_id)
    {
        $config_module = new ConfigModel();
        $info = $config_module->getInfo([
            'instance_id' => $instance_id,
            'key' => 'MERCHANT_SERVICE'
        ], "value");
        $res = array();
        if (! empty($info['value'])) {
            $res = json_decode($info['value'], true);
        }
        
        $count = 6; // 固定数量
        for ($i = 0; $i < $count; $i ++) {
            
            // 缺几个补几个
            if (count($res) != $count) {
                array_push($res, [
                    'id' => $i,
                    'title' => '',
                    'describe' => '',
                    'pic' => ''
                ]);
            }
        }
        return $res;
    }

    /**
     * 获取已经存在的商家服务，排除空
     * 创建时间：2018年1月22日19:15:55
     *
     * @param unknown $instance_id            
     */
    public function getExistingMerchantService($instance_id)
    {
        $model = new ConfigModel();
        
        $info = $model->getInfo([
            'instance_id' => $instance_id,
            'key' => 'MERCHANT_SERVICE'
        ], "value");
        if (! empty($info['value'])) {
            $info['value'] = json_decode($info['value'], true);
            if(!empty($info['value'])){
                foreach ($info['value'] as $k => $v) {
                    if (empty($v['title'])) {
                        unset($info['value'][$k]);
                    }
                }
            }
            return $info['value'];
        }
    }

    /**
     * 设置手机端分类显示方式，1:缩略图模式，2：列表模式
     * 创建时间：2018年1月23日15:26:25
     *
     * @param unknown $instanceid            
     * @param unknown $value            
     * @return Ambigous <number, boolean, \think\false, string>
     */
    public function setWapClassifiedDisplayMode($instanceid, $value)
    {
        $res = 0;
        $key = 'WAP_CLASSIFIED_DISPLAY_MODE';
        $config_model = new ConfigModel();
        $info = $config_model->getInfo([
            'key' => $key,
            'instance_id' => $instanceid
        ], 'value');
        
        $data['value'] = $value;
        if (empty($info)) {
            $data['instance_id'] = $instanceid;
            $data['key'] = $key;
            $data['create_time'] = time();
            $data['desc'] = '手机端分类显示方式，1:缩略图模式，2：列表模式';
            $data['is_use'] = 1;
            $res = $config_model->save($data);
        } else {
            
            $data['modify_time'] = time();
            $res = $config_model->save($data, [
                'key' => $key,
                'instance_id' => $instanceid
            ]);
        }
        return $res;
    }

    /**
     * 获取手机端分类显示方式,1:缩略图模式，2：列表模式
     * 创建时间：2018年1月23日15:44:16
     *
     * @param unknown $instanceid            
     */
    public function getWapClassifiedDisplayMode($instanceid)
    {
        $res = 1;
        $key = 'WAP_CLASSIFIED_DISPLAY_MODE';
        $config_model = new ConfigModel();
        $info = $this->config_module->getInfo([
            'key' => $key,
            'instance_id' => $instanceid
        ], 'value');
        if (! empty($info)) {
            $res = $info['value'];
        }
        return $res;
    }

    /**
     * 设置图片水印
     * (non-PHPdoc)
     */
    public function setPictureWatermark($shop_id, $value)
    {
        $water_info = $this->config_module->getInfo([
            "key" => "WATER_CONFIG",
            "instance_id" => $shop_id
        ], "*");
        if (empty($water_info)) {
            $data = array(
                "watermark" => "",
                "transparency" => "",
                "waterPosition" => "",
                "imgWatermark" => ""
            );
            $res = $this->addConfig($shop_id, "WATER_CONFIG", json_encode($data), "图片水印参数配置", 1);
        } else {
            $data = array(
                "value" => $value
            );
            $res = $this->config_module->save($data, [
                "key" => "WATER_CONFIG",
                "instance_id" => $shop_id
            ]);
        }
        return $res;
    }

    /**
     * 获取水印配置
     *
     * @param unknown $instanceid            
     */
    public function getWatermarkConfig($instanceid)
    {
        $water_info = $this->config_module->getInfo([
            "key" => "WATER_CONFIG",
            "instance_id" => $instanceid
        ], "*");
        if (empty($water_info)) {
            $data = array(
                "watermark" => "",
                "transparency" => "100",
                "waterPosition" => "9", // 默认水印在右下角
                "imgWatermark" => ""
            );
            $res = $this->addConfig($instanceid, "WATER_CONFIG", json_encode($data), "图片水印参数配置", 1);
            if (! $res > 0) {
                return null;
            } else {
                $water_info = $this->config_module->getInfo([
                    "key" => "WATER_CONFIG",
                    "instance_id" => $instanceid
                ], "*");
            }
        }
        $value_info = $water_info["value"];
        $value = json_decode($water_info["value"], true);
        return $value;
    }

    /**
     * 设置本地配送时间设置
     */
    function setDistributionTimeConfig($shop_id, $value)
    {
        $instanceid = $this->instance_id;
        $time_info = $this->getDistributionTimeConfig($instanceid);
        if ($time_info == 0) {
            $res = $this->addConfig($instanceid, "DISTRIBUTION_TIME_CONFIG", $value, "本地配送时间设置", 1);
            return $res;
        } else {
            $res = $this->config_module->save([
                'value' => $value
            ], [
                "key" => "DISTRIBUTION_TIME_CONFIG",
                "instance_id" => $shop_id
            ]);
            return $res;
        }
    }

    /**
     * 获取本地配送时间设置
     */
    function getDistributionTimeConfig($instanceid)
    {
        $time_info = $this->config_module->getInfo([
            "key" => "DISTRIBUTION_TIME_CONFIG",
            "instance_id" => $instanceid
        ], "*");
        
        if (empty($time_info)) {
            return 0;
        }
        
        return $time_info;
    }

    /**
     * 设置快捷菜单
     */
    function setShortcutMenu($shop_id, $uid, $menu_ids)
    {
        $model = new SysShortcutMenuModel();
        // 删除原先的
        $del_res = $model->destroy([
            'shop_id' => $shop_id,
            'uid' => $uid
        ]);
        // 添加新的
        $add_arr = explode(',', $menu_ids);
        foreach ($add_arr as $key => $val) {
            $model = new SysShortcutMenuModel();
            $data = [
                'shop_id' => $shop_id,
                'uid' => $uid,
                'module_id' => $val
            ];
            
            $add_res = $model->save($data);
        }
        return $add_res;
    }

    /**
     * 获取快捷菜单
     */
    function getShortcutMenu($shop_id, $uid)
    {
        $model = new SysShortcutMenuModel();
        $condition['shop_id'] = $shop_id;
        $condition['uid'] = $uid;
        $list = $model->getViewList(1, 0, $condition, '');
        return $list;
    }

    /**
     * 获取App升级列表
     * 创建时间：2018年6月11日14:38:00
     *
     * @param number $page_index            
     * @param string $page_size            
     * @param string $condition            
     * @param string $order            
     * @param string $field            
     * @return multitype:number unknown
     */
    function getAppUpgradeList($page_index = 1, $page_size = 0, $condition = "", $order = "id desc", $field = "*")
    {
        $model = new NsAppUpgradeModel();
        $res = $model->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $res;
    }

    /**
     * 添加App升级
     * 创建时间：2018年6月11日14:42:21
     *
     * @param $id 主键id            
     * @param $app_type App类型，Android，IOS            
     *
     * @param $version_number 版本号            
     *
     * @param $download_address app下载地址            
     *
     * @param $update_log 更新日志            
     * @return Ambigous <boolean, number, \think\false, string>
     */
    function editAppUpgrade($id, $title, $app_type, $version_number, $download_address, $update_log)
    {
        $model = new NsAppUpgradeModel();
        $data = array();
        $data['title'] = $title;
        $data['app_type'] = $app_type;
        $data['version_number'] = $version_number;
        $data['download_address'] = $download_address;
        $data['update_log'] = $update_log;
        if ($id == 0) {
            $data['create_time'] = time();
            $res = $model->save($data);
        } else {
            $data['update_time'] = time();
            
            $res = $model->save($data, [
                'id' => $id
            ]);
        }
        return $res;
    }

    /**
     * 删除App升级
     * 创建时间：2018年6月11日14:49:11
     *
     * @param $id 主键id，多个逗号隔开            
     * @return Ambigous <number, unknown>
     */
    function deleteAppUpgrade($id)
    {
        $model = new NsAppUpgradeModel();
        $data['id'] = [
            "in",
            $id
        ];
        $res = $model->destroy($data);
        return $res;
    }

    /**
     * 根据主键id查询App升级信息
     *
     * @param unknown $id            
     * @return unknown
     */
    function getAppUpgradeInfo($id)
    {
        $model = new NsAppUpgradeModel();
        $res = $model->getInfo([
            'id' => $id
        ]);
        return $res;
    }

    /**
     * 获取最新版App信息
     * 创建时间：2018年6月11日16:18:56
     *
     * @param $app_type App类型，Android，IOS            
     * @return Ambigous <string, unknown>
     */
    function getLatestAppVersionInfo($app_type)
    {
        $model = new NsAppUpgradeModel();
        $res = $model->getFirstData([
            'app_type' => $app_type
        ], "id desc");
        return $res;
    }

    /**
     * App欢迎页配置
     * 创建时间：2018年7月10日15:14:47
     * (non-PHPdoc)
     */
    public function setAppWelcomePageConfig($shop_id, $value)
    {
        $key = "APP_WELCOME_PAGE_CONFIG";
        $params[0] = array(
            'instance_id' => $shop_id,
            'key' => $key,
            'value' => $value,
            'desc' => 'App欢迎页配置',
            'is_use' => 1
        );
        $res = $this->setConfig($params);
        return $res;
    }

    /**
     * 获取App欢迎页配置
     * 创建时间：2018年7月10日15:14:47
     * (non-PHPdoc)
     */
    public function getAppWelcomePageConfig($shop_id)
    {
        $key = "APP_WELCOME_PAGE_CONFIG";
        $info = $this->getConfig($shop_id, $key);
        if (empty($info)) {
            $value = array(
                'residence_time' => 5,
                'jump_link' => '',
                'welcome_page_picture' => '',
                'goods_id' => 0
            );
            $params[0] = array(
                'instance_id' => $shop_id,
                'key' => $key,
                'value' => json_encode($value),
                'desc' => 'App欢迎页配置'
            );
            $this->setConfig($params);
            $info = $this->getConfig($shop_id, $key);
        }
        $info['value'] = json_decode($info['value'], true);
        return $info;
    }
}