<?php
require_once __DIR__ . '/../config/config.php';

class Validator {
    private $errors = [];

    public function validateUsername($username) {
        if (empty($username)) {
            $this->errors['username'] = "Username is required.";
            return false;
        }
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $this->errors['username'] = "Username must be 3-20 characters and contain only letters, numbers, and underscores.";
            return false;
        }
        return true;
    }

    public function validatePassword($password) {
        if (empty($password)) {
            $this->errors['password'] = "Password is required.";
            return false;
        }
        
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $this->errors['password'] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters.";
            return false;
        }

        if (PASSWORD_REQUIRES_SPECIAL && !preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
            $this->errors['password'] = "Password must contain at least one special character.";
            return false;
        }

        if (PASSWORD_REQUIRES_NUMBER && !preg_match('/[0-9]/', $password)) {
            $this->errors['password'] = "Password must contain at least one number.";
            return false;
        }

        if (PASSWORD_REQUIRES_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $this->errors['password'] = "Password must contain at least one uppercase letter.";
            return false;
        }

        return true;
    }

    public function validateEmail($email) {
        if (empty($email)) {
            $this->errors['email'] = "Email is required.";
            return false;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = "Invalid email format.";
            return false;
        }
        return true;
    }

    public function validatePhone($phone) {
        if (empty($phone)) {
            $this->errors['phone'] = "Phone number is required.";
            return false;
        }
        if (!preg_match('/^[0-9]{10}$/', preg_replace('/[^0-9]/', '', $phone))) {
            $this->errors['phone'] = "Invalid phone number format. Must be 10 digits.";
            return false;
        }
        return true;
    }

    public function validateRole($role) {
        $allowed_roles = ['student', 'faculty', 'admin'];
        if (empty($role) || !in_array($role, $allowed_roles)) {
            $this->errors['role'] = "Invalid role selected.";
            return false;
        }
        return true;
    }

    public function validateDepartment($department) {
        if (empty($department) || !in_array($department, DEPARTMENTS)) {
            $this->errors['department'] = "Invalid department selected.";
            return false;
        }
        return true;
    }

    public function validateRecaptcha($recaptcha_response) {
        if (empty($recaptcha_response)) {
            $this->errors['recaptcha'] = "Please complete the reCAPTCHA verification.";
            return false;
        }

        $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=" . RECAPTCHA_SECRET_KEY . "&response=" . $recaptcha_response);
        $response_keys = json_decode($response, true);

        if (!$response_keys['success']) {
            $this->errors['recaptcha'] = "reCAPTCHA verification failed. Please try again.";
            return false;
        }

        return true;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getFirstError() {
        return reset($this->errors);
    }
}
?> 