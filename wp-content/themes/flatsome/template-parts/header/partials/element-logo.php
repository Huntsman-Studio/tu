<!-- Header logo -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
  $(document).ready( function(){
    $(".custom-search").click( function(){
      $('.modal').fadeIn();
    });

    $('#close-modal').click( function(){
      $('.modal').fadeOut();
    });

    $('#search-input').keyup(function () { 
      var query = $('#search-input').val();

      // alert(query);
    });
  });
</script>

<style>
  p.custom-search{
    color: #707070;
    padding-top: 35px;
  }

  /* The Modal (background) */
  .modal {
    display: none; /* Hidden by default */
    position: fixed; /* Stay in place */
    z-index: 1; /* Sit on top */
    left: 0;
    top: 0;
    width: 100%; /* Full width */
    height: 100%; /* Full height */
    overflow: auto; /* Enable scroll if needed */
    background-color: rgb(0,0,0); /* Fallback color */
    background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
  }

  /* Modal Content/Box */
  .modal-content {
    background-color: #ebebeb;
    margin: 0px; /* 15% from the top and centered */
    padding: 20px;
    border: 1px solid #888;
    width: 100%; /* Could be more or less, depending on screen size */
  }
  .modal-content > input{
    background-color: transparent;
    border: none;
    box-shadow: none;
    width: 80%;
    color: black
  }
  .modal-content > input:focus{
    background-color: transparent;
    border: none;
    box-shadow: none;
  }

  /* Close modal */
  #close-modal:hover{
    cursor: pointer;
  }

  /* Categories */
  .categories{
    width: 100%;
    background-color: #fff;
    display: flex;
    justify-content: center;
    /* text-align: left; */
  }

  #prd{
    list-style-type: none;
    width: 80%;
    text-align: left;
    padding-top: 91px;
  }
  ul#prd > li{
    padding-bottom: 27px;
  }
  ul#prd > li > a {
    font-size: 18px;
    text-transform: none;
  }
</style>

<p class="custom-search">Αναζήτηση</p>

<div class="modal">
  <div class="modal-content">
    <input type="text" placeholder="Βρές το προϊόν που ψάχνεις πληκτρολογώντας εδώ" id="search-input"><span id="close-modal"><i class="fas fa-times"></i> Κλείσιμο</span>
  </div>
  <div class="categories">
    <ul id="prd">
      <?php
        $args = array(
            'post_type' => 'product',
            'meta_key' => 'total_sales',
            'orderby' => 'meta_value_num',
            'posts_per_page' => 8,
        );
        $loop = new WP_Query( $args );
        while ( $loop->have_posts() ) : $loop->the_post(); 
        global $product; 
        // $link = the_permalink();
        ?>
        <li><a id="prd-li" href="<?php the_permalink(); ?>" title="<?php the_title(); ?>"><?php the_title(); ?></a></li>

        <?php endwhile; ?>
        <?php wp_reset_query(); 
      ?>
    </ul>
  </div>
</div>

<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?><?php echo get_bloginfo( 'name' ) && get_bloginfo( 'description' ) ? ' - ' : ''; ?><?php bloginfo( 'description' ); ?>" rel="home">
    <?php if(flatsome_option('site_logo')){
      $logo_height = get_theme_mod('header_height',90);
      $logo_width = get_theme_mod('logo_width', 200);
      $site_title = esc_attr( get_bloginfo( 'name', 'display' ) );
      if(get_theme_mod('site_logo_sticky')) echo '<img width="'.$logo_width.'" height="'.$logo_height.'" src="'.get_theme_mod('site_logo_sticky').'" class="header-logo-sticky" alt="'.$site_title.'"/>';
      echo '<img width="'.$logo_width.'" height="'.$logo_height.'" src="'.flatsome_option('site_logo').'" class="header_logo header-logo" alt="'.$site_title.'"/>';
      if(!get_theme_mod('site_logo_dark')) echo '<img  width="'.$logo_width.'" height="'.$logo_height.'" src="'.flatsome_option('site_logo').'" class="header-logo-dark" alt="'.$site_title.'"/>';
      if(get_theme_mod('site_logo_dark')) echo '<img  width="'.$logo_width.'" height="'.$logo_height.'" src="'.get_theme_mod('site_logo_dark').'" class="header-logo-dark" alt="'.$site_title.'"/>';
    } else {
    bloginfo( 'name' );
  	}
  ?>
</a>
<?php
if(get_theme_mod('site_logo_slogan')){
	echo '<p class="logo-tagline">'.get_bloginfo('description').'</p>';
}
?>
