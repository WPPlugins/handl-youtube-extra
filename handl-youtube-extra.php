<?php
/*
Plugin Name: HandL YouTube Extra
Plugin URI: http://www.haktansuren.com/handl-youtube-extra
Description: Get more from your YouTube videos...
Author: Haktan Suren
Version: 1.0.2
Author URI: http://www.haktansuren.com/
*/

function handl_yt_func( $atts ) {

	$shortcode_defaults = array(
		'height' => '390',
		'width' => '640',
		'videoid' => false
	);
	
	$YTParams = array(
		'autoplay' => 0,
		'controls' => 0,
		'modestbranding' => 1,
		'rel' => 0,
		'showinfo' => 0,
		'fs' => 0,
		'playsinline' => 0,
		'theme' => 'dark',
		'start' => 0,
		'loop' =>0
	);
	
	$a = shortcode_atts( array_merge($shortcode_defaults,$YTParams), $atts );
	
	if (!$a['videoid']){
		return "You are missing videoId attributes in the shortcode.";
	}
	
	$post_id = get_the_ID();
	$args = array(
		'ajaxurl' => admin_url('admin-ajax.php'),
		'postID' => $post_id
	);
	wp_enqueue_script( 'handl-yt-extra', plugins_url( 'js/handl-yt-extra-front.js', __FILE__ ), array( 'jquery' ) );
	wp_localize_script( 'handl-yt-extra', 'handl_yt', $args );
	
	$email = handl_yt_get_user();
	
	
	if ($email != ''){
		$meta_name = $a['videoid']."||".$email;
		if (!$a['start'] = floor(get_post_meta( $post_id, '_handl_yt_last||'.$meta_name, true ))){
			//do nothing
		}
	}		
		
	$params = '';
	foreach ($a as $k=>$p){
		if (in_array($k, array_keys($YTParams)))
			$k = "params-$k";
			
		$params.=" data-handl-yt-$k='$p'";
	}	
	
	$u = uniqid();
	
	$sc = "<div class='handl-yt' $params id='player-$u'></div>";
	return $sc;
    
}
add_shortcode( 'handl_yt', 'handl_yt_func' );


function handl_yt_save_video_data_callback(){
	$email = handl_yt_get_user();
	$post_id = $_POST['postID'];
	$hashid = $_POST['i'];
	
	$d = $_POST['d'];
	$c = $_POST['c'];
	$t = $_POST['t'];
	
	$meta_name = $hashid."||".$email;
	
	if ( $vector_t = get_post_meta($post_id, '_handl_yt_seconds_watched_vector||'.$meta_name, true) ){
		$vector_t[$c-1]++;
		update_post_meta( $post_id, '_handl_yt_seconds_watched_vector||'.$meta_name, $vector_t);
	}else{
		$vector_t = array_fill(0,$d,0);
		add_post_meta( $post_id, '_handl_yt_seconds_watched_vector||'.$meta_name, $vector_t, true );
	}
	
	$secondswatched = 0;
	foreach ($vector_t as $i=>$v){		
		if ($vector_t[$i] > 0){
		    $secondswatched++;
		}
	}
	
	    
	add_post_meta( $post_id, '_handl_yt_seconds_watched||'.$meta_name, $secondswatched, true ) or update_post_meta( $post_id, '_handl_yt_seconds_watched||'.$meta_name, $secondswatched);
	add_post_meta( $post_id, '_handl_yt_total_seconds_watched||'.$meta_name, array_sum($vector_t), true ) or update_post_meta( $post_id, '_handl_yt_total_seconds_watched||'.$meta_name, array_sum($vector_t));
	add_post_meta( $post_id, '_handl_yt_last||'.$meta_name, $c, true ) or update_post_meta( $post_id, '_handl_yt_last||'.$meta_name, $c);
	!get_post_meta($post_id, '_handl_yt_date||'.$meta_name, time(), true) ? add_post_meta( $post_id, '_handl_yt_date||'.$meta_name, time(), true ) : '';
	!get_post_meta($post_id, '_handl_yt_duration||'.$meta_name, $d, true) ? add_post_meta( $post_id, '_handl_yt_duration||'.$meta_name, $d, true ) : '';
	!get_post_meta($post_id, '_handl_yt_title||'.$meta_name, $t, true) ? add_post_meta( $post_id, '_handl_yt_title||'.$meta_name, $t, true ) : '';
	
	wp_send_json(array('success'=>true));
	wp_die();
}

add_action( 'wp_ajax_handl_yt_save_video_data', 'handl_yt_save_video_data_callback' );
add_action( 'wp_ajax_nopriv_handl_yt_save_video_data', 'handl_yt_save_video_data_callback' );


