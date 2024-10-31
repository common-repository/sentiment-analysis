<?php
if (!class_exists("SentimentAdmin")) {
	class SentimentAdmin {
		var $adminOptionsName = "sentiment_analysis_options";
		
		function __construct() { 
			$this->init();
		}
		function init() {
			$this->get_admin_options();
			
		}
		function get_admin_options() {
			$sentimentAdminOptions 	= array('admin' => '',
											'frontend' => '',
											'apikey' => '',
											'apiurl' => '',
											'findnow' => '',);
			$sentimentOptions 		= get_option($this->adminOptionsName);
			if (!empty($sentimentOptions)) {
				foreach ($sentimentOptions as $key => $option)
					$sentimentAdminOptions[$key] = $option;
			}
			update_option($this->adminOptionsName, $sentimentAdminOptions);
			return $sentimentAdminOptions;
		}
	
		function printAdminPage() {
			$sentimentOptions = $this->get_admin_options();
			if (isset($_POST['update_sentimentAnalysisSettings'])) {
				$sentimentOptions['admin'] 		= apply_filters('keyword_save_pre', $_POST['admin']);
				$sentimentOptions['frontend'] 	= apply_filters('keyword_save_pre', $_POST['frontend']);
				$sentimentOptions['apikey'] 	= apply_filters('keyword_save_pre', $_POST['apikey']);
				$sentimentOptions['apiurl'] 	= apply_filters('keyword_save_pre', $_POST['apiurl']);
				update_option($this->adminOptionsName, $sentimentOptions);
				?>
				<div id="message" class="updated">
				 <p><strong><?php _e('Settings Updated.') ?></strong></p>
				</div>
				<?php
				} 
			if (isset($_POST['findSentimentValues'])) {
				$sentimentOptions['admin'] 		= apply_filters('keyword_save_pre', $_POST['admin']);
				$sentimentOptions['frontend'] 	= apply_filters('keyword_save_pre', $_POST['frontend']);
				$sentimentOptions['apikey'] 	= apply_filters('keyword_save_pre', $_POST['apikey']);
				$sentimentOptions['apiurl'] 	= apply_filters('keyword_save_pre', $_POST['apiurl']);
				$sentimentOptions['findnow'] 	= apply_filters('keyword_save_pre', '1');
				if( !wp_next_scheduled( 'sentiment_analysis_cron' ) )
				{
				   wp_schedule_event( time(), 'oneminute', 'sentiment_analysis_cron' );
				}
				update_option($this->adminOptionsName, $sentimentOptions);
				?>
				<div class="updated"><p><strong><?php _e("Settings Updated. Analysing Sentimental Values.", "sentimentAdmin");?></strong></p></div>
				<?php
				} ?>
				<style>
					.wrap .input_kwd{width:300px;}
					.sentiment-settings th {width:380px;}
					.sentiment-settings input {width:auto;}
				</style>
				<div class="wrap">
				
					<div style="float:left;width:100%">
						<form method="post" action="<?php echo admin_url('admin.php?page=sentiment-analysis')?>">
						<h2><img src="<?php echo plugins_url('images/settings.png', __FILE__ )?>" align="absbottom" />&nbsp;Sentiment Analysis</h2>
						<h3>Sentiment Analysis Settings</h3>
			<table class="form-table sentiment-settings">
				<tbody>
				 <tr class="form-field form-required">
				  <th scope="row">Show sentiment icon on front end with each comments </th>
				  <td><label for="send_password"><input type="checkbox" name="frontend" <?php checked('1', $sentimentOptions['frontend']); ?> value="1" ></label></td>
				 </tr>
				 <tr class="form-field form-required">
				  <th scope="row">Show sentiment icon on admin side </th>
				  <td><input type="checkbox" name="admin" <?php checked('1', $sentimentOptions['admin']); ?> value="1" ></td>
				 </tr>
				 <tr class="form-field form-required">
				  <th scope="row">Sentimental analysis API key <span class="description">(required)</span></th>
				  <td><input type="text" name="apikey" class="input_kwd"  value="<?php echo esc_attr($sentimentOptions['apikey']); ?>" required />
					<br>  <p class="description">Please request for api key <a href="http://www.sentimentanalysisonline.com/page/api-request/" target="_blank">here</a></p></td>
				 </tr>
				 <tr class="form-field form-required">
				  <th scope="row">Sentimental analysis API Url <span class="description">(required)</span></th>
				  <td><input type="text" name="apiurl" class="input_kwd" value="<?php echo esc_attr($sentimentOptions['apiurl']); ?>" required />
					  <br> <p class="description">Sample Url - http://api.sentimentanalysisonline.com/sentimentscore.asmx</p></td>
				 </tr>
				 <?php
				if ($sentimentOptions['apikey'] != '' && $sentimentOptions['apiurl'] != '' && $sentimentOptions['findnow'] != '2')
				{
				?>
				 <tr class="form-field form-required">
				  <th scope="row">Find sentimental values for earlier comments</th>
				  <td>
				  <?php
					if ($sentimentOptions['findnow'] == 1){
					?>
					<img src="<?php echo plugins_url('images/ajax-loader.gif', __FILE__ )?>" title="finding...">
					<?php } 
					else{
					?>
					<p><input type="submit" name="findSentimentValues" value="Find Now" class="button button-primary"></p>
					<?php } ?>
				  </td>
				 </tr>
				<?php } ?> 
				</tbody>
			</table>
			<p class="submit">
				<input type="submit" name="update_sentimentAnalysisSettings" value="Update Settings" class="button button-primary"></p>
			</form>
		  </div>
	   </div>
		<?php
	  }
	} 
}

