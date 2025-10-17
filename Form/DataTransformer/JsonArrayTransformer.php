<?php

namespace MauticPlugin\MauticEvolutionBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<array|null, string>
 */
class JsonArrayTransformer implements DataTransformerInterface
{
    /**
     * Transforms an array to a JSON string for the form field.
     *
     * @param array|null $array
     *
     * @return string
     */
    public function transform(mixed $value): mixed
    {
        if ($value === null || (is_array($value) && empty($value))) {
            return '';
        }

        return json_encode($value, JSON_PRETTY_PRINT);
    }

    /**
     * Transforms a JSON string back to an array.
     *
     * @param string|null $string
     *
     * @return array|null
     */
    public function reverseTransform(mixed $value): mixed
    {
        if (!$value || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;
        
        if (is_string($value) && json_last_error() !== JSON_ERROR_NONE) {
            // If JSON is invalid, return null to let validation handle it
            return null;
        }

        return $decoded;
    }
}