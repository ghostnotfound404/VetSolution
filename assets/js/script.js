$(document).ready(function() {
    $(".nav-link").click(function() {
        $(".nav-link").removeClass("active");
        $(this).addClass("active");
        $("#contenido").load($(this).attr("id") + ".php");
    });
});