function sentiment_analysis_menu() {
	add_menu_page( 'Sentiment Analysis Settings', 'Sentiments', 'manage_options', 'sentiment-analysis', 'display_sentiment_analysis_options', plugins_url('images/menu.png', __FILE__));
	add_options_page( 'Sentiment Analysis Settings', 'Sentiments', 'manage_options', 'sentiment-analysis', 'display_sentiment_analysis_options' );
}

function display_sentiment_analysis_options() {
	if ( !current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	global $ObjSentimentPlugin;
	if (!isset($ObjSentimentPlugin)) {
			return;
	}
	$ObjSentimentPlugin->printAdminPage();
}

function save_comment_sentimental_value() {
	global $wpdb;
	$comments		= get_comments();
	$comment		= (array) $comments[0];
	$lastComment	= $comment['comment_content'];
	$lastCommentID	= $comment['comment_ID'];
	$postID			= $comment['comment_post_ID'];
	$posts			= get_post($postID);
	$postDetails	= array ($posts);
	$sentimentOptions = get_option('sentiment_analysis_options');
	$ipAddress		= $_SERVER['REMOTE_ADDR'];
	$xml			= '<?xml version="1.0"?>
						 <root>
						 <apikey>'.$sentimentOptions['apikey'].'</apikey>
						 <QueryItems>
						  <query>
							<id>1</id>
							<brandname><![CDATA['.$postDetails[0]->post_title.']]></brandname>
							<ipaddress><![CDATA['.$ipAddress.']]></ipaddress>
							<paragraph><![CDATA['.$lastComment.']]></paragraph>
						  </query>
						</QueryItems>
						</root>';
	$params 		= array('searchXML' => $xml);
	$client 		= new SoapClient($sentimentOptions['apiurl']."?wsdl");
	$response 		= $client->GetScore($params);
	$apiResult		= (array) $response;
	$xmlData		= simplexml_load_string($apiResult['GetScoreResult']);
	$apiSentimentResult	= $xmlData->result;
	if ( $apiSentimentResult == 'Invalid API Key.' )
		$sentiments	= '';
	else if( $apiSentimentResult >= -0.25 && $apiSentimentResult <= 0.25 )
		$sentiments	= 'neutral';
	else if ( $apiSentimentResult < -0.25 )
		$sentiments	= 'bad';
	else if ( $apiSentimentResult > 0.25 )
		$sentiments	= 'good';
	$tableName		= $wpdb->prefix . "comments";
	$data			= array ('comment_sentiment_value' => $sentiments);
	$where			= array ('comment_ID' => $lastCommentID);
	$wpdb->update( $tableName, $data, $where, $format = null, $where_format = null );
}

function update_comment_sentimental_value($comment_ID) {
	global $wpdb;
	$comment		= get_comment($comment_ID);
	$lastComment	= $comment->comment_content;
	$lastCommentID	= $comment->comment_ID;
	$postID			= $comment->comment_post_ID;
	$posts			= get_post($postID);
	$postDetails	= array ($posts);
	$sentimentOptions = get_option('sentiment_analysis_options');
	$ipAddress		= $_SERVER['REMOTE_ADDR'];
	$xml			= '<?xml version="1.0"?>
						 <root>
						 <apikey>'.$sentimentOptions['apikey'].'</apikey>
						 <QueryItems>
						  <query>
							<id>1</id>
							<brandname><![CDATA['.$postDetails[0]->post_title.']]></brandname>
							<ipaddress><![CDATA['.$ipAddress.']]></ipaddress>
							<paragraph><![CDATA['.$lastComment.']]></paragraph>
						  </query>
						</QueryItems>
						</root>';
	$params 		= array('searchXML' => $xml);
	$client 		= new SoapClient($sentimentOptions['apiurl']."?wsdl");
	$response 		= $client->GetScore($params);
	$apiResult		= (array) $response;
	$xmlData		= simplexml_load_string($apiResult['GetScoreResult']);
	$apiSentimentResult	= $xmlData->result;
	if ( $apiSentimentResult == 'Invalid API Key.' )
		$sentiments	= '';
	else if( $apiSentimentResult >= -0.25 && $apiSentimentResult <= 0.25 )
		$sentiments	= 'neutral';
	else if ( $apiSentimentResult < -0.25 )
		$sentiments	= 'bad';
	else if ( $apiSentimentResult > 0.25 )
		$sentiments	= 'good';
	$tableName		= $wpdb->prefix . "comments";
	$data			= array ('comment_sentiment_value' => $sentiments);
	$where			= array ('comment_ID' => $lastCommentID);
	$wpdb->update( $tableName, $data, $where, $format = null, $where_format = null );
}

function add_sentiment_images_with_comments($comment_text,$comment_ID) {
	$sentimentOptions 	= get_option('sentiment_analysis_options');
	$comment			= get_comment($comment_ID);
	if (is_admin()){
		if ($sentimentOptions['admin']) {
			if ($comment->comment_sentiment_value == 'neutral')
				$sentimentalImage = '<img src="'.plugins_url('images/neutral.png', __FILE__).'" title="Neutral">';
			else if ($comment->comment_sentiment_value == 'good')
				$sentimentalImage = '<img src="'.plugins_url('images/good.png', __FILE__).'" title="Good">';
			else if ($comment->comment_sentiment_value == 'bad')
				$sentimentalImage = '<img src="'.plugins_url('images/bad.png', __FILE__).'" title="Bad">';
			else
				$sentimentalImage = '';
			return $sentimentalImage.' '.$comment_text;
		}else{
			return $comment_text;
		}
	}
	else{
		if ($sentimentOptions['frontend']) {
			if ($comment->comment_sentiment_value == 'neutral')
				$sentimentalImage = '<img src="'.plugins_url('images/neutral.png', __FILE__).'" title="Neutral">';
			else if ($comment->comment_sentiment_value == 'good')
				$sentimentalImage = '<img src="'.plugins_url('images/good.png', __FILE__).'" title="Good">';
			else if ($comment->comment_sentiment_value == 'bad')
				$sentimentalImage = '<img src="'.plugins_url('images/bad.png', __FILE__).'" title="Bad">';
			else
				$sentimentalImage = '';
			return $sentimentalImage.' '.$comment_text;
		}else{
			return $comment_text;
		}
	}
}

function add_cron_intervals( $schedules ) {
   $schedules['oneminute'] = array( 
							  'interval' => 20, 
							  'display' => __('One Minute'),
								);
   return $schedules; 
}

function find_all_comments_sentiment_value() {
	global $wpdb;
	$selectComments 	= " SELECT $wpdb->comments.comment_ID,$wpdb->comments.comment_content,$wpdb->comments.comment_post_ID 
							FROM $wpdb->comments
							WHERE ($wpdb->comments.comment_sentiment_value IS NULL OR $wpdb->comments.comment_sentiment_value = '') ";
	$comments 			= $wpdb->get_results($selectComments);
	if (count($comments) > 0) {
	$sentimentOptions = get_option('sentiment_analysis_options');
	$xml			= '<?xml version="1.0"?>
							 <root>
							 <apikey>'.$sentimentOptions['apikey'].'</apikey>
							 <QueryItems>';
	$commentCount = 0;
	$fetchCommentCount = count($comments);
	foreach($comments as $comment)
	{
		$commentText	= $comment->comment_content;
		$commentID		= $comment->comment_ID;
		$postID			= $comment->comment_post_ID;
		$posts			= get_post($postID);
		$postDetails	= array ($posts);
		$ipAddress		= $_SERVER['REMOTE_ADDR'];
		$xml			.= '<query>
								<id>'.$commentID.'</id>
								<brandname><![CDATA['.$postDetails[0]->post_title.']]></brandname>
								<ipaddress><![CDATA['.$ipAddress.']]></ipaddress>
								<paragraph><![CDATA['.$commentText.']]></paragraph>
							  </query>
							';
		$commentCount++;
		$fetchCommentCount--;
		if ($commentCount == 100 || $fetchCommentCount == 0){
			$xml .= '</QueryItems>
							</root>';
		$params 		= array('searchXML' => $xml);
		$client 		= new SoapClient($sentimentOptions['apiurl']."?wsdl");
		$response 		= $client->GetScore($params);
		$apiResult		= (array) $response;
		$xmlData		= simplexml_load_string($apiResult['GetScoreResult']);
		foreach($xmlData->children() as $child)
		{
			if ($child->getName() == 'id'){
				$commentID = $child;
			}
			if ($child->getName() == 'result'){
				$apiSentimentResult = $child;
			}
			if ($commentID != '' && $apiSentimentResult != '')
			{
				if ( $apiSentimentResult == 'Invalid API Key.' ) {
					$sentimentOptions 	= get_option('sentiment_analysis_options');
					$sentimentOptions['findnow']	= '0';
					update_option('sentiment_analysis_options', $sentimentOptions);
					$result	= 'InvalidApiKey';
					break;
				} else if( $apiSentimentResult >= -0.25 && $apiSentimentResult <= 0.25 ) {
					$sentiments	= 'neutral';
				} else if ( $apiSentimentResult < -0.25 ) {
					$sentiments	= 'bad';
				} else if ( $apiSentimentResult > 0.25 ) {
					$sentiments	= 'good';
				}
				$tableName		= $wpdb->prefix . "comments";
				$data			= array ('comment_sentiment_value' => $sentiments);
				$where			= array ('comment_ID' => $commentID);
				$wpdb->update( $tableName, $data, $where, $format = null, $where_format = null );
				$commentID	= $apiSentimentResult	= '';
			}
		}
		$xml			= '<?xml version="1.0"?>
							 <root>
							 <apikey>'.$sentimentOptions['apikey'].'</apikey>
							 <QueryItems>';
		$commentCount = 0;
		}
	}
	if ( $result	!= 'InvalidApiKey' ) {
		$sentimentOptions 	= get_option('sentiment_analysis_options');
		$sentimentOptions['findnow']	= '2';
		update_option('sentiment_analysis_options', $sentimentOptions);
		find_all_comments_sentiment_value();
	}
		
	}else{
		$sentimentOptions 	= get_option('sentiment_analysis_options');
		$sentimentOptions['findnow']	= '2';
		update_option('sentiment_analysis_options', $sentimentOptions);
		wp_clear_scheduled_hook('sentiment_analysis_cron');
	}
	
} 

?>