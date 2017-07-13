function getShortcode(webid){
	return '[register_free_webinar webid=' + webid + ']';
}

function getTrainingShortcode(webid){
	return '[register_free_training id=' + webid + ']';
}



jQuery(document).ready(function($){
	$('#tab-container').easytabs();	
	
	var clipboard = new Clipboard('.webinar-shortcode .copyshortcode', {
	    text: function(trigger) {
	    	var webid = $(trigger).closest('.table-row').attr('data-webid');
		 	var shcode = getShortcode(webid);
	        return shcode;
	    }
	});	

	clipboard.on('success', function(e) {
	    $(e.trigger).text('Copied');
	    setTimeout(function(){ $(e.trigger).text('Copy Shortcode'); }, 3000);
	    e.clearSelection();
	});	


	var clipboard2 = new Clipboard('.training-shortcode .copyshortcode', {
	    text: function(trigger) {
	    	var webid = $(trigger).closest('.table-row').attr('data-webid');
		 	var shcode = getTrainingShortcode(webid);
	        return shcode;
	    }
	});	

	clipboard2.on('success', function(e) {
	    $(e.trigger).text('Copied');
	    setTimeout(function(){ $(e.trigger).text('Copy Shortcode'); }, 3000);
	    e.clearSelection();
	});	

});