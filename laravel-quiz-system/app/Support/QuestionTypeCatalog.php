<?php

namespace App\Support;

use App\Enums\QuestionType;

class QuestionTypeCatalog
{
    public static function definitions(): array
    {
        return [
            QuestionType::BINARY->value => [
                'label' => 'Binary (Yes / No)',
                'uses_options' => true,
                'single_choice' => true,
            ],
            QuestionType::SINGLE_CHOICE->value => [
                'label' => 'Single Choice',
                'uses_options' => true,
                'single_choice' => true,
            ],
            QuestionType::MULTIPLE_CHOICE->value => [
                'label' => 'Multiple Choice',
                'uses_options' => true,
                'single_choice' => false,
            ],
            QuestionType::NUMBER->value => [
                'label' => 'Number Input',
                'uses_options' => false,
                'single_choice' => true,
            ],
            QuestionType::TEXT->value => [
                'label' => 'Text Input',
                'uses_options' => false,
                'single_choice' => true,
            ],
        ];
    }

    public static function values(): array
    {
        return array_keys(self::definitions());
    }

    public static function label(string $type): string
    {
        return self::definitions()[$type]['label'] ?? $type;
    }

    public static function usesOptions(string $type): bool
    {
        return (bool) (self::definitions()[$type]['uses_options'] ?? false);
    }

    public static function singleChoice(string $type): bool
    {
        return (bool) (self::definitions()[$type]['single_choice'] ?? true);
    }
}

