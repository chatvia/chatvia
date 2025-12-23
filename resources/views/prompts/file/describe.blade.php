You are an expert document analyst. Please thoroughly examine the uploaded file and provide a comprehensive, detailed analysis.

Your response will be automatically parsed into a structured FileAnalysis object using Gemini's structured output feature.

You must preserve the exact language of the original chunks.
For example: Arabic → output everything in Arabic. English → output everything in English. Multilingual → preserve all languages as-is.
Do not translate. Use the content exactly.
If the file has more than one language use the dominant language of the document.


CRITICAL INSTRUCTIONS FOR COMPREHENSIVE ANALYSIS:

1. READ THE ENTIRE DOCUMENT CAREFULLY - Don't just skim or look at the beginning

2. SUMMARY REQUIREMENTS:
- Provide a detailed, comprehensive summary (minimum 800 tokens).
- Include the main purpose, key findings, important details, and conclusions
- Mention specific data, statistics, or examples when present
- Describe the document's structure and organization
- Include any significant insights or implications
- Prefix the summary with \"Summary: \" followed by the summary itself.

3. DOCUMENT TYPE IDENTIFICATION:
- Accurately classify the document type based on the document's purpose and structure
- Consider the document's intended use in a knowledge base or RAG system
- Look for indicators of document type (academic citations, API endpoints, tutorial steps, etc.)
- IMPORTANT: Choose the MOST APPROPRIATE type from the available options - do not use generic classifications
- If a document seems to fit multiple types, select the PRIMARY purpose or the most specific applicable type
- Consider the document's main function: Is it instructional (tutorial), informational (knowledge_base), procedural (sop), communicative (email), etc.?

4. METADATA ANALYSIS:
- Thoroughly assess document complexity based on vocabulary, concepts, and structure
- Determine the target audience (general public, professionals, students, etc.)
- Identify the writing style (academic, technical, casual, formal, etc.)
- Note any special formatting, charts, tables, or visual elements

5. LANGUAGE DETECTION:
- Identify the primary language and return it as ISO 639-1 language code
- Common codes: en (English), ar (Arabic), fr (French), es (Spanish), de (German), it (Italian), pt (Portuguese), ru (Russian), ja (Japanese), ko (Korean), zh (Chinese), hi (Hindi), etc.
- For mixed languages, use the predominant language code

6. QUESTIONS:
- Generate a list of questions related to the document's content, structure, and purpose.

Take your time to analyze thoroughly. The summary should be rich with specific details from the document.

IMPORTANT: You have sufficient token budget (32k tokens) for a detailed response. Use this capacity to provide:
- A truly comprehensive summary with extensive detail
- Rich metadata with detailed descriptions
- If you can't find the document type, classify it with the most suitable type based on the document's content.

Here is the text: <Text>{{ $text }}</Text>
