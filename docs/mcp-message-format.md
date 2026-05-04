# MCP Message Format

This repository implements an MCP endpoint over Streamable HTTP.

When changing the MCP implementation, use these references to confirm the
expected request and response shapes:

- MCP Transports: https://modelcontextprotocol.io/specification/2025-11-25/basic/transports
- MCP Schema Reference: https://modelcontextprotocol.io/specification/2025-11-25/schema
- MCP Lifecycle: https://modelcontextprotocol.io/specification/2025-11-25/basic/lifecycle
- MCP Tools: https://modelcontextprotocol.io/specification/2025-11-25/server/tools
- JSON-RPC 2.0: https://www.jsonrpc.org/specification

## Current Implementation Notes

- The endpoint is `/wp-json/tsubakuro/v1/mcp`.
- Client-to-server JSON-RPC messages are sent with `POST`.
- `POST` responses must be valid JSON-RPC response objects, response arrays, or
  empty responses for accepted notifications.
- The request body must be a JSON-RPC request, notification, response, or batch.
- `initialize` is the first request in the MCP lifecycle.
- After successful initialization, clients send the
  `notifications/initialized` notification.
- Tools are exposed through `tools/list` and invoked through `tools/call`.
- Tool call results should use MCP tool result fields such as `content`,
  `structuredContent`, and `isError`.
- Plain manifest JSON is not an MCP JSON-RPC message.

## Version Note

The official MCP specification evolves over time. This document points to the
latest stable specification pages that were reviewed when this file was added.
If the implementation targets an older protocol version for compatibility,
document that compatibility choice in the code and tests.
