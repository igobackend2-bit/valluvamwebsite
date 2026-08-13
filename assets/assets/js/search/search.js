<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
$(document).ready(function(){

  // Run search while typing
  $("#search").on("keyup", function(){
    var query = $(this).val();

    $.ajax({
      url: "assets/db_query/search/search_query.php",   // PHP file for searching
      method: "POST",
      data: {query: query},
      success: function(data){
        $("#product-results").html(data); // show results
      }
    });
  });

});
