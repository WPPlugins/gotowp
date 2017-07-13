function getShortcode(webid){
	return '[register_webinar type=single webid=' + webid + ']';
}


jQuery(document).ready(function($){
	
/*	jQuery('#webinarprices').validate({
		ignore:".ignore",
		rules:{
			check_webinar_price:{required:true}
		}
		
	});	*/
	
	//$(".tabholder").msTabs({tabs:'li', defaultTab:0, effects:'fade', speed:'fast', selected:'active'}).data("msTabs");
	
	$('#tab-container').easytabs();

	var clipboard = new Clipboard('.webinar-shortcode .copyshortcode', {
	    text: function(trigger) {
	    	var webid = $(trigger).closest('.webinar-list-details').attr('data-webid');
		 	var shcode = getShortcode(webid);
	        return shcode;
	    }
	});	

	clipboard.on('success', function(e) {
	    $(e.trigger).text('Copied');
	    setTimeout(function(){ $(e.trigger).text('Copy Shortcode'); }, 3000);
	    e.clearSelection();
	});	
	
	
    $("#messagesettings").validate({
    	errorElement:"p",
    	rules:{
    		subject_name:{required:true},
    		message:{required:true},
    		replyto:{required:true,email:true}
    	}
    });
    
    $("#emailsettings").validate({
    	errorElement:"p",
    	rules:{
    		email_new:{required:true,email:true}
    	},
    	errorPlacement: function(error, element) {
           $(element).closest('ul').find('.error-msgs').html(error);

        }
    });
	
	$('#messagesettings #message_submit').click(function(e){
		var subject=$.trim($('#messagesettings #subject_name').val());
		var message=$.trim($('#messagesettings #message').val());
		var replyto=$.trim($('#messagesettings #replyto').val());
		    if($("#messagesettings").valid()){
		    	$.ajax({
		    		url:ajaxurl ,
		    		async:false,
		    		type:"POST",
		    		data:{action:'gotowp_personal_message_action',subject:subject,message:message,replyto:replyto},
		    		success:function(rData){
		    			$('.wrap #tab2_content .msgs').hide();
		    		  if(rData =='yes'){
		    			  $('#tab2_content .message_updated_success').show();
		    		  }else{
		    			  $('#tab2_content .message_updated_error').show();
		    		  }
		    		  
		    		  setTimeout(function(){$('.wrap #tab2_content .msgs').fadeOut();},5000);
		    		}
		    	});
		    }		
		
		return false;
	});
	
	
	
	$('#emailsettings #email_new_submit').click(function(e){
		var email_new=$.trim($('#emailsettings #email_new').val());

		    if($("#emailsettings").valid()){
		    	$.ajax({
		    		url:ajaxurl ,
		    		async:false,
		    		type:"POST",
		    		data:{action:'gotowp_personal_new_email_action',email_new:email_new},
		    		success:function(rData){
		    			$('#email_new').val('');
		    			// console.log(rData);		
		    		  if(rData.flag =='yes'){
		    			  $('#emailsettings #emailaddresses').append('<li><input type="text" size="41"  value="'+rData.email_val+'" name="'+rData.email_id+'"  id="'+rData.email_id+'"/> <input type="button" name="edit_email_submit" class="edit_email_submit"  value="Save"/> <input type="button" name="remove_email_submit" class="remove_email_submit"  value="Remove"/></li>');
		    			  $('#tab2_content .new_email_added_success').show();
		    		  }else{
		    			  $('#tab2_content .new_email_added_error').show();
		    		  }
		    		  setTimeout(function(){$('.wrap #tab2_content .msgs').fadeOut();},5000);
		    		},
		    		dataType:'json'
		    	});
		    }else{
		    	setTimeout(function(){$('.wrap .tabsCntents #emailsettings .error-msgs p.error').fadeOut();},5000);
		    }		
		
		return false;
	});
	
	
	$('#emailsettings .remove_email_submit').click(function(e){		
		var obj=$(this);
		var email_opt_id=$.trim($(this).closest('li').find('input[type="text"]').attr('id'));

		    if(email_opt_id !=''){
		    	$.ajax({
		    		url:ajaxurl ,
		    		async:false,
		    		type:"POST",
		    		data:{action:'gotowp_personal_remove_email_action',email_opt_id:email_opt_id},
		    		success:function(rData){
		    			$('.wrap #tab2_content .msgs').hide();
		    		  if(rData =='yes'){
		    			  $(obj).closest('li').remove();
		    			  $('#tab2_content .new_email_removed_success').show();
		    		  }else{
		    			  $('#tab2_content .new_email_added_error').show();
		    		  }
		    		  setTimeout(function(){$('.wrap #tab2_content .msgs').fadeOut();},5000);
		    		}
		    	});
		    }
		
		
		return false;
	});	
		
	
	$('#emailsettings .edit_email_submit').click(function(e){		
		//var obj=$(this);
		var email_opt_id=$.trim($(this).closest('li').find('input[type="text"]').attr('id'));
		var new_email_val=$.trim($(this).closest('li').find('input[type="text"]').val());

		    if(email_opt_id !='' && new_email_val!=''){
		    	$.ajax({
		    		url:ajaxurl ,
		    		async:false,
		    		type:"POST",
		    		data:{action:'gotowp_personal_edit_email_action',email_opt_id:email_opt_id,new_email_val:new_email_val},
		    		success:function(rData){
		    			$('.wrap #tab2_content .msgs').hide();
		    		  if(rData =='yes'){
		       			  $('#tab2_content .new_email_edited_success').show();
		    		  }else{
		    			  $('#tab2_content .new_email_added_error').show();
		    		  }
		    		  setTimeout(function(){$('.wrap #tab2_content .msgs').fadeOut();},5000);
		    		}
		    	});
		    }
		
		
		return false;
	});		
	
	
});