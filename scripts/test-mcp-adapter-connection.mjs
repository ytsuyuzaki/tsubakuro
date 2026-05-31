#!/usr/bin/env node

const {
    WP_API_URL,
    WP_API_USERNAME,
    WP_API_PASSWORD,
    MCP_PROTOCOL_VERSION = "2025-11-25",
} = process.env;

if (!WP_API_URL || !WP_API_USERNAME || !WP_API_PASSWORD) {
    console.error("Missing required environment variables: WP_API_URL, WP_API_USERNAME, WP_API_PASSWORD");
    process.exit(1);
}

const authToken = Buffer.from(`${WP_API_USERNAME}:${WP_API_PASSWORD}`).toString("base64");

async function postJsonRpc(payload, includeProtocolHeader = true) {
    const headers = {
        "Content-Type": "application/json",
        Accept: "application/json, text/event-stream",
        Authorization: `Basic ${authToken}`,
    };

    if (includeProtocolHeader) {
        headers["MCP-Protocol-Version"] = MCP_PROTOCOL_VERSION;
    }

    const response = await fetch(WP_API_URL, {
        method: "POST",
        headers,
        body: JSON.stringify(payload),
    });

    const json = await response.json();

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${JSON.stringify(json)}`);
    }

    if (json.error) {
        throw new Error(`JSON-RPC error ${json.error.code}: ${json.error.message}`);
    }

    return json;
}

async function main() {
    console.log(`Connecting to ${WP_API_URL}`);

    const initialize = await postJsonRpc(
        {
            jsonrpc: "2.0",
            id: 1,
            method: "initialize",
            params: {
                protocolVersion: MCP_PROTOCOL_VERSION,
                capabilities: {},
                clientInfo: {
                    name: "tsubakuro-connection-test",
                    version: "1.0.0",
                },
            },
        },
        false
    );

    console.log("initialize OK:", initialize.result?.serverInfo?.name || "unknown-server");

    const toolsList = await postJsonRpc({
        jsonrpc: "2.0",
        id: 2,
        method: "tools/list",
        params: {},
    });

    const toolNames = (toolsList.result?.tools || []).map((tool) => tool.name);

    if (!toolNames.includes("tsubakuro-list-tasks")) {
        throw new Error(`Expected tool not found. tools=${JSON.stringify(toolNames)}`);
    }

    console.log(`tools/list OK: ${toolNames.length} tools`);
}

main().catch((error) => {
    console.error("MCP adapter connection test failed:", error.message);
    process.exit(1);
});
