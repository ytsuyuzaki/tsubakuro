import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import {
	readPackageVersion,
	validateReleaseVersion,
} from './release-tools.mjs';

const scriptDirectory = dirname( fileURLToPath( import.meta.url ) );
const rootDirectory = resolve( scriptDirectory, '..' );
const tagOptionIndex = process.argv.indexOf( '--tag' );
const tag =
	tagOptionIndex === -1 ? undefined : process.argv[ tagOptionIndex + 1 ];

if ( tagOptionIndex !== -1 && ! tag ) {
	throw new Error( 'The --tag option requires a value.' );
}

const version = validateReleaseVersion(
	readPackageVersion( rootDirectory ),
	tag
);
process.stdout.write( `Package release version is valid: v${ version }.\n` );
