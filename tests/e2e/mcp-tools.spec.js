import { test, expect } from '@playwright/test';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const execFileAsync = promisify( execFile );

test.describe( 'MCP tools smoke check', () => {
	test( 'requires tsubakuro/list-tasks ability to be exposed', async () => {
		const scriptPath = 'tests/e2e/list-wordpress-mcp-tools.mjs';

		await execFileAsync(
			'node',
			[ scriptPath, '--require-ability', 'tsubakuro/list-tasks' ],
			{
				cwd: process.cwd(),
				env: process.env,
			}
		);

		expect( true ).toBe( true );
	} );
} );
