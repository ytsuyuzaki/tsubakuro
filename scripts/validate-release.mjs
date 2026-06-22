import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import {
	readReleaseMetadata,
	validateReleaseMetadata,
} from './release-tools.mjs';

const scriptDirectory = dirname( fileURLToPath( import.meta.url ) );
const rootDirectory = resolve( scriptDirectory, '..' );
const tagOptionIndex = process.argv.indexOf( '--tag' );
const tag =
	tagOptionIndex === -1 ? undefined : process.argv[ tagOptionIndex + 1 ];

if ( tagOptionIndex !== -1 && ! tag ) {
	throw new Error( 'The --tag option requires a value.' );
}

const version = validateReleaseMetadata(
	readReleaseMetadata( rootDirectory ),
	tag
);
process.stdout.write( `Release metadata is consistent for v${ version }.\n` );
