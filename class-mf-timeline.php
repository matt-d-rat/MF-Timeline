<?php
/*
Plugin Name: WP Facebook Timeline (MF-Timeline)
Plugin URI: http://www.aplaceformyhead.co.uk/2011/10/05/wp-facebook-timeline-mf-timeline/
Description: Creates a visual linear timeline representation from your Wordpress posts and other media sources in the style of Facebook Profile Timeline.
Version: 1.1.7
Author: Matt Fairbrass
Author URI: http://www.aplaceformyhead.co.uk
License: GPLv2
.
By default the timeline is styled to resemble Facebook's Profile Timeline, but you are free to override the enqueued plugin styles with your own.
.
*/
class MF_Timeline {
	public $years = array();
	public $pluginPath;
	public $pluginUrl;
	public $errors;
	protected $table_mf_timeline_stories;
	
	public function __construct() {
		global $wpdb;
		
		$this->pluginPath = dirname( __FILE__ );
		$this->pluginUrl = WP_PLUGIN_URL . '/mf-timeline';
		$this->errors = new WP_Error();
		
		$this->table_mf_timeline_stories = $wpdb->prefix . 'mf_timeline_stories';
		
		// Action Hooks
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_action( 'admin_init', array( &$this, 'mf_timeline_admin_init') );
		add_action( 'wp_print_styles', array( &$this, 'mf_timeline_styles' ) );
		add_action( 'init', array( &$this, 'mf_timeline_js' ) );
		add_action( 'admin_head', array( &$this, 'load_tiny_mce' ) );
		
		// Shortcode
		add_shortcode( 'mf_timeline', array( &$this, 'shortcode' ) );
	}
	
	/**
	 * MF Timeline Admin Init
	 * Initiate the plugin and its settings.
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function mf_timeline_admin_init() {
		global $mf_timeline_db_version;
		register_setting( 'mf_timeline_settings', 'mf_timeline', array( &$this, 'validate_settings' ) );
		
		wp_register_script( 'jquery-ui', ("https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/jquery-ui.min.js"), false, '1.8.16' );
		wp_register_script( 'mf_timeline_admin_js', plugins_url( 'scripts/js/jquery.mf_timeline_admin.min.js', __FILE__ ), true );
		wp_register_style( 'mf_timeline_admin_styles', plugins_url( 'styles/admin.min.css', __FILE__ ) );
		wp_register_style( 'jquery-ui', plugins_url( 'styles/jquery-ui/theme-wordpress.css', __FILE__ ) );
		 
		wp_enqueue_script( array( 'jquery', 'editor', 'thickbox', 'media-upload' ) );
		wp_enqueue_script( 'jquery-ui' );
		wp_enqueue_style( 'jquery-ui' );
		wp_enqueue_script( 'mf_timeline_admin_js' );
		wp_enqueue_style( 'mf_timeline_admin_styles' );
		
		$this->check_db_upgrade( $mf_timeline_db_version );
		
		// Hack: For some reason I can't seem to hook the parse_request action, so resorting to this:
		if( isset( $_POST['story'] ) && !empty( $_POST['story'] ) ) {
			$this->validate_timeline_stories( $_POST['story'] );
		}
	}
	
	/**
	 * MF Timeline Styles
	 * Enqueue the plugin stylesheets to style the timeline output.
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function mf_timeline_styles() {
		wp_register_style( 'mf_timeline_styles', plugins_url( 'styles/style.min.css' , __FILE__ ) );
        wp_enqueue_style( 'mf_timeline_styles' );
	}
	
	/**
	 * MF Timeline JS
	 * Register and enqueue the JS files used by the plugin
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function mf_timeline_js() {
		$options = get_option( 'mf_timeline' );
		
		if( !is_admin() && $options['options']['timeline_nav'] == 1 ) {	
			if ( function_exists( 'wp_register_script' ) ) {
				wp_register_script( 'afterscroll', plugins_url( 'scripts/js/jquery.afterscroll.min.js', __FILE__), array( 'jquery' ) );
				wp_register_script( 'stickyfloat', plugins_url( 'scripts/js/jquery.stickyfloat.min.js', __FILE__ ), array( 'jquery' ) );
			}
			
			if ( function_exists( 'wp_enqueue_script' ) ) {
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'afterscroll' );
				wp_enqueue_script( 'stickyfloat' );
			}	
		}
	}
	
	/**
	 * Load Tiny MCE
	 * Laods the scripts used by the visual editor
	 *
	 * @see add_action('admin_head', 'load_tiny_mce');
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function load_tiny_mce() {
		wp_tiny_mce( false, array(
			'editor_selector' => 'story[story_content]'
			)
		);
	}
	
	
	/**
	 * Admin Menu
	 * Set up the plugin options page.
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function admin_menu() {  
		add_options_page( 'MF Timeline Settings', 'MF-Timeline', 'manage_options', 'mf-timeline', array( &$this, 'get_plugin_options_page' ) );
	}
	
	/**
	 * Shortcode
	 * Create a shortcode that can be used within Wordpress posts to output the MF-Timeline.
	 *
	 * @return string the html output of the timeline
	 * @author Matt Fairbrass
	 **/
	public function shortcode() {
		return $this->get_timeline();
	}
	
