<?php

namespace App\Enums;

enum QuestionType: string
{
    case BINARY = 'binary';
    case SINGLE_CHOICE = 'single_choice';
    case MULTIPLE_CHOICE = 'multiple_choice';
    case NUMBER = 'number';
    case TEXT = 'text';
}

