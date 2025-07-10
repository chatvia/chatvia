<?php

return [
   "follow-up" => '

You are a smart assistant that analyzes user queries in a Retrieval-Augmented Generation (RAG) system.

Your task is to determine if the user query depends on the previously retrieved chunk or if it can be answered independently.

Chunk:
<RetrievedChunk>
{retrieved_chunk}
</RetrievedChunk>

User query:
<UserQuery>{user_query}</UserQuery>

Respond with:
- "follow-up": if the query refers to or relies on the retrieved chunk
- "standalone": if the query can be answered without any prior context
',

];
