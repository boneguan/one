$(function(){
    $(window).scroll(function(){
        var scroll = $("body").scrollTop()||$(document).scrollTop();//兼容IE8、chrome和firefox
        console.log(scroll)
        if(scroll>37){
            $('.navHeader').addClass('active')
        }else{
            $('.navHeader').removeClass('active')
        }
    })
})