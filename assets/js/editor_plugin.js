(function() {

    var webdata = {};
    jQuery.extend({
        getwebData: function() {
            var theResponse = null;
            jQuery.ajax({
                url: ajaxurl,
                data:{action:'gotowp_personal_webdata_action'},
                dataType: "json",
                async: false,
                success: function(respText) {
                    theResponse = respText;
                }
            });
            return theResponse;
        }
    });

    var webdata = jQuery.getwebData();

    // if(webdata.webinars){

    tinymce.PluginManager.add('mce_gotowp_button', function(editor, url) {
        editor.addButton('mce_gotowp_button', {
        	title: 'GoToWP',
        	type: 'menubutton',
            icon: 'false',
            text: 'GoToWP',
            menu: [
                {
                    text: 'Webinars',
		            onclick: function() {

		            	if(webdata.webinars){

		                editor.windowManager.open({
		                    title: 'Insert Webinar Shortcode',
		                    body: [
			                    {
			                        type: 'listbox',
			                        name: 'webinarKey',
			                        label: 'Select Webinar',
			                        values: webdata.webinars
			                    }, 
			                    {
			                        type: 'listbox',
			                        name: 'pageId',
			                        label: 'Redirect Page',
			                        values: webdata.pages
			                    },	                    
		                    ],
		                    onsubmit: function(e) {
		                        editor.insertContent(
		                            '[register_free_webinar webid=' + e.data.webinarKey +' pageid=' + e.data.pageId + ']' + "<br>"
		                        );
		                    }
		                });
		            }else{
			                editor.windowManager.open({
			                    title: 'Insert Webinar Shortcode',
			                    body: [
				                    {
				                        type: 'container',
				                        html: 'No Webinars available',
				                    }, 
                    
			                    ]
			                });		            	
		            }


		            }
                },
                {
                    text: 'Trainings',
		            onclick: function() {
		            	if(webdata.trainings){
			                editor.windowManager.open({
			                    title: 'Insert Training Shortcode',
			                    body: [
				                    {
				                        type: 'listbox',
				                        name: 'trainingKey',
				                        label: 'Select Training',
				                        values: webdata.trainings
				                    }, 
				                    {
				                        type: 'listbox',
				                        name: 'pageId',
				                        label: 'Redirect Page',
				                        values: webdata.pages
				                    },	                    
			                    ],
			                    onsubmit: function(e) {
			                        editor.insertContent(
			                            '[register_free_training id=' + e.data.trainingKey +' pageid=' + e.data.pageId + ']' + "<br>"
			                        );
			                    }
			                });
		              }else{
			                editor.windowManager.open({
			                    title: 'Insert Training Shortcode',
			                    body: [
				                    {
				                        type: 'container',
				                        html: 'No trainigs available',
				                    }, 
                    
			                    ]
			                });		              	
		              }
		            }
                }                
           ]            

        });
    });

//  }

})();