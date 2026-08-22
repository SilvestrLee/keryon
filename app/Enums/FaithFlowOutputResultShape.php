<?php

namespace App\Enums;

// Which of the two generation Agent classes (and rendering strategy) a given
// FaithFlowOutputType uses — see K-FAITHFLOW-001D "Agent/prompt architecture".
// TEXT = a single plain string. LIST = a structured array persisted as
// rendered plain text (faithflow_outputs.content/generated_content are text
// columns, not JSON).
enum FaithFlowOutputResultShape: string
{
    case TEXT = 'text';
    case LIST = 'list';
}
