$(document).keypress(function (e) {
	var key = e.which;
	if(key == 13) {
		$('input[name = links_searchbutton]').trigger("click");
		return false;
	}
});