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
			                        name: 'type',
			                        label: 'Type',
			                        values: webdata.types
			                    },	

			                    {
			                        type: 'textbox',
			                        name: 'price',
			                        label: 'Price',
			                        value: '0'
			                    }, 			                                        
		                    ],
		                    onsubmit: function(e) {                                
                                var amt = e.data.price;
                                var type = e.data.type;
                                var contnt = '';

                                if(type == 'single'){
                                         contnt = '[register_webinar webid=' + e.data.webinarKey +' amount=' + amt + ' type=' + type + ']' + "<br>";
                                }else{
                                	contnt = '[register_webinar type=' + type + ']' + "<br>";
                                }

		                        editor.insertContent(
		                            contnt
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
               
           ]            

        });
    });



})();