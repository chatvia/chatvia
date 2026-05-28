<?php

namespace Support\Enums;

/**
 * Agent kind. Only `knowledge_base` agents can be provisioned through the
 * public API; `widget` and `search` agents are dashboard-only.
 */
enum AgentType: string
{
    case KnowledgeBase = 'knowledge_base';
    case Widget = 'widget';
    case Search = 'search';
}
