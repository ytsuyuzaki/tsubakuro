#!/usr/bin/env node

import { access, readFile } from 'node:fs/promises';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const configPath = path.resolve( process.cwd(), '.codex/config.toml' );
const execFileAsync = promisify( execFile );

function writeStdout( message ) {
	process.stdout.write( `${ message }\n` );
}

function writeStderr( message ) {
	process.stderr.write( `${ message }\n` );
}

function parseCliArgs( argv ) {
	const requiredMcpTools = [];
	const requiredAbilities = [];

	for ( let i = 0; i < argv.length; i += 1 ) {
		const arg = argv[ i ];

		if ( arg === '--require-mcp-tool' ) {
			const value = argv[ i + 1 ];
			if ( ! value ) {
				throw new Error( '--require-mcp-tool には値が必要です。' );
			}
			requiredMcpTools.push( value );
			i += 1;
			continue;
		}

		if ( arg === '--require-ability' ) {
			const value = argv[ i + 1 ];
			if ( ! value ) {
				throw new Error( '--require-ability には値が必要です。' );
			}
			requiredAbilities.push( value );
			i += 1;
			continue;
		}
	}

	return { requiredMcpTools, requiredAbilities };
}

function assertRequiredItems( kind, required, actual ) {
	if ( required.length === 0 ) {
		return;
	}

	const actualSet = new Set( actual );
	const missing = required.filter( ( item ) => ! actualSet.has( item ) );

	if ( missing.length > 0 ) {
		throw new Error(
			`Missing required ${ kind }: ${ missing.join( ', ' ) }`
		);
	}
}

function parseWordPressEnv( toml ) {
	const env = {};
	let inWordPressEnv = false;

	for ( const rawLine of toml.split( /\r?\n/ ) ) {
		const line = rawLine.trim();

		if ( ! line || line.startsWith( '#' ) ) {
			continue;
		}

		if ( line.startsWith( '[' ) ) {
			inWordPressEnv = line === '[mcp_servers.wordpress.env]';
			continue;
		}

		if ( ! inWordPressEnv ) {
			continue;
		}

		const match = line.match( /^([A-Z0-9_]+)\s*=\s*"([\s\S]*)"$/ );
		if ( match ) {
			env[ match[ 1 ] ] = match[ 2 ];
		}
	}

	return env;
}

async function readOptionalConfigToml( filePath ) {
	try {
		await access( filePath );
		return await readFile( filePath, 'utf8' );
	} catch {
		return '';
	}
}

function normalizeBaseUrl( url ) {
	return url.replace( /\/+$/, '' );
}

function getDefaultEndpointCandidates() {
	const base = process.env.WP_ENV_SITE_URL ?? 'http://localhost:8888';
	const baseUrl = normalizeBaseUrl( base );

	return [
		`${ baseUrl }/wp-json/mcp/mcp-adapter-default-server`,
		`${ baseUrl }/wp-json/tsubakuro/v1/mcp`,
	];
}

async function getWpEnvApplicationPassword( username ) {
	try {
		const { stdout } = await execFileAsync(
			'npx',
			[
				'wp-env',
				'run',
				'cli',
				'wp',
				'user',
				'application-password',
				'create',
				username,
				'list-wordpress-mcp-tools',
				'--porcelain',
			],
			{ cwd: process.cwd() }
		);

		const token = stdout.trim();
		return token || null;
	} catch {
		return null;
	}
}

async function probeEndpoint( url, username, password ) {
	try {
		await rpcRequest( {
			url,
			username,
			password,
			id: 0,
			method: 'initialize',
			params: {
				protocolVersion: '2025-11-25',
				capabilities: {},
				clientInfo: {
					name: 'list-wordpress-mcp-tools',
					version: '0.1.0',
				},
			},
		} );
		return true;
	} catch {
		return false;
	}
}

async function resolveConnectionConfig( env ) {
	const username =
		process.env.WP_API_USERNAME ?? env.WP_API_USERNAME ?? 'admin';
	const explicitUrl = process.env.WP_API_URL ?? env.WP_API_URL;
	const explicitPassword = process.env.WP_API_PASSWORD ?? env.WP_API_PASSWORD;
	const autoPassword =
		process.env.WP_ENV_APP_PASSWORD ??
		( await getWpEnvApplicationPassword( username ) );
	const password = explicitPassword ?? autoPassword;

	if ( ! password ) {
		throw new Error(
			'WP_API_PASSWORD が未設定です。wp-env への接続には Application Password が必要です。\n' +
				'対処: WP_API_PASSWORD を設定するか、`npx wp-env run cli wp user application-password create admin list-wordpress-mcp-tools --porcelain` を実行してください。'
		);
	}

	if ( explicitUrl ) {
		return {
			url: explicitUrl,
			username,
			password,
			source: 'explicit',
		};
	}

	for ( const candidate of getDefaultEndpointCandidates() ) {
		if ( await probeEndpoint( candidate, username, password ) ) {
			return {
				url: candidate,
				username,
				password,
				source: 'auto',
			};
		}
	}

	throw new Error(
		'wp-env 向けの MCP エンドポイントを自動検出できませんでした。\n' +
			'WP_API_URL を指定してください（例: http://localhost:8888/wp-json/tsubakuro/v1/mcp もしくは /wp-json/mcp/mcp-adapter-default-server）。'
	);
}