	/**
	 * Validate Settings
	 * Validates the data being submitted from the MF-Timeline Settings
	 *
	 * @param $input array the data to validate
	 *
	 * @return $input array the sanitised data.
	 * @author Matt Fairbrass
	 **/
	public function validate_settings( $input ) {
		$valid_input = array();
		
		/* General Settings */
		$valid_input['options']['timeline_nav'] = ( $input['options']['timeline_nav'] == 1 ? 1 : 0 );
		$valid_input['db_version'] = $input['db_version'];
		
		/* Wordpress */
		if( !empty( $input['options']['wp']['content'] ) || !empty( $input['options']['wp']['filter'] ) ) {
			// Content
			foreach( $input['options']['wp']['content'] as $key=>$value ) {
				$valid_input['options']['wp']['content'][$key] = (int) ( $value == 1 ? 1 : 0 );
			}
			
			// Filters
			foreach( $input['options']['wp']['filter'] as $filter=>$value ) {
				foreach( $value as $id=>$val ) {
					switch( $filter ) {
						case 'taxonomy' :
							$valid_input['options']['wp']['filter']['taxonomy'][$id] = (int) ( $val == 1 ? 1 : 0 );
						break;
					
						default :
							$valid_input['options']['wp']['filter'][$filter][$id] = wp_filter_nohtml_kses( $val );
						break;
					}
				}
			}
		}
		
		/* Twitter */
		if( !empty( $input['options']['twitter']['content']) || !empty($input['options']['twitter']['filter'] ) ) {
			// Content
			foreach( $input['options']['twitter']['content'] as $key=>$value ) {
				switch( $key ) {
					case 'username' :
						$valid_input['options']['twitter']['content']['username'] = wp_filter_nohtml_kses( str_replace( '@', '', $value ) );
					break;
					
					case 'timeline' :
						$valid_options = array( '1', '2' );
						$valid_input['options']['twitter']['content']['timeline'] = ( in_array( $value, $valid_options ) == true ? $value : null );
					break;
					
					default :
						$valid_input['options']['twitter']['content'][$key] = wp_filter_nohtml_kses( $value );
					break;
				}
			}
			
			// Filters
			foreach( $input['options']['twitter']['filter'] as $filter=>$value ) {
				switch($filter) {
					case 'tags' :
						$valid_input['options']['twitter']['filter']['tags'] = wp_filter_nohtml_kses( str_replace('#', '', $value ) );
					break;
				}
			}
		}

		return $valid_input;
	}
	
