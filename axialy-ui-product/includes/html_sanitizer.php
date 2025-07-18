<?php
/**
 * A basic HTML sanitizer that whitelists a few tags and attributes.
 * This approach strips all other tags/attributes.
 *
 * IMPORTANT: This is a simple demonstration. For production,
 * consider using a more robust library like HTMLPurifier or similar,
 * especially if your environment is publicly accessible.
 */

/**
 * sanitizeHTML
 * Removes any non-whitelisted tags, removes dangerous tags
 * like <script> or <style>, and only allows certain attributes
 * for <a> tags.
 *
 * @param  string $html Raw HTML content
 * @return string       Safe (sanitized) HTML
 */
function sanitizeHTML($html) {
    // 1) Remove any <script> or <style> blocks entirely
    $html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $html);
    $html = preg_replace('#<style\b[^>]*>(.*?)</style>#is', '', $html);

    // 2) Define a small set of allowed tags
    //    (Feel free to expand this list as needed)
    //$allowedTags = '<b><strong><i><em><u><p><br><ul><ol><li><a>';
    $allowedTags = '<a><b><body><br><em><h1><h2><head><html><i><li><ol><p><strong><style><table><td><th><title><tr><u><ul>';

    

    // 3) Strip all tags not in our whitelist
    $html = strip_tags($html, $allowedTags);

    /**
     * 4) For <a> tags, remove all attributes except for:
     *       - href (URL only)
     *       - target (restrict to safe characters)
     */
    $html = preg_replace_callback(
        '/<a\s+([^>]+)>/i', // match <a ...> with attributes
        function($matches) {
            $originalAttrs = $matches[1];
            $newAttrs = '';

            // Extract href (safe-escape the URL)
            if (preg_match('/\bhref\s*=\s*"([^"]+)"/i', $originalAttrs, $m)) {
                $safeHref = filter_var($m[1], FILTER_SANITIZE_URL);
                $newAttrs .= ' href="' . $safeHref . '"';
            }
            // Extract target (allow only safe chars)
            if (preg_match('/\btarget\s*=\s*"([^"]+)"/i', $originalAttrs, $m)) {
                // Only allow a-z, digits, underscore, dash
                $safeTarget = preg_replace('/[^a-zA-Z0-9_\-]/', '', $m[1]);
                $newAttrs .= ' target="' . $safeTarget . '"';
            }

            // Return the sanitized <a ...>
            return '<a' . $newAttrs . '>';
        },
        $html
    );

    return $html;
}
