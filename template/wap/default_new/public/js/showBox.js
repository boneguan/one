
function showBox(str,type,url) {
	//类型 success：成功  error：失败  warning：警告
	var show_type = type != undefined && type.length > 0 ? type : "warning"; 
	$(".motify").css("opacity",0.9);
	$(".motify").fadeIn("slow");
	$(".motify-inner").text(str);
	$(".motify .show_type").attr("class","show_type").addClass(show_type);
	setTimeout(function() {
		if(url != undefined && url.length > 0){
			$(".motify").fadeOut("slow",function(){
				location.href = url;
			});
		}else{
			$(".motify").fadeOut("slow");
		}
	}, 1500);
}