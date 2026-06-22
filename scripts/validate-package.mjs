import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { validatePackageEntries } from './release-tools.mjs';

const archivePath = resolve( process.argv[ 2 ] ?? 'dist/tsubakuro.zip' );
const entries = execFileSync( 'unzip', [ '-Z1', archivePath ], {
	encoding: 'utf8',
} )
	.split( '\n' )
	.filter( Boolean );

validatePackageEntries( entries );
process.stdout.write( `Release ZIP is valid: ${ archivePath }\n` );
