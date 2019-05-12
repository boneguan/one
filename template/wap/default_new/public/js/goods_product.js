/**
 * 商品详情相关
 * 选择加入购物车，立即购买，商品限购等操作
 * 2017-01-07
 */
var is_full_payment = 0;
$(function() {
	is_full_payment = $('#is_full_payment').is(":checked");
	
	$('#is_full_payment').click(function(){
		var is_full_payment = $('#is_full_payment').is(":checked");
		if(!is_full_payment){
			price = $("#hidden_presell").val();
			$("#price").text("￥" + price);
			$("#hiddSkuprice").val(price);
		}else{
			price =parseFloat($("#goods_sku0").attr("price"));
			$("#price").text("￥" + price);
			$("#hiddSkuprice").val(price);
		}
	})
	
	// 是否下架
	if ($("#is_sale").val() != 1) {
		$(".js-shelves").css("display","block");
		$(".js-bottom-opts").css("display","none");
	}
	echoSpecData();
	// 点击确定触发事件  //选择地址触发事件
	$('#submit_ok,.address_block').bind("click",function() {
		if($("#uid").val() == null || $("#uid").val() == ""){
			window.location.href = __URL(APPMAIN+ "/login");
		}else{

			// if($(this).hasClass("disabled")) return;
			
			if ($("#hiddStock").val() == 0) {
				showBox("商品已售罄","warning");
			} else {

				if($(this).attr("tag") == "address_tag"){
					var address_tag = $(this).attr("tag");
					var address_id = $(this).attr("data-address-id");
					var distribution_type = $(this).attr("type");
					$(".address_block").removeClass("select_address");
					$(this).addClass("select_address");
					if(address_id == null || address_id == '' || address_id == 0 || address_id == undefined){
						if(distribution_type == "logistics"){
							showBox("请选择收货地址","warning");
						}else if(distribution_type == "pickup"){
							showBox("请选择自提地址","warning");
						}
						return;
					}
				}
				var trueId = "";
				var count = "";
				var $uiskuprop = $(".s-buy-ul .right button");
				var $uiskupropCount = $(".s-buy-ul .s-buy-li").length - 1;
				var flag = 0;
				$($uiskuprop).each(function() {
					flag = $(this).hasClass("current") ? flag + 1: flag; // 判断所有规格是否都选完整了
				});
				if ($uiskupropCount === flag) {

					var purchaseSum = $("#max_buy").val() * 1;
					var currentNum = $("#hidden_current_num").val() * 1;
					var num = $("#num").val() * 1;
					var nummax = $("#num").attr("max") * 1;
					if(currentNum!=0 && currentNum == purchaseSum){
						showBox("此商品限购，您最多可购买"+ purchaseSum+ goods_unit,"warning");
						return;
					}
					if (num >= 1) {
						if (num <= nummax) {
							if (num <= purchaseSum|| purchaseSum == 0) {
								var cart_detail = new Object();
								cart_detail.trueId = $("#hiddPDetailID").val();
								cart_detail.count = $("#num").val();
								cart_detail.goods_name = $("#itemName").text();
								cart_detail.select_skuid = $("#hiddSkuId").val();
								cart_detail.shop_id = $("#hidden_shop_id").val();
								cart_detail.shop_name = $("#hidden_shop_name").val();
								cart_detail.select_skuName = $("#hiddSkuName").val();
								cart_detail.price = $("#hiddSkuprice").val();
								//没有SKU商品，获取第一个
								if(cart_detail.select_skuid==null||cart_detail.select_skuid==""){
									cart_detail.select_skuid = $("#goods_sku0").attr("skuid");
									cart_detail.select_skuName = $("#goods_sku0").attr("skuname");
									cart_detail.price = $("#goods_sku0").attr("price");
								}
								cart_detail.picture = $("#default_img").val();
								cart_detail.cost_price = $("#cost_price").text();
								if($('#is_full_payment').is(":checked") == true){
									cart_detail.is_full_payment = 1;
								}else{
									cart_detail.is_full_payment = 0;
								}
								var cart_tag = $("#submit_ok").attr("tag");
								
								//选择地址触发事件
								if(address_tag == "address_tag"){
									
									var skuid = $("#hiddSkuId").val();
									var num = $("#num").val();

									//没有SKU商品，获取第一个
									if(skuid == null || skuid == "") skuid = $("#goods_sku0").attr("skuid");
									getGoodsPurchaseRestrictionForCurrentUser($("#goods_id").val(),num,function(purchase){
										if(purchase.code>0){
											
											$.ajax({
												url : __URL(APPMAIN + "/Goods/addBargainLaunch"),
												type : "post",
												data : { "tag" : "addBargain", "sku_id" : skuid, "num" : num, "address_id" : address_id,"bargain_id" : $("#hidden_bargain_id").val(), "distribution_type" : distribution_type },
												success : function(res){
													
													window.location.href = __URL(APPMAIN+"/Goods/bargainLaunch?launch_id="+res+"");
												}
											});
										}else{
											showBox(purchase.message);
										}
									});
								}
								if(cart_tag == "addCart"){
									$.ajax({
										url :  __URL(APPMAIN + "/goods/addcart"),
										type : "post",
										data : { "cart_detail" : JSON.stringify(cart_detail), "cart_tag" : cart_tag },
										success : function(data) {

											$('body').css("overflow", "auto");
											if(data.code>0){

												$("#s_buy").slideUp();
												$("#mask").hide();
												$("#addcart_way").show();
												$("#addcart_way").addClass("addcart-way");
												if ($("#submit_ok").attr("tag") == "addCart") {
													var new_count = $("#countcart").text()* 1+ cart_detail.count* 1;
													$("#icon_tip_code").show();
													$("#countcart").show();
													$("#countcart").text(new_count);
												}
												$("#loading").hide();
												$(".js-bottom-opts").show();
												$("#global-cart").addClass("buy-cart-msg");
												// 添加购物车
												if ($("#submit_ok").attr("tag") == "addCart") {
													if (purchaseSum != 0) {
														var tempCoun = purchaseSum - count;
														if (tempCoun == 0) {
															$("#purchaseSum").val(-1);
														} else {
															$("#purchaseSum").val(purchaseSum- count);
														}
													}
													$('#submit_ok').show();
													showBox("加入购物车成功","warning");
												}
												$("#loading").hide();
											}else if(data.code == -1){
												showBox("只有会员登录之后才能购买，请进入会员中心注册或登录。","warning",__URL(APPMAIN+ "/member"));
											}else if(data.code == 0){
												showBox(data.message,"error");
											}
										}
									});
								}else if(cart_tag == "spelling"){
									//拼单
									//立即购买
									var skuid = $("#hiddSkuId").val();
									var num = $("#num").val();
									//没有SKU商品，获取第一个
									if(skuid == null || skuid == "") skuid = $("#goods_sku0").attr("skuid");
									getGoodsPurchaseRestrictionForCurrentUser($("#goods_id").val(),num,function(purchase){
										if(purchase.code>0){
											$.ajax({
												url : __URL(APPMAIN + "/PintuanOrder/ordercreatesession"),
												type : "post",
												data : { "tag" : "spelling", "sku_id" : skuid, "num" : num, "goods_type" : $("#hidden_goods_type").val(),"tuangou_group_id" : $("#hidden_tuangou_group_id").val() },
												success : function(res){
													window.location.href = __URL(APPMAIN+"/PintuanOrder/paymentorder");
												}
											});
										}else{
											showBox(purchase.message);
										}
									});
								}else if(cart_tag == 'bargainBtn'){
									//砍价
									//立即砍价
									
									$("#s_buy").slideUp(300);
									$("body").css({ overflow : "hidden"});
									$("#mask").show();
									$(".bottom_popup[data-popup-type='address_popup']").slideDown(300);
									//没有SKU商品，获取第一个
									if(skuid == null || skuid == "") skuid = $("#goods_sku0").attr("skuid");
									
								}else if(cart_tag == "buyBtn1" || cart_tag == "theSelected"){
									//立即购买
									var skuid = $("#hiddSkuId").val();
									var num = $("#num").val();
									//没有SKU商品，获取第一个
									if(skuid == null || skuid == "") skuid = $("#goods_sku0").attr("skuid");
									getGoodsPurchaseRestrictionForCurrentUser($("#goods_id").val(),num,function(purchase){
										if(purchase.code>0){
											$.ajax({
												url : __URL(APPMAIN + "/order/ordercreatesession"),
												type : "post",
												data : { "tag" : "buy_now", "sku_id" : skuid, "num" :num, "goods_type" : $("#hidden_goods_type").val() },
												success : function(res){
													window.location.href = __URL(APPMAIN+"/order/paymentorder");
												}
											});
										}else{
											showBox(purchase.message,"error");
										}
									});
								}else if(cart_tag == "goods_presell"){
									//立即购买
									var skuid = $("#hiddSkuId").val();
									var num = $("#num").val();
									//没有SKU商品，获取第一个
									if(skuid == null || skuid == "") skuid = $("#goods_sku0").attr("skuid");
									getGoodsPurchaseRestrictionForCurrentUser($("#goods_id").val(),num,function(purchase){
										if(purchase.code>0){
											$.ajax({
												url : __URL(APPMAIN + "/order/ordercreatesession"),
												type : "post",
												data : { "tag" : "goods_presell", "sku_id" : skuid, "num" :num, "goods_type" : $("#hidden_goods_type").val()},
												success : function(res){
													window.location.href = __URL(APPMAIN+"/order/paymentorder");
												}
											});
										}else{
											showBox(purchase.message,"error");
										}
									});
								}else if(cart_tag == "groupbuy"){
									//团购
									var skuid = $("#hiddSkuId").val();
									var num = $("#num").val();
									//没有SKU商品，获取第一个
									if(skuid == null || skuid == "") skuid = $("#goods_sku0").attr("skuid");
									getGoodsPurchaseRestrictionForCurrentUser($("#goods_id").val(),num,function(purchase){
										if(purchase.code>0){
											$.ajax({
												url : __URL(APPMAIN + "/order/ordercreatesession"),
												type : "post",
												data : { "tag" : "groupbuy", "sku_id" : skuid, "num" :num, "goods_type" : $("#hidden_goods_type").val() },
												success : function(res){
													window.location.href = __URL(APPMAIN+"/order/paymentorder");
												}
											});
										}else{
											showBox(purchase.message,"error");
										}
									});
								}else if(cart_tag == "js_point_exchange"){
									//积分兑换
									var skuid = $("#hiddSkuId").val();
									var num = $("#num").val();
									//没有SKU商品，获取第一个
									if(skuid == null || skuid == "") skuid = $("#goods_sku0").attr("skuid");
									getGoodsPurchaseRestrictionForCurrentUser($("#goods_id").val(),num,function(purchase){
										if(purchase.code>0){
											$.ajax({
												url : __URL(APPMAIN + "/order/ordercreatesession"),
												type : "post",
												data : { "tag" : "js_point_exchange", "sku_id" : skuid, "num" :num, "goods_type" : $("#hidden_goods_type").val() },
												success : function(res){
													window.location.href = __URL(APPMAIN+"/order/paymentorder");
												}
											});
										}else{
											showBox(purchase.message,"error");
										}
									});
								}
							} else {
								if (purchaseSum <= 0)  purchaseSum = 0;
								showBox("此商品限购，您最多可购买"+ purchaseSum+ goods_unit,"warning");
							}
						} else {
							showBox("库存不足","warning");
						}
					} else {
						showBox("商品的数量至少为1","warning");
					}
				} else {
					showBox("请选择完整的商品规格","warning");
				}
			}
		}
	});


	$("#addCart,#buyBtn1,#spelling,#theSelected,#groupbuy,#js_point_exchange,#goods_presell,#bargainBtn").on("click",function(e) {

		flag = parseInt($("#is_sale").val());
		$(".motify").css("opacity",0);
		$(".motify").fadeIn();
		$(".motify").fadeOut();
		if (flag == 1) {

			var tag_id = $(this).attr("id");
			if(tag_id != "theSelected"){
				$("#submit_ok").attr("tag",tag_id);
			}
			echoSpecData();
			$("body").css({ overflow : "hidden"});
			$(".bar_wrap").hide();
			$("#mask").show();
			$("#s_buy").slideDown(300);
			$("#addcart_way").removeClass("addcart-way");
			
			//库存等于0变成灰色
			if ($("#hiddStock").val() == 0) {
				$("#submit_ok").addClass("disableds");
			}
			// 加入购物车
			if (tag_id == 'addCart') {
				$(".js-bottom-opts").hide();
				$("#submit_ok").text("加入购物车");
			} else if(tag_id == 'buyBtn1') {
				$(".js-bottom-opts").hide();
				$("#submit_ok").text("下一步");
			}else if(tag_id == 'theSelected'){
				$(".js-bottom-opts").hide();
				$("#submit_ok").text("下一步");
			}else if(tag_id == 'groupbuy'){
				$(".js-bottom-opts").hide();
				$("#submit_ok").text("下一步");

			}else if(tag_id == 'goods_presell'){
				$(".js-bottom-opts").hide();
				$("#submit_ok").text("下一步");

			}else if(tag_id == 'js_point_exchange'){
				$(".js-bottom-opts").hide();
				$("#submit_ok").text("下一步");

			} else if(tag_id == 'bargainBtn'){
				$("#num").attr("max",1);
				$("#submit_ok").text("下一步");

			} 
		} else {
			showBox("该商品已下架","warning");
		}
	});
	
	$("#mask,#icon_close,.bottom_popup_button").on("click", function() {
		$("#s_buy").slideUp(300);
		$(".bottom_popup").slideUp(300);
		$("#mask").hide();
		$('body').css("overflow", "auto");
		$(".js-bottom-opts").show();
	});
	
	$(".add").click(function() {
		var num = $("#num").val() * 1;
		var max_buy = $("#max_buy").val() * 1;
		var nummax = $("#num").attr('max') * 1;
		if (num >= max_buy && max_buy != 0) {
			var buy_num = max_buy;
			if (max_buy == -1) {
				buy_num = 0;
			}
			$(this).addClass("quantity-minus-disabled");
			showBox("此商品限购，您最多可购买" + buy_num + goods_unit,"warning");
		} else if (num < nummax) {
			num = num + 1;
			$(this).removeClass("quantity-minus-disabled");
		}
		$(".reduce").removeClass("quantity-minus-disabled");
		$("#num").val(num);
	});
	
	/**
	 * 数量减少
	 */
	$(".reduce").click(function() {
		var num = $("#num").val() * 1;
		var min_buy = $("#min_buy").val() * 1;
		var count = min_buy != 0 ? min_buy : 1;
		// if(count){
		// 	showBox("此商品最少购买" + count + goods_unit);
		// }
		if (num > count) {
			num -= 1;
			if (num == 1) {
				$(this).addClass("quantity-minus-disabled");
			}
		} else {
			$(this).addClass("quantity-minus-disabled");
		}
		$(".add").removeClass("quantity-minus-disabled");
		$("#num").val(num);
	});
	
	$("#num").bind("input propertychange", function() {
		if($(this).val().indexOf(".") != -1){
			$(this).val(1);
		}else{
			var num = $(this).val() * 1;
			var max_buy = $("#max_buy").val() * 1;
			var min_buy = $("#min_buy").val() * 1;
			var nummax = $(this).attr('max') * 1;
			if(min_buy !=0 && min_buy>num){
				showBox("此商品最少购买" + min_buy + goods_unit,"warning");
				num = min_buy;
			}
			if (num >= max_buy && max_buy != 0) {
				showBox("此商品限购，您最多可购买" + max_buy + goods_unit,"warning");
				num = max_buy;
			} else if (num > nummax) {
				num = nummax;
			}
			if (isNaN(num)) {
				num = 1;
			}
			$(this).val(num);
		}
	});
	
	$('#btnShare').bind("click",function() {
		var topheight = document.body.scrollTop;
		var scrollHeight = document.body.scrollHeight;
		$("#mask-bg").attr("style","height:" + (scrollHeight + topheight) + "px");
		$("#mask-content").attr("style","padding-top:" + topheight + "px");
		$("#mask-bg").show();
		$("#mask-content").show();
		document.addEventListener('touchmove',preventNo, false);
	});

	$('#mask-bg').bind("click",function() {
		$("#mask-bg").hide();
		$("#mask-content").hide();
		document.removeEventListener('touchmove',preventNo, false);
	});

	$('#mask-content').bind("click",function() {
		$("#mask-bg").hide();
		$("#mask-content").hide();
		document.removeEventListener('touchmove',preventNo, false);
	});

	// huxl
	$("#distribution-apply").click(function(event) {
		event.preventDefault();
		$("#distribution-tip").fadeIn();
		setTimeout(function() {
			$("#distribution-tip").fadeOut();
		}, 4000)
	});

	// close advertisement
	$("#advertisement-close").click(function() {
		$("#advertisement-apptip").hide();
		$("#fromesb-wechat").animate({ top : 0 });
	})

	// contact float
	$("#contFloat").click(function(event) {
		event.preventDefault();
		$("#contFloat-detail").show();
	})

	$("#contFloat-detail-close").click(function() {
		$("#contFloat-detail").hide();
	})

	$("#mask,#icon_close").click(function() {
		$("#s_buy").slideUp();
		$("#mask").hide();
		$('body').css("overflow", "auto");
	})

	//弹出优惠劵框
	$("#receive_coupons").click(function(){
		$("body").css({ overflow : "hidden"});
		$("#mask").show();
		$(".bottom_popup[data-popup-type='receive_coupons']").slideDown(300);
	})

	// 商品属性
	$("#goods-attribute").click(function(){
		$("body").css({ overflow : "hidden"});
		$("#mask").show();
		$(".bottom_popup[data-popup-type='goods-attribute']").slideDown(300);
	})

	//领取优惠劵
	var is_click = false;
	$(".js-coupon").on("click",function(){
		if($("#uid").val() == null || $("#uid").val() == ""){
			window.location.href = __URL(APPMAIN+ "/login");
		}else{
			var coupon_type_id = $(this).attr("data-coupon-id");
			var data_max_fetch = parseInt($(this).attr("data-max-fetch"));//最大领取数
			var data_receive_quantity = parseInt($(this).attr("data-receive-quantity"));//当前用户领取数
			if(data_max_fetch != 0 && data_receive_quantity>= data_max_fetch){
				showBox("您的领取已达到上限","warning");
				$(this).addClass("received");
				return false;
			}
			var $this = $(this);
			if(is_click){
				return false;
			}
			is_click = true;
			$.ajax({
				url : __URL(APPMAIN+"/goods/receiveGoodsCoupon"),
				type : "post",
				async: false,
				data : { "coupon_type_id" : coupon_type_id},
				success : function(res){
					is_click = false;
					if(res['code']>0){
						$($this).attr("data-receive-quantity",data_receive_quantity+1);
						showBox("领取成功","warning");
					}else if(res['code'] == -2011){
						$($this).addClass("received");
						showBox("来迟了，已经领完了","warning");
						return false;
					}else{
						showBox(res['message'],"error");
					}
				}
			})
		}
	})

	//弹出阶梯优惠信息框
	$("#ladder_preferential").click(function(){
		$("body").css({ overflow : "hidden"});
		$("#mask").show();
		$(".bottom_popup[data-popup-type='ladder_preferential']").slideDown(300);
	})

	//弹出阶梯优惠信息框
	$("#store_service").click(function(){
		$("body").css({ overflow : "hidden"});
		$("#mask").show();
		$(".bottom_popup[data-popup-type='store_service']").slideDown(300);
	})
})

