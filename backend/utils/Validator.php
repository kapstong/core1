<?php
/**
 * Validator Utility Class
 * Input validation helpers
 */

class Validator {

    /**
     * Validate required fields
     */
    public static function required($data, $fields) {
        $errors = [];

        foreach ($fields as $field) {
            if (!isset($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            } elseif (is_array($data[$field])) {
                if (empty($data[$field])) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' cannot be empty';
                }
            } elseif (is_string($data[$field])) {
                if (trim($data[$field]) === '') {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                }
            } else {
                // For other types (int, float, etc.), just check if set
                // They are considered valid if present
            }
        }

        return empty($errors) ? null : $errors;
    }

    /**
     * Validate email
     */
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate minimum length
     */
    public static function minLength($value, $min) {
        return strlen($value) >= $min;
    }

    /**
     * Validate maximum length
     */
    public static function maxLength($value, $max) {
        return strlen($value) <= $max;
    }

    /**
     * Validate numeric value
     */
    public static function numeric($value) {
        return is_numeric($value);
    }

    /**
     * Validate positive number
     */
    public static function positive($value) {
        return is_numeric($value) && $value > 0;
    }

    /**
     * Validate integer
     */
    public static function integer($value) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Sanitize string
     */
    public static function sanitize($value) {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize array
     */
    public static function sanitizeArray($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } else {
                $sanitized[$key] = self::sanitize($value);
            }
        }
        return $sanitized;
    }
}