function extractSessionId( headers ) {
	return headers.get( 'mcp-session-id' ) ?? headers.get( 'Mcp-Session-Id' );
}

function parseResponseBody( text, contentType ) {
	if ( contentType.includes( 'text/event-stream' ) ) {
		const payloads = text
			.split( /\r?\n/ )
			.filter( ( line ) => line.startsWith( 'data:' ) )
			.map( ( line ) => line.slice( 5 ).trim() )
			.filter( ( line ) => line && line !== '[DONE]' );

		return payloads.map( ( payload ) => JSON.parse( payload ) ).at( -1 );
	}

	return JSON.parse( text );
}

async function rpcRequest( {
	url,
	username,
	password,
	method,
	params,
	id,
	sessionId,
	protocolVersion,
} ) {
	const auth = Buffer.from( `${ username }:${ password }` ).toString(
		'base64'
	);
	const headers = {
		Authorization: `Basic ${ auth }`,
		Accept: 'application/json, text/event-stream',
		'Content-Type': 'application/json',
	};

	if ( sessionId ) {
		headers[ 'Mcp-Session-Id' ] = sessionId;
	}

	if ( method !== 'initialize' && protocolVersion ) {
		headers[ 'MCP-Protocol-Version' ] = protocolVersion;
	}

	const response = await fetch( url, {
		method: 'POST',
		headers,
		body: JSON.stringify( {
			jsonrpc: '2.0',
			id,
			method,
			params,
		} ),
	} );

	const text = await response.text();
	const body = parseResponseBody(
		text,
		response.headers.get( 'content-type' ) ?? ''
	);

	if ( ! response.ok || body?.error ) {
		const message = body?.error?.message ?? response.statusText;
		throw new Error( `${ method } failed: ${ message }` );
	}

	return {
		body,
		sessionId: extractSessionId( response.headers ),
	};
}

async function main() {
	const { requiredMcpTools, requiredAbilities } = parseCliArgs(
		process.argv.slice( 2 )
	);
	const toml = await readOptionalConfigToml( configPath );
	const env = parseWordPressEnv( toml );
	const { url, username, password, source } =
		await resolveConnectionConfig( env );

	writeStdout(
		`Connection: ${ url } (${
			source === 'auto' ? 'auto-detected for wp-env' : 'configured'
		})`
	);
	writeStdout( `Username: ${ username }` );

	const initialized = await rpcRequest( {
		url,
		username,
		password,
		id: 1,
		method: 'initialize',
		params: {
			protocolVersion: '2025-11-25',
			capabilities: {},
			clientInfo: {
				name: 'list-wordpress-mcp-tools',
				version: '0.1.0',
			},
		},
	} );

	const protocolVersion =
		initialized.body?.result?.protocolVersion ?? '2025-11-25';

	const listed = await rpcRequest( {
		url,
		username,
		password,
		id: 2,
		method: 'tools/list',
		params: {},
		sessionId: initialized.sessionId,
		protocolVersion,
	} );

	const tools = listed.body?.result?.tools ?? [];
	const toolNames = tools.map( ( tool ) => tool.name );

	assertRequiredItems( 'MCP tools', requiredMcpTools, toolNames );

	writeStdout( 'MCP tools:' );
	for ( const tool of tools ) {
		writeStdout( `- ${ tool.name }` );
	}

	const hasDiscoverTool = tools.some(
		( tool ) => tool.name === 'mcp-adapter-discover-abilities'
	);
	if ( ! hasDiscoverTool ) {
		if ( requiredAbilities.length > 0 ) {
			throw new Error(
				'Abilities discovery is unavailable on this endpoint.'
			);
		}

		writeStdout( '' );
		writeStdout( 'WordPress abilities:' );
		writeStdout(
			'- (not available: this endpoint exposes tools directly, not mcp-adapter discovery)'
		);
		return;
	}

	const discovered = await rpcRequest( {
		url,
		username,
		password,
		id: 3,
		method: 'tools/call',
		params: {
			name: 'mcp-adapter-discover-abilities',
			arguments: {},
		},
		sessionId: initialized.sessionId,
		protocolVersion,
	} );

	const content = discovered.body?.result?.content ?? [];
	const textContent = content.find( ( item ) => item.type === 'text' )?.text;
	const abilityResult = textContent
		? JSON.parse( textContent )
		: discovered.body?.result;
	const abilities =
		abilityResult?.abilities ?? abilityResult?.data?.abilities ?? [];
	const abilityNames = abilities
		.map( ( ability ) => ability.name )
		.filter( Boolean );

	assertRequiredItems( 'abilities', requiredAbilities, abilityNames );

	writeStdout( '' );
	writeStdout( 'WordPress abilities:' );
	if ( abilities.length === 0 ) {
		writeStdout( '- (no abilities discovered)' );
		return;
	}

	for ( const ability of abilities ) {
		const label = ability.label ? ` (${ ability.label })` : '';
		writeStdout( `- ${ ability.name }${ label }` );
	}
}

main().catch( ( error ) => {
	writeStderr( error.message );
	process.exitCode = 1;
} );
