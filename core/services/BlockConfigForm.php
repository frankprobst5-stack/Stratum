<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Renders real form inputs from a `ConfigurableBlock::configFields()`
 * schema, pre-filled with a placement's current config — the actual fix
 * for "nobody should need to know JSON syntax to pick a category"
 * (2026-07-18). One rendering path shared by both the initial admin page
 * load (existing placements) and the AJAX-created-placement response
 * (see `BlockPlacementsController`), so there's exactly one place that
 * knows how to turn a field schema into HTML — not duplicated in JS.
 */
final class BlockConfigForm
{
    /**
     * The reverse of render() — pulls this block's fields back out of
     * submitted POST data (each input named `field_{name}`, matching
     * render()'s own naming) into a plain config array ready to
     * `json_encode` and save. Blank strings are dropped (not stored as
     * `""`) so a field left empty falls back to the block's own
     * `render()`-time default instead of an explicit empty value
     * overriding it.
     *
     * @param array<int, array<string, mixed>> $fields from configFields()
     * @param array<string, mixed> $postData raw request body
     * @return array<string, string>
     */
    public static function extractConfig(array $fields, array $postData): array
    {
        $config = [];
        foreach ($fields as $field) {
            $name = (string) $field['name'];
            $value = trim((string) ($postData['field_' . $name] ?? ''));
            if ($value !== '') {
                $config[$name] = $value;
            }
        }

        return $config;
    }

    /**
     * @param array<int, array<string, mixed>> $fields from configFields()
     * @param array<string, mixed> $currentConfig decoded config_json, or [] for a brand new placement
     */
    public static function render(array $fields, array $currentConfig): string
    {
        $html = '';
        foreach ($fields as $field) {
            $name = (string) $field['name'];
            $value = $currentConfig[$name] ?? ($field['default'] ?? '');
            $html .= self::renderField($field, $name, $value);
        }

        return $html;
    }

    /** @param array<string, mixed> $field */
    private static function renderField(array $field, string $name, mixed $value): string
    {
        $label = (string) $field['label'];
        $type = (string) $field['type'];
        $inputName = 'field_' . $name;

        $input = match ($type) {
            'textarea' => '<textarea name="' . e($inputName) . '" rows="4" style="width:100%;max-width:28rem;">' . e((string) $value) . '</textarea>',
            'number' => '<input type="number" name="' . e($inputName) . '" value="' . e((string) $value) . '" style="width:6rem;">',
            'select' => self::renderSelect($inputName, (array) ($field['options'] ?? []), (string) $value),
            default => '<input type="text" name="' . e($inputName) . '" value="' . e((string) $value) . '" style="width:100%;max-width:28rem;">',
        };

        return '<p style="margin:0.5rem 0;"><label style="display:block;font-size:0.8rem;color:#666;margin-bottom:0.2rem;">' . e($label) . '</label>' . $input . '</p>';
    }

    /** @param array<string, string> $options value => label */
    private static function renderSelect(string $inputName, array $options, string $selected): string
    {
        $optionsHtml = '';
        foreach ($options as $value => $optionLabel) {
            $optionsHtml .= '<option value="' . e((string) $value) . '" ' . ((string) $value === $selected ? 'selected' : '') . '>' . e($optionLabel) . '</option>';
        }

        return '<select name="' . e($inputName) . '">' . $optionsHtml . '</select>';
    }
}
