import { readFileSync } from 'node:fs';

export function readReleaseMetadata( rootDirectory ) {
	const pluginSource = readFileSync(
		`${ rootDirectory }/tsubakuro.php`,
		'utf8'
	);
	const packageMetadata = JSON.parse(
		readFileSync( `${ rootDirectory }/package.json`, 'utf8' )
	);
	const readme = readFileSync( `${ rootDirectory }/readme.txt`, 'utf8' );
	const headerVersion = pluginSource.match( /^ \* Version:\s*(\S+)/m )?.[ 1 ];
	const constantVersion = pluginSource.match(
		/define\( 'TSUBAKURO_VERSION', '([^']+)' \);/
	)?.[ 1 ];
	const stableTag = readme.match( /^Stable tag:\s*(\S+)/m )?.[ 1 ];

	return {
		headerVersion,
		constantVersion,
		packageVersion: packageMetadata.version,
		stableTag,
	};
}

export function validateReleaseMetadata( metadata, tag ) {
	const entries = Object.entries( metadata );
	const missing = entries
		.filter( ( [ , value ] ) => ! value )
		.map( ( [ name ] ) => name );

	if ( missing.length > 0 ) {
		throw new Error(
			`Missing release metadata: ${ missing.join( ', ' ) }`
		);
	}

	const versions = new Set( entries.map( ( [ , value ] ) => value ) );
	if ( versions.size !== 1 ) {
		throw new Error(
			`Release versions do not match: ${ entries
				.map( ( [ name, value ] ) => `${ name }=${ value }` )
				.join( ', ' ) }`
		);
	}

	const version = entries[ 0 ][ 1 ];
	if ( ! /^\d+\.\d+\.\d+$/.test( version ) ) {
		throw new Error(
			`Release version must use X.Y.Z format: ${ version }`
		);
	}

	if ( tag && tag !== `v${ version }` ) {
		throw new Error(
			`Release tag ${ tag } does not match version v${ version }`
		);
	}

	return version;
}

export function validatePackageEntries( entries ) {
	const requiredEntries = [
		'tsubakuro/tsubakuro.php',
		'tsubakuro/includes/class-tsubakuro-updater.php',
		'tsubakuro/plugin-update-checker/plugin-update-checker.php',
		'tsubakuro/plugin-update-checker/license.txt',
		'tsubakuro/readme.txt',
	];
	const missing = requiredEntries.filter(
		( entry ) => ! entries.includes( entry )
	);

	if ( missing.length > 0 ) {
		throw new Error( `Release ZIP is missing: ${ missing.join( ', ' ) }` );
	}

	const invalidEntry = entries.find(
		( entry ) => ! entry.startsWith( 'tsubakuro/' )
	);
	if ( invalidEntry ) {
		throw new Error(
			`Release ZIP contains an invalid root entry: ${ invalidEntry }`
		);
	}

	for ( const excludedDirectory of [
		'node_modules/',
		'vendor/',
		'tests/',
		'.git/',
	] ) {
		const excludedEntry = entries.find( ( entry ) =>
			entry.startsWith( `tsubakuro/${ excludedDirectory }` )
		);
		if ( excludedEntry ) {
			throw new Error(
				`Release ZIP contains development files: ${ excludedEntry }`
			);
		}
	}
}
