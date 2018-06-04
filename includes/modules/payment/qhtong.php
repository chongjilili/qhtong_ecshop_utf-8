<?php

/**
 * ECSHOP 智付支付插件
 * ============================================================================
 * 版权所有 2005-2010 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: ---- $
 * $Id: cncard.php 17063 2010-03-25 06:35:46Z  $
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$payment_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/payment/qhtong.php';

if (file_exists($payment_lang))
{
    global $_LANG;

    include_once($payment_lang);
}

/**
 * 模块信息
 */
if (isset($set_modules) && $set_modules == TRUE)
{
    $i = isset($modules) ? count($modules) : 0;

    /* 代码 */
    $modules[$i]['code']    = basename(__FILE__, '.php');

    /* 描述对应的语言项 */
    $modules[$i]['desc']    = 'qhtong_desc';

    /* 是否支持货到付款 */
    $modules[$i]['is_cod']  = '0';

    /* 是否支持在线支付 */
    $modules[$i]['is_online']  = '1';

    /* 作者 */
    $modules[$i]['author']  = 'qhtong';

    /* 网址 */
    $modules[$i]['website'] = '';

    /* 版本号 */
    $modules[$i]['version'] = '1.0';

    /* 配置信息 */
    $modules[$i]['config'] = array(
        array('name' => 'qhtong_mid', 'type' => 'text',   'value' => ''),
        array('name' => 'qhtong_mkey',  'type' => 'text',   'value' => ''),
        array('name' => 'qhtong_rurl',  'type' => 'text',   'value' => ''),
        array('name' => 'qhtong_nurl',  'type' => 'text',   'value' => ''),
    );

    return;
}

class qhtong
{
    /**
     * 构造函数
     *
     * @access  public
     * @param
     *
     * @return void
     */
    function __construct()
    {
        $this->qhtong();
    }

    function qhtong()
    {
    }

    /**
     * 生成支付代码
     * @param   array   $order      订单信息
     * @param   array   $payment    支付方式信息
     */
    function get_code($order, $payment)
    {

        $version    = '1.0'; //版本号
        $customerid = $payment['qhtong_mid']; //商户号
        $total_fee  = number_format($order['order_amount'],2,'.',''); //付款金额 最少1元
        $sdorderno  = $order['order_sn']; //商户订单号不超过30
        
        $returnurl = trim($payment['qhtong_rurl']); //同步通知
        $notifyurl = trim($payment['qhtong_nurl']); //异步通知
        if(stripos($returnurl, 'http://') === false && stripos($returnurl, 'https://') === false) {            
            $returnurl  = return_url(basename(__FILE__, '.php'));
        } 
        if(stripos($notifyurl, 'http://') === false && stripos($notifyurl, 'https://') === false) {
            $notifyurl  = return_url(basename(__FILE__, '.php'));
        }
        
        $paytype    = 'bank'; //支付类型 bank
        //$bankcode = 'BOCSH'; //银行编号 ICBC
        $remark     = '';
        $sign       = md5('version='.$version.'&customerid='.$customerid.'&total_fee='.$total_fee.'&sdorderno='.$sdorderno.'&notifyurl='.$notifyurl.'&returnurl='.$returnurl.'&'.$payment['qhtong_mkey']);

        $def_url = '<form name="pay" action="http://www.qihuoc.com/apisubmit" method="post" target="_blank">'.            
                   '<select name="bankcode"><option value="BOCSH">中国银行</option><option value="ICBC">中国工商银行</option><option value="ABC">中国农业银行</option><option value="CCB">建设银行</option><option value="CMB">招商银行</option><option value="SPDB">浦发银行</option><option value="GDB">广发银行</option><option value="BOCOM">交通银行</option><option value="PSBC">邮政储蓄银行</option><option value="CNCB">中信银行</option><option value="CMBC">民生银行</option><option value="CEB">光大银行</option><option value="HXB">华夏银行</option><option value="CIB">兴业银行</option><option value="BOS">上海银行</option><option value="SRCB">上海农商</option><option value="PAB">平安银行</option><option value="BCCB">北京银行</option></select>'.
                   "<input type=\"hidden\" name=\"version\" value=\"{$version}\" />".
                   "<input type=\"hidden\" name=\"customerid\" value=\"{$customerid}\" />".
                   "<input type=\"hidden\" name=\"sdorderno\" value=\"{$sdorderno}\" />".
                   "<input type=\"hidden\" name=\"total_fee\" value=\"{$total_fee}\" />".
                   "<input type=\"hidden\" name=\"paytype\" value=\"{$paytype}\" />".
                   "<input type=\"hidden\" name=\"notifyurl\" value=\"{$notifyurl}\" />".
                   "<input type=\"hidden\" name=\"returnurl\" value=\"{$returnurl}\" />".
                   "<input type=\"hidden\" name=\"remark\" value=\"{$remark}\" />".
                   "<input type=\"hidden\" name=\"sign\" value=\"{$sign}\" />".
                   "&nbsp;<input type=\"submit\" value=\"{$GLOBALS['_LANG']['qhtong_button']}\" />";
                   '</form>';
        
		return $def_url;        
    }

    /**
     * 响应操作
     */

    function respond()
    {
        $payment = get_payment('qhtong');

        //异步通知
        $input_str = file_get_contents('php://input');
        $input = array();
        if(!empty($input_str)) {
            $input = (array)json_decode($input_str, true);
        }

        if(isset($_GET['status'])) { //return url
            $status     = $_GET['status'];
            $customerid = $_GET['customerid'];
            $sdorderno  = $_GET['sdorderno'];
            $total_fee  = $_GET['total_fee'];
            $paytype    = $_GET['paytype'];
            $sdpayno    = $_GET['sdpayno'];
            $remark     = $_GET['remark'];
            $sign       = $_GET['sign'];            
            $mysign     = md5('customerid='.$customerid.'&status='.$status.'&sdpayno='.$sdpayno.'&sdorderno='.$sdorderno.'&total_fee='.$total_fee.'&paytype='.$paytype.'&'.$payment['qhtong_mkey']);
        } else if(isset($input['status'])) { //notify url            
            $status     = $input['status'];
            $customerid = $input['customerid'];
            $sdorderno  = $input['sdorderno'];
            $total_fee  = $input['total_fee'];
            $paytype    = $input['paytype'];
            $sdpayno    = $input['sdpayno'];
            $remark     = $input['remark'];
            $sign       = $input['sign'];            
            $mysign     = md5('customerid='.$customerid.'&status='.$status.'&sdpayno='.$sdpayno.'&sdorderno='.$sdorderno.'&total_fee='.$total_fee.'&paytype='.$paytype.'&'.$payment['qhtong_mkey']);
        }        
        if(isset($_GET['status']) || isset($input['status'])) {
            if($sign==$mysign){
                if($status=='1'){
                    $log_id = get_order_id_by_sn($sdorderno);
                    if($log_id != ""){
                        $f = check_money($log_id, $total_fee);
                        if($f){
                            order_paid($log_id);
                            if(isset($input['status']) && $input['status']=='1') {
                                exit('success');
                            }
                            return true;
                        }else{
                            return false;
                        }
                    }else{
                        return false;
                    }
                } else {
                    //echo 'fail';
                    return false;
                }
            } else {
                //echo 'signerr';
                return false;
            }
        }        
    }
}

?>