function CheckInt(obj) {
	var pattern = /^[1-9]\d*|0$/; // 匹配非负整数
	if (!pattern.test(obj)) {
		return false;
	} else {
		return true;
	}
}

function preventNo(e) {
	e.preventDefault();
}

var specificationValueDatas = {};
var productDatas = {};
var obj = {
	Span0 : "",
	Span1 : "",
	Span2 : "",
	Span3 : "",
	Span4 : ""
};

//改变数据
function echoSpecData(){
	if(judge_is_all_selected()){
		var specificationValueSelecteds = '';
		var spec_array = new Array();
		var $specificationValueSelected = $(".s-buy-ul .right button");
		$specificationValueSelected.each(function(i) {
			var $this = $(this);
			if ($this.hasClass("current")) {
				specificationValueSelecteds += $this.attr("id") + ";";
				spec_array.push($this.attr("id"));
			}
		});
		spec_array.sort();
		$(".sku-array").each(function(i) {
			var sku_array =new Array();
			var $this = $(this);
			var value = $(this).val();
			if(value != ""){
				sku_array = value.split(";");
			}
			sku_array.sort();
			if(JSON.stringify(sku_array) == JSON.stringify(spec_array)){
				select_skuid = $this.attr("skuid");
				select_skuName = $this.attr("skuname");
				stock = parseInt($this.attr("stock"));
				if(stock==0){
					$("#submit_ok").addClass("disableds");
					$("#num").val(1);
				}else{
					if(parseInt($("#num").val()) > stock){
						$("#num").val(stock);
					}
					$("#submit_ok").removeClass("disableds");
				}
				$("#Stock").text("剩余" + stock + goods_unit);
				$("#num").attr("max", stock);
				$("#hiddStock").val(stock);
				$("#hiddSkuId").val(select_skuid);
				$("#hiddSkuName").val(select_skuName);
				//当前已选
				if(select_skuName != null){
					var selected = select_skuName;
					if($("#num").val() > 0){
						selected += '&nbsp;'+$("#num").val() + goods_unit;
					}
					$("#theSelected .selected").html(selected);
					$("#theSelected").show();
					$("#theSelected").next(".with-margin-l").show();
				}
				
				active = $("#submit_ok").attr("tag");
				if (active == 'addCart' || active == 'buyBtn1' || active == 'theSelected' || active == 'js_point_exchange') {
					price = $this.attr("price");
					$("#price").text("￥" + price);
					$("#hiddSkuprice").val(price);	
				}else if(active == "spelling"){
					price = $("#hidden_pintuan_price").val();
					$("#price").text("￥" + price);
					$("#hiddSkuprice").val(price);
				}else if (active == "groupbuy") {
					price = $("#hide_group_buy_price").val();
					$("#price").text("￥" + price);
					$("#hiddSkuprice").val(price);
				}else if(active == 'bargainBtn'){
					$("#num").attr("max",1).val(1);
				}
			}
		});
	}else{
		$("#hiddSkuId").val("");
		var active = $("#submit_ok").attr("tag");
		if (active == 'addCart' || active == 'buyBtn1' || active == 'theSelected' || active == 'js_point_exchange' || active == 'bargainBtn') {
			var price_num = price_section_arr.length;
			if(price_num > 1 && price_section_arr[0] != price_section_arr[price_num -1]){
				$("#price").text("￥" + price_section_arr[0] + '-' + price_section_arr[price_num - 1]);
			}
		}
	}
}

