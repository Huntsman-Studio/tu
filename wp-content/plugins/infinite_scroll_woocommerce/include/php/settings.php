<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class InfiniteWoocommerceScrollSettings {
    private $dir;
	private $file;
	private $assets_dir;
	private $assets_url;
	private $settings_base;
	private $settings;
	public function __construct( $file ) {
		$this->file = $file;
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'include';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/include/', $this->file ) ) );
		$this->settings_base = 'isw_';
		// Initialise settings
		add_action( 'admin_init', array( $this, 'init' ) );
		// Register plugin settings
		add_action( 'admin_init' , array( $this, 'register_settings' ) );
		// Add settings page to menu
		add_action( 'admin_menu' , array( $this, 'add_menu_item' ) );
		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ) , array( $this, 'add_settings_link' ) );
	}
	/**
	 * Initialise settings
	 * @return void
	 */
	public function init() {
		$this->settings = $this->settings_fields();
	}
	/**
	 * Add settings page to admin menu
	 * @return void
	 */
	public function add_menu_item() {
		$page = add_options_page( __( 'Infinite Scroll Woocommerce', 'infinite-scroll-woocommerce' ) , __( 'Infinite Scroll Woocommerce', 'infinite-scroll-woocommerce' ) , 'manage_options' , 'isw_settings' ,  array( $this, 'settings_page' ) );
		add_action( 'admin_print_styles-' . $page, array( $this, 'settings_assets' ) );
	}
	/**
	 * Load settings JS & CSS
	 * @return void
	 */
	public function settings_assets() {
    // We're including the WP media scripts here because they're needed for the image upload field
    // If you're not including an image upload then you can leave this function call out
    wp_enqueue_media();
	//Required for color picker
	wp_enqueue_style( 'farbtastic' );
    wp_enqueue_script( 'farbtastic' );
	wp_register_script('custom-jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/jquery-ui.js');	
	wp_enqueue_script( 'custom-jquery-ui' );
	wp_register_style('woo-infinite-switchButton-style', $this->assets_url . 'css/jquery.switchButton.css');
	wp_enqueue_style( 'woo-infinite-switchButton-style' );
	wp_register_style('ias-animate-css', $this->assets_url . 'css/animate.min.css');
	wp_enqueue_style( 'ias-animate-css' );
	wp_register_style('woo-infinite-scroll-style', $this->assets_url . 'css/admin-style.css');
	wp_enqueue_style( 'woo-infinite-scroll-style' );
	wp_register_script('custom-woo-switchButton', $this->assets_url . 'js/jquery.switchButton.js', array('farbtastic','jquery'), false, true);
	wp_enqueue_script( 'custom-woo-switchButton' );
	//register style.php for preview button
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
	 
	wp_add_inline_style( 'woo-infinite-scroll-style', $inline_style );
	
/* 	wp_register_style('ias-frontend-custom-style', $this->assets_url . 'css/style.php');
	wp_enqueue_style( 'ias-frontend-custom-style' ); */
	$suffix = ( WP_DEBUG ) ? '.dev' : '';
    wp_register_script( 'wpt-admin-js', $this->assets_url . 'js/admin'.$suffix.'.js', array(  'jquery' ), '1.0.0' );
    wp_enqueue_script( 'wpt-admin-js' );
	}
	/**
	 * Add settings link to plugin list table
	 * @param  array $links Existing links
	 * @return array 		Modified links
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=isw_settings">' . __( 'Settings', 'infinite-scroll-woocommerce' ) . '</a>';
  		array_push( $links, $settings_link );
  		return $links;
	}
	/**
	 * Build settings fields
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields() {
		$settings['basic'] = array(
			'title'					=> __( 'Basic Settings', 'infinite-scroll-woocommerce' ),
			'description'			=> __( 'These are some basic settings to get started.', 'infinite-scroll-woocommerce' ),
			'fields'				=> array(
				array(
					'id' 			=> 'number_of_products',
					'label'			=> __( 'How many products initially' , 'infinite-scroll-woocommerce' ),
					'description'	=> __( 'Enter the number of products to get in main products page before ajax call', 'infinite-scroll-woocommerce' ),
					'type'			=> 'number',
					'default'		=> '8',
					'placeholder'	=> '8'
				),
				array(
					'id' 			=> 'preloader_icon',
					'label'			=> __( 'Preloader icon' , 'infinite-scroll-woocommerce' ),
					'description'	=> __( 'Upload your own preloader icons. Leave it empty to use plugin default icon', 'infinite-scroll-woocommerce' ),
					'type'			=> 'image',
					'default'		=> '',
					'placeholder'	=> ''
				),
				array(
					'id' 			=> 'ajax_method',
					'label'			=> __( 'Choose ajax method' , 'infinite-scroll-woocommerce' ),
					'description'	=> __( 'Enter your prefered ajax method', 'infinite-scroll-woocommerce' ),
					'type'			=> 'select',
					'options'		=> array( 'method_infinite_scroll' => 'Infinite scroll', 'method_load_more_button' => 'Infinite scroll with load more button', 'method_simple_ajax_pagination' => 'Simple Ajax Pagination' ),
					'default'		=> 'method_infinite_scroll'
				),
			array(
					'id' 			=> 'start_loading_x_from_end',
					'label'			=> __( 'Start loading next page results X pixels before reach end (enter the amount of pixels)', 'infinite-scroll-woocommerce' ),
					'type'			=> 'number',
					'default'		=> '0',
					'placeholder'	=> '8'
				),				
			)	
		);
		$settings['btn_more'] = array(
			'title'					=> __( 'Button Settings', 'infinite-scroll-woocommerce' ),
			'description'			=> __( 'Here are the options if your preferred ajax method is Load More Button.', 'infinite-scroll-woocommerce' ),
			'fields'				=> array(
				array(
					'id' 			=> 'load_more_button_animate',
					'label'			=> __( 'Animate load more button', 'infinite-scroll-woocommerce' ),
					'description'	=> __( 'Check this option if you want to animate the load more button.', 'infinite-scroll-woocommerce' ),
					'type'			=> 'checkbox',
					'default'		=> ''
				),
				array(
					'id' 			=> 'animation_method_load_more_button',
					'label'			=> __( 'Choose animation type' , 'infinite-scroll-woocommerce' ),
					'type'			=> 'select',
					/* 'onChange'		=> 'preview_button_animate(this.value)', */
					'description'	=> '<a id="isw-load-more-button" href="#">More</a>',
					'options'		=> array('bounce' => 'bounce', 'flash' => 'flash', 'pulse' => 'pulse', 'rubberBand' => 'rubberBand', 'shake' => 'shake', 'swing' => 'swing', 'tada' => 'tada', 'bounceIn' => 'bounceIn', 'bounceInDown' => 'bounceInDown', 'bounceInLeft' => 'bounceInLeft' , 'bounceInRight' => 'bounceInRight', 'bounceInUp' => 'bounceInUp', 'fadeIn' => 'fadeIn'    , 'fadeInDown' => 'fadeInDown', 'fadeInDownBig' => 'fadeInDownBig', 'fadeInLeft' => 'fadeInLeft' , 'fadeInLeftBig' => 'fadeInLeftBig', 'fadeInRight' => 'fadeInRight', 'fadeInRightBig' => 'fadeInRightBig', 'fadeInUp' => 'fadeInUp', 'fadeInUpBig' => 'fadeInUpBig', 'zoomIn' => 'zoomIn', 'zoomInDown' => 'zoomInDown', 'zoomInLeft' => 'zoomInLeft', 'zoomInRight' => 'zoomInRight', 'zoomInUp' => 'zoomInUp'),
					'default'		=> 'zoomInUp'
				),	
				array(
					'id' 			=> 'button_text',
					'label'			=> __( 'Change load more button (next) text with: ', 'infinite-scroll-woocommerce' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> 'More'
				),
				array(
					'id' 			=> 'button_text_prev',
					'label'			=> __( 'Change load more button(previous) text with: ', 'infinite-scroll-woocommerce' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> 'Previous'
				),
				array(
					'id' 			=> 'button_background',
					'label'			=> __( 'Pick a colour for button background', 'infinite-scroll-woocommerce' ),
					'type'			=> 'color',
					'default'		=> '#DDDDDD'
				),
				array(
					'id' 			=> 'button_color',
					'label'			=> __( 'Pick a colour for button text color', 'infinite-scroll-woocommerce' ),
					'type'			=> 'color',
					'default'		=> '#000000'
				),
				array(
					'id' 			=> 'button_background_hover',
					'label'			=> __( 'Pick a colour for button background on mouseover', 'infinite-scroll-woocommerce' ),
					'type'			=> 'color',
					'default'		=> '#EEEEEE'
				),
				array(
					'id' 			=> 'button_color_hover',
					'label'			=> __( 'Pick a colour for button text color on mouseover', 'infinite-scroll-woocommerce' ),
					'type'			=> 'color',
					'default'		=> '#000000'
				),
				array(
					'id' 			=> 'button_padding',
					'label'			=> __( 'Choose padding for your button', 'infinite-scroll-woocommerce' ),
					'type'			=> 'number',
					'default'		=> '10',
					'placeholder'	=> '10'
				),
				array(
					'id' 			=> 'button_width',
					'label'			=> __( 'Choose width for your button', 'infinite-scroll-woocommerce' ),
					'type'			=> 'number',
					'default'		=> '80',
					'placeholder'	=> '80'
				),
				array(
					'id' 			=> 'button_height',
					'label'			=> __( 'Choose height for your button ', 'infinite-scroll-woocommerce' ),
					'type'			=> 'number',
					'default'		=> '40',
					'placeholder'	=> '40'
				),
				array(
					'id' 			=> 'button_border_radius',
					'label'			=> __( 'Choose border radius for your button ', 'infinite-scroll-woocommerce' ),
					'type'			=> 'number',
					'default'		=> '0',
					'placeholder'	=> '5'
				),
				array(
					'id' 			=> 'button_border_width',
					'label'			=> __( 'Choose border width for your button ', 'infinite-scroll-woocommerce' ),
					'type'			=> 'number',
					'default'		=> '0',
					'placeholder'	=> '1'
				),
				array(
					'id' 			=> 'button_border_color',
					'label'			=> __( 'Pick a colour for button border', 'infinite-scroll-woocommerce' ),
					'type'			=> 'color',
					'default'		=> '#000000'
				),
				array(
					'id' 			=> 'button_font_size',
					'label'			=> __( 'Choose font size of button text ', 'infinite-scroll-woocommerce' ),
					'type'			=> 'number',
					'default'		=> '14',
					'placeholder'	=> '14'
				)
			)
		);
		$settings['simple_ajax_pagination'] = array(
			'title'					=> __( 'Simple Ajax Pagination Settings', 'infinite-scroll-woocommerce' ),
			'description'			=> __( 'The settings in case you choose as preferred ajax method the simple.', 'infinite-scroll-woocommerce' ),
			'fields'				=> array(
				array(
					'id' 			=> 'animate_to_top',
					'label'			=> __( 'Do you want to animate the page to the top when ajax finish loading results?', 'infinite-scroll-woocommerce' ),
					'type'			=> 'checkbox',
					'default'		=> '',
					'placeholder'	=> '.woocommerce-result-count' 
				),
				array(
					'id' 			=> 'pixels_from_top',
					'label'			=> __( 'Pixels from top?', 'infinite-scroll-woocommerce' ),
					'type'			=> 'number',
					'default'		=> '',
					'placeholder'	=>  'eg 10' 
				),
				array(
					'id' 			=> 'wrapper_result_count',
					'label'			=> __( 'Enter the wrapper selector for result count.', 'infinite-scroll-woocommerce' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> '.woocommerce-result-count' 
				),
				array(
					'id' 			=> 'wrapper_breadcrumb',
					'label'			=> __( 'Enter the wrapper selector for breadcrumb.', 'infinite-scroll-woocommerce' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=>  '.woocommerce-breadcrumb' 
				)
			)
		);
		$settings['wrapper'] = array(
			'title'					=> __( 'Wrapper Settings', 'infinite-scroll-woocommerce' ),
			'description'			=> __( 'Enter here the wrapper selectors for products loop if you experience issues or if you have custom woocommerce theme. (Leave options empty if you want use the selectors from the default woocommerce theme)', 'infinite-scroll-woocommerce' ),
			'fields'				=> array(
				array(
					'id' 			=> 'products_selector',
					'label'			=> __( 'Products wrapper' , 'infinite-scroll-woocommerce' ),
					'description'	=> __( 'Enter here the wrapper selector of products page', 'infinite-scroll-woocommerce' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> 'ul.products'
				),
				array(
					'id' 			=> 'pagination_selector',
					'label'			=> __( 'Pagination wrapper' , 'infinite-scroll-woocommerce' ),
					'description'	=> __( 'Enter here the wrapper selector of pagination', 'infinite-scroll-woocommerce' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> '.woocommerce-pagination'
				),
				array(
					'id' 			=> 'next_page_selector',
					'label'			=> __( 'Next page selector' , 'infinite-scroll-woocommerce' ),
					'description'	=> __( 'Enter here the wrapper selector of pagination', 'infinite-scroll-woocommerce' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> '.next'
				),
				array(
					'id' 			=> 'prev_page_selector',
					'label'			=> __( 'Previous page selector' , 'infinite-scroll-woocommerce' ),
					'description'	=> __( 'Enter here the wrapper selector of pagination', 'infinite-scroll-woocommerce' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> '.prev'
				)
			)
		);
		$settings['advanced'] = array(
			'title'					=> __( 'Advanced Settings', 'infinite-scroll-woocommerce' ),
			'description'			=> __( 'Andvanced settings for developers.', 'infinite-scroll-woocommerce' ),
			'fields'				=> array(
				array(
					'id' 			=> 'enable_history',
					'label'			=> __( 'Enable history state support (Still in beta)', 'infinite-scroll-woocommerce' ),
					'type'			=> 'checkbox',
					'default'		=> 'on'
					),
				array(
					'id' 			=> 'masonry_bool',
					'label'			=> __( 'Enable masonry/isotope support? (you have to specify item selector bellow)', 'infinite-scroll-woocommerce' ),
					'description'	=> __( 'BETA this option is for themes which have already implement masonry/isotope!', 'infinite-scroll-woocommerce' ),
					'type'			=> 'checkbox',
					'default'		=> ''
					),
				array(
					'id' 			=> 'layout_mode',
					'label'			=> __( 'Choose layout method' , 'infinite-scroll-woocommerce' ),
					'description'	=> __( '', 'infinite-scroll-woocommerce' ),
					'type'			=> 'select',
					'options'		=> array( 'layout_isotope' => 'Isotope', 'layout_masonry' => 'Masonry' ),
					'default'		=> 'layout_isotope'
				),
				array(
					'id' 			=> 'masonry_item_selector',
					'label'			=> __( 'Enter item selector in order masonry/isotope and infinite scroll work together (usually .products li or .product)', 'infinite-scroll-woocommerce' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> '.product' 
				),
				array(
					'id' 			=> 'css_code',
					'label'			=> __( 'CSS code' , 'infinite-scroll-woocommerce' ),
					'description'	=> __( 'Enter here any css code you want to apply in shop loop.', 'infinite-scroll-woocommerce' ),
					'type'			=> 'textarea',
					'default'		=> '',
				)
			)
		);
		$settings = apply_filters( 'isw_plugin_settings_fields', $settings );
		return $settings;
	}
	/**
	 * Register plugin settings
	 * @return void
	 */
	public function register_settings() {
		if( is_array( $this->settings ) ) {
			foreach( $this->settings as $section => $data ) {
				// Add section to page
				add_settings_section( $section, $data['title'], array( $this, 'settings_section' ), 'isw_plugin_settings' );
				foreach( $data['fields'] as $field ) {
					// Validation callback for field
					$validation = '';
					if( isset( $field['callback'] ) ) {
						$validation = $field['callback'];
					}
					// Register field
					$option_name = $this->settings_base . $field['id'];
					register_setting( 'isw_plugin_settings', $option_name, $validation );
					// Add field to page
					add_settings_field( $field['id'], $field['label'], array( $this, 'display_field' ), 'isw_plugin_settings', $section, array( 'field' => $field ) );
				}
			}
		}
	}
	public function settings_section( $section ) {
		$html = '<p> ' . $this->settings[ $section['id'] ]['description'] . '</p>' . "\n";
		echo $html;
	}
	/**
	 * Generate HTML for displaying fields
	 * @param  array $args Field data
	 * @return void
	 */
	public function display_field( $args ) {
		$field = $args['field'];
		$html = '<div id=wrapper_'.$this->settings_base . $field['id'].'>';
		$option_name = $this->settings_base . $field['id'];
		$option = get_option( $option_name );
		$data = '';
		if( isset( $field['default'] ) ) {
			$data = $field['default'];
			if( $option ) {
				$data = $option;
			}
		}
		$field['description'] = isset($field['description'])?$field['description']:"";
		$field['placeholder'] = isset($field['placeholder'])?$field['placeholder']:"";
		switch( $field['type'] ) {
			case 'text':
			case 'password':
			case 'number':
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . $field['type'] . '" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . $data . '"/>' . "\n";
			break;
			case 'text_secret':
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="text" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value=""/>' . "\n";
			break;
			case 'textarea':
				$html .= '<textarea id="' . esc_attr( $field['id'] ) . '" rows="5" cols="50" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '">' . $data . '</textarea><br/>'. "\n";
			break;
			case 'checkbox':
				$checked = '';
				if( $option && 'on' == $option ){
					$checked = 'checked="checked"';
				}
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . $field['type'] . '" name="' . esc_attr( $option_name ) . '" ' . $checked . '/>' . "\n";
			break;
			case 'checkbox_multi':
				foreach( $field['options'] as $k => $v ) {
					$checked = false;
					if( in_array( $k, $data ) ) {
						$checked = true;
					}
					$html .= '<label for="' . esc_attr( $field['id'] . '_' . $k ) . '"><input type="checkbox" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $option_name ) . '[]" value="' . esc_attr( $k ) . '" id="' . esc_attr( $field['id'] . '_' . $k ) . '" /> ' . $v . '</label> ';
				}
			break;
			case 'radio':
				foreach( $field['options'] as $k => $v ) {
					$checked = false;
					if( $k == $data ) {
						$checked = true;
					}
					$html .= '<label for="' . esc_attr( $field['id'] . '_' . $k ) . '"><input type="radio" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $k ) . '" id="' . esc_attr( $field['id'] . '_' . $k ) . '" /> ' . $v . '</label> ';
				}
			break;
			case 'select':
				$onChange=!isset($field['onChange'])?"":'onChange="'.$field['onChange'].'"';
				$html .= '<select '.$onChange.' name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $field['id'] ) . '">';
				foreach( $field['options'] as $k => $v ) {
					$selected = false;
					if( $k == $data ) {
						$selected = true;
					}
					$html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '">' . $v .'</option>';
				}
				$html .= '</select> ';
			break;
			case 'select_multi':
				$html .= '<select name="' . esc_attr( $option_name ) . '[]" id="' . esc_attr( $field['id'] ) . '" multiple="multiple">';
				foreach( $field['options'] as $k => $v ) {
					$selected = false;
					if( in_array( $k, $data ) ) {
						$selected = true;
					}
					$html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '" />' . $v . '</label> ';
				}
				$html .= '</select> ';
			break;
			case 'image':
				$image_thumb = '';
				if( $data ) {
					$image_thumb = wp_get_attachment_thumb_url( $data );
				}
				$html .= '<img id="' . $option_name . '_preview" class="image_preview" src="' . $image_thumb . '" /><br/>' . "\n";
				$html .= '<input id="' . $option_name . '_button" type="button" data-uploader_title="' . __( 'Upload an image' , 'infinite-scroll-woocommerce' ) . '" data-uploader_button_text="' . __( 'Use image' , 'infinite-scroll-woocommerce' ) . '" class="image_upload_button button" value="'. __( 'Upload new image' , 'infinite-scroll-woocommerce' ) . '" />' . "\n";
				$html .= '<input id="' . $option_name . '_delete" type="button" class="image_delete_button button" value="'. __( 'Remove image' , 'infinite-scroll-woocommerce' ) . '" />' . "\n";
				$html .= '<input id="' . $option_name . '" class="image_data_field" type="hidden" name="' . $option_name . '" value="' . $data . '"/><br/>' . "\n";
			break;
			case 'color':
				?><div class="color-picker" style="position:relative;">
			        <input type="text" name="<?php esc_attr_e( $option_name ); ?>" class="color" value="<?php esc_attr_e( $data ); ?>" />
			        <div style="position:absolute;background:#FFF;z-index:99;border-radius:100%;" class="colorpicker"></div>
			    </div>
			    <?php
			break;
		}
		switch( $field['type'] ) {
			case 'checkbox_multi':
			case 'radio':
			case 'select_multi':
				$html .= '<br/><span class="description">' . $field['description'] . '</span>';
			break;
			default:
				$html .= '<label for="' . esc_attr( $field['id'] ) . '"><span class="description">' . $field['description'] . '</span></label>' . "\n";
			break;
		}
		$html .="</div>";
		echo $html;
	}
	/**
	 * Validate individual settings field
	 * @param  string $data Inputted value
	 * @return string       Validated value
	 */
	public function validate_field( $data ) {
		if( $data && strlen( $data ) > 0 && $data != '' ) {
			$data = urlencode( strtolower( str_replace( ' ' , '-' , $data ) ) );
		}
		return $data;
	}
	/**
	 * Load settings page content
	 * @return void
	 */
	public function settings_page() {
		// Build page HTML
		$html = '<div class="wrap" id="isw_plugin_settings">' . "\n";
			$html .= '<h2>' . __( 'Infinite scroll Woocommerce' , 'infinite-scroll-woocommerce' ) . '</h2>' . "\n";
			$html .= '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";
				// Setup navigation
				$html .= '<div class="ias_setting_sections_wrapper"><ul id="settings-sections" class="subsubsub hide-if-no-js">' . "\n";
				$html .= '<li><a class="tab all current" href="#all">' . __( 'All' , 'infinite-scroll-woocommerce' ) . '</a></li>' . "\n";
					foreach( $this->settings as $section => $data ) {
						$html .= '<li> <a class="tab" href="#' . $section . '">' . $data['title'] . '</a></li>' . "\n";
					}
				$html .= '</ul></div>' . "\n";
				$html .= ' <a class="add-new-h2" style="float:right;" href="http://www.pasaporti.net/infinite-scroll-woocommerce/documentation/" target="_blank">Online Documentation</a>' . "\n";
				$html .= '<div class="ias_main_settings"><div class="clear"></div>' . "\n";
				// Get settings fields
				ob_start();
				settings_fields( 'isw_plugin_settings' );
				do_settings_sections( 'isw_plugin_settings' );
				$html .= ob_get_clean();
				$html .= '<p class="submit">' . "\n";
					$html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings' , 'infinite-scroll-woocommerce' ) ) . '" />' . "\n";
				$html .= '</p>' . "\n";
			$html .= '</form>' . "\n";
		$html .= '</div></div>' . "\n";
		echo $html;
	}
}