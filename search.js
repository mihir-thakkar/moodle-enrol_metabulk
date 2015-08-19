<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script type="text/javascript">
$(document).keypress(function (e) {
	var key = e.which;
	if(key == 13) {
		$('input[name = links_searchbutton]').trigger("click");
		return false;  
	}
});
</script>