$('#is_full_payment').click(function(){
	var is_full_payment = $('#is_full_payment').is(":checked");
	if(!is_full_payment){
		price = $("#hidden_presell").val();
		$("#price").text("￥" + price);
		$("#hiddSkuprice").val(price);
	}else{
		price = $this.attr("price");
		$("#price").text("￥" + price);
		$("#hiddSkuprice").val(price);
	}
})

function imgview() {
	var arr = $("#imgs").val();
	var c = arr.substring(0, arr.length - 1).split(',');
	var index = $("#imgpage").text().split('/') - 1;
	if (typeof window.WeixinJSBridge != 'undefined') {
		WeixinJSBridge.invoke("imagePreview", {
			current : c[index],
			urls : c
		});
	}
}

function showPic() {
	$("#content").html(hdata);
	$("#p-detailoff").hide();
	$("#p-detail").show();
}

window.onload = function() {
	if (typeof window.WeixinJSBridge != 'undefined') {
		document.addEventListener("WeixinJSBridgeReady", onWeixinReady, false);
	} else {
		$("#p-detailoff").show();
	}
}
function onWeixinReady() {
	WeixinJSBridge.invoke('getNetworkType', {}, function(e) {
		WeixinJSBridge.log(e.err_msg);
		var state = e.err_msg.split(':')[1];
		if (state == "wifi") {
			$("#content").html(hdata);
			$("#p-detail").show();
		} else {
			$("#p-detailoff").show();
		}
	});
}

function getGoodsPurchaseRestrictionForCurrentUser(goods_id,num,callBack){
	$.ajax({
		type : "post",
		url : __URL(SHOPMAIN+"/goods/getGoodsPurchaseRestrictionForCurrentUser"),
		async : false,
		data : { "goods_id" : goods_id, "num" : num },
		success : function(res){
			if(callBack) callBack(res);
		}
	});
}