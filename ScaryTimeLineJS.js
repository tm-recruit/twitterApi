$(function() {
    $(document).ready(function(){
        $.ajax({
            url:"18.182.149.89/srv/api/ScaryTimeLineAPI.php",
            type:"post"
        }).done(function(re){
            result = JSON.parse(re);
        });
    });
});