	/**
	 * Validate Timeline Stories
	 * Validates the data being submitted from the MF-Timeline stories editor
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function validate_timeline_stories( $input ) {
		global $wpdb;
		$valid_input = array();

		$valid_input['story_title'] = wp_kses_post( $input['story_title'] );
		$valid_input['story_content'] = wp_kses_post( (string) $input['story_content'] );
		$valid_input['timeline_date'] = date( 'Y-m-d', strtotime( esc_html( $input['timeline_date'] ) ) );
		$valid_input['featured'] = (int) ( isset( $input['featured'] ) && $input['featured'] == 1 ? 1 : 0 );
		$valid_input['story_modified'] = date( 'Y-m-d H:i:s' );
		$valid_input['story_author'] = (int) $input['story_author'];
		
		
		
		if( $input['story_id'] == null ) {
			$result = $wpdb->insert( $this->table_mf_timeline_stories, $valid_input, array( '%s','%s','%s','%d','%s','%d' ) );
		}
		else {
			$story_id = (int) $input['story_id'];
			$result = $wpdb->update( $this->table_mf_timeline_stories, $valid_input, array( 'story_id' => $story_id ), array( '%s','%s','%s','%d','%s','%d' ), '%d' );
		}
		
		if($result === false) {
			$this->errors->add( 'timeline_story', __( 'An error occurred whilst attempting to save timeline story to the database.' ) );
		}
		else {
			add_settings_error('general', 'settings_updated', __('Timeline story saved.'), 'updated');
		}
	}
	
	/**
	 * Get Plugin Settings Page
	 * Output the options for the plugin settings page.
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function get_plugin_options_page() {
		if( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		
		if( isset( $_GET['tab'] ) && $_GET['tab'] == 'settings' || !isset( $_GET['tab'] ) ) {
			$settings_active = 'nav-tab-active';
		}
		else if( isset( $_GET['tab'] ) && $_GET['tab'] == 'stories' ) {
			$stories_active = 'nav-tab-active';
		}
		else if( isset( $_GET['tab'] ) && $_GET['tab'] == 'maintenance') {
			$maintenance_active = 'nav-tab-active';
		}
	?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"><br></div><h2>MF-Timeline Options</h2>
			
			<?php 
				$errors = $this->errors->get_error_messages();
				
				if( !empty( $errors ) ) :
			?>
				<?php foreach( $errors as $error ) : ?>
						<div class="error">
							<p><strong><?php echo $error; ?></strong></p>
						</div>
				<?php endforeach; ?>
			<?php endif;?>
			
			<div id="nav">
				<h3 class="themes-php">
					<a class="nav-tab <?php echo $settings_active;?>" href="?page=mf-timeline&amp;tab=settings">Settings</a>
					<a class="nav-tab <?php echo $stories_active;?>" href="?page=mf-timeline&amp;tab=stories">Timeline Stories</a>
					<a class="nav-tab <?php echo $maintenance_active;?>" href="?page=mf-timeline&amp;tab=maintenance">Upgrade</a>
				</h3>
			</div>
			
			<?php
				$active_tab = ( isset( $_GET['tab'] ) ) ? $_GET['tab'] : null;
				
				switch( $active_tab ) {
					case 'stories' :
						if( isset( $_GET['action'] ) && $_GET['action'] == 'editor' ) {
							$story_id = ( isset( $_GET['story_id'] ) ) ? (int) $_GET['story_id'] : null;
							$this->get_plugin_stories_editor( $story_id );
						}
						else if( isset( $_GET['action'] ) && $_GET['action'] == 'delete' ) {
							$story_id = (int) $_GET['story_id'];
							$this->delete_story( $story_id );
							$this->get_plugin_stories_list_page();
						}
						else {
							$this->get_plugin_stories_list_page();
						}
					break;
					
					case 'maintenance' :
						$this->get_plugin_maintenance_page();
					break;
					
					case 'settings' :
					default :
						$this->get_plugin_settings_page();
					break;
				}
			?>
		</div>
	<?php
	}
	
	/**
	 * Get Plugin Settings Page
	 * Output the settings page in the plugin options.
	 *
	 * @see get_plugion_options_page()
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function get_plugin_settings_page() { ?>
		<p>Configure the default MF-Timeline settings below. You can override these settings when calling the shortcode in your posts or the function in your templates.</p>
	
		<form action="options.php" method="POST">
			<?php
				global $wp_taxonomies, $wp;
				$nonhierarchical = null;
				
				settings_fields( 'mf_timeline_settings' );
				$options = get_option( 'mf_timeline' );
			?>
			<input type="hidden" name="mf_timeline[db_version]" value="<?php echo $options['db_version']; ?>" />
			
			<h3>General Settings</h3>
			<fieldset>
				<ul>
					<li>
						<label for="mf_timeline[options][timeline_nav]"><strong>Timeline Years Menu:</strong></label><br/>
						<select name="mf_timeline[options][timeline_nav]" id="mf_timeline[options][timeline_nav]" style="width: 100px;">
							<option value="1" <?php selected( '1', $options['options']['timeline_nav'] ); ?>>Show</option>
							<option value="0" <?php selected( '0', $options['options']['timeline_nav'] ); ?>>Hide</option>
						</select><br/>
						<span class="description">Appears fixed next to the timeline allowing the user to navigate past events more easily.</span>
					</li>
				</ul>
			</fieldset>
			<h3>Wordpress Content</h3>
			<fieldset>
				<ul>
					<li>
						<h4>Include content from:</h4>
						<?php foreach( get_post_types( '', 'object' ) as $key=>$post_type ) :?>
							<input type="checkbox" name="mf_timeline[options][wp][content][<?php echo $key;?>]" id="mf_timeline[options][wp][content][<?php echo $key;?>]" value="1" <?php checked( '1', $options['options']['wp']['content'][$key] ); ?> />
							<label for="mf_timeline[options][wp][content][<?php echo $key;?>]"><?php _e( $post_type->labels->name ); ?></label><br />
						<?php endforeach;?>
					</li>
					<li>
						<h4>Filter by the following taxonomies:</h4>
						<p class="description clear">Leave blank to not filter by taxonomies.</p>
						
						<?php if ( is_array( $wp_taxonomies ) ) : ?>
							<?php foreach ( $wp_taxonomies as $tax ) :?>
								<?php if ( !in_array( $tax->name, array( 'nav_menu', 'link_category', 'podcast_format' ) ) ) : ?>
									<?php if ( !is_taxonomy_hierarchical( $tax->name ) ) : // non-hierarchical ?>
										<?php 
											$nonhierarchical .= '<p class="alignleft"><label for="mf_timeline[options][wp][filter][term][' . esc_attr($tax->name).']"><strong>' . esc_html( $tax->label ) . ': </strong></label><br />';
											$nonhierarchical .= '<input type="text" name="mf_timeline[options][wp][filter][term][' . esc_attr( $tax->name ) . ']" id="mf_timeline[options][wp][filter][term][' . esc_attr( $tax->name ) . ']" class="widefloat" style="margin-right: 2em;" value="' . $options['options']['wp']['filter']['term'][$tax->name] . '" /></p>';
										?>
									<?php else: // hierarchical ?>
										 <div class="categorychecklistbox">
											<label><strong><?php echo $tax->label;?></strong><br />
								        	<ul class="categorychecklist">
									     		<?php $terms = get_terms( $tax->name );?>
												
												<?php foreach( $terms as $term ) :?>
													<li>
														<input type="checkbox" name="mf_timeline[options][wp][filter][taxonomy][<?php echo $term->term_id;?>]" id="mf_timeline[options][wp][filter][taxonomy][<?php echo $term->term_id;?>]" value="1" <?php checked('1', $options['options']['wp']['filter']['taxonomy'][$term->term_id]); ?> />
														<label for="mf_timeline[options][wp][filter][taxonomy][<?php echo esc_html($term->term_id);?>]"><?php echo $term->name;?></label>
													</li>
												<?php endforeach;?>
											</ul>  
										</div>
									<?php endif;?>
								<?php endif;?>
							<?php endforeach; ?>
						<?php endif; ?>
					</li>
					<li class="clear">
						<br /><h4>Filter by the following terms:</h4>
						<p class="description">Separate terms with commas. Leave blank to not filter by terms.</p>
						<?php echo $nonhierarchical;?>
					</li>
				</ul>
			</fieldset>
		
			<h3>Twitter Content</h3>
			<fieldset>
				<ul>
					<li>
						<label for="mf_timeline[options][twitter][content][username]"><strong>Twitter Username:</strong></label><br/>
						<input type="text" name="mf_timeline[options][twitter][content][username]" id="mf_timeline[options][twitter][content][username]" value="<?php echo ( !empty( $options['options']['twitter']['content']['username'] ) ) ? $options['options']['twitter']['content']['username'] : null;?>" />
					</li>
					<li>
						<label for="mf_timeline[options][twitter][filter][tags]"><strong>Filter by the following hashtags:</strong></label><br/>
						<input type="text" name="mf_timeline[options][twitter][filter][tags]" id="mf_timeline[options][twitter][filter][tags]" value="<?php echo ( !empty( $options['options']['twitter']['filter']['tags']) ) ? $options['options']['twitter']['filter']['tags'] : null;?>" />
						<span class="description">Separate tags with commas. Leave blank to not filter by any tags.</span>
					</li>
				</ul>
			</fieldset>
			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button-primary" value="Save Settings">
			</p>
		</form>
	<?php
	}
	
	/**
	 * Get Plugin Stories List Page
	 * Outputs the stories list page.
	 *
	 *	@see get_plugin_options_page()
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function get_plugin_stories_list_page() {
		global $wpdb;

		$stories = $wpdb->get_results( $wpdb->prepare("SELECT * FROM `{$this->table_mf_timeline_stories}`" ), 'ARRAY_A' );
	?>
		
		<p>Timeline stories enable you to add content to the timeline without the need to create individual posts. You can manage all your timeline stores from this area.</p><br />
		<p><a href="?page=mf-timeline&amp;tab=stories&amp;action=editor" class="add-new-h2">Add New Story</a></p>
		
		<table class="widefat post fixed" cellspacing="0">
			<thead>
				<tr>
					<th class="manage-column" scope="col" width="40">ID</th>
					<th class="manage-column column-title" scope="col">Story Title</th>
					<th scope="col" width="225">Author</th>
					<th scope="col" width="125">Timeline Date</th>
				</tr>			
			</thead>
			<tfoot>
				<tr>
					<th class="manage-column" scope="col" width="40">ID</th>
					<th class="manage-column column-title" scope="col">Story Title</th>
					<th scope="col column-title" width="225">Author</th>
					<th scope="col column-date" width="125">Timeline Date</th>
				</tr>			
			</tfoot>
		
			<tbody>
				<?php foreach( $stories as $story ) :?>
					<tr>
						<th scope="row">
							<?php echo $story['story_id']; ?>
						</th>
						<td class="column-title">
							<strong><a class="row-title" href="#"><?php echo stripslashes( $story['story_title'] ); ?></a></strong>
							<div class="row-actions">
								<span class="edit"><a href="?page=mf-timeline&amp;tab=stories&amp;action=editor&amp;story_id=<?php echo $story['story_id']; ?>">Edit</a> | </span>
								<span class="edit"><a href="?page=mf-timeline&amp;tab=stories&amp;action=delete&amp;story_id=<?php echo $story['story_id']; ?>">Delete Permanently</a></span>
							</div>
						</td>
						<td class="column-author">
							<?php $user = get_userdata( $story['story_author'] );?>
							<a href="#"><?php echo $user->display_name; ?></a>
						</td>
						<td>
							<?php echo date( 'Y/m/d', strtotime( $story['timeline_date'] ) );?>
						</td>
					</tr>
				<?php endforeach;?>
			</tbody>
		</table>
	<?php	
	}
	
	/**
	 * Get Plugin Stories Editor
	 * Outputs the timeline stories editor page.
	 * 
	 * @param $story_id int the id of the story we are editing. If null we are adding a new story.
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function get_plugin_stories_editor( $story_id = null ) {
		global $wpdb;
		
		if( isset( $story_id ) && $story_id != null ) {
			$story = $this->get_story( $story_id );
		}
	?>
		<div id="poststuff" class="metabox-holder has-right-sidebar">
			<form action="options-general.php?page=mf-timeline&amp;tab=stories" method="post">
				<div id="side-info-column" class="inner-sidebar">
					<div id="side-sortables" class="meta-box-sortables ui-sortable">
						<div id="submitdiv" class="postbox ">
							<div title="Click to toggle" class="handlediv"><br></div>
							<h3 class="hndle">
								<span>Timeline Publish</span>
							</h3>
							
							<div class="inside">
								<div id="submitpost" class="submitbox">
									<div id="misc-publishing-actions">
										<div class="misc-pub-section curtime">
											<span id="timestamp">
												<label for="timeline_date">Date:</label>
											</span>
											<input type="text" name="story[timeline_date]" id="timeline_date" value="<?php echo ( !empty( $story['timeline_date'] ) ) ? date( 'Y/m/d', strtotime( $story['timeline_date'] ) ) : date( 'Y/m/d' );?>" />
										</div>
										
										<div class="misc-pub-section misc-pub-section-last">
											<input type="checkbox" name="story[featured]" id="featured" value="1" <?php checked( '1', $story['featured'] );?> />
											<label for="featured">Featured event?</label>
										</div>
									</div>
								
									<div id="major-publishing-actions">
										<div id="delete-action">
											<a href="#" class="submitdelete deletion">Delete Permanently</a>
										</div>

										<div id="publishing-action">
												<input type="submit" accesskey="p" tabindex="5" value="Save Story" class="button-primary" id="save" name="save">
										</div>
										
										<div class="clear"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div id="post-body">
					<div id="post-body-content">
						<fieldset>
							<ul>
								<li>
									<div id="titlediv">
										<input type="text" name="story[story_title]" id="title" value="<?php echo ( isset( $story['story_title'] ) ) ? stripslashes( $story['story_title'] ) : null; ?>" class="title" placeholder="Enter story title here" tabindex="1" />
									</div>
								</li>
								<li>
									<div id="postdivrich" class="postarea">
										<?php 
											if( !isset( $story['story_content'] ) ) {
												$story['story_content'] = null;
											}
										?>
										<?php the_editor( stripslashes( $story['story_content'] ), 'story[story_content]', 'title', true, 2 );?>
									</div>
								</li>
							</ul>
						</fieldset>
					</div>
				</div>
				
				<?php global $current_user; get_currentuserinfo(); ?>
				<input type="hidden" name="story[story_author]" value="<?php echo ( !empty( $story['story_author'] ) ) ? $story['story_author'] : $current_user->ID;?>" />
				<input type="hidden" name="story[story_id]" value="<?php echo ( isset( $story['story_id'] ) ) ? $story['story_id'] : null;?>" />
			</form>
		</div>
	<?php	
	}
	
	/**
	 * Get Story
	 * Returns the specified story from the database
	 * @param int $story_id the id of the story to retrieve
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	function get_story( $story_id ) {
		global $wpdb;
		$story = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_mf_timeline_stories} WHERE story_id = %d", $story_id ), 'ARRAY_A' );
		
		if(!empty($story)) {
			return $story;
		}
		else {
			wp_die( __( 'Invalid timeline story ID. The story you are looking for does not exist in the database.' ) );
		}
	}
	
	/**
	 * Delete Story
	 * Deletes the specified story from the MF-Timeline database.
	 *
	 * @param int $story_id the id of the story to delete
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	protected function delete_story( $story_id ) {
		global $wpdb;
		$sql = $wpdb->prepare("DELETE FROM {$this->table_mf_timeline_stories} WHERE story_id = %d", $story_id);
		
		if($wpdb->query($sql) !== false) {
			add_settings_error('general', 'settings_updated', __('Timeline story successfully deleted.'), 'updated');
		}
	}
	
	/**
	 * Get Plugin Maintenance Page
	 * Output the options for the plugin maintenance page.
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function get_plugin_maintenance_page() {	
		global $mf_timeline_db_version;
			
		if( isset( $_GET['action'] ) && $_GET['action'] == 'upgrade' ) {
			self::upgrade_db();
		}
		
		$options = get_option( 'mf_timeline' );
	?>
		<p>The maintenance upgrade tool will upgrade your database to the latest version if a newer version is available. Please ensure that you have backed up the <strong>'<?php echo $this->table_mf_timeline_stories; ?>'</strong> table in your WordPress database <strong>before</strong> running the upgrade tool. I accept no responsibility for any data lost as a result of running the upgrade tool.</p>
		
		<ul>
			<li><strong>Your Version:</strong> <?php echo $options['db_version'];?></li>
			<li><strong>Latest Version:</strong> <?php echo $mf_timeline_db_version;?></li>
		</ul>
		<br/>
		<?php if($options['db_version'] < $mf_timeline_db_version) : ?>
			<p><strong style="color: #c40f0f;">Your MF-Timeline database is out of date, press the button below to run the upgrade tool.</strong></p>
			
			<form action="?page=mf-timeline&amp;tab=maintenance&amp;action=upgrade" method="post">
				<p><input class="button-primary" type="submit" name="upgrade" value="Run Upgrade" id="submitbutton"></p>
			</form>
		<?php else: ?>
			<p><strong style="color: #008000;">You have the latest MF-Timeline database. Nothing to upgrade :-)</strong></p>
		<?php endif;?>
	<?php
	}
	
	/**
	 * Get Timeline Posts
	 * Returns an array of wordpress posts organised by year filtered by taxonomies.
	 *
	 * @return $posts array the posts returned by the query
	 * @author Matt Fairbrass
	 **/
	public function get_content_posts() {
		global $wpdb;
		$options = get_option( 'mf_timeline' );
		
		if( isset( $options['options']['wp']['content']) && !empty($options['options']['wp']['content'] ) ) {			
			/**
			 * // HACK
			 * Wordpress $wpdb->prepare() doesn't handle passing multiple arrays to its values. So we have to merge them.
			 * It is also unable to determine how many placeholders are needed for handling array values, so we have to work out how many we need.
			 * To be blunt, this is crap and needs to be looked at by the Wordpress dev team.
			 **/
			$post_types = array_keys( $options['options']['wp']['content'] );
			
			foreach( $post_types as $post_type ) {
				$post_types_escape[] = '%s';
			}
			
			$sql = "SELECT {$wpdb->posts}.ID AS id, {$wpdb->posts}.post_title AS title, {$wpdb->posts}.post_content AS content, {$wpdb->posts}.post_excerpt AS excerpt, {$wpdb->posts}.post_date AS date, {$wpdb->posts}.post_author AS author, {$wpdb->terms}.term_id AS term_id
				FROM `{$wpdb->posts}` 
				INNER JOIN {$wpdb->term_relationships} ON ({$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id) 
				INNER JOIN {$wpdb->term_taxonomy} ON ({$wpdb->term_relationships}.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id)
				INNER JOIN {$wpdb->terms} ON ({$wpdb->term_taxonomy}.term_id = {$wpdb->terms}.term_id)
				WHERE {$wpdb->posts}.post_status = 'publish' 
				AND {$wpdb->posts}.post_type IN (".implode(',', $post_types_escape).")";
			
			// Check if we are filtering the post types by hireachrical taxonomy terms
			if( isset( $options['options']['wp']['filter']['taxonomy'] ) && !empty( $options['options']['wp']['filter']['taxonomy'] ) ) {
				$term_ids = array_keys( $options['options']['wp']['filter']['taxonomy'] );
				
				foreach( $term_ids as $term_id ) {
					$term_ids_escape[] = '%d';
				}
			}
			
			// Check if we are filter the post types by non-hireachrical taxonomy terms
			if( isset($options['options']['wp']['filter']['term'] ) && !empty( $options['options']['wp']['filter']['term'] ) ) {
				foreach( $options['options']['wp']['filter']['term'] as $taxonomy_name=>$terms ) {
					foreach( explode( ',', $terms ) as $term ) {
						$the_term = get_term_by( 'slug', str_replace( ' ', '-', trim( $term ) ), $taxonomy_name );
						
						if( $the_term != false ) {
							$term_ids[] = $the_term->term_id;
							$term_ids_escape[] = '%d';
						}
					}	
				}
			}
			
			// Append the filters to the SQL statement
			if( isset( $term_ids_escape ) && !empty( $term_ids_escape ) ) {
				$sql .= "AND {$wpdb->terms}.term_id IN (" . implode( ',', $term_ids_escape ) . ")";
			}
			
			$sql .= "GROUP BY {$wpdb->posts}.ID";
			
			$query = $wpdb->prepare( $sql, array_merge( (array) $post_types, (array) $term_ids ) );
			$results = $wpdb->get_results( $query, 'ARRAY_A' );
			
			foreach($results as $post) {
				$year = date( 'Y', strtotime( $post['date'] ) );
				$post['source'] = 'wp';
				$posts[$year][] = $post;
			}
		
			return $posts;
		}
		else {
			return null;
		}
	}
	
	/**
	 * Get Content Tweets
	 * Returns an array of tweets organised by year filtered by hashtags.
	 *
	 * @return $tweets array the tweets returned by the query
	 * @author Matt Fairbrass
	 **/
	public function get_content_tweets() {
		global $wpdb;
		$options = get_option( 'mf_timeline' );
		
		if( isset($options['options']['twitter']['content']['username'] ) && !empty( $options['options']['twitter']['content']['username'] ) ) {

			$user = $options['options']['twitter']['content']['username'];
			
			if( !empty($options['options']['twitter']['filter']['tags'] ) ) {
				$hashtags = explode( ',', $options['options']['twitter']['filter']['tags'] );
				
				
				foreach( $hashtags as $key=>$hashtag ) {
					$hashtags[$key] = urlencode( '#' . $hashtag );
				}
				
				$query = implode( '+OR+', $hashtags ) . "+from:{$user}&amp;rpp=100";
			}
			else {
				$query = "from:{$user}&amp;rpp=100";
			}
			
			
			$url = "http://search.twitter.com/search.json?q={$query}";
			$json_file = file_get_contents( $url, 0, null, null );
			$json = json_decode( $json_file );
			
			$tweets = array();	
				
			if( is_object($json) && isset( $json->results ) ) {
				foreach( $json->results as $result ) {
					$year = date( 'Y', strtotime( $result->created_at ) );
					
					$row['content'] = (string) $result->text;
					$row['date'] = (string) $result->created_at;
					$row['author'] = (string) $result->from_user;
					$row['author_image'] = (string) $result->profile_image_url;
					$row['source'] = 'twitter';
					
					$tweets[$year][] = $row;
				}
			}
			
			return $tweets;
		}
		else {
			return null;
		}
	}
	
	/**
	 * Get Content Stories
	 * Returns an array of timeline stories organised by year
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	function get_content_stories() {
		global $wpdb;
		
		$sql = "SELECT story_id AS id, story_title AS title, story_content AS content, timeline_date AS date, featured FROM {$this->table_mf_timeline_stories}";
		$results = $wpdb->get_results( $sql, 'ARRAY_A' );
		
		if( !empty( $results ) ) {
			foreach( $results as $story ) {
				$year = date( 'Y', strtotime( $story['date'] ) );
				$story['source'] = 'timeline_stories';
				$stories[$year][] = $story;
			}
			
			return $stories;
		}
		else {
			return null;
		}
	}
	
	/**
	 * Get Timeline Events
	 * Returns an array of events organised by year.
	 *
	 * @uses get_content_posts()
	 * @uses get_content_tweets()
	 *
	 * @return $events array the events merged returned by the queries.
	 * @author Matt Fairbrass
	 **/
	public function get_timeline_events() {
		$contents[] = $this->get_content_posts();
		$contents[] = $this->get_content_tweets();
		$contents[] = $this->get_content_stories();
		
		$events = array();
		
		// Process each of the contents we have attempted to grab and combine them as events by year.
		foreach( $contents as $content ) {
			if( is_array ( $content ) ) {
				foreach ( $content as $year => $values ) {
					if( empty( $events[$year] ) || !isset( $events[$year] ) ) {
						$events[$year] = $values;
					}
					else {
						$events[$year] = array_merge( $events[$year], $values);
					}
				}
			}
		}
		
		foreach( $events as $year=>&$event ) {
			usort( $event, array( &$this, 'sort_events_by_date' ) );
		}
		
		krsort( $events ); // Sort the years numeric
		
		return $events;
	}
	
	/**
	 * Sort Events By Date
	 * Sorts the combined events array by date in ascending order.
	 *
	 * @return int the calculation.
	 * @author Matt Fairbrass
	 **/
	public function sort_events_by_date( $elem1, $elem2 ) {
		return strtotime( $elem2['date'] ) - strtotime( $elem1['date'] );
	}
	
	/**
	 * Get Timeline
	 * Output the timeline html to the page. This function can be called either via a shortcode or within a theme's template page.
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function get_timeline() {
		$events = $this->get_timeline_events();
		$class = null;
		$html = '<div class="timeline">';
			$html .= '<a href="#" class="timeline_spine"></a>';
			
			foreach( $events as $year=>$timeline_events ) {
				$html .= '<div class="section" id="' . $year . '">';
					$html .= '<div class="title">';
						$html .= '<a href="#">' . $year . '</a>';
					$html .= '</div>';
					
					$html .= '<ol class="events">';
						foreach( $timeline_events as $event ) {
							switch( $event['source'] ) {
								case 'wp' :
									if( get_post_meta( $event['id'], 'mf_timeline_featured', true ) ) {
										$excerpt_length = 700;
										$class = ' featured';
									}
									else {
										$excerpt_length = 300;
										$class = null;
									}
								
									$html .= '<li class="event ' . $event['source'] . $class . '">';
										$html .= '<div class="event_pointer"></div>';
										$html .= '<div class="event_container">';
											$html .= '<div class="event_title">';
												$html .= '<h3><a href="' . get_permalink( $event['id'] ) . '">' . $event['title'] . '</a></h3>';
									
												$html .= '<span class="subtitle">';
													$html .= $this->format_date( $event['date'] );
												$html .= '</span>';
											$html .= '</div>';
								
											$html .= '<div class="event_content">';
												$html .= apply_filters( 'the_content', $this->format_excerpt( $event['content'], $excerpt_length, $event['excerpt'] ) );
											$html .= '</div>';
										$html .= '</div>';
									$html .= '</li>';
								break;
								
								case 'twitter' :
									$html .= '<li class="event ' . $event['source'] . '">';
										$html .= '<div class="event_pointer"></div>';
										$html .= '<div class="event_container">';
											$html .= '<div class="event_title">';
												$html .= '<img src="' . $event['author_image'] . '" alt="' . $event['author'] . '" width="50" height="50" class="profile_image" />';
												$html .= '<h3><a href="http://www.twitter.com/' . $event['author'] . '/">@' . $event['author'] . '</a></h3>';
										
												$html .= '<span class="subtitle">';
													$html .= $this->format_date( $event['date'] );
												$html .= '</span>';
											$html .= '</div>';
									
											$html .= '<div class="event_content">';
												$html .= apply_filters( 'the_content', $this->format_text( $event['content'] ) );
											$html .= '</div>';
										$html .= '</div>';
									$html .= '</li>';
								break;
								
								case 'timeline_stories' :
									$class = ( $event['featured'] == 1 ) ? ' featured' : null;
									
									$html .= '<li class="event ' . $event['source'] . $class . '">';
										$html .= '<div class="event_pointer"></div>';
										$html .= '<div class="event_container">';
											$html .= '<div class="event_title">';
												$html .= '<h3>' . stripslashes( $event['title'] ) . '</h3>';
										
												$html .= '<span class="subtitle">';
													$html .= $this->format_date( $event['date'] );
												$html .= '</span>';
											$html .= '</div>';
									
											$html .= '<div class="event_content">';
												$html .= apply_filters( 'the_content', $this->format_text( stripslashes( $event['content'] ) ) );
											$html .= '</div>';
										$html .= '</div>';
									$html .= '</li>';
								break;
							}
						}
					$html .= '</ol>';
					
				$html .= '</div>';
			}
			
			$html .= $this->get_timeline_nav( array_keys( $events ) );
		$html .= '</div>';
			
		return $html;
	}
	
	/**
	 * Get Timeline Nav
	 * Outputs the timeline navigation menu and enqueues the the Javascript
	 *
	 * @param $years array an array of years
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	public function get_timeline_nav( $years ) {
		$options = get_option( 'mf_timeline' );
		
		if( $options['options']['timeline_nav'] == 1 ) {
			$html = '<ol class="timeline_nav">';
				foreach( $years as $year ) {
					$html .= '<li id="menu_year_' . $year . '"><a href="#' . $year . '">' . $year . '</a></li>';
				}
			$html .= '</ol>';
			$html .= '<script type="text/javascript" src="' . plugins_url( 'scripts/js/jquery.mf_timeline.min.js', __FILE__ ) . '"></script>';
			
			return $html;
		}
	}
	
	/**
	 * Format Date
	 * Convert a given date to (x) minutes/hours/days/weeks ago/from now or the date if outside of difference range.
	 *
	 * @param $date string the date to convert
	 *
	 * @return $difference $periods[$j] {$tense}
	 * @author Matt Fairbrass
	 **/
	public function format_date($date) {
	    if( empty( $date ) ) {
	        return false;
	    }

	    $periods = array( 'second', 'minute', 'hour', 'day', 'week', 'date' );
	    $lengths = array( '60', '60','24','7', '2', '12' );

	    $now = time();
	    $unix_date = strtotime( $date );

	    // check validity of date
	    if( empty( $unix_date ) ) {   
	        return 'Bad date';
	    }

	    // is it future date or past date
	    if( $now > $unix_date ) {   
	        $difference = $now - $unix_date;
	        $tense = 'ago';

	    } else {
	        $difference = $unix_date - $now;
	        $tense = 'from now';
	    }

	    for( $j = 0; $difference >= $lengths[$j] && $j < count( $lengths ) - 1; $j++ ) {
	        $difference /= $lengths[$j];
	    }

	    $difference = round( $difference );

	    if( $difference != 1 ) {
	        $periods[$j].= 's';
	    }
		
		if( $j == count( $lengths ) -1 ) {
			return date( 'd F Y', $unix_date );
		}
		else {
			return "$difference $periods[$j] {$tense}";
		}
	}
	
	/**
	 * Format Excerpt
	 * Allows us to format the content as an except outside of the loop
	 *
	 * @param $text string the text to format - usually the post content
	* @param $length int the number of characters to trim to. Set to 140 by default so full tweet content is shown on the timeline.
	 * @param $excerpt the except of the post
	 *
	 * @return $text string the formatted text.
	 * @author Matt Fairbrass
	 **/
	public function format_excerpt( $text, $length = 140, $excerpt ) {
	    if ( $excerpt ) return $excerpt;

	    $text = strip_shortcodes( $text );

	    $text = apply_filters( 'the_content', $text );
	    $text = str_replace( ']]>', ']]&gt;', $text );
	    $text = strip_tags( $text );
	    $excerpt_length = apply_filters( 'excerpt_length', $length );
	    $excerpt_more = apply_filters( 'excerpt_more', ' ' . '[...]' );
	    $words = preg_split( "/[\n\r\t ]+/", $text, $excerpt_length + 1, PREG_SPLIT_NO_EMPTY );
	    
		if ( count( $words ) > $excerpt_length ) {
	    	array_pop( $words );
	        $text = implode( ' ', $words );
	        $text = $text . $excerpt_more;
	    } 
		else {
			$text = implode( ' ', $words );
	    }
		
		$text = $this->format_text( $text );
		
	    return apply_filters( 'wp_trim_excerpt', $text );
	}
	
	
	/**
	 * Format Text
	 * Calls format_text_to_links and format_text_to_twitter
	 * 
	 * @param $text string the text to format
	 *
	 * @see format_text_to_links()
	 * @see format_text_to_twitter()
	 *
	 * @return $text string the formatted text
	 * @author Matt Fairbrass
	 **/
	public function format_text( $text ) {
		$text = $this->format_text_to_links( $text );
		$text = $this->format_text_to_twitter( $text );
		
		return $text;
	}
	
	/**
	 * Format Text To Links
	 * Transforms text urls into valid html hyperlinks
	 * 
	 * @param $text string the text to format
	 *
	 * @return $text string the formatted text
	 * @author Matt Fairbrass
	 **/
	public function format_text_to_links( $text ) {
		if( empty( $text ) ) {
			return null;
		}
		
	    $text = preg_replace( "/(^|[\n ])([\w]*?)((ht|f)tp(s)?:\/\/[\w]+[^ \,\"\n\r\t<]*)/is", "$1$2<a href=\"$3\" >$3</a>", $text );
	    $text = preg_replace( "/(^|[\n ])([\w]*?)((www|ftp)\.[^ \,\"\t\n\r<]*)/is", "$1$2<a href=\"http://$3\" >$3</a>", $text );
	    $text = preg_replace( "/(^|[\n ])([a-z0-9&\-_\.]+?)@([\w\-]+\.([\w\-\.]+)+)/i", "$1<a href=\"mailto:$2@$3\">$2@$3</a>", $text );
	    
		return $text;
	}
	
	/**
	 * Format Text To Twitter
	 * Transforms text to twitter users (@someone) links and hastag (#something) to links
	 * 
	 * @param $text string the text to format
	 *
	 * @return $text string the formatted text
	 * @author Matt Fairbrass
	 **/
	public function format_text_to_twitter($text) {
		if( empty( $text ) ) {
			return null;
		}
		
	    $text = preg_replace( "/(^|\s)@([a-z0-9_]+)/i", '<a href="http://www.twitter.com/$2" target="_blank">&#64;$2</a>', $text );
		$text = preg_replace( "/([^&]|^)\#([a-z0-9_\-]+)/", ' <a href="http://search.twitter.com/search?q=$2" target="_blank">&#35;$2</a>', $text );
		
		return $text;
	}
	
	/**
	 * Check DB Upgrade
	 * Checks the user's current MF-Timeline DB version against a specified db versipn to determine if an upgrade is available.
	 * 
	 * @param $db_version int the current version of the DB.
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	protected function check_db_upgrade( $db_version ) {
		$options = get_option( 'mf_timeline' );

		if( !isset( $options['db_version'] ) || empty( $options['db_version'] ) ) {
			self::install_db();
		}
		else if( $options['db_version'] < $db_version ) {
			$this->errors->add( 'db_upgrade', __('An upgrade is available for the MF-Timeline database. We recommend that you run the <a href="?page=mf-timeline&amp;tab=maintenance">maintenance upgrade tool</a> immediately to avoid any problems.' ) );
		}
	}
	
	/**
	 * Install DB
	 * Installs the mf_timeline_stories DB and then upgrades it to the latest version.
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	static function install_db() {
		global $wpdb;
		$options = get_option( 'mf_timeline' );
		$table = $wpdb->prefix . 'mf_timeline_stories';
		
		$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
			`story_id` mediumint(9) NOT NULL AUTO_INCREMENT,
			`story_title` text NOT NULL,
			`story_content` text,
			`timeline_date` date NOT NULL DEFAULT '0000-00-00',
			`featured` int(1) NOT NULL DEFAULT '0',
			`story_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (`story_id`),
			KEY `timeline_date` (`timeline_date`,`story_modified`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		
		// Update the user's db version to version 1 and run the upgrade script.
		if( !isset( $options['db_version'] ) || empty( $options['db_version'] ) ) {
			$options['db_version'] = 1;
			update_option( 'mf_timeline', $options );

			self::upgrade_db();
		}
	}
	
	/**
	 * Upgrade DB
	 * Upgrades the mf_timeline_stories DB.
	 *
	 * @return void
	 * @author Matt Fairbrass
	 **/
	static function upgrade_db() {
		global $wpdb, $mf_timeline_db_version;
		$options = get_option( 'mf_timeline' );

		if( $options['db_version'] < $mf_timeline_db_version ) {
			switch( true ) {
				/**
				 * Version: 2
				 * Purpose: Added author column to the stories table.
				 * @author Matt Fairbrass
				 */
				case ($options['db_version'] < 2) :
					$table = $wpdb->prefix . 'mf_timeline_stories';
					
					$sql = "ALTER TABLE {$table} ADD `story_author` BIGINT( 20 ) UNSIGNED NOT NULL DEFAULT '0',
					ADD INDEX (  `story_author` )";
					
					if( $wpdb->query($sql) !== false ) {
						$options['db_version'] = 2;
					}
			}
			
			update_option( 'mf_timeline', $options );
		}
	}
}

global $mf_timeline, $mf_timeline_db_version;

$mf_timeline = new MF_Timeline();
$mf_timeline_db_version = 2;

register_activation_hook( __FILE__, 'MF_Timeline::install_db' );
?>
