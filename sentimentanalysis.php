<?php
/*
Plugin Name: Sentiment Analysis
Plugin Script: sentimentanalysis.php
Plugin URI: http://sentimentanalysisonline.com
Description:  It is a sentimental analysis tool which shows the 'tone' within the context of comments. The comment may be an attitude, opinion or feeling towards something such as a person, organization, product or location. It also shows the overall sentimental value of a page or a post.
Version: 1.0
Author: ISPG
Author URI: http://sentimentanalysisonline.com
Template by: http://sentimentanalysisonline.com

=== RELEASE NOTES ===
2013-11-05 - v1.0 - first version
*/

include_once(dirname(__FILE__).'/admin.php');

register_activation_hook( __FILE__, 'activate_sentiment_analysis');
register_deactivation_hook( __FILE__, 'deactivate_sentiment_analysis');
register_uninstall_hook( __FILE__, 'uninstall_sentiment_analysis');

add_action( 'admin_menu', 'sentiment_analysis_menu' );
add_action('comment_post','save_comment_sentimental_value');
add_action('edit_comment','update_comment_sentimental_value');
add_action('current_screen','total_sentimental_count');

add_filter( 'cron_schedules', 'add_cron_intervals' );
add_action( 'sentiment_analysis_cron', 'find_all_comments_sentiment_value' );
add_filter('comment_text','add_sentiment_images_with_comments', 10, 2);
add_filter( 'plugin_action_links', 'add_sentimental_settings_link', 10, 2 );

if (class_exists("SentimentAdmin")) {
	$ObjSentimentPlugin = new SentimentAdmin();
}

function activate_sentiment_analysis(){
	global $wpdb;
	$tableName = $wpdb->prefix . "comments";
	$sql = "ALTER TABLE ".$tableName." ADD comment_sentiment_value varchar(20)";
	$wpdb->query($sql);
}

function deactivate_sentiment_analysis() {
	$sentimentOptions = get_option('sentiment_analysis_options');
	$sentimentOptions['findnow']	= '0';
	update_option( 'sentiment_analysis_options', $sentimentOptions );
}

function uninstall_sentiment_analysis() {
	global $wpdb;
	$tableName = $wpdb->prefix . "comments";
	$sql = "ALTER TABLE ".$tableName." DROP comment_sentiment_value ";
	$wpdb->query($sql);
	delete_option( 'sentiment_analysis_options');
}

function total_sentimental_count($screen) {
	if ( $screen->id != 'edit-comments' )
        return;
	add_filter( 'comment_status_links', 'comment_status_links_with_sentimental_values' );
}

function comment_status_links_with_sentimental_values($status_links) {
	if (is_admin()){
		$sentimentOptions = get_option('sentiment_analysis_options');
		if ($sentimentOptions['admin']) {
			$comment_status = isset( $_REQUEST['comment_status'] ) ? $_REQUEST['comment_status'] : 'all';
			if ( !in_array( $comment_status, array( 'all', 'moderated', 'approved', 'spam', 'trash' ) ) )
				$comment_status = 'all';
			$post_id		= ($_REQUEST['p']) ? $_REQUEST['p'] : '';
			$search			= ($_REQUEST['s']) ? $_REQUEST['s'] : '';
			$commentType	= ($_REQUEST['comment_type']) ? $_REQUEST['comment_type'] : '';
			$status_map 	= array(
								'moderated' => 'hold',
								'approved' => 'approve',
								'all' => '',);
			$arg		= array('status' => isset( $status_map[$comment_status] ) ? $status_map[$comment_status] : $comment_status,
								'post_id' => $post_id,
								'search' => $search,
								'type' => $commentType,);
			$comments 	= get_comments( $arg );
			$neutral	= $good = $bad = 0;
			foreach($comments as $sentiment)
			{
				if($sentiment->comment_sentiment_value == 'good'){
					$good	+=1;
				}else if ($sentiment->comment_sentiment_value == 'bad'){
					$bad +=1;
				}else if ($sentiment->comment_sentiment_value == 'neutral'){
					$neutral +=1;
				}
			}
			$status_links['sentiment']	= '&nbsp;&nbsp;&nbsp;<a href="javascript:void(0);" style="cursor:default;"><img src="'.plugins_url('images/bad.png', __FILE__ ).'" title="Bad Comments" align="absmiddle"><span class="count">&nbsp;(<span class="bad-count">'.$bad.'</span>)</span></a>
			<a href="javascript:void(0);" style="cursor:default;"><img src="'.plugins_url('images/neutral.png', __FILE__ ).'" title="Neutral Comments" align="absmiddle"><span class="count">&nbsp;(<span class="neutral-count">'.$neutral.'</span>)</span></a>
			<a href="javascript:void(0);" style="cursor:default;"><img src="'.plugins_url('images/good.png', __FILE__ ).'"  title="Good Comments" align="absmiddle"><span class="count">&nbsp;(<span class="good-count">'.$good.'</span>)</span></a>
			';
		}
	}
	return $status_links;
}
	
function add_sentimental_settings_link($links, $file){
	if ( plugin_basename( __FILE__ ) == $file ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=sentiment-analysis' ) . '">' . __( 'Settings', 'sentiment-analysis' ) . '</a>';
		array_push( $links, $settings_link );
	}
	return $links;
}

?>