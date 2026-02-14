<?php
// modules/specs_manager.php - (V2.0 - Multi-Type Specification Library)

/**
 * This file centralizes the logic for extracting DIFFERENT TYPES of technical specifications.
 * It's a powerful, scalable solution for handling specs across all modules.
 */

// --- PATTERN DEFINITIONS (The "Single Source of Truth" for all specs) ---

const CARTON_SPEC_PATTERNS = [
    'mat'       => '/الخامة الخارجية:\s*(.*)/u',
    'layers'    => '/عدد الطبقات:\s*(.*)/u',
    'cut'       => '/مقاس القص:\s*(.*)/u',
    'die'       => '/رقم الفورمة:\s*(.*)/u',
    'colors'    => '/الألوان:\s*(.*)/u',
];

const PRINT_SPEC_PATTERNS = [
    'paper_size'  => '/مقاس الورق:\s*(.*)/u',
    'cut_size'    => '/مقاس القص:\s*(.*)/u',
    'machine'     => '/الماكينة: (.*?)(?:\||$)/u',
    'print_face'  => '/الوجه: (.*?)(?:\||$)/u',
    'colors'      => '/الألوان: (.*?)(?:\||$)/u',
    'zincs'       => '/الزنكات: ([\d\.]+)/u',
    'finishing'   => '/التكميلي: (.*?)(?:\||$)/u',
];

// To add a new module (e.g., plastic), simply create a new const array:
// const PLASTIC_SPEC_PATTERNS = [ ... ];


// --- CORE FUNCTIONS ---

/**
 * Extracts a single specification from a text block based on a given pattern.
 * (This helper function remains the same)
 */
function get_single_spec($pattern, $text, $default = '-') {
    if (empty($text)) return $default;
    preg_match($pattern, $text, $matches);
    return trim($matches[1] ?? $default);
}

/**
 * ✨ Main, upgraded function to extract all specifications for a GIVEN TYPE.
 *
 * @param string $raw_text The full job_details text.
 * @param string $type The type of job ('carton', 'print', etc.).
 * @return array An associative array of all extracted specifications for that type.
 */
function extract_all_specs($raw_text, $type = 'carton') {
    $patterns = [];
    switch ($type) {
        case 'carton':
            $patterns = CARTON_SPEC_PATTERNS;
            break;
        case 'print':
            $patterns = PRINT_SPEC_PATTERNS;
            break;
        // Add cases for other types here
        // case 'plastic':
        //     $patterns = PLASTIC_SPEC_PATTERNS;
        //     break;
    }

    $extracted_specs = [];
    foreach ($patterns as $key => $pattern) {
        $extracted_specs[$key] = get_single_spec($pattern, $raw_text, '');
    }
    return $extracted_specs;
}

?>
