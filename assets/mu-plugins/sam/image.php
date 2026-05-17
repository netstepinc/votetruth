<?php if(!defined('ABSPATH')){exit;}

add_action( 'after_setup_theme', function() {
	remove_image_size( 'medium_large' );
	remove_image_size( '1536x1536' );
	remove_image_size( '2048x2048' );
}, 20 );

add_filter( 'intermediate_image_sizes_advanced', function( $sizes ) {
	$keep = array( 'thumbnail', 'medium', 'large' );
	foreach ( array_keys( $sizes ) as $size ) {
		if ( ! in_array( $size, $keep, true ) ) {
			unset( $sizes[ $size ] );
		}
	}
	return $sizes;
}, 20 );

add_filter( 'big_image_size_threshold', '__return_false' );




/* Adjust file uploads
 * Route media and pdf files into dedicated directories
 * Otherwise route them into year folders so the main directory doesn't get completely out of control
**/
function sam_upload_directory( $dirs ) {

	// Define your specific file types and their corresponding directories
	$types_directories = array(
		'audio/mpeg' => 'mp3',  // for mp3 files
		'application/pdf' => 'pdf',  // for pdf files
		'video/mp4' => 'mp4'  // for mp4 files
	);

	// Default directory structure for other file types: /assets/{year}/
	$year = date('Y');
	$default_subdir = "{$year}";

	// Get the file type from the $_FILES global variable
	$file_type = isset( $_FILES['async-upload']['type'] ) ? $_FILES['async-upload']['type'] : '';

	// Determine the custom or default subdirectory based on file type
	$custom_subdir = (array_key_exists($file_type, $types_directories)) ? $types_directories[$file_type] : $default_subdir;

	// Check if directory exists before trying to create it
	$full_path = $dirs['basedir'] . '/' . $custom_subdir;
	
	// Only attempt to create directory if it doesn't exist
	if ( ! is_dir( $full_path ) ) {
		// Check parent directory permissions first
		if ( ! is_writable( dirname( $full_path ) ) ) {
			error_log(__FILE__ . '|'.__LINE__." Parent directory not writable: " . dirname( $full_path ) );
			return $dirs; // Return original dirs if we can't write to parent
		}
		

		// Create directory with explicit permissions
		if ( ! wp_mkdir_p( $full_path ) ) {
			error_log(__FILE__ . '|'.__LINE__. "Failed to create directory: " . $full_path);
			return $dirs;  // Return original dirs if directory creation fails
		}
		
		// Ensure proper permissions after creation
		chmod( $full_path, 0775 ); //755 for web server.
	}

	// Only modify paths if directory exists and is writable
	if ( is_dir( $full_path ) && is_writable( $full_path ) ) {
		$full_url = $dirs['baseurl'] . '/' . $custom_subdir;
		
		// Update the directory paths
		$dirs['path'] = $full_path;
		$dirs['url'] = $full_url;
		$dirs['subdir'] = '/' . $custom_subdir;
	} else {
		error_log(__FILE__ . '|'.__LINE__. "Directory not writable or doesn't exist: " . $full_path );
	}

	return $dirs;
}
add_filter( 'upload_dir', 'sam_upload_directory' );