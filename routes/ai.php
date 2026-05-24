<?php

use App\Mcp\Servers\ChatviaServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/chatvia', ChatviaServer::class);
