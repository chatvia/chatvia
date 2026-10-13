{{--
    What hybrid search does, in the popup of the "?" beside its switch in
    the admin setup ({@see \App\Admin\Pages\Setup}): the two ways a question
    is searched and the tools behind each. Styled by the panel theme
    (resources/css/filament/admin/theme.css).
--}}
<div class="cv-hybrid">
    <p>
        Each question is searched in two ways. The two result lists are then merged into one ranking
        (reciprocal rank fusion), so a passage that is found both ways comes first.
    </p>

    <div class="cv-hybrid__ways">
        <div class="cv-hybrid__way">
            <div class="cv-hybrid__head">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedLightBulb" class="cv-hybrid__icon" />
                <span>By meaning</span>
            </div>
            <p>Finds passages about the same idea, even when they use other words.</p>
            <p class="cv-hybrid__how">Vectors from the embedding model, searched with a VectorChord index.</p>
        </div>

        <div class="cv-hybrid__way">
            <div class="cv-hybrid__head">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedHashtag" class="cv-hybrid__icon" />
                <span>By keywords (BM25)</span>
            </div>
            <p>Finds the exact words of the question: names, codes, product numbers and rare words.</p>
            <p class="cv-hybrid__how">BM25 with VectorChord BM25. The llmlingua2 tokenizer splits the text into words.</p>
        </div>
    </div>

    <p class="cv-hybrid__note">
        Keyword search runs inside PostgreSQL, so it needs no other service and no API key. When hybrid search is off, agents search by meaning only.
    </p>
</div>
