<?php
/**
* Plugin Name: Infinite scroll for woocommerce
* Plugin URI: http://codecanyon.net/item/infinite-ajax-scroll-woocommerce/9343295
* Description: Infinite Scroll Plugin for your woocommerce eshop.
* Version: 1.4
* Text Domain: infinite-scroll-woocommerce
* Author: Leonidas Maroulis
* Author URI: http://www.maroulis.net.gr
**/


class InfiniteScrollWoocommerce {
    public $version = '20151128'; // Latest version release date Year-Month-Day
	public $url = ''; // URL of plugin installation
	public $path = ''; // Path of plugin installation
	public $file = ''; // Path of this file
    public $settings; // Settings object
	//settings variables
	public $number_of_products 			= "";
	public $icon 						= "";
	public $ajax_method		 			= "";//Prefered ajax method -- Infinite scroll | Load More | Simple 
	public $load_more_button_animate 	= "";//checkbox on - off
	public $load_more_button_transision = "";//animation type
	public $wrapper_result_count		= "";//wrapper for pagination
	public $wrapper_breadcrumb  		= "";//wrapper for pagination
	public $wrapper_products 			= "";//wrapper for products
	public $wrapper_pagination			= "";//wrapper for pagination
	public $selector_next				= "";//selector next
	public $selector_prev				= "";//selector previous
	public $load_more_button_text		= "";//text of load more button
	public $load_more_button_prev_text	= "";//text of load previous button
	public $animate_to_top				= "";//animate to top on/off
	public $pixels_from_top				= "";//pixels from top number
	public $start_loading_x_from_end	= "";
	public $masonry_bool				= "";
	public $masonry_item_selector		= "";
	public $layout_mode					= "";
	public $enable_history 				= "";

	
	function __construct() {
        $this->file = __file__;
        $this->path = dirname($this->file) . '/';
        $this->url = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__file__)) . '/';
		require_once ($this->path . 'include/php/settings.php');
		$this->settings = new InfiniteWoocommerceScrollSettings($this->file);
		$this->number_of_products = get_option('isw_number_of_products', '8');
		$preloader= wp_get_attachment_thumb_url(get_option('isw_preloader_icon'))==""?$this->url."include/icons/ajax-loader.gif":wp_get_attachment_thumb_url(get_option('isw_preloader_icon'));
		
		$this->load_more_button_text		= get_option('isw_button_text')==""?__("More", "infinite-scroll-woocommerce"):get_option('isw_button_text');
		$this->load_more_button_prev_text	= get_option('isw_button_text_prev')==""?__("Previous Page", "infinite-scroll-woocommerce"):get_option('isw_button_text_prev');
		
		$this->icon 						= $preloader;
		$this->ajax_method					= get_option('isw_ajax_method');
		$this->wrapper_result_count 		= get_option('isw_wrapper_result_count')==""?".woocommerce-result-count":get_option('isw_wrapper_result_count');		
		$this->wrapper_breadcrumb	 		= get_option('isw_wrapper_breadcrumb')==""?".woocommerce-breadcrumb":get_option('isw_wrapper_breadcrumb');
		$this->wrapper_products 			= get_option('isw_products_selector')==""?"ul.products":get_option('isw_products_selector');
		$this->wrapper_pagination 			= get_option('isw_pagination_selector')==""?".pagination, .woo-pagination, .woocommerce-pagination, .emm-paginate, .wp-pagenavi, .pagination-wrapper":get_option('isw_pagination_selector');
		$this->selector_next 				= get_option('isw_next_page_selector')==""?".next":get_option('isw_next_page_selector');		
		$this->selector_prev 				= get_option('isw_prev_page_selector')==""?".prev,.previous":get_option('isw_prev_page_selector');		
		$this->load_more_button_animate 	= get_option('isw_load_more_button_animate');		
		$this->load_more_button_transision  = get_option('isw_animation_method_load_more_button');		
		$this->animate_to_top				= get_option('isw_animate_to_top');	
		$this->pixels_from_top				= get_option('isw_pixels_from_top')==""?"0":get_option('isw_pixels_from_top');
		$this->start_loading_x_from_end		= get_option('isw_start_loading_x_from_end')==""?"0":get_option('isw_start_loading_x_from_end');
		$this->masonry_bool					= get_option('isw_masonry_bool');	
		$this->masonry_item_selector		= get_option('isw_masonry_item_selector')==""?"li.product":get_option('isw_masonry_item_selector');
		$this->layout_mode					= get_option('isw_layout_mode');
		$this->enable_history				= get_option('isw_enable_history');	

		
		
		add_action('woocommerce_before_shop_loop', array($this, 'before_products'), 3);
		//add_action('woocommerce_after_shop_loop', array($this, 'after_products'), 40);
		// Wrap shop pagination 
		add_action('woocommerce_pagination', array($this, 'before_pagination'), 3);
		add_action('woocommerce_pagination', array($this, 'after_pagination'), 40);
		add_action('plugins_loaded', array($this,'configLang'));
		// Register frontend scripts and styles
		add_action('wp_enqueue_scripts', array($this,'register_frontend_assets'));
		add_action('wp_enqueue_scripts', array($this, 'load_frontend_assets'));
		add_action('wp_enqueue_scripts', array($this, 'localize_frontend_script_config'));
		
    }
	public function version() {
        return $this->version;
    }
	public function register_frontend_assets() {
        // Add frontend assets in footer
		if($this->enable_history=="on"){
			wp_register_script('history-isw', $this->url . 'include/js/jquery.history.js', array('jquery'), false, true);
		}
		$suffix = ( WP_DEBUG ) ? '.dev' : '';
        wp_register_script('js-plugin-isw', $this->url . 'include/js/jquery.infinite-scroll'.$suffix.'.js', array('jquery'), false, true);
		wp_register_script('js-init-isw', $this->url . 'include/js/custom.js', array('jquery'), false, true);
		wp_register_style('ias-animate-css', $this->url . 'include/css/animate.min.css');
		wp_register_style('ias-frontend-style', $this->url . 'include/css/style.css');
		//wp_register_style('ias-frontend-custom-style', $this->url . 'include/css/style.php');
    }
	public function load_frontend_assets() {
		//load all scripts
		wp_enqueue_script( 'history-isw' );
        wp_enqueue_script( 'js-plugin-isw' );
		wp_enqueue_script( 'js-init-isw' );
		wp_enqueue_style( 'ias-animate-css' );
		wp_enqueue_style( 'ias-frontend-style' );
		$inline_style = "
			#isw-load-more-button,#isw-load-more-button-prev {
			background: ". get_option('isw_button_background').";
			color: ". get_option('isw_button_color').";
			padding: ".get_option('isw_button_padding')."px;
			width: ". get_option('isw_button_width')."px;
			height: ". get_option('isw_button_height')."px;
			margin-bottom: 10px;
			border-radius: ". get_option('isw_button_border_radius')."px;
			border: ". get_option('isw_button_border_width')."px solid  ". get_option('isw_button_border_color') .";
			font-size: ". get_option('isw_button_font_size')."px;
		}
		#isw-load-more-button:hover,#isw-load-more-button-prev:hover {
			background: ". get_option('isw_button_background_hover').";
			color: ". get_option('isw_button_color_hover').";
		}
		 ". get_option('isw_css_code');
	 
		wp_add_inline_style( 'ias-frontend-style', $inline_style );
		//wp_enqueue_style( 'ias-frontend-custom-style' );
    }
	public function localize_frontend_script_config() {
        $handle = 'js-init-isw';
        $object_name = 'options_isw';
	    $error = __('There was a problem.Please try again.', "infinite-scroll-woocommerce");
        $l10n = array(
			'error' 						=>	$error,		
			'ajax_method'					=>  $this->ajax_method,
            'number_of_products' 			=>	$this->number_of_products,		
			'wrapper_result_count'	 		=>	$this->wrapper_result_count,			
			'wrapper_breadcrumb'	 		=>	$this->wrapper_breadcrumb,
			'wrapper_products'	 			=>	$this->wrapper_products,
			'wrapper_pagination'	 		=>	$this->wrapper_pagination,
			'selector_next'	 				=>	$this->selector_next,
			'selector_prev'	 				=>	$this->selector_prev,
			'icon' 							=>	$this->icon,
			'load_more_button_text' 		=>	$this->load_more_button_text,
			'load_prev_button_text' 		=>	$this->load_more_button_prev_text,
			'load_more_button_animate'		=>  $this->load_more_button_animate,
			'load_more_transition'			=>  $this->load_more_button_transision,
			'animate_to_top'				=>  $this->animate_to_top,
			'pixels_from_top'				=>  $this-> pixels_from_top, 
			'start_loading_x_from_end'		=>  $this-> start_loading_x_from_end,
			'masonry_bool'					=>  $this-> masonry_bool,
			'masonry_item_selector'			=>  $this-> masonry_item_selector,
			'layout_mode'					=>  $this-> layout_mode,
			'enable_history'				=>	$this-> enable_history,
            'paged' 						=> 	(get_query_var('paged')) ? get_query_var('paged') : 1
        );
        wp_localize_script($handle, $object_name, $l10n);
    }
	public function before_products() {
		if ($this->ajax_method!='method_simple_ajax_pagination'){
			//remove Result Count
			remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
		}
        //echo '<div class="isw-shop-loop-wrapper">';
    }
    public function after_products() {
        $html = '</div>';
		echo $html;
    }
	public function configLang(){
		$lang_dir = basename(dirname(__FILE__)). '/languages';
		load_plugin_textdomain( 'infinite-scroll-woocommerce', false, $lang_dir );
	}
	public function before_pagination($template_name = '', $template_path = '', $located = '') {
        echo '<div class="isw-shop-pagination-wrapper">';
    }
    public function after_pagination($template_name = '', $template_path = '', $located = '') {
        echo '</div>';
    }
	public function set_number_of_product_items_per_page(){
		add_filter( 'loop_shop_per_page', create_function( '$cols', "return $this->number_of_products;" ), $this->number_of_products );
	}
	
}
$woocommerce_isw = new InfiniteScrollWoocommerce();
$woocommerce_isw -> set_number_of_product_items_per_page();
 ?>