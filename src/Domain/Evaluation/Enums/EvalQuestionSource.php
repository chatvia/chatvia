<?php

namespace Domain\Evaluation\Enums;

/**
 * Where a knowledge-base question offered by the Evals "From knowledge"
 * picker came from: a question mined from an ingested chunk, or a manual
 * QA pair (which carries a human-written ground-truth answer).
 */
enum EvalQuestionSource: string
{
    case Chunk = 'chunk';
    case Qa = 'qa';
}
