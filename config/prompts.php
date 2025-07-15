<?php

return [
    'follow-up-system-prompt' => '
    You are a smart assistant that analyzes user queries in a Retrieval-Augmented Generation (RAG) system.

Your task is to determine if the user query can be answered based on the retrieved chunk or if it requires additional context.

    ',
    'follow-up' => '


Chunk:
<RetrievedChunks>
{retrieved_chunks}
</RetrievedChunks>

User query:
<UserQuery>{user_query}</UserQuery>

Respond with:
- "search": if the query needs additioal context.
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
