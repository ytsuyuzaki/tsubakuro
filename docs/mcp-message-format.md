# MCP Message Format

This repository implements an MCP endpoint over Streamable HTTP.

When changing the MCP implementation, use these references to confirm the
expected request and response shapes:

- MCP Transports: <https://modelcontextprotocol.io/specification/2025-11-25/basic/transports>
- MCP Schema Reference: <https://modelcontextprotocol.io/specification/2025-11-25/schema>
- MCP Lifecycle: <https://modelcontextprotocol.io/specification/2025-11-25/basic/lifecycle>
- MCP Tools: <https://modelcontextprotocol.io/specification/2025-11-25/server/tools>
- JSON-RPC 2.0: <https://www.jsonrpc.org/specification>

## Current Implementation Notes

- The endpoint is `/wp-json/tsubakuro/v1/mcp`.
- The implementation target is MCP protocol version `2025-11-25`.
- Client-to-server JSON-RPC messages are sent with `POST`.
- Streamable HTTP clients should send `Accept: application/json, text/event-stream`.
- HTTP requests after initialization should send `MCP-Protocol-Version: 2025-11-25`.
- `POST` responses must be valid JSON-RPC response objects, response arrays, or
  empty responses for accepted notifications.
- The request body must be a single JSON-RPC request, notification, or response.
- JSON-RPC batches are rejected because Streamable HTTP requires one message per
  `POST`.
- `initialize` is the first request in the MCP lifecycle.
- After successful initialization, clients send the
  `notifications/initialized` notification.
- Tools are exposed through `tools/list` and invoked through `tools/call`.
- Tool call results should use MCP tool result fields such as `content`,
  `structuredContent`, and `isError`.
- `GET` is reserved for optional SSE streams in Streamable HTTP. This
  implementation does not provide an SSE stream and returns `405 Method Not
  Allowed`.
- Plain manifest JSON is not an MCP JSON-RPC message.

## Version Note

The official MCP specification evolves over time. This document points to the
latest stable specification pages that were reviewed when this file was added.
If the implementation target changes again, update `Tsubakuro_MCP::PROTOCOL_VERSION`,
tests, and the examples in the README and admin MCP guide together.
