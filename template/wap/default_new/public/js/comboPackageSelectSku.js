/**
 * 商品详情相关
 * 选择加入购物车，立即购买，商品限购等操作
 * 2017-01-07
 */
$(function() {
	// 是否下架
	if ($("#is_sale").val() != 1) {
		$(".js-shelves").css("display","block");
		$(".js-bottom-opts").css("display","none");
	}
	echoSpecData();
	// 点击确定触发事件
	$('#submit_ok').bind("click",function() {
		if($("#uid").val() == null || $("#uid").val() == ""){
			window.location.href = __URL(APPMAIN+ "/login");
		}else{
			if($(this).hasClass("disabled")) return;
			if ($("#hiddStock").val() == 0) {
				showBox("商品已售罄","warning");
			} else {
				var trueId = "";
				var count = "";
				var $uiskuprop = $(".s-buy-ul .right button");
				var $uiskupropCount = $(".s-buy-ul .s-buy-li").length - 1;
				var flag = 0;
				$($uiskuprop).each(function() {
					flag = $(this).hasClass("current") ? flag + 1: flag; // 判断所有规格是否都选完整了
				});
				if ($uiskupropCount === flag) {
					var combo_id = $("#submit_ok").attr("combo_id");
					var sku_info = new Array(); //选中的规格信息
					sku_info.curr_sku_id = $("#hiddSkuId").val();
					sku_info.curr_stock = $("#specification input[skuid='" + sku_info.curr_sku_id + "']").attr("stock");
					sku_info.curr_price = $("#specification input[skuid='" + sku_info.curr_sku_id + "']").attr("member_price");
					sku_info.curr_goods_id = $("#specification input[skuid='" + sku_info.curr_sku_id + "']").attr("goods_id");
					sku_info.curr_sku_name =  $("#specification input[skuid='" + sku_info.curr_sku_id + "']").attr("skuname");
					
					var img_src = $("#spec_picture_id" + $("#default_img").val()).val();	

					$("#combo_id_"+combo_id).find("[class='goods_"+sku_info.curr_goods_id+"']").attr({"price":sku_info.curr_price,"stock":sku_info.curr_stock,"sku_id":sku_info.curr_sku_id,"skuname":sku_info.curr_sku_name});
					$("#combo_id_"+combo_id).find("[class='goods_"+sku_info.curr_goods_id+"']").prev(".data_info").find(".select_sku_"+sku_info.curr_goods_id).text("已选规格："+sku_info.curr_sku_name);
					$("#combo_id_"+combo_id).find("[class='goods_"+sku_info.curr_goods_id+"']").prev(".data_info").find("span.price").text("￥"+sku_info.curr_price);
					$("#combo_id_"+combo_id).find("[class='goods_"+sku_info.curr_goods_id+"']").parent(".goods_info").find(".pic").attr('src',img_src);

					$(".combo_package_content .combo_package_name input[type='checkbox']").prop("checked",false);
					$("#combo_id_"+combo_id+" .combo_package_name").find("input[type='checkbox']").prop("checked",true);

					ini_combo_package_price(combo_id);
					$("#mask").hide();
					$("#s_buy").slideUp(300);
				} else {
					showBox("请选择完整的商品规格","warning");
				}
			}
		}
	});
	
	$("#mask,#icon_close,#complete").on("click", function() {
		$("#s_buy").slideUp(300);
		$(".bottom_popup").slideUp(300);
		$("#mask").hide();
		$('body').css("overflow", "auto");
		$(".js-bottom-opts").show();
	});
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

// 样式选择事件
function change(span) {
	$('button[name=' + $(span).attr('name') + ']').each(function() {
		$(this).removeClass("current");
	});
	$(span).addClass("current");
	
	//判断是否有SKU主图
	if(parseInt($(span).attr("data-picture-id")) !=0){
		$("#default_img").val($(span).attr("data-picture-id"));
		$(".js-thumbnail").attr("src",$("#spec_picture_id" + $(span).attr("data-picture-id")).val());
	}
	
	echoSpecData();
}

//改变数据
function echoSpecData(){
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
				$("#submit_ok").addClass("disabled");
				$("#num").val(1);
			}else{
				if(parseInt($("#num").val()) > stock){
					$("#num").val(stock);
				}
				$("#submit_ok").removeClass("disabled");
			}
			$("#Stock").text("剩余" + stock + "件");
			$("#num").attr("max", stock);
			$("#hiddStock").val(stock);
			$("#hiddSkuId").val(select_skuid);
			$("#hiddSkuName").val(select_skuName);
			active = $("#submit_ok").attr("tag");
			if (active == 'addCart' || active == 'buyBtn1') {
				price = $this.attr("price");
				$("#price").text("￥" + price);
				$("#hiddSkuprice").val(price);
			} else if (active == "groupbuy") {
			}
		}
		
	});
}

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

};
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