function handl_yt_set_user(){
	
	if (is_user_logged_in()){
		$user_id = get_current_user_id();
		$user_info = get_userdata($user_id);
		$email = $user_info->user_email;
	}elseif(isset($_GET['email']) && $_GET['email'] != '')
		$email = $_GET['email'];
	elseif(isset($_COOKIE['handl_yt_extra_email']) && $_COOKIE['handl_yt_extra_email'] != ''){ 
		$email = $_COOKIE['handl_yt_extra_email'];
	}else{
		$email = '';
	}

	setcookie('handl_yt_extra_email', $email , time()+60*60*24*30, '/' );
	$_COOKIE['handl_yt_extra_email'] = $email;
	
	return $email;
}
add_action('init', 'handl_yt_set_user');

function handl_yt_get_user(){
	return $_COOKIE['handl_yt_extra_email'];
}

add_action( 'admin_menu', 'handl_yt_stats_page' );
function handl_yt_stats_page() {
	add_options_page( 'HandL YouTube Extra', 'HandL YouTube Extra', 'manage_options', 'handl-yt-extra', 'handl_yt_stats_page_func' );
}
function handl_yt_stats_page_func() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	
	wp_enqueue_script( 'dataTablesJS', plugins_url( 'plugins/DataTables/jquery.dataTables.min.js', __FILE__ ), array( 'jquery' ) );
	wp_enqueue_script( 'jquery.hottie', plugins_url( 'js/jquery.hottie.js', __FILE__ ), array( 'jquery' ) );
        wp_enqueue_style( 'dataTablesCSS', plugins_url( 'plugins/DataTables/jquery.dataTables.min.css', __FILE__ ) );
	
        global $wpdb;
        $myrows = $wpdb->get_results( "SELECT * FROM `wp_postmeta` WHERE meta_key LIKE '_handl_yt_seconds_watched_vector||%'" );
		
	$table = "
	<style>
	ul.handl-yt-heatmap li{
		float: left;
		//padding: 0 3px;
		height: 20px;
	}
	
	.video-heatmap-wrapper{
		margin-top:20px;
	}
	</style>
	<table id='handl-youtube-table' class='display'>";
	$header = $footer = "
	<tr>
	  <th>Post Name</th>
          <th>Video ID</th>
          <th>Email</th>
          <th>Date</th>
	  <th>Unique Watched (sec)</th>
	  <th>Total Watched (sec)</th>
	  <th>Left At (sec)</th>
	  <th>Perc Watched</th>
          <th>Remove</th>
	</tr>
	";
        
        $table .= "<thead>".$header."</thead><tfoot>".$footer."</tfoot>";
        
        foreach ($myrows as $p){
		$post_id = $p->post_id;
		$vector = $p->meta_value;
    
		preg_match("/_handl_yt_seconds_watched_vector\|\|(.*)\|\|(.*)/", $p->meta_key, $out);
		
		$hashid = $out[1];
		$email = $out[2];
		
		$meta_name = $hashid."||".$email;
		
		//$v = get_post_meta( $post_id, '_handl_yt_seconds_watched_vector||'.$meta_name, true );
		$sw = get_post_meta( $post_id, '_handl_yt_seconds_watched||'.$meta_name, true );
		$tsw = get_post_meta( $post_id, '_handl_yt_total_seconds_watched||'.$meta_name,  true );
		$d = get_post_meta( $post_id, '_handl_yt_duration||'.$meta_name,  true );
		$last = round(get_post_meta( $post_id, '_handl_yt_last||'.$meta_name, true ),0);
		$date = date("Y-m-d H:i:s", get_post_meta( $post_id, '_handl_yt_date||'.$meta_name, true ));
		$title = get_post_meta( $post_id, '_handl_yt_title||'.$meta_name,  true );
		$title = strlen($title) > 40 ? substr($title,0,40)."..." : $title;
		$c = round(100*$sw/$d,2);
		
		$po = get_post($post_id);
		
		$table .= "
		<tr>
		  <td>($po->ID) $po->post_title</td>
		  <td><a href='javascript:void(0)' class='generate-heatmap' data-video-id='$hashid'>$title ($hashid)</a></td>
		  <td>$email</td>
		  <td>$date</td>
		  <td>$sw</td>
		  <td>$tsw</td>
		  <td>$last</td>
		  <td>$c%</td>
		  <td><a href='#' class='remove_meta' data-id='$po->ID' data-meta_name='$meta_name'><img width='16' src='".plugins_url( 'images/delete.png', __FILE__ )."'/></a></td>
		</tr>
		";
		
        }
	$table .= "</table>";
	
        $script = "
        <script>
        jQuery( document ).ready(function($) {
		
		var tag = document.createElement('script');
		tag.src = 'https://www.youtube.com/iframe_api';
		var firstScriptTag = document.getElementsByTagName('script')[0];
		firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
		
		var table = $('#handl-youtube-table').DataTable();
		
		jQuery( '.remove_meta' ).click(function() {
		    var target=jQuery(this).parent().parent()
		    var post_id = $(this).attr('data-id')
		    var meta_name = $(this).attr('data-meta_name')
			jQuery.ajax({
			    url: ajaxurl,
			    data: {'action':'handl_yt_remove_meta', post_id: post_id, meta_name:meta_name},
			    type: 'POST',
			    dataType: 'json',
			    success: function(data){
				target.hide('slow', function(){ target.remove(); });
				//table.fnDraw();
				//console.log(data)
			    }
			});
		});
		
		
		jQuery( '.generate-heatmap' ).click(function() {
		    var hashid = $(this).attr('data-video-id')
		    jQuery.ajax({
			    
			    url: ajaxurl,
			    data: {'action':'handl_yt_create_video_heatmap', videoid: hashid},
			    type: 'POST',
			    dataType: 'json',
			    success: function(data){
				    $('.video-heatmap-wrapper').show()
				    $('#video_heatmap').html(data.map)
				    $('.handl-yt-heatmap li').hottie({
					    readValue : function(e) {
					      return $(e).attr('data-hist');
					    },
					    colorArray : [
					      '#E5FFCC',
					      '#193300'
					      
					    ]
				    });
				    $('.handl-yt-heatmap li').css('padding','0 '+data.pad+'px')
				    handl_yt_load_video(hashid)
			    }
		    });
		});
        });
	
	function handl_yt_load_video(hashid) {
		if (typeof(player_p) == 'undefined'){
			player_p = new YT.Player('handl-yt-video-preview', {
			    height: 390,
			    width: 640,
			    videoId: hashid
			});
		}else{
			player_p.cueVideoById(hashid)
		}
	}
        </script>
        ";
        
	echo '<div class="wrap">';
	echo '<h2>HandL YouTube Extra Stats</h2>';
	echo "$table";
	echo '<div class="video-heatmap-wrapper" style="display:none;"><h3>Video Detailed Stats (Heatmap)</h3><div id="handl-yt-video-preview"></div><div id="video_heatmap"></div></div>';
	echo '</div>';
        echo "$script";
}

