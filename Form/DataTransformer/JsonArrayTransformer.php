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
    public function transform($array)
    {
        if (null === $array || empty($array)) {
            return '';
        }

        return json_encode($array, JSON_PRETTY_PRINT);
    }

    /**
     * Transforms a JSON string back to an array.
     *
     * @param string|null $string
     *
     * @return array|null
     */
    public function reverseTransform($string)
    {
        if (!$string || trim($string) === '') {
            return null;
        }

        $decoded = json_decode($string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If JSON is invalid, return null to let validation handle it
            return null;
        }

        return $decoded;
    }
}