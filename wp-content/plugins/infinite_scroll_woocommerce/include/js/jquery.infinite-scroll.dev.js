;(function ( $ ) {

	 	
    $.fn.pantrif_infinite_scroll = function(options) {
	    	var settings = $.extend({
	            // These are the defaults.
	           error: "There was a problem.Please try again.",
	           ajax_method: "method_infinite_scroll",
	           number_of_products: "1",
	           wrapper_result_count: ".woocommerce-result-count", 
	           wrapper_breadcrumb: ".woocommerce-breadcrumb",
	           icon: "",
	           layout_mode: "",
	           load_more_button_animate: "",
	           load_more_button_text: "More",
	           load_more_transition: "",
	           load_prev_button_text: "Previous Page",
	           masonry_bool: "",
	           masonry_item_selector: "li.product",
	           pixels_from_top: "0",
	           selector_next: ".next",
	           selector_prev: ".prev",
			   enable_history: "off",
	           start_loading_x_from_end: "0",
	           wrapper_breadcrumb: ".woocommerce-breadcrumb",
	           wrapper_pagination: ".pagination, .woo-pagination, .woocommerce-pagination, .emm-paginate, .wp-pagenavi, .pagination-wrapper",
	           wrapper_products: "ul.products",
	           wrapper_result_count: ".woocommerce-result-count"
	        },
	         options );

	var $wrapper 				= 	$(settings.wrapper_products),
		$wrapper_initial_height	=	$wrapper.height(),
		masonry_support 		= 	settings.masonry_bool,
		new_class				=   "",
		i						=	0,
		current_page			=   0,
		icon 					=	settings.icon,
		simple_ajax_method		=   settings.ajax_method=="method_simple_ajax_pagination"?true:false,
		pagination_selector 	= 	settings.wrapper_pagination,
		page_points				=   [],
	    page_title				=   $('title').text(),
		currentUrl				= 	window.location.href ,
		flag_load_next_part		=	false,
		history_support			= 	settings.enable_history==="on"?true:false,
		t0,t1,
		flag_load_prev_part		=	false ;	

	 var pantrif_infinite_scroll = {
		init: function(){
			 if($wrapper.length>0 && !simple_ajax_method){
			   //we dont want pagination links
				$(pagination_selector).hide();
			 }
			if(history_support && !simple_ajax_method){
				var stateObj = { scrollTop: $(window).scrollTop(),title:page_title,url:currentUrl};
				page_points.push([current_page++,stateObj]);
				_self.updatePage();
			}
			
			//if ajax method is infinite scroll or load more button
			 if (!simple_ajax_method){
				 $("html").on("click","#isw-load-more-button", function(e){
					 e.preventDefault();
					 _self.products_loop();
				 });		
				 $("html").on("click","#isw-load-more-button-prev", function(e){
					 e.preventDefault();
					 _self.products_loop("",true);
				 });		
					
				 $(_self.element).scroll( function ( event ) {	
				   if($wrapper.length>0){
						var inbottom= _self.isScrolledToBottom(settings.wrapper_products);
						if(inbottom && !flag_load_next_part){ //if pagination is inview and the flag of load next part false
							 if (settings.ajax_method==="method_load_more_button"){
								var next_url = $(settings.selector_next).attr("href");
								//check if there is next link
								if(typeof next_url != 'undefined'){
									$wrapper.append('<div class="isw_load_more_button_wrapper"><a id="isw-load-more-button" href="#">'+settings.load_more_button_text+'</a></div>');
									if(settings.masonry_item_selector.length>0 && masonry_support==="on" ){
										if(typeof Masonry!="undefined"  || typeof Isotope!="undefined"){
											next_button_height = $(".isw_load_more_button_wrapper").height();
											$(".isw_load_more_button_wrapper").css({"position":"absolute","bottom":-next_button_height,"left":"25%"});
											$wrapper.css({"margin-bottom":next_button_height,"overflow":"visible"});
										}
									}
									if (settings.load_more_button_animate==="on" ){
										$(".isw_load_more_button_wrapper").show().find('a').addClass('animated '+settings.load_more_transition);
									}else{
										$(".isw_load_more_button_wrapper").show();
									}
								}
								flag_load_next_part=true;
							 }else{
								_self.products_loop();
							 }
						}
						if(history_support){
							//check if scroll to top
							var intop= _self.isScrolledToTop(settings.wrapper_products);
							if(intop && !flag_load_prev_part){ //if pagination is inview and the flag of load next part false
								 if (!simple_ajax_method){
									//console.log("top");
									var prev_url = $(settings.selector_prev).attr("href");
									//if there is previous page link
									if(typeof prev_url != 'undefined'){
										$wrapper.prepend('<div class="isw_load_more_button_prev_wrapper"><a id="isw-load-more-button-prev" href="#">'+settings.load_prev_button_text+'</a></div>');
										if(settings.masonry_item_selector.length>0 && masonry_support==="on" ){
											if(typeof Masonry!="undefined" || typeof Isotope!="undefined"){
												prev_button_height = $(".isw_load_more_button_prev_wrapper").height();
												$wrapper.css({"top":prev_button_height,"overflow":"visible"});
												$(".isw_load_more_button_prev_wrapper").css({"position":"absolute","top":-(prev_button_height+10),"left":"25%"});
											}
										}
										if (settings.load_more_button_animate==="on" ){
											$(".isw_load_more_button_prev_wrapper").show().find('a').addClass('animated '+settings.load_more_transition);
										}else{
											$(".isw_load_more_button_prev_wrapper").show();
										}
									}
								 }
								flag_load_prev_part=true;
							}
						}
					}
				 });
				 
			 }else{//ajax method: simple ajax navigation
				_self.bind_pagination_clicks();
			 }
		},
		updatePage:function(){
			$(_self.element).scroll( function ( event ) {
				var scrollOffset = $(window).scrollTop();
					var closest_page = _self.closest(scrollOffset,page_points);
					History.replaceState({}, closest_page.title, closest_page.url);

			});
		},
		
		closest: function(num, arr) {
                var curr = arr[0][1].scrollTop;
				var page = arr[0][1];
                var diff = Math.abs (num - curr);
                for (var val = 0; val < arr.length; val++) {
                    var newdiff = Math.abs (num - arr[val][1].scrollTop);
                    if (newdiff < diff) {
                        diff = newdiff;
                        curr = arr[val][1].scrollTop;
						page=  arr[val][1]
                    }
                }
                return page;
        },
		isScrolledIntoPage:function (elem) {
		  var docViewTop = $(window).scrollTop();
		  var docViewBottom = docViewTop + $(window).height();
		  
		  var elemTop = $(elem).offset().top;
		  var elemBottom = elemTop + $(elem).height();

		  return ((elemBottom <= docViewBottom) && (elemTop >= docViewTop));
		},
		addPreviousPage:function(stateObj){
			if(!simple_ajax_method){
				page_points.unshift([0,stateObj]);
				current_page++;
				// update points
				for (var i = 1, l = page_points.length; i < l; i++) {
					page_points[i][0] = page_points[i][0] + 1 ;
					page_points[i][1].scrollTop = page_points[i][1].scrollTop + $wrapper_initial_height;
				}
			}
		},
		addNextPage:function(stateObj){
			if(!simple_ajax_method){
				page_points.push([current_page++,stateObj]);
			}
		},
		/**
		* Function to bind pagination link clicks (Used for simple ajax method)
		**/
		bind_pagination_clicks: function (){
					$pagination_links= $(pagination_selector).find('a');
					$pagination_links.bind("click", function(e){
					e.preventDefault();
					var link = $(this).attr("href");
					_self.products_loop(link,false);
					//return false;
					});
		},
		/** 
		* Function for getting page of products.
		**/
		products_loop: function (url,previous,change_hash){
					 //t0 = performance.now();

					if (typeof previous === 'undefined') { previous=false; }
					if (typeof change_hash === 'undefined') { change_hash=true; }
					//if no url specified
					if (typeof url === 'undefined' || url==="") {
						var url = previous?$(settings.selector_prev).attr("href"):$(settings.selector_next).attr("href"); 
					}
					
					if(previous){
						flag_load_prev_part=true;
					}else{
						flag_load_next_part=true;//make it true in order to run only once this function on scroll
					}
					
					if(typeof url != 'undefined'){//check if url has been set
						if (typeof isw_before_ajax == 'function') {
						  isw_before_ajax();
						}
						$.event.trigger( "isw_before_ajax",[url] );

						var preloader = '<div class="isw_preloader"><img src="'+settings.icon+'"/></div>';

						if(previous){
							$wrapper.prepend(preloader).fadeIn();	
							if (masonry_support==="on" ){
								$(".isw_preloader").css({"position":"absolute","top":0,"left":"0"});
							}
						}else{
							$wrapper.append(preloader).fadeIn();	
							if (masonry_support==="on" ){
								$(".isw_preloader").css({"position":"absolute","bottom":0,"left":"0"});
							}
						}	
											
						 jQuery.get(url , function(data) {
									var $data = $(data);
									var shop_loop = $data.find(settings.wrapper_products);
									page_title = $data.filter('title').text();
									
									if(shop_loop.length>0){	
											new_class="new_item"+ (i++);
											if(settings.masonry_item_selector.length>0 && masonry_support==="on"){
												shop_loop.find(settings.masonry_item_selector).addClass(new_class);
											}
									
											var $new_pagination = $data.find(pagination_selector);
											if (!simple_ajax_method){
												if(previous){
													$(pagination_selector).find(settings.selector_prev).replaceWith($new_pagination.find(settings.selector_prev));
													 $wrapper.prepend(shop_loop.html()).fadeIn();
												}else{
													$(pagination_selector).find(settings.selector_next).replaceWith($new_pagination.find(settings.selector_next));
													$wrapper.append(shop_loop.html()).fadeIn();
												}
												
												//$(pagination_selector).hide().html($new_pagination.html());
											}else{
												//simple ajax pagination
												$wrapper.hide().html(shop_loop.html()).fadeIn();
												$(pagination_selector).html($new_pagination.html());
											}
											
											var $new_results_count = $data.find(settings.wrapper_result_count);
											var $new_breadcrumb = $data.find(settings.wrapper_breadcrumb);
											if($new_results_count.length>0){
												$(settings.wrapper_result_count).html($new_results_count.html());
											}
											if($new_breadcrumb.length>0){
												$(settings.wrapper_breadcrumb).html($new_breadcrumb.html());
											}
									}
									}).done(function() {
										//history state
										if(history_support){
											var stateObj = { scrollTop: $(window).scrollTop()+parseInt(settings.start_loading_x_from_end),title:page_title,url:url};
											if(change_hash){
												if (simple_ajax_method){
													History.pushState(stateObj, page_title, url);
												}else{
													History.replaceState(stateObj, page_title, url);
												}
												$.event.trigger( "infiniteScrollPageChanged",[stateObj] );
											}
											
											if(previous){
													flag_load_prev_part=false;
													_self.addPreviousPage(stateObj);
													 $(".isw_preloader,.isw_load_more_button_prev_wrapper").remove();

											}else{
													flag_load_next_part=false;
													_self.addNextPage(stateObj);
													$(".isw_preloader,.isw_load_more_button_wrapper").remove();
											}
												//console.log(page_points);
											
										}else{
											flag_load_next_part=false;
										}
										$(".isw_preloader,.isw_load_more_button_wrapper").remove();

										
										if (simple_ajax_method){
											_self.bind_pagination_clicks();//bind again click for new pagination links
											if (settings.animate_to_top==="on" ){
												$('html, body').animate({ scrollTop: settings.pixels_from_top }, 500, 'swing');
											}
										}
										
										//if masonry support
										if (settings.masonry_item_selector.length>0 && masonry_support==="on" ){
											 var $block = $wrapper;
											 $newElems= $("."+new_class);
											 if(!simple_ajax_method){
														$newElems.imagesLoaded(function(){
															if (settings.layout_mode==="layout_masonry") {
																if(previous){
																	$block.masonry('prepended', $newElems);
																}else{
																	$block.masonry('appended', $newElems);
																}
															}else{
																if(previous){
																	$block.prepend( $newElems ).isotope( 'reloadItems', $newElems ).isotope();

																}else{
																	$block.isotope('appended', $newElems);
																}
															}
														});
													
											}
										}
										//t1 = performance.now();
										//console.log('Took', (t1 - t0).toFixed(4), 'milliseconds to load next page results');

										if (typeof isw_ajax_done == 'function') { 
											isw_ajax_done(); 
										}
										$.event.trigger( "isw_ajax_done",[new_class] );
								   }).fail(function() {
										$(".isw_preloader,.isw_load_more_button_wrapper").remove();
										$wrapper.hide().html(settings.error).fadeIn();
										if (typeof isw_ajax_fail == 'function') { 
											  isw_ajax_fail(); 
										}
										$.event.trigger( "isw_ajax_fail");
									}).always(function() {
										if (typeof isw_after_ajax == 'function') {
										  isw_after_ajax(); 
										}
										$.event.trigger( "isw_after_ajax");
									   });
					}//end if url exists
					else{
							$(".isw_preloader,.isw_load_more_button_wrapper").remove();
					}
		},
		/**
		*Function to check if element is scrolled to bottom
		**/
		isScrolledToBottom:	function (el){
				if ($(el).length>0){
					if($(window).scrollTop() >= $(el).offset().top + $(el).outerHeight() - window.innerHeight - parseInt(settings.start_loading_x_from_end)) {
						return true;
					}
				}
				return false;
		},
		/**
		*Function to check if element is scrolled to top
		**/
		isScrolledToTop:function (el){
				if ($(el).length>0){
					if($(window).scrollTop() < $(el).offset().top) {
						return true;
					}
				}
				return false;
		}
	 }
	if(options.enable_history==="on"){
		 if ( typeof History.Adapter === 'undefined' ) {
				throw new Error('Infinite scroll plugin require History.js...');
		 }
		// Bind to StateChange Event
		History.Adapter.bind(window,'statechange',function(){ // Note: We are using statechange instead of popstate
			var State = History.getState(); // Note: We are using History.getState() instead of event.state
			//document.title = State.title;
			if(simple_ajax_method && !flag_load_next_part){
				link = document.location.href; 
				_self.products_loop(link, false, false);
			}
		});	 
	}

     var _self = pantrif_infinite_scroll;
	 pantrif_infinite_scroll.element = this;
	 return pantrif_infinite_scroll.init();



 	 };
}( jQuery ));