function handl_yt_remove_meta_callback(){
    if (isset($_POST['post_id'])){
        
        $post_id = $_POST['post_id'];
        $meta_name = $_POST['meta_name'];
        
        delete_post_meta( $post_id, '_handl_yt_seconds_watched_vector||'.$meta_name);
        delete_post_meta( $post_id, '_handl_yt_seconds_watched||'.$meta_name);
        delete_post_meta( $post_id, '_handl_yt_total_seconds_watched||'.$meta_name);
        delete_post_meta( $post_id, '_handl_yt_duration||'.$meta_name);
        delete_post_meta( $post_id, '_handl_yt_last||'.$meta_name);
        delete_post_meta( $post_id, '_handl_yt_date||'.$meta_name);
	delete_post_meta( $post_id, '_handl_yt_title||'.$meta_name);
        
    }
    
    wp_send_json(array('success'=>true));
    wp_die();
}
add_action( 'wp_ajax_handl_yt_remove_meta', 'handl_yt_remove_meta_callback' );
add_action( 'wp_ajax_nopriv_handl_yt_remove_meta', 'handl_yt_remove_meta_callback' );

function handl_yt_create_video_heatmap_callback(){
	$videoid = $_POST['videoid'];
	
	global $wpdb;
        $myrows = $wpdb->get_results( "SELECT * FROM `wp_postmeta` WHERE meta_key LIKE '_handl_yt_seconds_watched_vector||$videoid||%'" );
	
	$sum_vector = array();
	foreach ($myrows as $i=>$p){
		$post_id = $p->post_id;
		$vector = unserialize($p->meta_value);
		
		if (sizeof($sum_vector) == 0){
			$sum_vector = $vector;
		}else{
			foreach ($vector as $i1=>$v1){
				$sum_vector[$i1] += $v1;
			}
		}
	}
	
	$map = "<ul class='handl-yt-heatmap' data-videoid='$videoid'>";
	foreach ($sum_vector as $i=>$p){
		$map .= "<li data-hist='$p'></li>";
	}
	$map .= "</ul>";
	
	$pad = 640/(sizeof($sum_vector)*2);
	
	wp_send_json(array('success'=>true, 'map' => $map, 'pad' => $pad));
	
}

add_action( 'wp_ajax_handl_yt_create_video_heatmap', 'handl_yt_create_video_heatmap_callback' );
add_action( 'wp_ajax_nopriv_handl_yt_create_video_heatmap', 'handl_yt_create_video_heatmap_callback' );