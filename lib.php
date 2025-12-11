<?php
/**
 * Get the MoodleConnect API URL.
 * 
 * For local development, you can override the default production URL by setting
 * this in config.php:
 * 
 *   $CFG->local_mc_plugin_moodleconnect_url = 'http://172.17.0.1:5000/api';
 * 
 * @return string The MoodleConnect API URL
 */
function local_mc_plugin_get_api_url() {
    global $CFG;
    
    // Check for config.php override (for development)
    if (!empty($CFG->local_mc_plugin_moodleconnect_url)) {
        return rtrim($CFG->local_mc_plugin_moodleconnect_url, '/');
    }
    
    // Default production URL
    return 'https://moodleconnect.com/api';
}

/**
 * Get the MoodleConnect frontend URL.
 * 
 * For local development where frontend runs on a different port than the API,
 * you can override this in config.php:
 * 
 *   $CFG->local_mc_plugin_moodleconnect_frontend_url = 'http://172.17.0.1:5173';
 * 
 * In production, the frontend URL is derived from the API URL by removing /api.
 * 
 * @return string The MoodleConnect frontend URL
 */
function local_mc_plugin_get_frontend_url() {
    global $CFG;
    
    // Check for config.php override (for development)
    if (!empty($CFG->local_mc_plugin_moodleconnect_frontend_url)) {
        return rtrim($CFG->local_mc_plugin_moodleconnect_frontend_url, '/');
    }
    
    // Derive from API URL by removing /api suffix
    $api_url = local_mc_plugin_get_api_url();
    return preg_replace('/\/api\/?$/', '', $api_url);
}
