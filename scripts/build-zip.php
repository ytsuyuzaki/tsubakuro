<?php
/**
 * Build a distributable ZIP for the Tsubakuro plugin.
 *
 * Intended to be run inside the wp-env CLI container:
 *   wp-env run cli php /var/www/html/wp-content/plugins/tsubakuro/scripts/build-zip.php
 */

$plugin_slug = 'tsubakuro';
$plugin_dir  = '/var/www/html/wp-content/plugins/' . $plugin_slug;
$dist_dir    = $plugin_dir . '/dist';
$zip_file    = $dist_dir . '/' . $plugin_slug . '.zip';

if ( file_exists( $zip_file ) ) {
	unlink( $zip_file );
}

if ( ! is_dir( $dist_dir ) ) {
	mkdir( $dist_dir, 0755, true );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Failed to create: $zip_file\n" );
	exit( 1 );
}

$paths = array( 'tsubakuro.php', 'README.md', 'includes', 'admin', 'public', 'templates' );
foreach ( $paths as $path ) {
	$src = $plugin_dir . '/' . $path;
	if ( ! file_exists( $src ) ) {
		continue;
	}
	zip_add_path( $zip, $src, $plugin_slug . '/' . $path );
}

$zip->close();
echo "Created $zip_file\n";

function zip_add_path( ZipArchive $zip, string $src, string $local_path ): void {
	if ( is_file( $src ) ) {
		$zip->addFile( $src, $local_path );
		return;
	}
	$src_prefix = rtrim( $src, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
	$files      = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $files as $file ) {
		$relative_path = substr( $file->getPathname(), strlen( $src_prefix ) );
		$zip->addFile( $file->getPathname(), $local_path . '/' . $relative_path );
	}
}
