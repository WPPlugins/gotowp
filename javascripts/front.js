jQuery(document).ready(function($){
	
	$('.webinar-item .register').on('click',function(e){
		var obj = $(this);
		$('form.webinarregistration').removeClass('current');
		$(this).closest('.webinar-item').find('form.webinarregistration').addClass('current');
		$('form.webinarregistration:not(".current")').hide('slow');		
		$(obj).closest('.webinar-item').find('form.webinarregistration').toggle('slow');		
	});		
	
});