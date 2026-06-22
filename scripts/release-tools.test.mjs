import assert from 'node:assert/strict';
import test from 'node:test';
import {
	validatePackageEntries,
	validateReleaseMetadata,
} from './release-tools.mjs';

const validMetadata = {
	headerVersion: '1.2.3',
	constantVersion: '1.2.3',
	packageVersion: '1.2.3',
	stableTag: '1.2.3',
};

test( 'release metadata versions and tag must match', () => {
	assert.equal( validateReleaseMetadata( validMetadata, 'v1.2.3' ), '1.2.3' );
	assert.throws(
		() =>
			validateReleaseMetadata(
				{ ...validMetadata, packageVersion: '1.2.4' },
				'v1.2.3'
			),
		/versions do not match/
	);
	assert.throws(
		() => validateReleaseMetadata( validMetadata, '1.2.3' ),
		/does not match version/
	);
} );

test( 'release ZIP contains updater runtime files under one plugin root', () => {
	const entries = [
		'tsubakuro/tsubakuro.php',
		'tsubakuro/includes/class-tsubakuro-updater.php',
		'tsubakuro/plugin-update-checker/plugin-update-checker.php',
		'tsubakuro/plugin-update-checker/license.txt',
		'tsubakuro/readme.txt',
	];

	assert.doesNotThrow( () => validatePackageEntries( entries ) );
	assert.throws(
		() => validatePackageEntries( entries.slice( 1 ) ),
		/Release ZIP is missing/
	);
	assert.throws(
		() => validatePackageEntries( [ ...entries, 'other/file.php' ] ),
		/invalid root entry/
	);
	assert.throws(
		() =>
			validatePackageEntries( [
				...entries,
				'tsubakuro/tests/test.php',
			] ),
		/development files/
	);
} );
