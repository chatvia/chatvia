<?php

namespace App\Mcp\Tools\Charts;

use InvalidArgumentException;

/**
 * Chart arguments that pass field validation but contradict each other (a
 * heatmap cell naming an unknown category, a radar series with the wrong
 * number of values). {@see EChartsTool} turns the message into a tool error
 * the model can read and correct.
 */
final class InvalidChartInputException extends InvalidArgumentException {}
