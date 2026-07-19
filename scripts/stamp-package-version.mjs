import { mkdirSync, rmSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import AdmZip from 'adm-zip';
import { readPackageVersion, stampReleaseFiles } from './release-tools.mjs';

const scriptDirectory = dirname( fileURLToPath( import.meta.url ) );
const rootDirectory = resolve( scriptDirectory, '..' );
const inputPath = resolve( process.argv[ 2 ] ?? 'tsubakuro.zip' );
const outputPath = resolve( process.argv[ 3 ] ?? 'dist/tsubakuro.zip' );
const version = readPackageVersion( rootDirectory );
const archive = new AdmZip( inputPath );
const targetPaths = [ 'tsubakuro/tsubakuro.php', 'tsubakuro/readme.txt' ];
const files = Object.fromEntries(
	targetPaths.map( ( path ) => [ path, archive.readAsText( path, 'utf8' ) ] )
);
const stamped = stampReleaseFiles( files, version );

for ( const [ path, contents ] of Object.entries( stamped ) ) {
	archive.updateFile( path, Buffer.from( contents, 'utf8' ) );
}

mkdirSync( dirname( outputPath ), { recursive: true } );
archive.writeZip( outputPath );
if ( inputPath !== outputPath ) {
	rmSync( inputPath );
}

process.stdout.write(
	`Stamped v${ version } into ${ targetPaths.join(
		', '
	) }.\nOutput: ${ outputPath }\n`
);
