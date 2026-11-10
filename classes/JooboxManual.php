<?php
/** Local user documentation only: no requests, tracking, configuration writes or Markdown execution. */
if (!class_exists('JooboxModuleManual', false)) {
    class JooboxModuleManual
    {
        public static function data($root, $language)
        {
            $locale = !empty($language->locale) ? $language->locale : ($language->iso_code ?? 'en');
            $locale = strtolower(str_replace('_', '-', (string) $locale));
            $base = explode('-', $locale)[0];
            $aliases = ['no' => 'nb', 'nn' => 'nb'];
            $base = $aliases[$base] ?? $base;
            if (!preg_match('/^[a-z]{2}$/D', $base) || !is_file($root . '/views/manual/' . $base . '.json')) {
                $base = 'en';
            }
            $path = $root . '/views/manual/' . $base . '.json';
            return is_file($path) ? (array) json_decode(file_get_contents($path), true) : [];
        }

        public static function title($root, $language)
        {
            $data = self::data($root, $language);
            return $data['title'] ?? 'Manual';
        }

        public static function render($root, $language, $module, $version)
        {
            $data = self::data($root, $language);
            $escape = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
            $html = '<article class="jooim-manual" lang="' . $escape($data['language'] ?? 'en') . '"><h3>'
                . $escape($data['title'] ?? 'Manual') . '</h3><p>' . $escape($module . ' ' . $version) . '</p><p>'
                . $escape($data['scope'] ?? '') . '</p><nav class="jooim-manual-index">';
            foreach ($data['sections'] ?? [] as $section) {
                $html .= '<a href="#' . $escape($module . '-manual-' . $section['id']) . '">' . $escape($section['title']) . '</a> ';
            }
            $html .= '</nav>';
            foreach ($data['sections'] ?? [] as $section) {
                $html .= '<section id="' . $escape($module . '-manual-' . $section['id']) . '"><h4>' . $escape($section['title'])
                    . '</h4><p>' . nl2br($escape($section['text']), false) . '</p>';
                if ($section['id'] === 'configuration') {
                    $html .= self::settings($data['configuration'] ?? []);
                }
                $html .= '</section>';
            }
            $html .= '<pre>' . $escape($data['examples'] ?? '') . '</pre>';
            $repo = 'https://github.com/JOO-INTERNET-MEDIA-LTD/' . rawurlencode($module);
            $readme = 'README.' . ($data['language'] ?? 'en') . '.md';
            if (!is_file($root . '/' . $readme)) { $readme = 'README.md'; }
            $html .= '<p><a target="_blank" rel="noopener noreferrer" href="' . $escape($repo . '/blob/main/' . $readme) . '">GitHub</a> · '
                . '<a target="_blank" rel="noopener noreferrer" href="https://support.joobox.eu">Joobox</a> · <a target="_blank" rel="noopener noreferrer" href="' . $escape($repo . '/blob/main/LICENSE') . '">Joobox Module License 1.0</a></p></article>';
            // English uses the root README.md convention.
            return str_replace('/README.en.md', '/README.md', $html);
        }

        /** Static public defaults only; never display saved credentials or customer data. */
        private static function settings(array $data)
        {
            $e = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
            $c = $data['captions'] ?? [];
            $html = '<div class="jooim-setting-guide"><p>' . $e($c['intro'] ?? '') . '</p>';
            foreach ($data['groups'] ?? [] as $group) {
                $html .= '<section class="jooim-manual-group"><h5>' . $e($group['title']) . '</h5>';
                foreach ($group['fields'] as $field) {
                    $value = $field['default'];
                    if ($field['type'] === 'action') { $value = $c['action']; }
                    elseif ($value === null) { $value = $c['dynamic']; }
                    elseif (is_bool($value)) { $value = $c[$value ? 'on' : 'off']; }
                    elseif ($value === '' || $value === []) { $value = $c['empty']; }
                    elseif (is_array($value)) { $value = implode(', ', $value); }
                    elseif (!empty($field['options'])) {
                        foreach ($field['options'] as $option) {
                            if ((string) $option['value'] === (string) $value) { $value = $option['label']; break; }
                        }
                    }
                    $html .= '<details class="jooim-manual-field"><summary>' . $e($field['label']) . '</summary>'
                        . '<p lang="' . $e($field['text_language']) . '">' . $e($field['text']) . '</p>'
                        . '<p><strong>' . $e($c['default']) . ':</strong> ' . $e($value) . '</p>';
                    if (isset($field['expected'])) {
                        $html .= '<p><strong>' . $e($c['expected']) . ':</strong> ' . $e($field['expected']) . '</p>';
                    }
                    if (!empty($field['options'])) {
                        $html .= '<dl class="jooim-manual-options">';
                        foreach ($field['options'] as $option) {
                            $html .= '<dt>' . $e($option['label']) . '</dt><dd lang="' . $e($option['text_language']) . '">' . $e($option['text']) . '</dd>';
                        }
                        $html .= '</dl>';
                    }
                    $html .= '</details>';
                }
                $html .= '</section>';
            }
            return $html . '</div>';
        }

        /** Keep existing settings forms intact and outside the read-only Manual panel. */
        public static function tabs($root, $language, $module, $version, $settings, $path)
        {
            $data = self::data($root, $language);
            $e = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
            $id = $e($module . '-documentation');
            $css = 'views/css/manual-guide.css';
            $js = 'views/js/manual-guide.js';
            $html = '<link rel="stylesheet" href="' . $e($path . $css . '?v=' . substr(hash_file('sha256', $root . '/' . $css), 0, 12)) . '">'
                . '<div data-jooim-documentation><nav class="jooim-documentation-tabs" aria-label="' . $e($data['title'] ?? 'Manual') . '">';
            foreach (['settings' => ($data['settings'] ?? 'Settings'), 'manual' => ($data['title'] ?? 'Manual')] as $key => $label) {
                $html .= '<a id="' . $id . '-tab-' . $key . '" href="#' . $id . '-' . $key . '" data-documentation-tab="' . $key . '">' . $e($label) . '</a>';
            }
            $html .= '</nav><section id="' . $id . '-settings" data-documentation-panel="settings">' . $settings . '</section>'
                . '<section id="' . $id . '-manual" data-documentation-panel="manual">' . self::render($root, $language, $module, $version) . '</section></div>'
                . '<script defer src="' . $e($path . $js . '?v=' . substr(hash_file('sha256', $root . '/' . $js), 0, 12)) . '"></script>';
            return $html;
        }
    }
}
