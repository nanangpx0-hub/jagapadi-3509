/**
 * Qwen Editor AI Token Client
 * 
 * Handles fetching and caching access tokens for Qwen AI API.
 */

const QwenTokenClient = {
    /**
     * Get access token from backend
     * @returns {Promise<string>} Access token
     * @throws {Error} If token cannot be fetched
     */
    async getToken() {
        try {
            const response = await fetch("/api/qwen/token", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-CSRF-Token": document.querySelector("meta[name=\"csrf-token\"]")?.content || ""
                }
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                // Backend returned an error response
                const errorMessage = data.message || data.error || `HTTP ${response.status}`;
                throw new Error(errorMessage);
            }
            
            if (!data.success || !data.data || !data.data.access_token) {
                throw new Error("Invalid response format from token endpoint");
            }
            
            return data.data.access_token;
        } catch (error) {
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                throw new Error("Network error: Unable to connect to server. Please check your internet connection.");
            }
            throw error;
        }
    },

    /**
     * Get token status for debugging
     * @returns {Promise<object>} Token status
     */
    async getStatus() {
        try {
            const response = await fetch("/api/qwen/status", {
                method: "GET",
                headers: {
                    "X-CSRF-Token": document.querySelector("meta[name=\"csrf-token\"]")?.content || ""
                }
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                const errorMessage = data.message || data.error || `HTTP ${response.status}`;
                throw new Error(errorMessage);
            }
            
            return data.data;
        } catch (error) {
            console.error("Failed to get token status:", error);
            throw error;
        }
    }
};

if (typeof module !== "undefined" && module.exports) {
    module.exports = QwenTokenClient;
}
