<?php
/**
 * Grab a collection of pages
 *
 * @param array $data Options for the function.
 * @return array|null Collection of pages in an array,  * or null if none.
 * @since 0.0.1
 */

// get a collection of pages with parameters
function bwe_get_pages( WP_REST_Request $request ) {

  // check for params
  $posts_per_page = $request['per_page']?: '10';
  $page = $request['page']?: '1';
  $show_content = $request['content']?: 'true';
  $orderby = $request['orderby']?: null;
  $order = $request['order']?: null;
  $exclude = $request['exclude']?: null;

  // WP_Query arguments
  $args = array(
    'post_type'              => 'page',
    'nopaging'               => false,
  	'posts_per_page'         => $posts_per_page,
    'paged'                  => $page,
    'order'                  => $order?:'DESC',
    'orderby'                => $orderby?:'date',
    'post__not_in'           => array($exclude),
  );

  $query = new WP_Query( $args );

  // Setup pages array
  $pages = array();

  // The Loop
  if( $query->have_posts() ){

    // For Headers
    $total = $query->found_posts;
    $total_pages = $query->max_num_pages;

    while( $query->have_posts() ) {
      $query->the_post();

      global $post;

      // better wordpress endpoint page object
      $bwe_page = new stdClass();

      /*
       *
       * get page data
       *
       */
      $bwe_page->id = get_the_ID();
      $bwe_page->title = get_the_title();
      $bwe_page->slug = basename(get_permalink());

      /*
       *
       * return template name
       *
       */
      if( get_page_template() ){
        // strip file extension to return just the name of the template
        $template_name = preg_replace('/\\.[^.\\s]{3,4}$/', '', basename(get_page_template()));

        $bwe_page->template = $template_name;

      } else {
        $bwe_page->template = 'default';
      }

      /*
       *
       * return parent slug if it exists
       *
       */
      $parents = get_post_ancestors( $post->ID );
      /* Get the top Level page->ID count base 1, array base 0 so -1 */
    	$id = ($parents) ? $parents[count($parents)-1]: $post->ID;
    	/* Get the parent and set the $class with the page slug (post_name) */
      $parent = get_post( $id );
    	$bwe_page->parent = $parent->post_name != $post->post_name ? $parent->post_name : false;


      // show post content unless parameter is false
      if( $show_content === 'true' ) {
        $bwe_page->content = apply_filters('the_content', get_the_content());
      }

      /*
       *
       * return acf fields if they exist
       *
       */
      $bwe_page->acf = bwe_get_acf();

      /*
       *
       * get possible thumbnail sizes and urls
       *
       */
      $thumbnail_names = get_intermediate_image_sizes();
      $bwe_thumbnails = new stdClass();

      if( has_post_thumbnail() ){
        foreach ($thumbnail_names as $key => $name) {
          $bwe_thumbnails->$name = esc_url(get_the_post_thumbnail_url($post->ID, $name));
        }

        $bwe_page->media = $bwe_thumbnails;
      } else {
        $bwe_page->media = false;
      }

      // Push the post to the main $post array
      array_push($pages, $bwe_page);
    }

    // return the pages array
    $response = rest_ensure_response( $pages );
    $response->header( 'X-WP-Total', (int) $total );
    $response->header( 'X-WP-TotalPages', (int) $total_pages );

    return $response;

  } else {

    // return the empty pages array if no posts
    return $pages;
  }

  // restore post data
  wp_reset_postdata();
}

 /*
  *
  * Register Rest API Endpoint
  *
  */
 add_action( 'rest_api_init', function () {
   register_rest_route( 'better-wp-endpoints/v1', '/pages/', array(
     'methods' => 'GET',
     'callback' => 'bwe_get_pages',
     'args' => array(
       'per_page' => array(
         'description'       => 'Maxiumum number of items to show per page.',
         'type'              => 'integer',
         'validate_callback' => function( $param, $request, $key ) {
           return is_numeric( $param );
          },
         'sanitize_callback' => 'absint',
       ),
       'page' =>  array(
         'description'       => 'Current page of the collection.',
         'type'              => 'integer',
         'validate_callback' => function( $param, $request, $key ) {
           return is_numeric( $param );
          },
         'sanitize_callback' => 'absint',
       ),
       'exclude' =>  array(
         'description'       => 'Exclude an item from the collection.',
         'type'              => 'integer',
         'validate_callback' => function( $param, $request, $key ) {
           return is_numeric( $param );
          },
         'sanitize_callback' => 'absint',
       ),
       'order' =>  array(
         'description'       => 'Change order of the collection.',
         'type'              => 'string',
         'validate_callback' => function($param, $request, $key) {
             return is_string( $param );
           },
         'sanitize_callback' => 'sanitize_text_field',
       ),
       'orderby' =>  array(
         'description'       => 'Change how the collection is ordered.',
         'type'              => 'string',
         'validate_callback' => function($param, $request, $key) {
             return is_string( $param );
           },
         'sanitize_callback' => 'sanitize_text_field',
       ),
     ),
   ) );
 } );
