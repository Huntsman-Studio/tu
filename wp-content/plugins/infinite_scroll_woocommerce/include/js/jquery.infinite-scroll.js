/**
 * @plugin Infinite-scroll-woocommerce
 * @version 1.4
 * @author Leonidas Maroulis
 */
!function(e){e.fn.pantrif_infinite_scroll=function(o){var t=e.extend({error:"There was a problem.Please try again.",ajax_method:"method_infinite_scroll",number_of_products:"1",wrapper_result_count:".woocommerce-result-count",wrapper_breadcrumb:".woocommerce-breadcrumb",icon:"",layout_mode:"",load_more_button_animate:"",load_more_button_text:"More",load_more_transition:"",load_prev_button_text:"Previous Page",masonry_bool:"",masonry_item_selector:"li.product",pixels_from_top:"0",selector_next:".next",selector_prev:".prev",enable_history:"off",start_loading_x_from_end:"0",wrapper_breadcrumb:".woocommerce-breadcrumb",wrapper_pagination:".pagination, .woo-pagination, .woocommerce-pagination, .emm-paginate, .wp-pagenavi, .pagination-wrapper",wrapper_products:"ul.products",wrapper_result_count:".woocommerce-result-count"},o),r=e(t.wrapper_products),n=r.height(),a=t.masonry_bool,i="",_=0,l=0,s=(t.icon,"method_simple_ajax_pagination"==t.ajax_method?!0:!1),p=t.wrapper_pagination,d=[],c=e("title").text(),f=window.location.href,u=!1,m="on"===t.enable_history?!0:!1,w=!1,h={init:function(){if(r.length>0&&!s&&e(p).hide(),m&&!s){var o={scrollTop:e(window).scrollTop(),title:c,url:f};d.push([l++,o]),b.updatePage()}s?b.bind_pagination_clicks():(e("html").on("click","#isw-load-more-button",function(e){e.preventDefault(),b.products_loop()}),e("html").on("click","#isw-load-more-button-prev",function(e){e.preventDefault(),b.products_loop("",!0)}),e(b.element).scroll(function(o){if(r.length>0){var n=b.isScrolledToBottom(t.wrapper_products);if(n&&!u)if("method_load_more_button"===t.ajax_method){var i=e(t.selector_next).attr("href");"undefined"!=typeof i&&(r.append('<div class="isw_load_more_button_wrapper"><a id="isw-load-more-button" href="#">'+t.load_more_button_text+"</a></div>"),t.masonry_item_selector.length>0&&"on"===a&&("undefined"!=typeof Masonry||"undefined"!=typeof Isotope)&&(next_button_height=e(".isw_load_more_button_wrapper").height(),e(".isw_load_more_button_wrapper").css({position:"absolute",bottom:-next_button_height,left:"25%"}),r.css({"margin-bottom":next_button_height,overflow:"visible"})),"on"===t.load_more_button_animate?e(".isw_load_more_button_wrapper").show().find("a").addClass("animated "+t.load_more_transition):e(".isw_load_more_button_wrapper").show()),u=!0}else b.products_loop();if(m){var _=b.isScrolledToTop(t.wrapper_products);if(_&&!w){if(!s){var l=e(t.selector_prev).attr("href");"undefined"!=typeof l&&(r.prepend('<div class="isw_load_more_button_prev_wrapper"><a id="isw-load-more-button-prev" href="#">'+t.load_prev_button_text+"</a></div>"),t.masonry_item_selector.length>0&&"on"===a&&("undefined"!=typeof Masonry||"undefined"!=typeof Isotope)&&(prev_button_height=e(".isw_load_more_button_prev_wrapper").height(),r.css({top:prev_button_height,overflow:"visible"}),e(".isw_load_more_button_prev_wrapper").css({position:"absolute",top:-(prev_button_height+10),left:"25%"})),"on"===t.load_more_button_animate?e(".isw_load_more_button_prev_wrapper").show().find("a").addClass("animated "+t.load_more_transition):e(".isw_load_more_button_prev_wrapper").show())}w=!0}}}}))},updatePage:function(){e(b.element).scroll(function(o){var t=e(window).scrollTop(),r=b.closest(t,d);History.replaceState({},r.title,r.url)})},closest:function(e,o){for(var t=o[0][1].scrollTop,r=o[0][1],n=Math.abs(e-t),a=0;a<o.length;a++){var i=Math.abs(e-o[a][1].scrollTop);n>i&&(n=i,t=o[a][1].scrollTop,r=o[a][1])}return r},isScrolledIntoPage:function(o){var t=e(window).scrollTop(),r=t+e(window).height(),n=e(o).offset().top,a=n+e(o).height();return r>=a&&n>=t},addPreviousPage:function(e){if(!s){d.unshift([0,e]),l++;for(var o=1,t=d.length;t>o;o++)d[o][0]=d[o][0]+1,d[o][1].scrollTop=d[o][1].scrollTop+n}},addNextPage:function(e){s||d.push([l++,e])},bind_pagination_clicks:function(){$pagination_links=e(p).find("a"),$pagination_links.bind("click",function(o){o.preventDefault();var t=e(this).attr("href");b.products_loop(t,!1)})},products_loop:function(o,n,l){if("undefined"==typeof n&&(n=!1),"undefined"==typeof l&&(l=!0),"undefined"==typeof o||""===o)var o=n?e(t.selector_prev).attr("href"):e(t.selector_next).attr("href");if(n?w=!0:u=!0,"undefined"!=typeof o){"function"==typeof isw_before_ajax&&isw_before_ajax(),e.event.trigger("isw_before_ajax",[o]);var d='<div class="isw_preloader"><img src="'+t.icon+'"/></div>';n?(r.prepend(d).fadeIn(),"on"===a&&e(".isw_preloader").css({position:"absolute",top:0,left:"0"})):(r.append(d).fadeIn(),"on"===a&&e(".isw_preloader").css({position:"absolute",bottom:0,left:"0"})),jQuery.get(o,function(o){var l=e(o),d=l.find(t.wrapper_products);if(c=l.filter("title").text(),d.length>0){i="new_item"+_++,t.masonry_item_selector.length>0&&"on"===a&&d.find(t.masonry_item_selector).addClass(i);var f=l.find(p);s?(r.hide().html(d.html()).fadeIn(),e(p).html(f.html())):n?(e(p).find(t.selector_prev).replaceWith(f.find(t.selector_prev)),r.prepend(d.html()).fadeIn()):(e(p).find(t.selector_next).replaceWith(f.find(t.selector_next)),r.append(d.html()).fadeIn());var u=l.find(t.wrapper_result_count),m=l.find(t.wrapper_breadcrumb);u.length>0&&e(t.wrapper_result_count).html(u.html()),m.length>0&&e(t.wrapper_breadcrumb).html(m.html())}}).done(function(){if(m){var _={scrollTop:e(window).scrollTop()+parseInt(t.start_loading_x_from_end),title:c,url:o};l&&(s?History.pushState(_,c,o):History.replaceState(_,c,o),e.event.trigger("infiniteScrollPageChanged",[_])),n?(w=!1,b.addPreviousPage(_),e(".isw_preloader,.isw_load_more_button_prev_wrapper").remove()):(u=!1,b.addNextPage(_),e(".isw_preloader,.isw_load_more_button_wrapper").remove())}else u=!1;if(e(".isw_preloader,.isw_load_more_button_wrapper").remove(),s&&(b.bind_pagination_clicks(),"on"===t.animate_to_top&&e("html, body").animate({scrollTop:t.pixels_from_top},500,"swing")),t.masonry_item_selector.length>0&&"on"===a){var p=r;$newElems=e("."+i),s||$newElems.imagesLoaded(function(){"layout_masonry"===t.layout_mode?n?p.masonry("prepended",$newElems):p.masonry("appended",$newElems):n?p.prepend($newElems).isotope("reloadItems",$newElems).isotope():p.isotope("appended",$newElems)})}"function"==typeof isw_ajax_done&&isw_ajax_done(),e.event.trigger("isw_ajax_done",[i])}).fail(function(){e(".isw_preloader,.isw_load_more_button_wrapper").remove(),r.hide().html(t.error).fadeIn(),"function"==typeof isw_ajax_fail&&isw_ajax_fail(),e.event.trigger("isw_ajax_fail")}).always(function(){"function"==typeof isw_after_ajax&&isw_after_ajax(),e.event.trigger("isw_after_ajax")})}else e(".isw_preloader,.isw_load_more_button_wrapper").remove()},isScrolledToBottom:function(o){return e(o).length>0&&e(window).scrollTop()>=e(o).offset().top+e(o).outerHeight()-window.innerHeight-parseInt(t.start_loading_x_from_end)?!0:!1},isScrolledToTop:function(o){return e(o).length>0&&e(window).scrollTop()<e(o).offset().top?!0:!1}};if("on"===o.enable_history){if("undefined"==typeof History.Adapter)throw new Error("Infinite scroll plugin require History.js...");History.Adapter.bind(window,"statechange",function(){History.getState();s&&!u&&(link=document.location.href,b.products_loop(link,!1,!1))})}var b=h;return h.element=this,h.init()}}(jQuery);