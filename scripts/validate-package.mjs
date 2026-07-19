import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import AdmZip from 'adm-zip';
import {
	readPackageVersion,
	readStampedMetadata,
	validatePackageEntries,
	validateStampedMetadata,
} from './release-tools.mjs';

const scriptDirectory = dirname( fileURLToPath( import.meta.url ) );
const rootDirectory = resolve( scriptDirectory, '..' );
const archivePath = resolve( process.argv[ 2 ] ?? 'dist/tsubakuro.zip' );
const archive = new AdmZip( archivePath );
const entries = archive.getEntries().map( ( entry ) => entry.entryName );

validatePackageEntries( entries );

const pluginSource = archive.readAsText( 'tsubakuro/tsubakuro.php', 'utf8' );
const readme = archive.readAsText( 'tsubakuro/readme.txt', 'utf8' );
const version = readPackageVersion( rootDirectory );

validateStampedMetadata( readStampedMetadata( pluginSource, readme ), version );
process.stdout.write(
	`Release ZIP is valid and stamped with v${ version }: ${ archivePath }\n`
);
