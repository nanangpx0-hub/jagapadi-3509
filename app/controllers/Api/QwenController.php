<?php
require_once ROOT_PATH . "/app/controllers/Api/BaseApiController.php";
require_once ROOT_PATH . "/app/services/QwenEditorTokenManager.php";

class QwenController extends BaseApiController {
    private $tokenManager;

    public function __construct() {
        $this->tokenManager = new QwenEditorTokenManager();
    }

    public function token() {
        try {
            // Check if Qwen API is configured
            if (!$this->tokenManager->isConfigured()) {
                $this->sendError(
                    'Qwen API credentials not configured. Please set QWEN_API_KEY and QWEN_API_SECRET in your .env file.', 
                    503
                );
                return;
            }

            $token = $this->tokenManager->getAccessToken();
            
            if (empty($token)) {
                $this->sendError('Failed to retrieve access token from Qwen API.', 500);
                return;
            }

            $this->sendResponse(["access_token" => $token], "Success");
        } catch (Exception $e) {
            error_log('Qwen Token Error: ' . $e->getMessage());
            $this->sendError('Failed to get Qwen access token: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get token status for debugging
     */
    public function status() {
        try {
            $status = $this->tokenManager->getStatus();
            $this->sendResponse($status, "Token status retrieved");
        } catch (Exception $e) {
            $this->sendError('Failed to get token status: ' . $e->getMessage(), 500);
        }
    }
}
