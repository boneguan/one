/**
 * 订单操作中转流程相关操作
 * 修改时间：2017年9月21日 14:32:21 王永杰
 * @param no
 * @param order_id
 */
function operation(no,order_id){
	switch(no){
	case 'pay'://支付
		pay(order_id);
		break;
	case 'close'://订单关闭
		orderClose(order_id);
		break;
	case 'getdelivery'://订单收货
		getdelivery(order_id);
		break;
	case 'refund'://申请退款
		orderRefund(order_id);
		break;
	case 'delete_order'://删除订单
		delete_order(order_id);
		break;
	case 'logistics' ://查看物流
		logistics(order_id);
		break;
	case 'pay_presell' : //预定金支付
		pay_presell(order_id);
		break;
	case 'member_pickup' : //提货
		member_pickup(order_id);
		break;
	}
}
/**
 * 微信支付
 * @param order_id
 */
function pay(order_id){
	//去支付
	window.location.href = __URL(SHOPMAIN+"/order/orderPay?id="+order_id);
}

/**
 * 预定金去支付
 */
function pay_presell(order_id){
	window.location.href = __URL(SHOPMAIN+"/order/orderPresellPay?id="+order_id);
}

/**
 * 查看物流
 */
function logistics(order_id){
	window.location.href = __URL(SHOPMAIN+ "/member/seeLogistics?orderid="+order_id);
}

/**
 * 订单交易关闭
 * @param order_id
 */
function orderClose(order_id){
	$( "#dialog" ).dialog({
		buttons: {
			"确定": function() {
				$.ajax({
					type : "post",
					url : __URL(SHOPMAIN+"/order/orderClose"),
					data : { "order_id" : order_id },
					success : function(data) {
						if(data["code"] > 0 ){
							$.msg("操作成功");
							location.href=__URL(SHOPMAIN+"/member/orderlist?status=0");
						}else{
							showMessage('error', "订单关闭失败！", location.href);
						}
					}
				})
				$(this).dialog('close');
			},
			"取消,#f5f5f5,#666": function() {
				$(this).dialog('close');
			},
		},
	contentText:"确定关闭订单吗？",
	});
}

/**
 * 订单收货
 * @param order_id
 */
function getdelivery(order_id){
	$.ajax({
		type : "post",
		url : __URL(SHOPMAIN+"/order/orderTakeDelivery"),
		data : { "order_id" : order_id },
		success : function(data) {
			if(data["code"] > 0 ){
				$.msg("收货成功");
				location.href=__URL(SHOPMAIN+"/member/orderlist?status=3");
			}else{
				showMessage('error', "订单收货失败！", location.href);
			}
		}
	})
}

//删除订单
function delete_order(order_id){
	$( "#dialog" ).dialog({
		buttons: {
			"确定": function() {
				$.ajax({
					type : "post",
					url : __URL(SHOPMAIN+"/order/deleteOrder"),
					data : {"order_id" : order_id},
					success : function(data) {
						if(data["code"] > 0 ){
							showMessage('success', data["message"], location.href);
						}else{
							showMessage('error', "订单删除失败！", location.href);
						}
					}
				});
				$(this).dialog('close');
			},
			"取消,#f5f5f5,#666": function() {
				$(this).dialog('close');
			},
		},
		contentText:"确定要删除订单吗？",
	});
}

function member_pickup(order_id){
	$.ajax({
		type : "post",
		url : __URL(SHOPMAIN+"/order/memberPickup"),
		data : {"order_id" : order_id},
		success : function(data) {
			if(data['code'] > 0){
				if(data['path'] != ""){
					$(".pickup-code-layer .layer-wrap img").attr('src', __IMG(data['path']));
					$(".pickup-code-layer").show();
				}else{
					showMessage('error', "提货码生成失败！", location.href);
				}
			}else{
				showMessage('error', data["message"], location.href);
			}
		}
	});
}