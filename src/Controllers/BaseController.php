<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Core\Router;

abstract class BaseController
{
    protected Config $config;
    protected Database $database;
    protected Session $session;
    protected Router $router;

    public function __construct(Config $config, Database $database, Session $session, Router $router)
    {
        $this->config = $config;
        $this->database = $database;
        $this->session = $session;
        $this->router = $router;
    }

    protected function render(string $template, array $data = []): void
    {
        // Add common data available to all templates
        $data['config'] = $this->config;
        $data['session'] = $this->session;
        // Every rendered form needs a CSRF token — including guest forms like
        // login/register — so always emit one (it's tied to the session, not
        // to being logged in).
        $data['csrf_token'] = $this->session->getCsrfToken();
        $data['current_user'] = $this->getCurrentUser();
        $data['flash_messages'] = $this->getFlashMessages();

        // Extract data to variables
        extract($data);

        // Include template
        $templatePath = __DIR__ . '/../../templates/' . $template . '.php';
        
        if (!file_exists($templatePath)) {
            throw new \Exception("Template not found: {$template}");
        }

        include $templatePath;
    }

    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function redirect(string $url, int $code = 302): void
    {
        $this->router->redirect($url, $code);
    }

    protected function redirectBack(string $default = '/'): void
    {
        $this->router->redirectBack($default);
    }

    protected function getCurrentUser(): ?array
    {
        if (!$this->session->isLoggedIn()) {
            return null;
        }

        $userId = $this->session->getUserId();
        return $this->database->queryOne(
            'SELECT id, handle, about, avatar_path, is_admin, created_at FROM users WHERE id = ?',
            [$userId]
        );
    }

    protected function validateInput(array $rules, array $data): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $rulesParts = explode('|', $rule);

            foreach ($rulesParts as $rulePart) {
                $rulePart = trim($rulePart);

                if ($rulePart === 'required' && empty($value)) {
                    $errors[$field] = ucfirst($field) . ' is required';
                    break;
                }

                if (str_starts_with($rulePart, 'min:')) {
                    $min = (int)substr($rulePart, 4);
                    if (strlen((string)$value) < $min) {
                        $errors[$field] = ucfirst($field) . " must be at least {$min} characters";
                        break;
                    }
                }

                if (str_starts_with($rulePart, 'max:')) {
                    $max = (int)substr($rulePart, 4);
                    if (strlen((string)$value) > $max) {
                        $errors[$field] = ucfirst($field) . " must not exceed {$max} characters";
                        break;
                    }
                }

                if ($rulePart === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = ucfirst($field) . ' must be a valid email address';
                    break;
                }

                if ($rulePart === 'numeric' && !empty($value) && !is_numeric($value)) {
                    $errors[$field] = ucfirst($field) . ' must be a number';
                    break;
                }

                if (str_starts_with($rulePart, 'unique:')) {
                    $table = substr($rulePart, 7);
                    $existing = $this->database->queryOne(
                        "SELECT id FROM {$table} WHERE {$field} = ?",
                        [$value]
                    );
                    if ($existing) {
                        $errors[$field] = ucfirst($field) . ' is already taken';
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    protected function sanitizeInput(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    protected function sanitizeArray(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeInput($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    protected function getFlashMessages(): array
    {
        $messages = [];
        
        if ($this->session->hasFlash('success')) {
            $messages['success'] = $this->session->getFlash('success');
        }
        
        if ($this->session->hasFlash('error')) {
            $messages['error'] = $this->session->getFlash('error');
        }
        
        if ($this->session->hasFlash('warning')) {
            $messages['warning'] = $this->session->getFlash('warning');
        }
        
        if ($this->session->hasFlash('info')) {
            $messages['info'] = $this->session->getFlash('info');
        }

        return $messages;
    }

    protected function setFlash(string $type, string $message): void
    {
        $this->session->flash($type, $message);
    }

    protected function getPagination(int $total, int $perPage, int $currentPage): array
    {
        $totalPages = (int)ceil($total / $perPage);
        $currentPage = max(1, min($currentPage, $totalPages));
        $offset = ($currentPage - 1) * $perPage;

        return [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'offset' => $offset,
            'has_prev' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'prev_page' => $currentPage > 1 ? $currentPage - 1 : null,
            'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null
        ];
    }
}