<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Helpers;

use JustinHoltWeb\FreeNav\Elements\Conditions\NodeCondition;
use JustinHoltWeb\FreeNav\Elements\Conditions\NodeTypeConditionRule;
use JustinHoltWeb\FreeNav\Elements\Node;
use JustinHoltWeb\FreeNav\FieldLayoutElements\BadgeField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\CssClassesField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\CustomAttributesField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\IconField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\NewWindowField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\UrlSuffixField;
use JustinHoltWeb\FreeNav\FieldLayoutElements\VisibilityRulesField;
use JustinHoltWeb\FreeNav\Fields\MenuField;

/**
 * FreeNav 5's class names, which Craft stores (element types, field types, field layout
 * elements, condition rules), mapped to FreeNav 6's.
 *
 * Used by the Craft 6 upgrade migration. A FreeNav 5 class name is a whole stored value, and no
 * other plugin's value can equal one, so values are replaced only on an exact match.
 */
final class LegacyClassMap
{
    public const array MAP = [
        'justinholt\\freenav\\elements\\Node' => Node::class,
        'justinholt\\freenav\\elements\\conditions\\NodeCondition' => NodeCondition::class,
        'justinholt\\freenav\\elements\\conditions\\NodeTypeConditionRule' => NodeTypeConditionRule::class,
        'justinholt\\freenav\\fields\\MenuField' => MenuField::class,
        'justinholt\\freenav\\fieldlayoutelements\\BadgeField' => BadgeField::class,
        'justinholt\\freenav\\fieldlayoutelements\\CssClassesField' => CssClassesField::class,
        'justinholt\\freenav\\fieldlayoutelements\\CustomAttributesField' => CustomAttributesField::class,
        'justinholt\\freenav\\fieldlayoutelements\\IconField' => IconField::class,
        'justinholt\\freenav\\fieldlayoutelements\\NewWindowField' => NewWindowField::class,
        'justinholt\\freenav\\fieldlayoutelements\\UrlSuffixField' => UrlSuffixField::class,
        'justinholt\\freenav\\fieldlayoutelements\\VisibilityRulesField' => VisibilityRulesField::class,
    ];

    public static function className(string $value): string
    {
        return self::MAP[$value] ?? $value;
    }

    /**
     * Replaces FreeNav 5 class names anywhere in a stored value: plain strings, array keys
     * (`elementSources.<class>`), nested arrays (project config, including its `__assoc__` pairs)
     * and JSON held in a string (stored conditions).
     */
    public static function replace(mixed $value): mixed
    {
        if (is_string($value)) {
            if (isset(self::MAP[$value])) {
                return self::MAP[$value];
            }

            $trimmed = ltrim($value);

            if (($trimmed[0] ?? '') === '{' || ($trimmed[0] ?? '') === '[') {
                if (! str_contains($value, 'justinholt') || ! json_validate($value)) {
                    return $value;
                }

                $decoded = json_decode($value, true);
                $updated = self::replace($decoded);

                return $updated === $decoded ? $value : (string) json_encode($updated, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            }

            return $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        $updated = [];

        foreach ($value as $key => $item) {
            $updated[is_string($key) ? self::className($key) : $key] = self::replace($item);
        }

        return $updated;
    }
}
