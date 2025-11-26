<?php
/**
 * Response Utility Class
 * Standardized JSON API responses
 */

class Response {

    /**
     * Send success response
     */
    public static function success($data = null, $message = 'Success', $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');

        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];

        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send error response
     */
    public static function error($message = 'An error occurred', $code = 400, $errors = null) {
        http_response_code($code);
        header('Content-Type: application/json');

        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send validation error response
     */
    public static function validationError($errors, $message = 'Validation failed') {
        self::error($message, 422, $errors);
    }

    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, 401);
    }

    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }

    /**
     * Send not found response
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }

    /**
     * Send server error response
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }
}
