<?php
/**
 * Part importer
 *
 * Import parts data and set data to existing rows on Parts List Page
 *
 * @author 		Ray Flores
 * @category 	Admin
 * @package 		Admin/Importers
 * @version     	1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( class_exists( 'WP_Importer' ) ) {
    class RF_ACF_CPT_Importer extends WP_Importer {

        var $id;
        var $file_url;
        var $import_page;
        var $delimiter = ",";
        var $posts = array();
        var $total;
        var $imported;
        var $skipped;

        /**
         * __construct function.
         *
         * @access public
         */
        public function __construct() {
            $this->import_page = 'rf_acf_cpt_importer';
        }
		function delete_all( $cpts ){
			if (count($cpts) > 0 ){
				return true;
			}
			return false;
		}
		function delete_all_posts( $cpts ){
			$cpts = get_posts( array( 'post_type' => 'parts', 'numberposts' => -1));
			$cnt = 0;
			$total = 0;
			foreach ( $cpts as $cpt ){
				wp_delete_post($cpt->ID, true);
				$total = $cnt++;
			} 
			echo $total . ' Posts Deleted.';
			echo '<a class="button" href="admin.php?import=rf_acf_cpt_importer">Ok, Great!</a>';
		}
        /**
         * Registered callback function for the WordPress Importer
         *
         * Manages the three separate stages of the CSV import process
         */
        function dispatch() {
            $this->header();
			
            $step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
            switch ( $step ) {
                case 0:
                    $this->greet();
                    break;
                case 1:
					
				    check_admin_referer( 'import-upload' );
                    if ( $this->handle_upload() ) {

                        if ( $this->id )
                            $file = get_attached_file( $this->id );
                        else
                            $file = ABSPATH . $this->file_url;

                        add_filter( 'http_request_timeout', array( $this, 'bump_request_timeout' ) );

                        if ( function_exists( 'gc_enable' ) )
                            gc_enable();

                        @set_time_limit(0);
                        @ob_flush();
                        @flush();

                        $this->import( $file );
                    }
                    break;
					case 5:
					check_admin_referer( 'import-delete' );
					$cpts = get_posts( array( 'post_type' => 'parts', 'numberposts' => -1));
					if ( $this->delete_all( $cpts ) ) {
						
						add_filter( 'http_request_timeout', array( $this, 'bump_request_timeout' ) );

                        if ( function_exists( 'gc_enable' ) )
                            gc_enable();

                        @set_time_limit(0);
                        @ob_flush();
                        @flush();
												
						$this->delete_all_posts( $cpts );
						
					} else {
						echo 'No Posts exists: <a class="button" href="admin.php?import=rf_acf_cpt_importer">Go Back</a>'; 
					}
					break;
            }
            $this->footer();
        }

        /**
         * format_data_from_csv function.
         *
         * @access public
         * @param mixed $data
         * @param string $enc
         * @return string
         */
        function format_data_from_csv( $data, $enc ) {
            return ( $enc == 'UTF-8' ) ? $data : utf8_encode( $data );
        }
		/* search for matching company_name and return the key */
		function searchForCompany($company, $array) {
		   foreach ($array as $key => $val) {
			   if ($val['company_name'] === $company) {
				   return $key;
			   }
		   }
		   return null;
		}
		
		function check_if_customer_exists($all_current_full_names, $field, $check_full_name)
		{
		   foreach($all_current_full_names as $key => $current_name)
		   {
			  if ( $current_name['NAME'] === $check_full_name )
				 return $current_name['ID'];
		   }
		   return false;
		}
		
		function removeRepeaterRow($array, $key, $value){
			 foreach($array as $subKey => $subArray){
				  if($subArray[$key] == $value){
					   unset($array[$subKey]);
				  }
			 }
			 return $array;
		}
        /**
         * import function.
         *
         * @access public
         * @param mixed $file
         * @return void
         */
        function import( $file ) {
			global $wpdb;
						
							
			$this->total = $this->imported = $this->skipped = 0;
            $skipped_report = '';

            if ( ! is_file($file) ) {
                echo '<p><strong>' . __( 'Sorry, there has been an error.', 'rf-exhibitor-importer' ) . '</strong><br />';
                echo __( 'The file does not exist, please try again.', 'rf-exhibitor-importer' ) . '</p>';
                $this->footer();
                die();
            }
			$fp = file($file); // get rows
			
			
            ini_set( 'auto_detect_line_endings', '1' );
				
				$numRows = count($fp); // how many rows in CSV file
				
				
            if ( ( $handle = fopen( $file, "r" ) ) !== FALSE ) {

                $header = fgetcsv( $handle, 0, $this->delimiter );

                if ( sizeof( $header ) == 6 ) {

                    $loop = 0;

                    while ( ( $row = fgetcsv( $handle, 0, $this->delimiter ) ) !== FALSE ) {

                        list( $partno, $title, $cc, $qty, $category, $image ) = $row;
                        // verify partno is found
						if ( $partno ) {
								$cat = get_category_by_slug( $category );
								$catid = $cat->term_id;
								$post_array = array(
									'post_title' => $title,
									'post_type' => 'parts',
									'post_status' => 'publish',
									'post_category' => array($catid),
									
								);
						
								$post_id = wp_insert_post( $post_array );
								
								update_post_meta( $post_id, 'part_number', $partno );
								update_post_meta( $post_id, 'quantity', $qty );
								update_post_meta( $post_id, 'condition', $cc );
								
								if ($image){
									// $filename should be the path to a file in the upload directory.
									$filename = $image;

									// The ID of the post this attachment is for.
									$parent_post_id = $post_id;

									// Check the type of file. We'll use this as the 'post_mime_type'.
									$filetype = wp_check_filetype( basename( $filename ), null );

									// Get the path to the upload directory.
									$wp_upload_dir = wp_upload_dir();

									// Prepare an array of post data for the attachment.
									$attachment = array(
										'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ), 
										'post_mime_type' => $filetype['type'],
										'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
										'post_content'   => '',
										'post_status'    => 'inherit'
									);

									// Insert the attachment.
									$attach_id = wp_insert_attachment( $attachment, $filename, $parent_post_id );

									// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
									require_once( ABSPATH . 'wp-admin/includes/image.php' );

									// Generate the metadata for the attachment, and update the database record.
									$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
									wp_update_attachment_metadata( $attach_id, $attach_data );

									set_post_thumbnail( $parent_post_id, $attach_id );
								}
								
                                $loop++;
                                $this->imported++;
                        } else {
                            echo "<p>Skipped row " . $loop . ", Part No. not found.</p>";
                            $loop++;
                            $this->skipped++;
                        }
                    }$this->total = $loop;

                } else {

                    echo '<p><strong>' . __( 'Sorry, there has been an error.', 'woocommerce' ) . '</strong><br />';
                    echo __( 'The CSV is invalid.', 'woocommerce' ) . '</p>';
                    $this->footer();
                    die();

                }
				
                fclose( $handle );
            }
			
            // Show Result
            echo '<div class="updated settings-error below-h2"><p>
				'.sprintf( __( 'Import complete: Total rows: <strong>%s</strong>. Total rows skipped: <strong>%s</strong>', 'rf-exhibitor-importer' ),  $this->imported, $this->skipped ).'</p></div>';

            $this->import_end();
        }

        /**
         * Performs post-import cleanup of files and the cache
         */
        function import_end() {
            echo '<p>' . __( 'All done!', 'rf-exhibitor-importer' ) . '</p>';

            do_action( 'import_end' );
        }

        /**
         * Handles the CSV upload and initial parsing of the file to prepare for
         * displaying author import options
         *
         * @return bool False if error uploading or invalid file, true otherwise
         */
        function handle_upload() {

            if ( empty( $_POST['file_url'] ) ) {

                $file = wp_import_handle_upload();

                if ( isset( $file['error'] ) ) {
                    echo '<p><strong>' . __( 'Sorry, there has been an error.', 'rf-exhibitor-importer' ) . '</strong><br />';
                    echo esc_html( $file['error'] ) . '</p>';
                    return false;
                }

                $this->id = (int) $file['id'];

            } else {

                if ( file_exists( ABSPATH . $_POST['file_url'] ) ) {

                    $this->file_url = esc_attr( $_POST['file_url'] );

                } else {

                    echo '<p><strong>' . __( 'Sorry, there has been an error.', 'rf-exhibitor-importer' ) . '</strong></p>';
                    return false;

                }

            }

            return true;
        }

        /**
         * header function.
         *
         * @access public
         * @return void
         */
        function header() {
            echo '<div class="wrap"><div class="icon32 icon32-woocommerce-importer" id="icon-woocommerce"><br></div>';
            echo '<h2>' . __( 'Import Parts List', 'rf-exhibitor-importer' ) . '</h2>';
        }

        /**
         * footer function.
         *
         * @access public
         * @return void
         */
        function footer() {
            echo '</div>';
        }

        /**
         * greet function.
         *
         * @access public
         * @return void
         */
        function greet() {
			
            echo '<div class="narrow">';
            echo '<p>' . __( 'Howdy! Upload a CSV file containing Parts data to import. Choose a .csv file to upload, then click "Upload file and import".', 'rf-exhibitor-importer' ).'</p>';

            echo '<p>' . __( 'The file needs to have five columns: Part No., Title, Condition, Quantity, Image Url', 'rf-exhibitor-importer' ) . '</p>';

            $action = 'admin.php?import=rf_acf_cpt_importer&step=1';
			
            $bytes = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
            $size = size_format( $bytes );
            $upload_dir = wp_upload_dir();
            if ( ! empty( $upload_dir['error'] ) ) :
                ?><div class="error"><p><?php _e( 'Before you can upload your import file, you will need to fix the following error:', 'rf-exhibitor-importer' ); ?></p>
                <p><strong><?php echo $upload_dir['error']; ?></strong></p></div><?php
            else :
                ?>
                <form enctype="multipart/form-data" id="import-upload-form" method="post" action="<?php echo esc_attr(wp_nonce_url($action, 'import-upload')); ?>">
                    <table class="form-table">
                        <tbody>
						
                        <tr>
                            <th>
                                <label for="upload"><?php _e( 'Choose a file from your computer:', 'rf-exhibitor-importer' ); ?></label>
                            </th>
                            <td>
                                <input type="file" id="upload" name="import" size="25" />
                                <input type="hidden" name="action" value="save" />
                                <input type="hidden" name="max_file_size" value="<?php echo $bytes; ?>" />
                                <small><?php printf( __('Maximum size: %s', 'rf-exhibitor-importer' ), $size ); ?></small>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button" value="<?php esc_attr_e( 'Upload file and import', 'rf-acf-cpt-importer' ); ?>" />
						
                    </p>
                </form>
				<?php 
				$cpts = get_posts( array( 'post_type' => 'parts', 'numberposts' => -1));
				if ( count($cpts) > 0 ) {
				$action2 = 'admin.php?import=rf_acf_cpt_importer&step=5&deletion=1'; ?>
				<form id="import-delete-form" method="post" action="<?php echo esc_attr(wp_nonce_url($action2, 'import-delete')); ?>"> 
					<p class="delete">
						<input type="submit" class="import-delete button" value="<?php esc_attr_e( 'Delete All Posts?', 'rf-acf-cpt-importer' ); ?>"/>
					</p>
				</form>
				<?php } else {
					echo '<p>Currently there are no Parts posts</p>';
				}
            endif;

            echo '</div>';
        }

        /**
         * Added to http_request_timeout filter to force timeout at 60 seconds during import
         * @param  int $val
         * @return int 60
         */
        function bump_request_timeout( $val ) {
            return 60;
        }
    }
}
