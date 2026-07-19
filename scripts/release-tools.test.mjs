import assert from 'node:assert/strict';
import test from 'node:test';
import {
	readStampedMetadata,
	stampReleaseFiles,
	validatePackageEntries,
	validateReleaseVersion,
	validateStampedMetadata,
} from './release-tools.mjs';

const sourceFiles = {
	'tsubakuro/tsubakuro.php': `<?php
/**
 * Version:     {{TSUBAKURO_VERSION}}
 */
define( 'TSUBAKURO_VERSION', '{{TSUBAKURO_VERSION}}' );
`,
	'tsubakuro/readme.txt': `Stable tag: {{TSUBAKURO_VERSION}}

== Changelog ==

= 1.2.2 =
`,
};

test( 'package release version and tag must be valid', () => {
	assert.equal( validateReleaseVersion( '1.2.3', 'v1.2.3' ), '1.2.3' );
	assert.throws(
		() => validateReleaseVersion( '1.2', 'v1.2' ),
		/must use X.Y.Z/
	);
	assert.throws(
		() => validateReleaseVersion( '1.2.3', 'v1.2.4' ),
		/does not match version/
	);
} );

test( 'release files are stamped without changing the changelog heading', () => {
	const stamped = stampReleaseFiles( sourceFiles, '1.2.3' );

	assert.doesNotMatch(
		stamped[ 'tsubakuro/tsubakuro.php' ],
		/{{TSUBAKURO_VERSION}}/
	);
	assert.match( stamped[ 'tsubakuro/readme.txt' ], /Stable tag: 1.2.3/ );
	assert.match( stamped[ 'tsubakuro/readme.txt' ], /= 1.2.2 =/ );
	assert.equal(
		validateStampedMetadata(
			readStampedMetadata(
				stamped[ 'tsubakuro/tsubakuro.php' ],
				stamped[ 'tsubakuro/readme.txt' ]
			),
			'1.2.3'
		),
		'1.2.3'
	);
} );

test( 'stamping fails when a placeholder is missing or duplicated', () => {
	assert.throws(
		() =>
			stampReleaseFiles(
				{
					...sourceFiles,
					'tsubakuro/readme.txt': 'Stable tag: 1.2.3',
				},
				'1.2.3'
			),
		/expected 1 version placeholders/i
	);
	assert.throws(
		() =>
			stampReleaseFiles(
				{
					...sourceFiles,
					'tsubakuro/readme.txt':
						'Stable tag: {{TSUBAKURO_VERSION}} {{TSUBAKURO_VERSION}}',
				},
				'1.2.3'
			),
		/expected 1 version placeholders/i
	);
} );

test( 'stamped metadata must match the package version', () => {
	assert.throws(
		() =>
			validateStampedMetadata(
				{
					headerVersion: '1.2.4',
					constantVersion: '1.2.4',
					stableTag: '1.2.4',
				},
				'1.2.3'
			),
		/does not match package version/
	);
} );

test( 'release ZIP contains Composer-managed updater files under one plugin root', () => {
	const entries = [
		'tsubakuro/tsubakuro.php',
		'tsubakuro/includes/class-tsubakuro-updater.php',
		'tsubakuro/vendor/autoload.php',
		'tsubakuro/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php',
		'tsubakuro/vendor/yahnis-elsts/plugin-update-checker/license.txt',
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
				'tsubakuro/plugin-update-checker/plugin-update-checker.php',
			] ),
		/removed bundled library/
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
