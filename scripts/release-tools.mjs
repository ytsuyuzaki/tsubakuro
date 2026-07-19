import { readFileSync } from 'node:fs';

export const VERSION_PLACEHOLDER = '{{TSUBAKURO_VERSION}}';

export function readPackageVersion( rootDirectory ) {
	const packageMetadata = JSON.parse(
		readFileSync( `${ rootDirectory }/package.json`, 'utf8' )
	);

	return packageMetadata.version;
}

export function validateReleaseVersion( version, tag ) {
	if ( typeof version !== 'string' || ! /^\d+\.\d+\.\d+$/.test( version ) ) {
		throw new Error(
			`Release version must use X.Y.Z format: ${ version ?? '' }`
		);
	}

	if ( tag && tag !== `v${ version }` ) {
		throw new Error(
			`Release tag ${ tag } does not match version v${ version }`
		);
	}

	return version;
}

function countOccurrences( source, search ) {
	return source.split( search ).length - 1;
}

export function stampReleaseFiles( files, version ) {
	validateReleaseVersion( version );

	const expectedCounts = {
		'tsubakuro/tsubakuro.php': 2,
		'tsubakuro/readme.txt': 1,
	};
	const stamped = {};

	for ( const [ path, expectedCount ] of Object.entries( expectedCounts ) ) {
		const source = files[ path ];
		if ( typeof source !== 'string' ) {
			throw new Error( `Release ZIP is missing stamp target: ${ path }` );
		}

		const actualCount = countOccurrences( source, VERSION_PLACEHOLDER );
		if ( actualCount !== expectedCount ) {
			throw new Error(
				`Expected ${ expectedCount } version placeholders in ${ path }, found ${ actualCount }`
			);
		}

		stamped[ path ] = source.replaceAll( VERSION_PLACEHOLDER, version );
	}

	return stamped;
}

export function readStampedMetadata( pluginSource, readme ) {
	return {
		headerVersion: pluginSource.match( /^ \* Version:\s*(\S+)/m )?.[ 1 ],
		constantVersion: pluginSource.match(
			/define\( 'TSUBAKURO_VERSION', '([^']+)' \);/
		)?.[ 1 ],
		stableTag: readme.match( /^Stable tag:\s*(\S+)/m )?.[ 1 ],
	};
}

export function validateStampedMetadata( metadata, version ) {
	validateReleaseVersion( version );
	const entries = Object.entries( metadata );
	const missing = entries
		.filter( ( [ , value ] ) => ! value )
		.map( ( [ name ] ) => name );

	if ( missing.length > 0 ) {
		throw new Error(
			`Missing stamped metadata: ${ missing.join( ', ' ) }`
		);
	}

	const placeholderEntry = entries.find( ( [ , value ] ) =>
		value.includes( VERSION_PLACEHOLDER )
	);
	if ( placeholderEntry ) {
		throw new Error(
			`Version placeholder remains in ${ placeholderEntry[ 0 ] }`
		);
	}

	const mismatch = entries.find( ( [ , value ] ) => value !== version );
	if ( mismatch ) {
		throw new Error(
			`Stamped version does not match package version: ${ mismatch[ 0 ] }=${ mismatch[ 1 ] }, packageVersion=${ version }`
		);
	}

	return version;
}

export function validatePackageEntries( entries ) {
	const requiredEntries = [
		'tsubakuro/tsubakuro.php',
		'tsubakuro/includes/class-tsubakuro-updater.php',
		'tsubakuro/vendor/autoload.php',
		'tsubakuro/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php',
		'tsubakuro/vendor/yahnis-elsts/plugin-update-checker/license.txt',
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

	const bundledLibrary = entries.find( ( entry ) =>
		entry.startsWith( 'tsubakuro/plugin-update-checker/' )
	);
	if ( bundledLibrary ) {
		throw new Error(
			`Release ZIP contains the removed bundled library: ${ bundledLibrary }`
		);
	}

	for ( const excludedDirectory of [ 'node_modules/', 'tests/', '.git/' ] ) {
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
