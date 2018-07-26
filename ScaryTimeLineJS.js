$(function() {
    $(document).ready(function(){
        $.ajax({
            url:"http://ec2-18-182-149-89.ap-northeast-1.compute.amazonaws.com/twitterApi/ScaryTimeLineAPI.php",
            type: "post",
            dataType : "json",
        }).done(function(re){
            for(var i = 0; i< re.length; i++){
                $('#scary-timeline').prepend('<div class=Inner-scary><p>'+re[i]+'</p></div>');
            }
        }).fail(function(re){
            $('#scary-timeline').text('失敗')
        });
    });
});