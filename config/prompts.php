<?php

return [
    'follow-up-system-prompt' => '
    You are a smart assistant that analyzes user queries in a Retrieval-Augmented Generation (RAG) system.

Your task is to determine if the user query depends on the previously retrieved chunk or if it can be answered independently.

    ',
    'follow-up' => '


Chunk:
<RetrievedChunk>
{retrieved_chunk}
</RetrievedChunk>

User query:
<UserQuery>{user_query}</UserQuery>

Respond with:
- "search": if the query refers to or relies on the retrieved chunk
- "stay": if the query can be answered without any prior context

You must responde with plain text, without quotes or HTML tags or anything else.
',

    'rewrite-query' => '
You are a query rewriting assistant for a document search system.
Rewrite the following query to maximize retrieval of relevant passages.
You must only return the new query in plain text, without quotes or HTML tags or anything else.

Query: <Query>{query}</Query>
    ',

];
